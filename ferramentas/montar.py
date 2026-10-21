#!/usr/bin/env python3
"""
Monta o site da Danni Som: src/ -> site/ (pasta pronta para a public_html da Hostinger).

  python3 ferramentas/montar.py              # recria site/
  python3 ferramentas/montar.py --zip        # recria site/ e gera danni-som-site.zip (1ª publicação, site completo)
  python3 ferramentas/montar.py --atualizacao  # recria site/ e gera danni-som-atualizacao.zip
                                               # (sem os dados/*.js, a midia/ e o painel/config.php: não apaga
                                               # o que o painel gravou no servidor; antes, traga para src/ os
                                               # dados/ e a midia/ do servidor — veja painel/LEIA-ME.md, parte 7)

Sem dependências: só Python 3.8+.

O que ele faz
  - junta cada página de src/paginas/ com as partes comuns de src/partials/
    (cabeçalho, menu, rodapé, ícones), marcando o link da aba atual;
  - copia assets/, dados/, midia/ e os arquivos de hospedagem (.htaccess, robots.txt…);
  - põe ?v=<hash> nos CSS/JS (o navegador baixa a versão nova depois de cada publicação);
  - gera sitemap.xml e o JSON-LD (dados da empresa para o Google) a partir de dados/empresa.js;
  - confere tudo: arquivos locais referenciados que não existem, âncoras (#id) que não existem,
    JSON dos arquivos de dados. Se algo estiver errado, para e a site/ anterior fica como estava.
"""
import hashlib
import html as htmlmod
import os
import json
import re
import shutil
import sys
import zipfile
from datetime import date
from pathlib import Path

RAIZ = Path(__file__).resolve().parent.parent
SRC = RAIZ / "src"
SAIDA = Path(os.environ["DANNI_SAIDA"]).resolve() if os.environ.get("DANNI_SAIDA") else RAIZ / "site"
DOMINIO = "https://dannisom.com.br"

erros = []


def ler(p):
    return Path(p).read_text(encoding="utf-8")


def dados_js(nome):
    """Lê o JSON de dados/<nome>.js (formato: window.X = {...};)."""
    txt = ler(SRC / "dados" / nome)
    m = re.search(r"window\.\w+\s*=\s*", txt)
    if not m:
        erros.append(f"dados/{nome}: falta 'window.NOME = ...'")
        return {}
    ini = m.end()
    fim = max(txt.rfind("}"), txt.rfind("]"))
    try:
        return json.loads(txt[ini:fim + 1])
    except json.JSONDecodeError as e:
        erros.append(f"dados/{nome}: JSON inválido ({e})")
        return {}


def validar_lista(nome, lista, campos_texto):
    """O painel grava esses arquivos; um tipo errado (ex.: número no lugar de texto) quebraria a página."""
    if not isinstance(lista, list):
        erros.append(f"dados/{nome}: tinha que ser uma lista [ ... ]")
        return
    for i, item in enumerate(lista):
        if not isinstance(item, dict):
            erros.append(f"dados/{nome}: item {i + 1} não é um objeto {{ ... }}")
            continue
        rotulo = item.get("nome") or item.get("id") or f"item {i + 1}"
        if not isinstance(item.get("nome"), str) or not item.get("nome").strip():
            erros.append(f"dados/{nome}: {rotulo}: falta o nome")
        for k in campos_texto:
            if k in item and not isinstance(item[k], str):
                erros.append(f"dados/{nome}: {rotulo}: \"{k}\" tem que ser texto")
        if "visivel" in item and not isinstance(item["visivel"], bool):
            erros.append(f"dados/{nome}: {rotulo}: \"visivel\" tem que ser true ou false")
        for k in ("w", "h"):
            if k in item and not isinstance(item[k], int):
                erros.append(f"dados/{nome}: {rotulo}: \"{k}\" tem que ser um número inteiro")


def meta_da_pagina(html, nome):
    m = re.match(r"\s*<!--meta\s*(\{.*?\})\s*-->", html, re.S)
    if not m:
        erros.append(f"{nome}: falta o bloco <!--meta {{...}} -->")
        return {}, html
    try:
        meta = json.loads(m.group(1))
    except json.JSONDecodeError as e:
        erros.append(f"{nome}: meta inválido ({e})")
        meta = {}
    return meta, html[m.end():]


def incluir(txt, profundidade=0):
    def troca(m):
        p = SRC / "partials" / (m.group(1) + ".html")
        if not p.exists():
            erros.append(f"parte inexistente: {m.group(1)}")
            return ""
        return incluir(ler(p), profundidade + 1) if profundidade < 5 else ler(p)
    return re.sub(r"\{\{>\s*([\w.\-]+)\s*\}\}", troca, txt)


def jsonld(E):
    end = E.get("endereco", {})
    org = {
        "@context": "https://schema.org",
        "@type": ["LocalBusiness", "ElectronicsStore"],
        "@id": DOMINIO + "/#empresa",
        "name": E.get("nome", "Danni Som"),
        "legalName": E.get("razaoSocial"),
        "slogan": E.get("slogan"),
        "url": DOMINIO + "/",
        "logo": DOMINIO + "/assets/img/logo/danni-som-azul.png",
        "image": [DOMINIO + "/assets/img/og/og-danni-som.jpg", DOMINIO + "/assets/img/fotos/loja-frente-1600.webp"],
        "foundingDate": E.get("fundacao") or str(E.get("desde", "")),
        "founder": [{"@type": "Person", "name": n} for n in E.get("socios", [])],
        "telephone": E.get("telefoneLink"),
        "email": E.get("email") or None,
        "address": {
            "@type": "PostalAddress",
            "streetAddress": end.get("rua"),
            "addressLocality": end.get("cidade"),
            "addressRegion": end.get("uf"),
            "postalCode": end.get("cep"),
            "addressCountry": "BR",
        },
        "areaServed": {"@type": "City", "name": end.get("cidade") or "Rio Claro"},
        "priceRange": "$$",
        "hasMap": E.get("mapa"),
        "sameAs": [v for v in (E.get("redes") or {}).values() if v],
    }
    geo = E.get("geo")
    if geo:
        org["geo"] = {"@type": "GeoCoordinates", "latitude": geo[0], "longitude": geo[1]}
    oh = []
    nomes = ["Sunday", "Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday"]
    for dia, faixas in (E.get("grade") or {}).items():
        for a, b in faixas:
            oh.append({"@type": "OpeningHoursSpecification", "dayOfWeek": nomes[int(dia)],
                       "opens": f"{a // 60:02d}:{a % 60:02d}", "closes": f"{b // 60:02d}:{b % 60:02d}"})
    if oh:
        org["openingHoursSpecification"] = oh
    org = {k: v for k, v in org.items() if v not in (None, "", [], {})}
    return '<script type="application/ld+json" data-empresa>' + json.dumps(org, ensure_ascii=False) + "</script>"


def trilha(nome_pagina, url):
    """BreadcrumbList das páginas internas (a mesma trilha "Início / Página" que aparece no topo)."""
    lista = [{"@type": "ListItem", "position": 1, "name": "Início", "item": DOMINIO + "/"},
             {"@type": "ListItem", "position": 2, "name": nome_pagina, "item": url}]
    return ('\n<script type="application/ld+json">' +
            json.dumps({"@context": "https://schema.org", "@type": "BreadcrumbList", "itemListElement": lista}, ensure_ascii=False) +
            "</script>")


# ---------- Conteúdo de reserva (o mesmo que o core.js monta) ----------
# Sem JavaScript (ou antes dele), a página já mostra telefones, endereço, clientes, avaliações e
# nomes das marcas com os dados do dia da publicação. Com JavaScript, o core.js refaz tudo com os
# dados atuais (o que o painel gravou), então nada fica velho para quem visita.
SEGMENTOS = {"saude": "Saúde", "educacao": "Educação", "industria": "Indústria", "igrejas": "Igrejas",
             "comercio": "Comércio e serviços", "automotivo": "Automotivo", "publico": "Serviços públicos"}


def esc(v):
    return htmlmod.escape("" if v is None else str(v), quote=True)


def derivados(E):
    e = dict(E.get("endereco") or {})
    uf = "\u2011" + e["uf"] if e.get("uf") else ""
    E = dict(E)
    E["enderecoCurto"] = " · ".join(x for x in (e.get("rua"), e.get("bairro"), (e.get("cidade") or "") + uf) if x)
    e["linha1"] = " · ".join(x for x in (e.get("rua"), e.get("bairro")) if x)
    e["linha2"] = " · ".join(x for x in ((e.get("cidade") or "") + uf, e.get("cep")) if x)
    E["endereco"] = e
    return E


def valor(E, caminho):
    o = E
    for k in caminho.split("."):
        o = o.get(k) if isinstance(o, dict) else None
    return o


def visiveis(lista):
    return [x for x in (lista if isinstance(lista, list) else []) if isinstance(x, dict) and x.get("visivel") is not False]


def card_cliente(c):
    # só o nome: o logo do dia da publicação pode ter sido trocado ou apagado pelo painel
    # (o core.js põe os logos atuais; sem JavaScript ficam os nomes, que é o que importa para busca)
    return f'<li class="logo-card logo-card--text" data-seg="{esc(c.get("segmento") or "")}"><b>{esc(c["nome"])}</b></li>'


def card_marca(m):
    # o logo vira máscara CSS no core.js (a URL depende do endereço da página); aqui fica o nome
    return f'<li class="brand" data-cat="{esc(m.get("categoria") or "")}" title="{esc(m["nome"])}"><b class="brand__txt">{esc(m["nome"])}</b></li>'


def attr(atributos, nome):
    m = re.search(r'\b%s(?:="([^"]*)")?(?=[\s>]|$)' % re.escape(nome), atributos)
    return None if not m else (m.group(1) if m.group(1) is not None else "")


def reserva(html, E, C, M, pagina):
    E = derivados(E)
    clientes, marcas = visiveis(C), visiveis(M)

    def lista_clientes(m):
        a = m.group(1)
        lst = clientes
        seg = attr(a, "data-segmento")
        if seg:
            lst = [c for c in lst if seg in str(c.get("segmento") or "").split()]
        if attr(a, "data-so-logos") is not None:
            lst = [c for c in lst if c.get("logo")]
        # data-reserva: quantos nomes vão no HTML (o mural do início mostra todos só depois do JavaScript)
        lim = int(attr(a, "data-limite") or 0) or int(attr(a, "data-reserva") or 0)
        if lim:
            lst = lst[:lim]
        convite =('<li class="logo-card logo-card--cta" data-seg="todos"><a href="contato.html"><b>Sua empresa aqui?</b>'
                   "<span>Fale com a gente</span></a></li>") if attr(a, "data-convite") is not None else ""
        return f"<ul{a}>" + "".join(card_cliente(c) for c in lst) + convite + "</ul>"
    html = re.sub(r"<ul(\s[^>]*\bdata-clientes\b[^>]*)></ul>", lista_clientes, html)

    def lista_marcas(m):
        a = m.group(1)
        lst = marcas
        cat = attr(a, "data-categoria")
        if cat:
            lst = [x for x in lst if cat in str(x.get("categoria") or "").split()]
        return f"<ul{a}>" + "".join(card_marca(x) for x in lst) + "</ul>"
    html = re.sub(r"<ul(\s[^>]*\bdata-marcas\b[^>]*)></ul>", lista_marcas, html)

    def lista_depoimentos(m):
        a = m.group(1)
        lim = int(attr(a, "data-limite") or 0)
        deps = [r for r in (E.get("depoimentos") or []) if isinstance(r, dict)]
        deps = deps[:lim] if lim else deps
        return f"<ul{a}>" + "".join(
            f'<li class="rev"><svg class="rev__q" aria-hidden="true"><use href="#i-quote"/></svg><p>{esc(r.get("texto"))}</p>'
            f'<span class="rev__a"><b>{esc(r.get("nome"))}</b> · avaliação no Google</span></li>' for r in deps) + "</ul>"
    html = re.sub(r"<ul(\s[^>]*\bdata-depoimentos\b[^>]*)></ul>", lista_depoimentos, html)

    lista_horas = [h for h in (E.get("horarios") or []) if isinstance(h, dict)]
    horas = "".join(f"<dt>{esc(h.get('dias'))}</dt><dd>{esc(h.get('horas'))}</dd>" for h in lista_horas)
    html = re.sub(r"(<dl\b[^>]*\bdata-horarios\b[^>]*>)(</dl>)", lambda m: m.group(1) + horas + m.group(2), html)
    if lista_horas:   # o mesmo horário em uma frase (perguntas frequentes), igual ao core.js
        frase = "; ".join(f"{h.get('dias')}: {str(h.get('horas') or '').lower()}" for h in lista_horas)
        html = re.sub(r"(<span data-horarios-texto>)[^<]*(</span>)", lambda m: m.group(1) + esc(frase) + m.group(2), html)
    nota = (E.get("google") or {}).get("nota")
    if nota:
        html = re.sub(r'(<span\b[^>]*\baria-label=")[^"]*("[^>]*\bdata-nota-estrelas)', lambda m: f"{m.group(1)}Nota {esc(nota)} de 5{m.group(2)}", html)

    def conta_seg(m):
        n = sum(1 for c in clientes if m.group(2) in str(c.get("segmento") or "").split())
        return f"{m.group(1)}{n}{m.group(3)}"
    html = re.sub(r'(<b data-conta-seg="([\w-]+)">)\d*(</b>)', conta_seg, html)

    def conta(m):
        n = len(clientes) if m.group(2) == "clientes" else len(marcas) if m.group(2) == "marcas" else 0
        if not n:
            return m.group(0)
        abre = re.sub(r'data-count="\d+"', f'data-count="{n}"', m.group(1))
        return f"{abre}{n}{m.group(3)}"
    html = re.sub(r'(<span\b[^>]*\bdata-conta="(\w+)"[^>]*>)\d*(</span>)', conta, html)

    # textos (telefone, e-mail, endereço…) e links (tel:, mailto:, mapa, redes)
    def texto(m):
        v = valor(E, m.group(3))
        if v in (None, "") or isinstance(v, (dict, list)):
            return m.group(0)
        v = str(v)
        if m.group(3) == "email" and "@" in v:   # e-mail quebra de linha só depois do @
            i = v.index("@") + 1
            return f"{m.group(1)}{esc(v[:i])}<wbr>{esc(v[i:])}{m.group(4)}"
        return f"{m.group(1)}{esc(v)}{m.group(4)}"
    html = re.sub(r'(<(\w+)\b[^>]*\bdata-e="([\w.]+)"[^>]*>)[^<]*(</\2>)', texto, html)

    redes = E.get("redes") or {}
    geo = E.get("geo")
    hrefs = {
        "tel": "tel:" + E["telefoneLink"] if E.get("telefoneLink") else "",
        "tel2": "tel:" + E["telefone2Link"] if E.get("telefone2Link") else "",
        "mail": "mailto:" + E["email"] if E.get("email") else "",
        "mapa": E.get("mapa") or "",
        "instagram": redes.get("instagram") or "",
        "facebook": redes.get("facebook") or "",
        "google": (E.get("google") or {}).get("link") or "",
        "waze": f"https://waze.com/ul?ll={geo[0]},{geo[1]}&navigate=yes" if geo else "",
    }

    def link(m):
        tag, k = m.group(0), m.group(1)
        v = hrefs.get(k)
        if v:
            return re.sub(r'\shref="[^"]*"', lambda _: f' href="{esc(v)}"', tag, count=1)
        if k in ("instagram", "facebook", "google") and " hidden" not in tag:
            return tag[:-1] + " hidden>"
        return tag
    html = re.sub(r'<a\b[^>]*\bdata-href="(\w+)"[^>]*>', link, html)

    def se(m):
        tag, k = m.group(0), m.group(1)
        v = valor(E, k)
        vazio = v is None or v == "" or (isinstance(v, list) and not v)
        return tag[:-1] + " hidden>" if vazio and " hidden" not in tag else tag
    html = re.sub(r'<\w+\b[^>]*\bdata-if="([\w.]+)"[^>]*>', se, html)

    # sem WhatsApp cadastrado, os botões já saem como o core.js os deixaria: ícone de conversa,
    # texto alternativo e link para o formulário (com número, o core.js volta ícone e texto)
    if not re.sub(r"\D", "", str(E.get("whatsapp") or "")):
        destino = "#orcamento" if pagina == "contato.html" else "contato.html#orcamento"

        def sem_wa(m):
            a = m.group(0)
            a = re.sub(r'^(<a\b[^>]*?\shref=")[^"]*"', lambda x: x.group(1) + destino + '"', a, count=1)
            a = a.replace('<use href="#i-whatsapp"/>', '<use href="#i-chat" data-wa-ic/>')
            return re.sub(r'<span data-wa-txt="([^"]*)">([^<]*)</span>',
                          lambda x: f'<span data-wa-txt="{x.group(1)}" data-wa-on="{x.group(2)}">{x.group(1)}</span>', a)
        html = re.sub(r'<a\b[^>]*\bdata-wa="[^"]*"[^>]*>.*?</a>', sem_wa, html, flags=re.S)
    return html


def hash_de(p):
    return hashlib.sha1(Path(p).read_bytes()).hexdigest()[:10]


def montar():
    E = dados_js("empresa.js")
    C = dados_js("clientes.js") if (SRC / "dados" / "clientes.js").exists() else []
    M = dados_js("marcas.js") if (SRC / "dados" / "marcas.js").exists() else []
    validar_lista("clientes.js", C, ("id", "segmento", "logo", "fundo"))
    validar_lista("marcas.js", M, ("id", "categoria", "logo"))

    tmp = SAIDA.parent / ".site-montando"
    if tmp.exists():
        shutil.rmtree(tmp)
    tmp.mkdir()

    # arquivos estáticos
    for pasta in ("assets", "dados", "midia"):
        if (SRC / pasta).exists():
            shutil.copytree(SRC / pasta, tmp / pasta, ignore=shutil.ignore_patterns(".DS_Store", "Thumbs.db", "_*"))
    for f in (SRC / "hospedagem").iterdir():
        if f.is_file():
            shutil.copy2(f, tmp / f.name)
        elif f.is_dir():
            shutil.copytree(f, tmp / f.name)
    if (RAIZ / "painel").exists():
        shutil.copytree(RAIZ / "painel", tmp / "painel",
                        ignore=shutil.ignore_patterns("privado", "*.log", ".DS_Store", "testes"))

    versoes = {}
    for p in (tmp / "assets").rglob("*"):
        # fontes ficam sem ?v=: o preload e o url() do CSS precisam ser o mesmo endereço
        if p.suffix in (".css", ".js", ".webp", ".jpg", ".png", ".svg"):
            versoes[p.relative_to(tmp).as_posix()] = hash_de(p)
    # dados/*.js ficam sem ?v=: o painel muda esses arquivos no servidor (o .htaccess de dados/ manda conferir sempre)

    paginas = sorted((SRC / "paginas").glob("*.html"))
    mapa = []
    for arq in paginas:
        meta, corpo = meta_da_pagina(ler(arq), arq.name)
        nome = arq.name
        url = DOMINIO + "/" + ("" if nome == "index.html" else nome)
        css = "\n".join(f'<link rel="stylesheet" href="assets/css/{c}.css">' for c in meta.get("css", []))
        js = "\n".join(f'<script src="assets/js/{j}.js" defer></script>' for j in meta.get("js", []))
        dados = "\n".join(f'<script src="dados/{d}.js" defer></script>' for d in meta.get("dados", []))
        libs = "\n".join(f'<script src="assets/js/vendor/{l}.min.js" defer></script>' for l in meta.get("libs", []))
        html = incluir(ler(SRC / "partials" / "head.html") + ler(SRC / "partials" / "header.html") + corpo + ler(SRC / "partials" / "footer.html"))
        crumb = re.search(r'<nav class="crumbs"[^>]*>.*?<span aria-current="page">([^<]+)</span>', corpo, re.S)
        troca = {
            "title": meta.get("title", "Danni Som"),
            "og_title": meta.get("og_title", meta.get("title", "Danni Som")),
            "description": meta.get("description", ""),
            "canonical": url,
            "robots": meta.get("robots", "index, follow"),
            "og_image": DOMINIO + "/assets/img/og/" + meta.get("og", "og-danni-som.jpg"),
            "page_css": css, "page_js": js, "page_data": dados, "page_libs": libs,
            "jsonld": (jsonld(E) if meta.get("jsonld", True) else "") + (trilha(crumb.group(1), url) if crumb and nome not in ("index.html", "404.html") else ""),
            "nav": meta.get("nav", ""),
            "body_class": meta.get("body", ""),
        }
        for k, v in troca.items():
            html = html.replace("{{" + k + "}}", v)
        html = re.sub(r"\{\{cur:(\w+)\}\}", lambda m: ' aria-current="page"' if m.group(1) == meta.get("nav") else "", html)
        sobra = re.findall(r"\{\{[^}]*\}\}", html)
        if sobra:
            erros.append(f"{nome}: marcadores sem valor: {sorted(set(sobra))}")
        html = reserva(html, E, C, M, nome)
        # o início é "/" (o .htaccess manda index.html para lá): link direto, sem redirecionamento
        html = html.replace('href="index.html"', 'href="./"').replace('href="index.html#', 'href="./#')
        # versão nos arquivos locais (o navegador baixa a nova depois de cada publicação)
        html = re.sub(r'((?<![\w-])(?:href|src)=")(assets/[^"?#]+\.(?:css|js|webp|jpg|png|svg))"',
                      lambda m: f'{m.group(1)}{m.group(2)}?v={versoes.get(m.group(2), "0")}"', html)

        def versao_srcset(m):
            itens = []
            for parte in m.group(1).split(","):
                u, _, larg = parte.strip().partition(" ")
                itens.append(f"{u}?v={versoes[u]} {larg}".strip() if u in versoes else parte.strip())
            return 'srcset="' + ", ".join(itens) + '"'
        html = re.sub(r'(?<![\w-])srcset="([^"]+)"', versao_srcset, html)
        if nome == "404.html":
            # a 404 pode abrir em qualquer pasta: tudo relativo à raiz do site (data-href e afins ficam de fora)
            html = re.sub(r'((?<![\w-])(?:href|src)=")(?!https?:|mailto:|tel:|#|/|data:)', r"\1/", html)
            html = html.replace('href="/./"', 'href="/"').replace('href="/./#', 'href="/#')
            html = re.sub(r'((?<![\w-])srcset=")([^"]+)"', lambda m: m.group(1) + ", ".join(
                ("/" + c.strip()) if not c.strip().startswith(("/", "http")) else c.strip() for c in m.group(2).split(",")) + '"', html)
            # a mesma 404 responde por qualquer endereço que não existe: sem canonical nem og:url
            html = re.sub(r'<link rel="canonical"[^>]*>\n?|<meta property="og:url"[^>]*>\n?', "", html)
        (tmp / nome).write_text(html, encoding="utf-8")
        if meta.get("sitemap", True):
            mapa.append((url, meta.get("prioridade", "0.8"), date.fromtimestamp(arq.stat().st_mtime).isoformat()))

    # sitemap
    linhas = ['<?xml version="1.0" encoding="UTF-8"?>', '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">']
    for url, pr, quando in mapa:
        linhas.append(f"  <url><loc>{url}</loc><lastmod>{quando}</lastmod><priority>{pr}</priority></url>")
    linhas.append("</urlset>")
    (tmp / "sitemap.xml").write_text("\n".join(linhas) + "\n", encoding="utf-8")

    conferir(tmp)
    if erros:
        print("\nNÃO MONTADO — corrija antes:\n  - " + "\n  - ".join(erros))
        shutil.rmtree(tmp)
        sys.exit(1)
    if SAIDA.exists():
        shutil.rmtree(SAIDA)
    tmp.rename(SAIDA)
    print(f"site/ montada: {len(paginas)} páginas.")


def conferir(base):
    ids_por_pagina = {}
    for p in base.glob("*.html"):
        ids_por_pagina[p.name] = set(re.findall(r'\sid="([^"]+)"', ler(p)))
    for p in base.glob("*.html"):
        html = ler(p)
        refs = re.findall(r'(?<![\w-])(?:href|src)="([^"]+)"', html)
        for srcset in re.findall(r'(?<![\w-])srcset="([^"]+)"', html):
            refs += [p_.strip().split(" ")[0] for p_ in srcset.split(", ")]
        for ref in refs:
            for parte in [ref]:
                alvo = parte.strip()
                if not alvo or alvo.startswith(("http:", "https:", "mailto:", "tel:", "data:", "javascript:")):
                    continue
                if p.name == "404.html":
                    alvo = alvo.lstrip("/")
                caminho, _, ancora = alvo.partition("#")
                caminho = caminho.split("?")[0]
                if caminho and not (base / caminho).exists() and not caminho.endswith("/"):
                    erros.append(f"{p.name}: arquivo não existe: {caminho}")
                if caminho.endswith("/") and not (base / caminho).exists():
                    erros.append(f"{p.name}: pasta não existe: {caminho}")
                if ancora and not ancora.startswith(("p=", "/")):
                    # "./#x" é o início; na 404 os links "/#x" (já sem a "/") também apontam para o início
                    inicio = caminho == "./" or (caminho == "" and p.name == "404.html")
                    destino = "index.html" if inicio else (caminho or p.name)
                    if destino in ids_por_pagina and ancora not in ids_por_pagina[destino]:
                        erros.append(f"{p.name}: âncora #{ancora} não existe em {destino}")
        for use in re.findall(r'<use href="#([\w-]+)"', html):
            if use not in ids_por_pagina[p.name]:
                erros.append(f"{p.name}: ícone #{use} não existe no sprite")
    # imagens citadas nos dados
    for nome, chave in (("clientes.js", "logo"), ("marcas.js", "logo")):
        f = base / "dados" / nome
        if f.exists():
            txt = ler(f)
            for logo in re.findall(r'"%s"\s*:\s*"([^"]+)"' % chave, txt):
                if logo and not (base / logo).exists():
                    erros.append(f"dados/{nome}: logo não existe: {logo}")


def empacotar(nome, sem=()):
    """sem: pastas/arquivos que ficam de fora; "pasta/*.js" tira só esses arquivos da pasta."""
    destino = RAIZ / nome
    with zipfile.ZipFile(destino, "w", zipfile.ZIP_DEFLATED, compresslevel=9) as z:
        for p in sorted(SAIDA.rglob("*")):
            rel = p.relative_to(SAIDA).as_posix()
            if any(Path(rel).match(s) if "*" in s else (rel == s or rel.startswith(s + "/")) for s in sem):
                continue
            if p.is_file():
                z.write(p, rel)
    print(f"{nome}: {destino.stat().st_size / 1024 / 1024:.1f} MB")


if __name__ == "__main__":
    montar()
    if "--zip" in sys.argv:
        empacotar("danni-som-site.zip")
    if "--atualizacao" in sys.argv:
        # dados/.htaccess vai junto (é ele que impede o cache de 1 ano nos dados); o painel/config.php do
        # servidor pode ter ajustes feitos lá (pasta privada, formato do logo) e não é sobrescrito
        empacotar("danni-som-atualizacao.zip", sem=("dados/*.js", "midia", "painel/privado", "painel/config.php"))

#!/usr/bin/env python3
"""
Monta o site da Danni Som: src/ -> site/ (pasta pronta para a public_html da Hostinger).

  python3 ferramentas/montar.py              # recria site/
  python3 ferramentas/montar.py --zip        # recria site/ e gera danni-som-site.zip (1ª publicação, site completo)
  python3 ferramentas/montar.py --atualizacao  # recria site/ e gera danni-som-atualizacao.zip
                                               # (sem dados/ e midia/: não apaga o que o painel gravou no servidor)

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
import os
import json
import re
import shutil
import sys
import zipfile
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
        "image": DOMINIO + "/assets/img/og/og-danni-som.jpg",
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
        "areaServed": "Rio Claro e região",
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
    return '<script type="application/ld+json">' + json.dumps(org, ensure_ascii=False) + "</script>"


def hash_de(p):
    return hashlib.sha1(Path(p).read_bytes()).hexdigest()[:10]


def montar():
    E = dados_js("empresa.js")
    for extra in ("clientes.js", "marcas.js"):
        if (SRC / "dados" / extra).exists():
            dados_js(extra)

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
        if p.suffix in (".css", ".js"):
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
        dados = "\n".join(f'<script src="dados/{d}.js"></script>' for d in meta.get("dados", []))
        libs = "\n".join(f'<script src="assets/js/vendor/{l}.min.js" defer></script>' for l in meta.get("libs", []))
        html = incluir(ler(SRC / "partials" / "head.html") + ler(SRC / "partials" / "header.html") + corpo + ler(SRC / "partials" / "footer.html"))
        troca = {
            "title": meta.get("title", "Danni Som"),
            "og_title": meta.get("og_title", meta.get("title", "Danni Som")),
            "description": meta.get("description", ""),
            "canonical": url,
            "robots": meta.get("robots", "index, follow"),
            "og_image": DOMINIO + "/assets/img/og/" + meta.get("og", "og-danni-som.jpg"),
            "page_css": css, "page_js": js, "page_data": dados, "page_libs": libs,
            "jsonld": jsonld(E) if meta.get("jsonld", True) else "",
            "nav": meta.get("nav", ""),
            "body_class": meta.get("body", ""),
        }
        for k, v in troca.items():
            html = html.replace("{{" + k + "}}", v)
        html = re.sub(r"\{\{cur:(\w+)\}\}", lambda m: ' aria-current="page"' if m.group(1) == meta.get("nav") else "", html)
        sobra = re.findall(r"\{\{[^}]*\}\}", html)
        if sobra:
            erros.append(f"{nome}: marcadores sem valor: {sorted(set(sobra))}")
        # versão nos CSS/JS locais
        html = re.sub(r'((?:href|src)=")(assets/[^"?#]+\.(?:css|js))"',
                      lambda m: f'{m.group(1)}{m.group(2)}?v={versoes.get(m.group(2), "0")}"', html)
        if nome == "404.html":
            # a 404 pode abrir em qualquer pasta: tudo relativo à raiz do site
            html = re.sub(r'((?:href|src)=")(?!https?:|mailto:|tel:|#|/|data:)', r"\1/", html)
        (tmp / nome).write_text(html, encoding="utf-8")
        if meta.get("sitemap", True):
            mapa.append((url, meta.get("prioridade", "0.8")))

    # sitemap
    linhas = ['<?xml version="1.0" encoding="UTF-8"?>', '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">']
    for url, pr in mapa:
        linhas.append(f"  <url><loc>{url}</loc><priority>{pr}</priority></url>")
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
                    destino = caminho or p.name
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
    destino = RAIZ / nome
    with zipfile.ZipFile(destino, "w", zipfile.ZIP_DEFLATED, compresslevel=9) as z:
        for p in sorted(SAIDA.rglob("*")):
            rel = p.relative_to(SAIDA).as_posix()
            if any(rel == s or rel.startswith(s + "/") for s in sem):
                continue
            if p.is_file():
                z.write(p, rel)
    print(f"{nome}: {destino.stat().st_size / 1024 / 1024:.1f} MB")


if __name__ == "__main__":
    montar()
    if "--zip" in sys.argv:
        empacotar("danni-som-site.zip")
    if "--atualizacao" in sys.argv:
        empacotar("danni-som-atualizacao.zip", sem=("dados", "midia", "painel/privado"))

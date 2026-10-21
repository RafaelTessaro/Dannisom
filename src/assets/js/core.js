/* =====================================================================
   Danni Som — js/core.js (todas as páginas)
   - preenche telefone, WhatsApp, endereço, horário e redes a partir de
     dados/empresa.js (o painel muda esse arquivo; o site acompanha);
   - monta os murais de logos de dados/clientes.js e dados/marcas.js;
   - cabeçalho, menu do celular, rolagem suave (Lenis), animações (GSAP),
     contadores, faixa que rola e botão flutuante do WhatsApp.
   Sem GSAP/Lenis (CDN fora do ar) ou com "reduzir movimento", tudo
   aparece normalmente, só sem animação.
   ===================================================================== */
(function () {
  "use strict";

  var d = document, w = window, html = d.documentElement;
  var $ = function (s, c) { return (c || d).querySelector(s); };
  var $$ = function (s, c) { return Array.prototype.slice.call((c || d).querySelectorAll(s)); };
  var reduz = w.matchMedia && w.matchMedia("(prefers-reduced-motion: reduce)").matches;
  var E = w.DANNI_EMPRESA || {};
  // dados/*.js que não carregou (ou com erro) não apaga o que a página já trouxe do dia da publicação
  var temE = !!w.DANNI_EMPRESA && typeof w.DANNI_EMPRESA === "object" && !!(E.nome || E.telefoneLink);
  var DS = (w.DS = w.DS || {});   // funções compartilhadas com as páginas (home.js etc.)
  DS.E = E; DS.$ = $; DS.$$ = $$; DS.reduz = reduz; DS.temE = temE;

  function temGSAP() { return !!(w.gsap && w.ScrollTrigger); }
  function esc(s) {
    return String(s == null ? "" : s).replace(/[&<>"']/g, function (c) {
      return { "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[c];
    });
  }
  DS.esc = esc;
  function valor(caminho) {
    return caminho.split(".").reduce(function (o, k) { return o == null ? undefined : o[k]; }, E);
  }

  /* ---------- Dados da empresa ---------- */
  function derivados() {
    var e = E.endereco && typeof E.endereco === "object" && !Array.isArray(E.endereco) ? E.endereco : {};
    // hífen que não quebra: "Rio Claro‑SP" nunca fica dividido em duas linhas
    E.enderecoCurto = [e.rua, e.bairro, (e.cidade || "") + (e.uf ? "\u2011" + e.uf : "")].filter(Boolean).join(" · ");
    e.linha1 = [e.rua, e.bairro].filter(Boolean).join(" · ");
    e.linha2 = [(e.cidade || "") + (e.uf ? "\u2011" + e.uf : ""), e.cep].filter(Boolean).join(" · ");
    E.endereco = e;
  }
  function linkWhats(msg) {
    var n = String(E.whatsapp || "").replace(/\D/g, "");
    if (!n) return "";
    return "https://wa.me/" + n + "?text=" + encodeURIComponent(msg || E.whatsappMensagem || "Olá!");
  }
  DS.linkWhats = linkWhats;

  function preencher() {
    passo(derivados);
    $$("[data-e]").forEach(function (el) {
      var k = el.getAttribute("data-e"), v = valor(k);
      if (v == null || v === "" || typeof v === "object") return;
      // onde o espaço é curto (data-wbr), o e-mail quebra de linha só depois do @, nunca no meio do domínio
      if (k === "email" && el.hasAttribute("data-wbr") && String(v).indexOf("@") > 0) { var i = String(v).indexOf("@"); el.innerHTML = esc(String(v).slice(0, i + 1)) + "<wbr>" + esc(String(v).slice(i + 1)); }
      // telefone não se divide entre o DDD e o número
      else if (/^telefone2?$/.test(k)) el.textContent = String(v).replace(/ /g, "\u00a0");
      else el.textContent = v;
    });
    // o montar.py já esconde o que estava vazio no dia da publicação; aqui vale o dado de agora
    if (temE) $$("[data-if]").forEach(function (el) {
      var v = valor(el.getAttribute("data-if"));
      el.hidden = v == null || v === "" || (Array.isArray(v) && !v.length);
    });
    var redes = E.redes || {};
    var hrefs = {
      tel: E.telefoneLink ? "tel:" + E.telefoneLink : "",
      tel2: E.telefone2Link ? "tel:" + E.telefone2Link : "",
      mail: E.email ? "mailto:" + E.email : "",
      mapa: E.mapa || "",
      instagram: redes.instagram || "",
      facebook: redes.facebook || "",
      google: (E.google && E.google.link) || "",
      waze: E.geo ? "https://waze.com/ul?ll=" + E.geo[0] + "," + E.geo[1] + "&navigate=yes" : ""
    };
    $$("[data-href]").forEach(function (el) {
      var k = el.getAttribute("data-href"), v = hrefs[k];
      var rede = k === "instagram" || k === "facebook" || k === "google";
      if (v) { el.setAttribute("href", v); if (rede) el.hidden = false; }
      else if (rede && temE) el.hidden = true;
    });
    // sem os dados (arquivo com erro), os botões ficam como a página foi publicada
    if (temE) $$("[data-wa]").forEach(function (el) {
      var l = linkWhats(el.getAttribute("data-wa"));
      if (l) {
        el.href = l; el.target = "_blank"; el.rel = "noopener";
        // a página foi publicada sem WhatsApp (ícone de conversa): volta o ícone e o texto do WhatsApp
        $$("use[data-wa-ic]", el).forEach(function (u) { u.setAttribute("href", "#i-whatsapp"); });
        $$("[data-wa-on]", el).forEach(function (t) { t.textContent = t.getAttribute("data-wa-on"); });
        return;
      }
      // sem WhatsApp cadastrado: o botão leva direto ao formulário de orçamento, com ícone de conversa.
      // data-wa-alt (posto pelo montar.py quando o site foi publicado com WhatsApp) já diz para onde ir;
      // senão, o endereço sai do próprio link (na página 404 ele vem a partir da raiz do site)
      var alt = el.getAttribute("data-wa-alt"), atual = el.getAttribute("href") || "";
      var base = /^https?:/.test(atual) ? "contato.html" : atual.split("#")[0] || "contato.html";
      el.setAttribute("href", d.body.classList.contains("pg-contato") ? "#orcamento" : alt || base + "#orcamento");
      el.removeAttribute("target"); el.removeAttribute("rel");
      $$('use[href="#i-whatsapp"]', el).forEach(function (u) { u.setAttribute("href", "#i-chat"); u.setAttribute("data-wa-ic", ""); });
      $$("[data-wa-txt]", el).forEach(function (t) {
        if (!t.hasAttribute("data-wa-on")) t.setAttribute("data-wa-on", t.textContent);
        t.textContent = t.getAttribute("data-wa-txt");
      });
    });
    // estrelas da nota do Google: o leitor de tela ouve a mesma nota que aparece
    var nota = E.google && E.google.nota;
    if (nota) $$("[data-nota-estrelas]").forEach(function (el) { el.setAttribute("aria-label", "Nota " + nota + " de 5"); });
    $$("[data-ano]").forEach(function (el) { el.textContent = new Date().getFullYear(); });
    // links que abrem outra aba avisam isso aos leitores de tela
    $$('a[target="_blank"]').forEach(function (a) {
      if (a.querySelector(".sr-only")) return;
      var s = d.createElement("span"); s.className = "sr-only"; s.textContent = " (abre em nova aba)"; a.appendChild(s);
      if (a.hasAttribute("aria-label")) a.setAttribute("aria-label", a.getAttribute("aria-label") + " (abre em nova aba)");
    });
    $$("[data-anos]").forEach(function (el) { el.textContent = new Date().getFullYear() - (E.desde || 2000); });
    if (temE) { passo(dadosEstruturados); passo(horarios); }
  }

  // O JSON-LD da empresa sai do montar.py com os dados do dia da publicação; aqui ele acompanha
  // o que o painel mudou depois (o Google lê os dados estruturados da página já montada)
  function dadosEstruturados() {
    var ld = $('script[type="application/ld+json"][data-empresa]'), j;
    if (!ld) return;
    try { j = JSON.parse(ld.textContent); } catch (x) { return; }
    var e = E.endereco || {}, redes = E.redes || {};
    // cada campo por conta própria: um valor estranho num deles não impede os outros
    function campo(k, fn) { try { var v = fn(); if (v == null || v === "" || (Array.isArray(v) && !v.length)) delete j[k]; else j[k] = v; } catch (x) { /* fica o da publicação */ } }
    campo("telephone", function () { return E.telefoneLink ? String(E.telefoneLink) : null; });
    campo("email", function () { return E.email ? String(E.email) : null; });
    campo("address", function () {
      if (!e.rua) return j.address;
      var ad = { "@type": "PostalAddress", streetAddress: String(e.rua), addressLocality: e.cidade, addressRegion: e.uf, postalCode: e.cep, addressCountry: "BR" };
      Object.keys(ad).forEach(function (k) { if (ad[k] == null || ad[k] === "") delete ad[k]; });
      return ad;
    });
    campo("geo", function () {
      var g = E.geo;
      return Array.isArray(g) && g.length === 2 && isFinite(g[0]) && isFinite(g[1]) ? { "@type": "GeoCoordinates", latitude: +g[0], longitude: +g[1] } : null;
    });
    campo("hasMap", function () { return E.mapa ? String(E.mapa) : null; });
    campo("openingHoursSpecification", function () {
      var nomes = ["Sunday", "Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday"], oh = [], g = E.grade;
      if (!g || typeof g !== "object") return j.openingHoursSpecification;
      var hm = function (m) { return ("0" + Math.floor(m / 60)).slice(-2) + ":" + ("0" + m % 60).slice(-2); };
      // as mesmas faixas que o "Aberto agora" aceita
      nomes.forEach(function (nome, k) {
        faixasDoDia(g, k).forEach(function (f) { oh.push({ "@type": "OpeningHoursSpecification", dayOfWeek: nome, opens: hm(f[0]), closes: hm(f[1]) }); });
      });
      return oh.length ? oh : j.openingHoursSpecification;
    });
    campo("sameAs", function () { return Object.keys(redes).map(function (k) { return redes[k]; }).filter(function (v) { return typeof v === "string" && v; }); });
    ld.textContent = JSON.stringify(j);
  }

  /* ---------- Horário e "Aberto agora" ---------- */
  function agoraSP() {
    // hora oficial de Brasília (Rio Claro), independente do fuso de quem abre o site
    try {
      var p = new Intl.DateTimeFormat("en-US", { timeZone: "America/Sao_Paulo", weekday: "short", hour: "numeric", minute: "numeric", hourCycle: "h23" }).formatToParts(new Date());
      var o = {}; p.forEach(function (x) { o[x.type] = x.value; });
      var dias = { Sun: 0, Mon: 1, Tue: 2, Wed: 3, Thu: 4, Fri: 5, Sat: 6 };
      return { dia: dias[o.weekday], min: (+o.hour) * 60 + (+o.minute) };
    } catch (e) {
      var n = new Date(); return { dia: n.getDay(), min: n.getHours() * 60 + n.getMinutes() };
    }
  }
  function hora(m) { var h = Math.floor(m / 60), mm = m % 60; return h + "h" + (mm ? (mm < 10 ? "0" : "") + mm : ""); }
  function faixasDoDia(g, dia) {
    // só faixas de verdade: [abre, fecha] em minutos inteiros, abre antes de fechar
    return (Array.isArray(g[dia]) ? g[dia] : []).filter(function (f) {
      return Array.isArray(f) && typeof f[0] === "number" && typeof f[1] === "number" && f[0] % 1 === 0 && f[1] % 1 === 0 && f[0] >= 0 && f[0] < f[1] && f[1] <= 1440;
    });
  }
  function status() {
    var g = E.grade; if (!g || typeof g !== "object") return null;
    var a = agoraSP(), hoje = faixasDoDia(g, a.dia);
    for (var i = 0; i < hoje.length; i++) {
      if (a.min >= hoje[i][0] && a.min < hoje[i][1]) return { aberto: true, txt: "Aberto agora · até " + hora(hoje[i][1]) };
      if (a.min < hoje[i][0]) return { aberto: false, txt: "Fechado agora · abre às " + hora(hoje[i][0]) };
    }
    for (var k = 1; k <= 7; k++) {
      var dia = (a.dia + k) % 7, f = faixasDoDia(g, dia);
      if (f && f.length) {
        var nome = k === 1 ? "amanhã" : ["domingo", "segunda", "terça", "quarta", "quinta", "sexta", "sábado"][dia];
        return { aberto: false, txt: "Fechado agora · abre " + nome + " às " + hora(f[0][0]) };
      }
    }
    return null;
  }
  DS.status = status;
  function horarios() {
    var lista = (Array.isArray(E.horarios) ? E.horarios : []).filter(function (h) { return h && typeof h === "object"; });
    if (lista.length) {
      $$("[data-horarios]").forEach(function (dl) {
        dl.innerHTML = lista.map(function (h) { return "<dt>" + esc(h.dias) + "</dt><dd>" + esc(h.horas) + "</dd>"; }).join("");
      });
      // horário em uma frase (respostas das perguntas frequentes)
      $$("[data-horarios-texto]").forEach(function (el) {
        el.textContent = lista.map(function (h) { return h.dias + ": " + String(h.horas || "").toLowerCase(); }).join("; ");
      });
    }
    pintaStatus();
    // a página pode ficar aberta na hora de fechar: confere a cada minuto e ao voltar para a aba
    if ($("[data-status]")) {
      setInterval(pintaStatus, 60000);
      d.addEventListener("visibilitychange", function () { if (!d.hidden) pintaStatus(); });
    }
  }
  function pintaStatus() {
    var s = status();
    $$("[data-status]").forEach(function (el) {
      if (!s) return;
      if (!s.aberto && el.closest(".hero")) { el.hidden = true; return; }   // no topo, só "Aberto agora"
      el.hidden = false;
      el.classList.toggle("is-closed", !s.aberto);
      var t = $("span", el); if (t) t.textContent = s.txt;
    });
  }

  /* ---------- Murais de logos ---------- */
  var SEGMENTOS = {
    saude: "Saúde", educacao: "Educação", industria: "Indústria", igrejas: "Igrejas",
    comercio: "Comércio e serviços", automotivo: "Automotivo", publico: "Serviços públicos"
  };
  var CATEGORIAS = {
    "som-automotivo": "Som automotivo", sonorizacao: "Sonorização e áudio", imagem: "Imagem e vídeo",
    seguranca: "Segurança e CFTV", iluminacao: "Iluminação", estetica: "Estética automotiva",
    eletrica: "Materiais elétricos", ferramentas: "Ferramentas"
  };
  DS.SEGMENTOS = SEGMENTOS; DS.CATEGORIAS = CATEGORIAS;

  function visiveis(lista) { return (Array.isArray(lista) ? lista : []).filter(function (x) { return x && x.visivel !== false; }); }
  DS.clientes = function () { return visiveis(w.DANNI_CLIENTES); };
  DS.marcas = function () { return visiveis(w.DANNI_MARCAS); };

  function cardCliente(c) {
    var seg = String(c.segmento || "").split(" ").map(function (k) { return SEGMENTOS[k]; }).filter(Boolean).join(" e ");
    var corpo = c.logo
      ? '<img src="' + esc(c.logo) + '" alt="' + esc(c.nome) + '" loading="lazy" decoding="async"' + (c.w ? ' width="' + c.w + '" height="' + c.h + '"' : "") + ">"
      : "<b>" + esc(c.nome) + "</b>";
    return '<li class="logo-card' + (c.logo ? "" : " logo-card--text" + (String(c.nome || "").length > 24 ? " logo-card--longo" : "")) + (c.fundo === "escuro" ? " logo-card--dark" : "") + '" data-seg="' + esc(String(c.segmento || "")) + '">' + corpo +
      (c.logo ? '<span class="logo-card__name" aria-hidden="true">' + esc(c.nome) + (seg ? " · " + esc(seg) : "") + "</span>" : "") + "</li>";
  }
  // Logo da marca: com fundo transparente vira máscara (pinta na cor da seção: branco no escuro,
  // azul no claro); "cor": true mostra o arquivo original num quadro branco.
  function cardMarca(m) {
    var corpo;
    if (m.logo && m.cor) corpo = '<img src="' + esc(m.logo) + '" alt="' + esc(m.nome) + '" loading="lazy" decoding="async">';
    // endereço completo: url() dentro de variável CSS seria lido a partir da pasta do CSS
    else if (m.logo) corpo = '<span class="brand__logo" role="img" aria-label="' + esc(m.nome) + '" style="--logo:url(\'' + esc(new URL(m.logo, d.baseURI).href) + '\')"></span>';
    else corpo = '<b class="brand__txt">' + esc(m.nome) + "</b>";
    return '<li class="brand' + (m.cor ? " brand--color" : "") + '" data-cat="' + esc(String(m.categoria || "")) + '" title="' + esc(m.nome) + '">' + corpo + "</li>";
  }
  DS.cardCliente = cardCliente; DS.cardMarca = cardMarca;

  function murais() {
    var temC = Array.isArray(w.DANNI_CLIENTES), temM = Array.isArray(w.DANNI_MARCAS);
    if (temC) $$("[data-clientes]").forEach(function (ul) {
      var lista = DS.clientes(), seg = ul.getAttribute("data-segmento"), lim = +ul.getAttribute("data-limite") || 0;
      if (seg) lista = lista.filter(function (c) { return String(c.segmento || "").split(" ").indexOf(seg) >= 0; });
      if (ul.hasAttribute("data-so-logos")) lista = lista.filter(function (c) { return c.logo; });
      if (lim) lista = lista.slice(0, lim);
      ul.innerHTML = lista.map(cardCliente).join("") + (ul.hasAttribute("data-convite")
        ? '<li class="logo-card logo-card--cta" data-seg="todos"><a href="contato.html"><b>Sua empresa aqui?</b><span>Fale com a gente</span></a></li>' : "");
      // página Clientes: segmento que ficou sem nenhum cliente visível sai da página
      var sec = seg && ul.closest(".seg"); if (sec) sec.hidden = !lista.length;
    });
    if (temM) $$("[data-marcas]").forEach(function (ul) {
      var lista = DS.marcas(), cat = ul.getAttribute("data-categoria");
      if (cat) lista = lista.filter(function (m) { return String(m.categoria || "").split(" ").indexOf(cat) >= 0; });
      var linha = +ul.getAttribute("data-linha");   // faixas: metade das marcas em cada linha
      if (linha) { var meio = Math.ceil(lista.length / 2); lista = linha === 1 ? lista.slice(0, meio) : lista.slice(meio); }
      ul.innerHTML = lista.map(cardMarca).join("");
    });
    if (Array.isArray(E.depoimentos)) $$("[data-depoimentos]").forEach(function (ul) {
      var lim = +ul.getAttribute("data-limite") || 0, lista = (Array.isArray(E.depoimentos) ? E.depoimentos : []).slice(0, lim || undefined);
      ul.innerHTML = lista.map(function (r) {
        return '<li class="rev"><svg class="rev__q" aria-hidden="true"><use href="#i-quote"/></svg><p>' + esc(r.texto) + '</p><span class="rev__a"><b>' + esc(r.nome) + "</b> · avaliação no Google</span></li>";
      }).join("");
    });
    if (temC) $$("[data-conta-seg]").forEach(function (el) {
      var k = el.getAttribute("data-conta-seg");
      var n = DS.clientes().filter(function (c) { return String(c.segmento || "").split(" ").indexOf(k) >= 0; }).length;
      el.textContent = n;
      var li = el.closest("li"); if (li) li.hidden = !n;
    });
    $$("[data-conta]").forEach(function (el) {
      var k = el.getAttribute("data-conta");
      var n = k === "clientes" ? (temC ? DS.clientes().length : 0) : k === "marcas" ? (temM ? DS.marcas().length : 0) : 0;
      if (n) { el.textContent = n; if (el.hasAttribute("data-count")) el.setAttribute("data-count", n); }
    });
    filtros();
    $$("[data-convite]").forEach(fechaLinha);
  }

  // O quadro "Sua empresa aqui?" ocupa o que sobra da última linha (a grade nunca fica com buraco)
  function fechaLinha(ul) {
    var f = ul.querySelector(".logo-card--cta"); if (!f) return;
    f.style.gridColumn = "";
    var cols = getComputedStyle(ul).gridTemplateColumns.split(" ").filter(Boolean).length;
    var n = Array.prototype.filter.call(ul.children, function (li) { return li !== f && !li.hidden && getComputedStyle(li).display !== "none"; }).length;
    var r = cols - (n % cols);
    if (cols > 1 && r > 1) f.style.gridColumn = "span " + r;
    f.classList.toggle("is-wide", cols > 1 && r > 1);
  }
  DS.fechaLinha = fechaLinha;
  var tReflow;
  w.addEventListener("resize", function () { clearTimeout(tReflow); tReflow = setTimeout(function () { $$("[data-convite]").forEach(fechaLinha); eixo(); }, 150); });

  // Filtros por segmento/categoria (chips) com animação Flip quando disponível
  function filtros() {
    $$("[data-filtros]").forEach(function (grupo) {
      var alvo = $(grupo.getAttribute("data-filtros")); if (!alvo) return;
      var attr = grupo.getAttribute("data-attr") || "data-seg";
      var botoes = $$("button", grupo);
      // esconde filtros sem itens
      botoes.forEach(function (b) {
        var v = b.getAttribute("data-v");
        if (v && !alvo.querySelector("[" + attr + '="' + v + '"], [' + attr + '~="' + v + '"]')) b.hidden = true;
        var n = v ? alvo.querySelectorAll("[" + attr + '~="' + v + '"]').length : alvo.querySelectorAll(":scope > :not([" + attr + '="todos"])').length;
        var c = $("sup", b); if (c) c.textContent = n;
      });
      botoes.forEach(function (b) {
        b.addEventListener("click", function () {
          var v = b.getAttribute("data-v");
          botoes.forEach(function (x) { x.setAttribute("aria-pressed", x === b ? "true" : "false"); });
          var itens = Array.prototype.slice.call(alvo.children);
          var estado = w.Flip && !reduz ? w.Flip.getState(itens) : null;
          var vis = 0;
          itens.forEach(function (it) {
            var vals = (it.getAttribute(attr) || "").split(" ");
            it.hidden = !!v && vals.indexOf(v) < 0 && vals.indexOf("todos") < 0;
            if (!it.hidden && vals.indexOf("todos") < 0) vis++;
          });
          var aviso = grupo.parentNode.querySelector("[data-filtro-aviso]");
          if (aviso) aviso.textContent = vis + (vis === 1 ? " item" : " itens") + (v ? " em " + b.textContent.replace(/\d+/g, "").trim() : "");
          if (alvo.hasAttribute("data-convite")) fechaLinha(alvo);
          if (estado) w.Flip.from(estado, { duration: .55, ease: "power3.inOut", scale: true, absolute: true,
            onComplete: function () { if (alvo.hasAttribute("data-convite")) fechaLinha(alvo); }, onEnter: function (els) { return w.gsap.fromTo(els, { opacity: 0, scale: .9 }, { opacity: 1, scale: 1, duration: .45 }); }, onLeave: function (els) { return w.gsap.to(els, { opacity: 0, scale: .9, duration: .3 }); } });
          if (w.ScrollTrigger) setTimeout(function () { w.ScrollTrigger.refresh(); }, 650);
        });
      });
    });
  }

  /* ---------- Cabeçalho ---------- */
  var hdr = $(".hdr"), ultimoY = 0;
  function aoRolar(y) {
    if (!hdr) return;
    hdr.classList.toggle("is-solid", y > 24);
    var menuAberto = d.body.classList.contains("menu-open"), dy = y - ultimoY;
    // o Lenis termina cada rolagem com passos de 1px: só muda o estado com movimento de verdade
    if (!menuAberto) {
      if (y > 520 && dy > 4) hdr.classList.add("is-hidden");
      else if (dy < -4 || y <= 520) hdr.classList.remove("is-hidden");
    }
    ultimoY = y;
    var on = y > (w.innerHeight * .55);
    var fab = $(".fab"), bar = $(".actbar"), cta = $(".ftr__cta");
    var noRodape = cta && cta.getBoundingClientRect().top < w.innerHeight * .9;
    if (fab) fab.classList.toggle("is-on", on && !noRodape);
    if (bar) bar.classList.toggle("is-on", on);
    faixa(y);
  }

  /* ---------- Menu do celular ---------- */
  var burger = $(".burger"), menu = $("#menu"), lenis = null;
  function focaveis() { return [burger].concat($$("a, button", menu)); }
  function abrir() {
    menu.hidden = false;
    void menu.offsetWidth;   // aplica o display antes da transição
    requestAnimationFrame(function () {
      d.body.classList.add("menu-open");
      burger.setAttribute("aria-expanded", "true");
      burger.setAttribute("aria-label", "Fechar menu");
      if (lenis) lenis.stop(); else d.body.style.overflow = "hidden";
      ["main", ".ftr", ".actbar", ".fab", ".dial"].forEach(function (s) { var el = $(s); if (el) el.inert = true; });
      var a = $(".menu__links a", menu); if (a) setTimeout(function () { a.focus({ preventScroll: true }); }, 80);
    });
  }
  function fechar(semFoco) {
    d.body.classList.remove("menu-open");
    burger.setAttribute("aria-expanded", "false");
    burger.setAttribute("aria-label", "Abrir menu");
    if (lenis) lenis.start(); else d.body.style.overflow = "";
    ["main", ".ftr", ".actbar", ".fab", ".dial"].forEach(function (s) { var el = $(s); if (el) el.inert = false; });
    if (!semFoco) burger.focus({ preventScroll: true });
    setTimeout(function () { if (!d.body.classList.contains("menu-open")) menu.hidden = true; }, 650);
  }
  function setupMenu() {
    if (!burger || !menu) return;
    burger.addEventListener("click", function () { d.body.classList.contains("menu-open") ? fechar() : abrir(); });
    d.addEventListener("keydown", function (ev) {
      if (!d.body.classList.contains("menu-open")) return;
      if (ev.key === "Escape") { fechar(); return; }
      if (ev.key === "Tab") {
        var f = focaveis().filter(function (x) { return x.offsetParent !== null; }), i = f.indexOf(d.activeElement);
        if (ev.shiftKey && i <= 0) { ev.preventDefault(); f[f.length - 1].focus(); }
        else if (!ev.shiftKey && i === f.length - 1) { ev.preventDefault(); f[0].focus(); }
      }
    });
    $$("a", menu).forEach(function (a) { a.addEventListener("click", function () { fechar(true); }); });
    w.addEventListener("resize", function () { if (w.innerWidth > 1100 && d.body.classList.contains("menu-open")) fechar(true); });
  }

  /* ---------- Faixa de áudio do rodapé ---------- */
  var onda = null, barras = [], ultimoP = -1;
  function montaFaixa() {
    onda = $("[data-track-wave]"); if (!onda) return;
    var n = Math.max(48, Math.min(180, Math.round(onda.clientWidth / 8)));
    var h = "";
    for (var i = 0; i < n; i++) {
      // forma de onda fixa (soma de senos): parece música, mas não é dado
      var t = i / n, k = .18 + .82 * Math.abs(Math.sin(i * .31) * .55 + Math.sin(i * .113 + 1.3) * .35 + Math.sin(i * .9) * .1) * (0.55 + .45 * Math.sin(t * Math.PI));
      h += '<i style="--k:' + Math.min(1, k).toFixed(3) + '"></i>';
    }
    onda.innerHTML = h;
    barras = $$("i", onda);
    onda.addEventListener("pointermove", function (ev) {
      var r = onda.getBoundingClientRect(), j = Math.floor((ev.clientX - r.left) / r.width * barras.length);
      barras.forEach(function (b, x) { b.classList.toggle("hv", x === j); });
    });
    onda.addEventListener("pointerleave", function () { barras.forEach(function (b) { b.classList.remove("hv"); }); });
    onda.addEventListener("click", function (ev) {
      var r = onda.getBoundingClientRect(), f = Math.max(0, Math.min(1, (ev.clientX - r.left) / r.width));
      var max = d.documentElement.scrollHeight - w.innerHeight;
      if (lenis) lenis.scrollTo(f * max, { duration: 1.6 }); else w.scrollTo({ top: f * max, behavior: reduz ? "auto" : "smooth" });
    });
  }
  /* ---------- Frequências: eixo da faixa do rodapé e o "sintonizador" lateral ---------- */
  function secoesHz() {
    return $$("[data-hz]").map(function (el) { return { el: el, hz: el.getAttribute("data-hz"), nome: el.getAttribute("data-nome") || "" }; });
  }
  function eixo() {
    var box = $("[data-track-eixo]"), lista = secoesHz(); if (!box || lista.length < 2) return;
    var max = d.documentElement.scrollHeight - w.innerHeight; if (max <= 0) return;
    // cada frequência fica no ponto da faixa em que a seção aparece (mesma conta das barras amarelas)
    box.classList.add("track__axis--hz");
    box.innerHTML = lista.map(function (s) {
      var top = s.el.getBoundingClientRect().top + w.scrollY - w.innerHeight * .3;
      var p = Math.max(0, Math.min(1, top / max));
      return '<span style="--x:' + (p * 100).toFixed(2) + '%">' + esc(s.hz) + "</span>";
    }).join("");
    // em telas estreitas os rótulos se encostam: some com os do meio que não cabem
    var sps = $$("span", box), fim = sps[sps.length - 1].getBoundingClientRect().left, ult = -1e9;
    sps.forEach(function (sp, i) {
      var r = sp.getBoundingClientRect(), cabe = i === 0 || i === sps.length - 1 || (r.left >= ult + 12 && r.right <= fim - 12);
      sp.style.visibility = cabe ? "" : "hidden";
      if (cabe) ult = r.right;
    });
  }
  function sintonizador() {
    var lista = secoesHz(); if (lista.length < 4) return;
    var nav = d.createElement("nav");
    nav.className = "dial"; nav.setAttribute("aria-label", "Seções da página");
    nav.innerHTML = '<ol role="list">' + lista.map(function (s, i) {
      return '<li><button type="button" data-i="' + i + '" aria-label="Ir para ' + esc(s.nome) + " (" + esc(s.hz) + ')"><i style="--k:' + (0.35 + 0.65 * i / (lista.length - 1)).toFixed(2) + '"></i><span>' + esc(s.nome) + "</span></button></li>";
    }).join("") + '</ol><output class="dial__hz" aria-hidden="true">' + esc(lista[0].hz) + "</output>";
    d.body.appendChild(nav);
    var bts = $$("button", nav), out = $(".dial__hz", nav), atual = -1;
    bts.forEach(function (b) {
      b.addEventListener("click", function () {
        var s = lista[+b.getAttribute("data-i")].el;
        if (lenis) lenis.scrollTo(s, { duration: 1.3 }); else s.scrollIntoView({ behavior: reduz ? "auto" : "smooth" });
      });
    });
    function marca() {
      var meio = w.innerHeight * .45, i = 0;
      lista.forEach(function (s, k) { if (s.el.getBoundingClientRect().top <= meio) i = k; });
      if (i === atual) return;
      atual = i;
      bts.forEach(function (b, k) { b.classList.toggle("on", k === i); if (k === i) b.setAttribute("aria-current", "true"); else b.removeAttribute("aria-current"); });
      out.textContent = lista[i].hz;
      nav.classList.toggle("is-dark", !!lista[i].el.closest(".t-dark, .t-deep, .t-blue") || lista[i].el.classList.contains("t-dark") || lista[i].el.classList.contains("t-deep") || lista[i].el.classList.contains("t-blue"));
    }
    DS.dialMarca = marca;
    marca();
    w.addEventListener("scroll", marca, { passive: true });
  }

  function faixa(y) {
    if (!barras.length) return;
    var max = d.documentElement.scrollHeight - w.innerHeight, p = max > 0 ? y / max : 1;
    var k = Math.round(p * barras.length);
    if (k === ultimoP) return;
    ultimoP = k;
    for (var i = 0; i < barras.length; i++) barras[i].classList.toggle("on", i < k);
  }

  /* ---------- Faixas que rolam ---------- */
  function tickers() {
    $$(".ticker__track").forEach(function (t) {
      if (t.dataset.pronto) return;
      var itens = $$(".ticker__item", t), largura = t.scrollWidth || 1, vezes = Math.max(2, Math.ceil((w.innerWidth * 2) / largura));
      // duas metades iguais (a animação anda -50%); as cópias ficam escondidas dos leitores de tela
      for (var k = 1; k < vezes * 2; k++) itens.forEach(function (it) { var c = it.cloneNode(true); c.setAttribute("aria-hidden", "true"); t.appendChild(c); });
      t.dataset.pronto = "1";
    });
    // pausa a faixa e as animações que não param sozinhas (WCAG 2.2.2)
    $$("[data-pausa]").forEach(function (b) {
      b.addEventListener("click", function () {
        var on = b.getAttribute("aria-pressed") !== "true";
        b.setAttribute("aria-pressed", on ? "true" : "false");
        $(".sr-only", b).textContent = on ? "Continuar animações" : "Pausar animações";
        var tk = b.closest(".ticker"); if (tk) tk.classList.toggle("is-paused", on);
        d.documentElement.classList.toggle("pausado", on);
        (DS.loops || []).forEach(function (tw) { tw.paused(on); });
      });
    });
  }

  /* ---------- O que está na tela ---------- */
  function vigia() {
    if (!("IntersectionObserver" in w)) return;
    html.classList.add("io", "vigia");
    // as animações que não param sozinhas (ondinhas, faixas, anéis) só rodam na seção que está na tela
    var secoes = new IntersectionObserver(function (es) {
      es.forEach(function (e) { e.target.classList.toggle("is-vis", e.isIntersecting); });
    });
    $$("main section, .ftr").forEach(function (el) { secoes.observe(el); });
    // logos das marcas são máscaras CSS (não aceitam loading="lazy"): só baixam quando o mural chega perto
    var marcas = new IntersectionObserver(function (es) {
      es.forEach(function (e) { if (e.isIntersecting) { e.target.classList.add("is-vis"); marcas.unobserve(e.target); } });
    }, { rootMargin: "600px 0px" });
    $$("[data-marcas]").forEach(function (el) { marcas.observe(el); });
  }

  /* ---------- Contadores ---------- */
  function contar(el) {
    var fim = parseFloat(el.getAttribute("data-count")) || 0;
    if (reduz || !temGSAP()) { el.textContent = fim; return; }
    var o = { v: 0 };
    w.gsap.to(o, { v: fim, duration: 1.6, ease: "power3.out", onUpdate: function () { el.textContent = Math.round(o.v); } });
  }

  /* ---------- Rolagem até âncoras (com Lenis) ---------- */
  // o destino de uma âncora aparece já no lugar: sem esperar a entrada nem ficar 28 px abaixo
  function mostra(alvo) {
    var el = alvo.closest("[data-rv]");
    if (el && !el.classList.contains("is-in")) {
      el.classList.add("is-in");
      if (w.gsap) w.gsap.set(el, { clearProps: "opacity,transform" });
    }
  }
  function ancoras() {
    d.addEventListener("click", function (ev) {
      var a = ev.target.closest && ev.target.closest('a[href^="#"]');
      if (!a) return;
      var id = a.getAttribute("href").slice(1); if (!id) return;
      if (id === "topo") {   // o cabeçalho é fixo: "Voltar ao início" vai para o topo da página
        ev.preventDefault();
        if (lenis) lenis.scrollTo(0, { duration: 1.4 }); else w.scrollTo({ top: 0, behavior: reduz ? "auto" : "smooth" });
        var logo = $(".hdr__logo"); if (logo) logo.focus({ preventScroll: true });
        history.replaceState(null, "", location.pathname + location.search);
        return;
      }
      var alvo = d.getElementById(id); if (!alvo) return;
      ev.preventDefault();
      mostra(alvo);
      // a distância do cabeçalho vem do scroll-padding-top do html (o Lenis e o scrollIntoView descontam)
      if (lenis) lenis.scrollTo(alvo, { duration: 1.2 });
      else alvo.scrollIntoView({ behavior: reduz ? "auto" : "smooth" });
      // o foco vai junto (leitores de tela e teclado continuam dali)
      if (!alvo.matches("a[href], button, input, select, textarea, [tabindex]")) alvo.setAttribute("tabindex", "-1");
      alvo.focus({ preventScroll: true });
      history.replaceState(null, "", "#" + id);
    });
  }

  /* ---------- Animações ---------- */
  function animar() {
    var g = w.gsap, ST = w.ScrollTrigger;
    g.registerPlugin(ST);
    if (w.SplitText) g.registerPlugin(w.SplitText);
    if (w.Flip) g.registerPlugin(w.Flip);

    // Lenis: rolagem suave só com mouse (no toque fica a rolagem nativa do aparelho)
    if (w.Lenis && !w.matchMedia("(pointer: coarse)").matches) {
      lenis = new w.Lenis({ duration: 1.1, easing: function (t) { return Math.min(1, 1.001 - Math.pow(2, -10 * t)); }, smoothWheel: true });
      lenis.on("scroll", function (e) { ST.update(); aoRolar(e.scroll); });
      g.ticker.add(function (t) { lenis.raf(t * 1000); });
      g.ticker.lagSmoothing(0);
      DS.lenis = lenis;
    }

    // a página já apareceu sem animação (o JavaScript chegou depois de 1,5 s): não refaz as entradas
    var entrada = html.classList.contains("anim");

    // títulos linha a linha
    var dividir = function () {
      $$("[data-split]").forEach(function (el) {
        if (el.closest(".phero")) return;   // o título do topo das páginas internas entra só com CSS
        el.classList.add("is-in");
        if (!w.SplitText) { g.from(el, { y: 30, opacity: 0, duration: 1, ease: "expo.out", scrollTrigger: { trigger: el, start: "top 88%", once: true } }); return; }
        w.SplitText.create(el, {
          type: "lines", mask: "lines", linesClass: "ln", autoSplit: true,
          onSplit: function (self) {
            return g.from(self.lines, { yPercent: 108, duration: 1.1, ease: "expo.out", stagger: .09, scrollTrigger: { trigger: el, start: "top 88%", once: true } });
          }
        });
      });
    };
    if (entrada) { if (d.fonts && d.fonts.ready) d.fonts.ready.then(dividir); else dividir(); }

    // blocos que sobem (o topo das páginas internas entra só com CSS: a imagem principal não espera o JavaScript)
    if (entrada) ST.batch($$("[data-rv]").filter(function (el) { return !el.closest(".phero"); }), {
      start: "top 90%", once: true,
      onEnter: function (b) {
        // chegando por âncora (ou pulando com a barra), tudo o que ficou acima entra de uma vez:
        // o que já passou aparece na hora e o que está na tela não espera a fila inteira
        var passou = b.filter(function (el) { return el.getBoundingClientRect().bottom <= 0; });
        var resto = b.filter(function (el) { return passou.indexOf(el) < 0 && !el.classList.contains("is-in"); });
        passou.forEach(function (el) { el.classList.add("is-in"); g.set(el, { clearProps: "opacity,transform" }); });
        if (!resto.length) return;
        g.to(resto, {
          opacity: 1, y: 0, scale: 1, duration: 1, ease: "expo.out", stagger: Math.min(.08, .4 / resto.length), overwrite: true,
          onComplete: function () { resto.forEach(function (el) { el.classList.add("is-in"); g.set(el, { clearProps: "opacity,transform" }); }); }
        });
      }
    });

    // contadores (sem entrada, já mostram o número final: não voltam a zero na frente de quem lê)
    $$("[data-count]").forEach(function (el) {
      if (!entrada) { el.textContent = el.getAttribute("data-count"); return; }
      ST.create({ trigger: el, start: "top 92%", once: true, onEnter: function () { contar(el); } });
    });

    // imagens com leve parallax
    $$("[data-parallax]").forEach(function (el) {
      var q = parseFloat(el.getAttribute("data-parallax")) || 10;
      g.fromTo(el, { yPercent: -q }, { yPercent: q, ease: "none", scrollTrigger: { trigger: el.parentElement, start: "top bottom", end: "bottom top", scrub: true } });
    });

    html.classList.add("anim-on");
    if (typeof DS.paginaAnimar === "function") DS.paginaAnimar(g, ST, entrada);
    w.addEventListener("load", function () { ST.refresh(); });
  }

  /* ---------- Início ---------- */
  function passo(fn) {
    try { fn(); return true; } catch (e) { if (w.console) console.error(e); return false; }
  }
  function iniciar() {
    passo(preencher);
    passo(murais);
    passo(setupMenu);
    passo(montaFaixa);
    passo(tickers);
    passo(vigia);
    w.addEventListener("load", function () { passo(eixo); });
    setTimeout(function () { passo(eixo); }, 1500);
    passo(ancoras);
    if (typeof DS.pagina === "function") passo(DS.pagina);
    if (!(!reduz && temGSAP() && passo(animar))) {
      html.classList.remove("anim");
      $$("[data-count]").forEach(function (el) { el.textContent = el.getAttribute("data-count"); });
    }
    if (!lenis) w.addEventListener("scroll", function () { aoRolar(w.scrollY); }, { passive: true });
    // chegou por um link com #âncora (ex.: contato.html#orcamento): para logo abaixo do cabeçalho
    if (location.hash.length > 1) {
      var alvo = d.getElementById(location.hash.slice(1));
      if (alvo) {
        // a posição sai do scroll real: o Lenis ainda não leu o pulo que o navegador deu até a âncora
        var mexeu = false, vai = function () {
          if (mexeu) return;
          if (!lenis) { alvo.scrollIntoView(); return; }
          var pad = parseFloat(getComputedStyle(html).scrollPaddingTop) || 0;
          lenis.scrollTo(alvo.getBoundingClientRect().top + w.scrollY - pad, { immediate: true, force: true });
        };
        ["wheel", "touchstart", "keydown", "mousedown"].forEach(function (t) { w.addEventListener(t, function () { mexeu = true; }, { passive: true, once: true }); });
        mostra(alvo);
        setTimeout(function () {
          vai();
          if (location.hash === "#orcamento" && w.matchMedia("(pointer: fine)").matches) { var n = d.getElementById("f-nome"); if (n) n.focus({ preventScroll: true }); }
        }, 60);
        // e de novo quando as imagens terminam (até o "load" o navegador também reposiciona a âncora)
        w.addEventListener("load", function () { setTimeout(vai, 0); });
      }
    }
    passo(sintonizador);
    passo(function () { aoRolar(w.scrollY); });
    // sem rolagem a página inteira cabe: mostra o botão flutuante assim mesmo
    setTimeout(function () { var f = $(".fab"); if (f && d.documentElement.scrollHeight <= w.innerHeight * 1.3) f.classList.add("is-on"); }, 1200);
  }
  DS.contar = contar;
  // scripts com defer: o DOMContentLoaded só chega depois de todos (home.js etc. já carregados)
  if (d.readyState === "complete") iniciar(); else d.addEventListener("DOMContentLoaded", iniciar);
})();

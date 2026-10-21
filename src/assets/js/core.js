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
  var DS = (w.DS = w.DS || {});   // funções compartilhadas com as páginas (home.js etc.)
  DS.E = E; DS.$ = $; DS.$$ = $$; DS.reduz = reduz;

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
    var e = E.endereco || {};
    E.enderecoCurto = [e.rua, e.bairro, (e.cidade || "") + (e.uf ? "-" + e.uf : "")].filter(Boolean).join(" · ");
    e.linha1 = [e.rua, e.bairro].filter(Boolean).join(" · ");
    e.linha2 = [(e.cidade || "") + (e.uf ? "-" + e.uf : ""), e.cep].filter(Boolean).join(" · ");
    E.endereco = e;
  }
  function linkWhats(msg) {
    var n = String(E.whatsapp || "").replace(/\D/g, "");
    if (!n) return "";
    return "https://wa.me/" + n + "?text=" + encodeURIComponent(msg || E.whatsappMensagem || "Olá!");
  }
  DS.linkWhats = linkWhats;

  function preencher() {
    derivados();
    $$("[data-e]").forEach(function (el) {
      var v = valor(el.getAttribute("data-e"));
      if (v != null && v !== "") el.textContent = v;
    });
    $$("[data-if]").forEach(function (el) {
      var v = valor(el.getAttribute("data-if"));
      if (v == null || v === "" || (Array.isArray(v) && !v.length)) el.hidden = true;
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
      if (v) el.setAttribute("href", v);
      else if (k === "instagram" || k === "facebook" || k === "google") el.hidden = true;
    });
    $$("[data-wa]").forEach(function (el) {
      var l = linkWhats(el.getAttribute("data-wa"));
      if (l) { el.href = l; el.target = "_blank"; el.rel = "noopener"; return; }
      // sem WhatsApp cadastrado: o botão leva para a página de contato, com ícone de conversa
      $$('use[href="#i-whatsapp"]', el).forEach(function (u) { u.setAttribute("href", "#i-chat"); });
      $$("[data-wa-txt]", el).forEach(function (t) { t.textContent = t.getAttribute("data-wa-txt"); });
    });
    $$("[data-ano]").forEach(function (el) { el.textContent = new Date().getFullYear(); });
    $$("[data-anos]").forEach(function (el) { el.textContent = new Date().getFullYear() - (E.desde || 2000); });
    horarios();
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
  function status() {
    var g = E.grade; if (!g) return null;
    var a = agoraSP(), hoje = g[a.dia] || [];
    for (var i = 0; i < hoje.length; i++) {
      if (a.min >= hoje[i][0] && a.min < hoje[i][1]) return { aberto: true, txt: "Aberto agora · até " + hora(hoje[i][1]) };
      if (a.min < hoje[i][0]) return { aberto: false, txt: "Fechado agora · abre às " + hora(hoje[i][0]) };
    }
    for (var k = 1; k <= 7; k++) {
      var dia = (a.dia + k) % 7, f = g[dia];
      if (f && f.length) {
        var nome = k === 1 ? "amanhã" : ["domingo", "segunda", "terça", "quarta", "quinta", "sexta", "sábado"][dia];
        return { aberto: false, txt: "Fechado agora · abre " + nome + " às " + hora(f[0][0]) };
      }
    }
    return null;
  }
  DS.status = status;
  function horarios() {
    var lista = E.horarios || [];
    $$("[data-horarios]").forEach(function (dl) {
      dl.innerHTML = lista.map(function (h) { return "<dt>" + esc(h.dias) + "</dt><dd>" + esc(h.horas) + "</dd>"; }).join("");
    });
    var s = status();
    $$("[data-status]").forEach(function (el) {
      if (!s) return;
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

  function visiveis(lista) { return (lista || []).filter(function (x) { return x && x.visivel !== false; }); }
  DS.clientes = function () { return visiveis(w.DANNI_CLIENTES); };
  DS.marcas = function () { return visiveis(w.DANNI_MARCAS); };

  function cardCliente(c) {
    var seg = (c.segmento || "").split(" ").map(function (k) { return SEGMENTOS[k]; }).filter(Boolean).join(" e ");
    var corpo = c.logo
      ? '<img src="' + esc(c.logo) + '" alt="' + esc(c.nome) + '" loading="lazy" decoding="async"' + (c.w ? ' width="' + c.w + '" height="' + c.h + '"' : "") + ">"
      : "<b>" + esc(c.nome) + "</b>";
    return '<li class="logo-card' + (c.logo ? "" : " logo-card--text") + (c.fundo === "escuro" ? " logo-card--dark" : "") + '" data-seg="' + esc(c.segmento || "") + '">' + corpo +
      (c.logo ? '<span class="logo-card__name">' + esc(c.nome) + (seg ? " · " + esc(seg) : "") + "</span>" : "") + "</li>";
  }
  // Logo da marca: com fundo transparente vira máscara (pinta na cor da seção: branco no escuro,
  // azul no claro); "cor": true mostra o arquivo original num quadro branco.
  function cardMarca(m) {
    var corpo;
    if (m.logo && m.cor) corpo = '<img src="' + esc(m.logo) + '" alt="' + esc(m.nome) + '" loading="lazy" decoding="async">';
    // endereço completo: url() dentro de variável CSS seria lido a partir da pasta do CSS
    else if (m.logo) corpo = '<span class="brand__logo" role="img" aria-label="' + esc(m.nome) + '" style="--logo:url(\'' + esc(new URL(m.logo, d.baseURI).href) + '\')"></span>';
    else corpo = '<b class="brand__txt">' + esc(m.nome) + "</b>";
    return '<li class="brand' + (m.cor ? " brand--color" : "") + '" data-cat="' + esc(m.categoria || "") + '" title="' + esc(m.nome) + '">' + corpo + "</li>";
  }
  DS.cardCliente = cardCliente; DS.cardMarca = cardMarca;

  function murais() {
    $$("[data-clientes]").forEach(function (ul) {
      var lista = DS.clientes(), seg = ul.getAttribute("data-segmento"), lim = +ul.getAttribute("data-limite") || 0;
      if (seg) lista = lista.filter(function (c) { return (c.segmento || "").split(" ").indexOf(seg) >= 0; });
      if (ul.hasAttribute("data-so-logos")) lista = lista.filter(function (c) { return c.logo; });
      if (lim) lista = lista.slice(0, lim);
      ul.innerHTML = lista.map(cardCliente).join("") + (ul.hasAttribute("data-convite")
        ? '<li class="logo-card logo-card--cta" data-seg="todos"><a href="contato.html"><b>Sua empresa aqui?</b><span>Fale com a gente</span></a></li>' : "");
    });
    $$("[data-marcas]").forEach(function (ul) {
      var lista = DS.marcas(), cat = ul.getAttribute("data-categoria");
      if (cat) lista = lista.filter(function (m) { return (m.categoria || "").split(" ").indexOf(cat) >= 0; });
      var linha = +ul.getAttribute("data-linha");   // faixas: metade das marcas em cada linha
      if (linha) { var meio = Math.ceil(lista.length / 2); lista = linha === 1 ? lista.slice(0, meio) : lista.slice(meio); }
      ul.innerHTML = lista.map(cardMarca).join("");
    });
    $$("[data-depoimentos]").forEach(function (ul) {
      var lim = +ul.getAttribute("data-limite") || 0, lista = (E.depoimentos || []).slice(0, lim || undefined);
      ul.innerHTML = lista.map(function (r) {
        return '<li class="rev"><svg class="rev__q" aria-hidden="true"><use href="#i-quote"/></svg><p>' + esc(r.texto) + '</p><span class="rev__a"><b>' + esc(r.nome) + "</b> · avaliação no Google</span></li>";
      }).join("");
    });
    $$("[data-conta-seg]").forEach(function (el) {
      var k = el.getAttribute("data-conta-seg");
      var n = DS.clientes().filter(function (c) { return (c.segmento || "").split(" ").indexOf(k) >= 0; }).length;
      el.textContent = n;
      var li = el.closest("li"); if (li && !n) li.hidden = true;
    });
    $$("[data-conta]").forEach(function (el) {
      var k = el.getAttribute("data-conta");
      var n = k === "clientes" ? DS.clientes().length : k === "marcas" ? DS.marcas().length : 0;
      if (n) { el.textContent = n; if (el.hasAttribute("data-count")) el.setAttribute("data-count", n); }
    });
    filtros();
  }

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
          if (estado) w.Flip.from(estado, { duration: .55, ease: "power3.inOut", scale: true, absolute: true, onEnter: function (els) { return w.gsap.fromTo(els, { opacity: 0, scale: .9 }, { opacity: 1, scale: 1, duration: .45 }); }, onLeave: function (els) { return w.gsap.to(els, { opacity: 0, scale: .9, duration: .3 }); } });
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
    var menuAberto = d.body.classList.contains("menu-open");
    if (!menuAberto) hdr.classList.toggle("is-hidden", y > 520 && y > ultimoY + 2);
    if (y < ultimoY - 2) hdr.classList.remove("is-hidden");
    ultimoY = y;
    var on = y > (w.innerHeight * .55);
    var fab = $(".fab"), bar = $(".actbar");
    if (fab) fab.classList.toggle("is-on", on);
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
      var a = $(".menu__links a", menu); if (a) setTimeout(function () { a.focus({ preventScroll: true }); }, 80);
    });
  }
  function fechar(semFoco) {
    d.body.classList.remove("menu-open");
    burger.setAttribute("aria-expanded", "false");
    burger.setAttribute("aria-label", "Abrir menu");
    if (lenis) lenis.start(); else d.body.style.overflow = "";
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
      var base = t.innerHTML, largura = t.scrollWidth || 1, vezes = Math.max(2, Math.ceil((w.innerWidth * 2) / largura));
      var copia = "";
      for (var i = 1; i < vezes; i++) copia += base.replace(/<(\w+)/, '<$1 aria-hidden="true"');
      // duas metades iguais: a animação anda -50%
      t.innerHTML = base + copia + base + copia;
      $$("[aria-hidden='true'] a, [aria-hidden='true'] button", t).forEach(function (x) { x.tabIndex = -1; });
      t.dataset.pronto = "1";
    });
  }

  /* ---------- Contadores ---------- */
  function contar(el) {
    var fim = parseFloat(el.getAttribute("data-count")) || 0;
    if (reduz || !temGSAP()) { el.textContent = fim; return; }
    var o = { v: 0 };
    w.gsap.to(o, { v: fim, duration: 1.6, ease: "power3.out", onUpdate: function () { el.textContent = Math.round(o.v); } });
  }

  /* ---------- Rolagem até âncoras (com Lenis) ---------- */
  function ancoras() {
    d.addEventListener("click", function (ev) {
      var a = ev.target.closest && ev.target.closest('a[href^="#"]');
      if (!a) return;
      var id = a.getAttribute("href").slice(1); if (!id) return;
      var alvo = d.getElementById(id); if (!alvo) return;
      ev.preventDefault();
      var off = -(hdr ? hdr.offsetHeight : 0) - 12;
      if (lenis) lenis.scrollTo(alvo, { offset: off, duration: 1.2 });
      else w.scrollTo({ top: alvo.getBoundingClientRect().top + w.scrollY + off, behavior: reduz ? "auto" : "smooth" });
      if (id === "conteudo") alvo.focus({ preventScroll: true });
      history.replaceState(null, "", "#" + id);
    });
  }

  /* ---------- Animações ---------- */
  function animar() {
    var g = w.gsap, ST = w.ScrollTrigger;
    g.registerPlugin(ST);
    if (w.SplitText) g.registerPlugin(w.SplitText);
    if (w.Flip) g.registerPlugin(w.Flip);

    // Lenis: rolagem suave no computador (no celular fica a rolagem nativa)
    if (w.Lenis) {
      lenis = new w.Lenis({ duration: 1.1, easing: function (t) { return Math.min(1, 1.001 - Math.pow(2, -10 * t)); }, smoothWheel: true });
      lenis.on("scroll", function (e) { ST.update(); aoRolar(e.scroll); });
      g.ticker.add(function (t) { lenis.raf(t * 1000); });
      g.ticker.lagSmoothing(0);
      DS.lenis = lenis;
    }

    // títulos linha a linha
    var dividir = function () {
      $$("[data-split]").forEach(function (el) {
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
    if (d.fonts && d.fonts.ready) d.fonts.ready.then(dividir); else dividir();

    // blocos que sobem
    ST.batch("[data-rv]", {
      start: "top 90%", once: true,
      onEnter: function (b) {
        g.to(b, {
          opacity: 1, y: 0, scale: 1, duration: 1, ease: "expo.out", stagger: .08, overwrite: true,
          onComplete: function () { b.forEach(function (el) { el.classList.add("is-in"); g.set(el, { clearProps: "opacity,transform" }); }); }
        });
      }
    });

    // contadores
    $$("[data-count]").forEach(function (el) {
      ST.create({ trigger: el, start: "top 92%", once: true, onEnter: function () { contar(el); } });
    });

    // imagens com leve parallax
    $$("[data-parallax]").forEach(function (el) {
      var q = parseFloat(el.getAttribute("data-parallax")) || 10;
      g.fromTo(el, { yPercent: -q }, { yPercent: q, ease: "none", scrollTrigger: { trigger: el.parentElement, start: "top bottom", end: "bottom top", scrub: true } });
    });

    html.classList.add("anim-on");
    if (typeof DS.paginaAnimar === "function") DS.paginaAnimar(g, ST);
    w.addEventListener("load", function () { ST.refresh(); });
  }

  /* ---------- Início ---------- */
  function iniciar() {
    preencher();
    murais();
    setupMenu();
    montaFaixa();
    tickers();
    ancoras();
    if (typeof DS.pagina === "function") DS.pagina();
    if (!reduz && temGSAP()) animar();
    else {
      html.classList.remove("anim");
      $$("[data-count]").forEach(function (el) { el.textContent = el.getAttribute("data-count"); });
    }
    if (!lenis) w.addEventListener("scroll", function () { aoRolar(w.scrollY); }, { passive: true });
    aoRolar(w.scrollY);
    // sem rolagem a página inteira cabe: mostra o botão flutuante assim mesmo
    setTimeout(function () { var f = $(".fab"); if (f && d.documentElement.scrollHeight <= w.innerHeight * 1.3) f.classList.add("is-on"); }, 1200);
  }
  DS.contar = contar;
  // scripts com defer: o DOMContentLoaded só chega depois de todos (home.js etc. já carregados)
  if (d.readyState === "complete") iniciar(); else d.addEventListener("DOMContentLoaded", iniciar);
})();

/* =====================================================================
   Danni Som — js/home.js (página inicial)
   Abas "Para cada lugar", faixas de marcas, texto do manifesto que
   acende com a rolagem e animações do hero (as 4 barras do logo).
   Usa window.DS (de core.js). Carrega antes do core.js terminar de iniciar.
   ===================================================================== */
(function () {
  "use strict";
  var w = window, d = document, DS = (w.DS = w.DS || {});
  var $ = function (s, c) { return (c || d).querySelector(s); };
  var $$ = function (s, c) { return Array.prototype.slice.call((c || d).querySelectorAll(s)); };

  /* ---------- Abas (teclado: setas, Home, End) ---------- */
  function abas() {
    $$("[data-tabs]").forEach(function (box) {
      var tabs = $$('[role="tab"]', box);
      function ativar(t, foco) {
        tabs.forEach(function (x) {
          var on = x === t;
          x.setAttribute("aria-selected", on ? "true" : "false");
          x.tabIndex = on ? 0 : -1;
          var p = d.getElementById(x.getAttribute("aria-controls"));
          if (p) p.hidden = !on;
        });
        if (foco) t.focus();
        t.scrollIntoView({ block: "nearest", inline: "center", behavior: DS.reduz ? "auto" : "smooth" });
        if (w.ScrollTrigger) w.ScrollTrigger.refresh();
      }
      tabs.forEach(function (t, i) {
        t.addEventListener("click", function () { ativar(t); });
        t.addEventListener("keydown", function (ev) {
          var k = ev.key, n = tabs.length, j = null;
          if (k === "ArrowRight") j = (i + 1) % n;
          else if (k === "ArrowLeft") j = (i - 1 + n) % n;
          else if (k === "Home") j = 0;
          else if (k === "End") j = n - 1;
          if (j !== null) { ev.preventDefault(); ativar(tabs[j], true); }
        });
      });
    });
  }

  /* ---------- Colunas-equalizador: abre a banda sob o mouse/foco ---------- */
  function bandas() {
    $$("[data-spectrum]").forEach(function (sp) {
      var bs = $$(".band", sp);
      function abre(b) { bs.forEach(function (x) { x.classList.toggle("is-open", x === b); }); }
      bs.forEach(function (b) {
        b.addEventListener("mouseenter", function () { if (w.matchMedia("(min-width: 901px)").matches) abre(b); });
        b.addEventListener("focus", function () { abre(b); });
      });
    });
  }

  /* ---------- Manifesto: palavras em <span class="w"> ---------- */
  function palavras() {
    $$("[data-words]").forEach(function (el) {
      (function quebra(no) {
        Array.prototype.slice.call(no.childNodes).forEach(function (n) {
          if (n.nodeType === 3) {
            var partes = n.textContent.split(/(\s+)/), frag = d.createDocumentFragment();
            partes.forEach(function (p) {
              if (!p) return;
              if (/^\s+$/.test(p)) frag.appendChild(d.createTextNode(p));
              else { var s = d.createElement("span"); s.className = "w"; s.textContent = p; frag.appendChild(s); }
            });
            no.replaceChild(frag, n);
          } else if (n.nodeType === 1) quebra(n);
        });
      })(el);
    });
  }

  DS.pagina = function () {
    abas();
    bandas();
    if (!DS.reduz) palavras();
  };

  /* ---------- Animações (só com GSAP) ---------- */
  DS.paginaAnimar = function (g, ST) {
    var hero = $(".hero");
    if (hero) introHero(g, hero);

    // manifesto: as palavras "acendem" conforme a leitura
    $$("[data-words]").forEach(function (el) {
      var ws = $$(".w", el); if (!ws.length) return;
      ST.create({
        trigger: el, start: "top 78%", end: "bottom 52%", scrub: true,
        onUpdate: function (s) {
          var n = Math.round(s.progress * ws.length);
          for (var i = 0; i < ws.length; i++) ws[i].classList.toggle("is-on", i < n);
        }
      });
    });

    // processo: as barras sobem como volume
    $$(".step__bar i").forEach(function (b, i) {
      g.from(b, { scaleY: 0, duration: 1.2, ease: "expo.out", delay: i * .12, scrollTrigger: { trigger: ".steps", start: "top 80%", once: true } });
    });

    // história: a faixa amarela cresce entre 2000 e hoje
    var linha = $(".historia__linha i");
    if (linha) g.fromTo(linha, { scaleX: 0 }, { scaleX: 1, ease: "none", scrollTrigger: { trigger: ".historia", start: "top 75%", end: "center 55%", scrub: .6 } });
    var de = $(".historia__de");
    if (de) g.from(de, { xPercent: -8, opacity: 0, duration: 1.2, ease: "expo.out", scrollTrigger: { trigger: ".historia", start: "top 75%", once: true } });
    var ate = $(".historia__ate");
    if (ate) g.from(ate, { xPercent: 8, opacity: 0, duration: 1.2, ease: "expo.out", delay: .15, scrollTrigger: { trigger: ".historia", start: "top 75%", once: true } });
  };

  function introHero(g, hero) {
    var pills = $$(".viz__pill", hero), caps = $$(".viz__bar figcaption", hero), eq = $$(".viz__eq i", hero);
    var tl = g.timeline({ defaults: { ease: "expo.out" }, onComplete: function () { hero.classList.add("is-in"); idle(); } });
    tl.to(".hero__eb", { opacity: 1, y: 0, duration: .8 }, .1)
      .to(".hero__l > span", { y: 0, yPercent: 0, duration: 1.25, stagger: .12 }, .15)
      .to(pills, { scaleY: 1, duration: 1.4, stagger: { each: .1, from: "center" }, ease: "expo.inOut" }, .2)
      .from($$(".viz__pill img", hero), { scale: 1.45, duration: 1.8, stagger: .1 }, .35)
      .to(eq, { scaleY: 1, duration: 1, stagger: .04, ease: "back.out(1.6)" }, .9)
      .to(caps, { opacity: 1, duration: .8, stagger: .08 }, 1.1)
      .to([".hero__lead", ".hero__btns"], { opacity: 1, y: 0, duration: 1, stagger: .1 }, .55)
      .to(".facts li", { opacity: 1, y: 0, duration: .9, stagger: .08 }, .9)
      .to(".hero__scroll", { opacity: 1, duration: .8 }, 1.3);

    // as barras "respiram" como um equalizador e seguem o mouse de leve
    function idle() {
      g.set(pills.concat(caps, eq), { clearProps: "opacity,transform" });
      pills.forEach(function (p, i) {
        g.to(p, { scaleY: .9 - (i % 2) * .04, duration: 1.3 + i * .27, ease: "sine.inOut", yoyo: true, repeat: -1, delay: i * .2 });
      });
      eq.forEach(function (b, i) {
        g.to(b, { scaleY: .35 + ((i * 37) % 50) / 100, duration: .7 + ((i * 13) % 9) / 10, ease: "sine.inOut", yoyo: true, repeat: -1, delay: (i % 5) * .15 });
      });
      if (!w.matchMedia("(pointer: fine)").matches) return;
      var bars = $$(".viz__bar", hero);
      var movers = bars.map(function (b) { return { x: g.quickTo(b, "x", { duration: 1.1, ease: "power3" }), y: g.quickTo(b, "y", { duration: 1.1, ease: "power3" }) }; });
      hero.addEventListener("pointermove", function (ev) {
        var r = hero.getBoundingClientRect(), nx = (ev.clientX - r.left) / r.width - .5, ny = (ev.clientY - r.top) / r.height - .5;
        movers.forEach(function (m, i) { var f = 10 + i * 6; m.x(nx * f); m.y(ny * f * (i % 2 ? -1 : 1)); });
      });
      hero.addEventListener("pointerleave", function () { movers.forEach(function (m) { m.x(0); m.y(0); }); });
    }

    // ao rolar, as barras se afastam e a cópia sobe um pouco (profundidade)
    g.to(".viz", { yPercent: -8, ease: "none", scrollTrigger: { trigger: hero, start: "top top", end: "bottom top", scrub: true } });
    g.to(".hero__copy", { yPercent: 10, opacity: .35, ease: "none", scrollTrigger: { trigger: hero, start: "center center", end: "bottom top", scrub: true } });
  }
})();

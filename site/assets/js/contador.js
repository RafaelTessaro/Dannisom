/* =====================================================================
   Danni Som — js/contador.js (todas as páginas)
   Contador de visitas próprio e anônimo (explicado em privacidade.html):
   uma "página vista" por carregamento e os cliques que mostram o que
   interessa (WhatsApp, telefone, e-mail, mapa, redes, abas, filtros,
   links para as soluções e a loja, envio do formulário de orçamento).
   Vai para painel/visita.php com navigator.sendBeacon. Sem cookies,
   nada guardado no aparelho, nada do que a pessoa digita.
   Não manda nada com "Não rastrear" ou GPC ligado, em navegador
   automatizado, no computador de quem cuida do site (localhost), na
   cópia de demonstração (*.vercel.app, sem PHP) nem nos aparelhos
   marcados no painel ("não contar as minhas visitas").
   ===================================================================== */
(function () {
  "use strict";
  var w = window, d = document, n = navigator, DS = (w.DS = w.DS || {});
  var eu = d.currentScript || d.querySelector('script[src*="assets/js/contador.js"]');
  DS.contaEvento = function () {};   // contato.js chama; sem contador, não faz nada

  function ligado() {
    var h = location.hostname;
    if (!n.sendBeacon || !w.JSON || !/^https?:$/.test(location.protocol)) return false;
    if (h === "localhost" || h === "127.0.0.1" || h === "[::1]" || /\.vercel\.app$/.test(h)) return false;
    if (n.doNotTrack === "1" || w.doNotTrack === "1" || n.globalPrivacyControl) return false;
    if (n.webdriver) return false;
    try { if (w.localStorage.getItem("ds-nao-contar") === "1") return false; } catch (e) { /* sem acesso: conta normalmente */ }
    return true;
  }
  if (!ligado()) return;

  var destino;
  // ao lado do próprio arquivo (assets/js/ → painel/): vale também na 404, que abre em qualquer endereço
  try { destino = eu && eu.src ? new URL("../../painel/visita.php", eu.src).href : new URL("/painel/visita.php", location.href).href; } catch (e) { return; }

  function envia(dados) {
    try { n.sendBeacon(destino, JSON.stringify(dados)); } catch (e) { /* sem contador: o site segue igual */ }
  }

  // a página, pelo endereço (a 404 responde por qualquer endereço: vale a classe do body)
  function pagina() {
    if (d.body && d.body.classList.contains("pg-404")) return "404";
    var p = location.pathname.split("/").pop().replace(/\.html$/, "");
    return !p || p === "index" ? "inicio" : p;
  }

  // onde o clique foi: o id (ou data-nome) da seção; fora delas, a parte fixa da página
  var FORA = [[".fab", "botao-flutuante"], [".actbar", "barra-de-atalhos"], ["#menu", "menu"], [".hdr", "cabecalho"], [".ftr", "rodape"]];
  function secao(el) {
    var s = el.closest("section");
    var id = s && (s.id || s.getAttribute("data-nome") || (s.classList.contains("phero") || s.classList.contains("erro") ? "topo" : ""));
    if (id) return id.slice(0, 60);
    for (var i = 0; i < FORA.length; i++) if (el.closest(FORA[i][0])) return FORA[i][1];
    return "";
  }

  // o texto que aparece no botão (sem ícones, contagens e avisos para leitores de tela)
  function texto(el) {
    var c = el.cloneNode(true);
    Array.prototype.forEach.call(c.querySelectorAll(".sr-only, sup, small, svg, [aria-hidden='true']"), function (x) { x.parentNode.removeChild(x); });
    Array.prototype.forEach.call(c.querySelectorAll("br"), function (x) { x.parentNode.replaceChild(d.createTextNode(" "), x); });
    var t = c.textContent.replace(/\u2011/g, "-").replace(/\s+/g, " ").trim();
    if (!t) t = (el.getAttribute("aria-label") || "").replace(/\s*\(abre em nova aba\)/, "").trim();
    return t.slice(0, 60);
  }

  // destino de um link do próprio site: "solucoes#led", "loja", "clientes#educacao"…
  function destinoInterno(u) {
    var p = u.pathname.split("/").pop().replace(/\.html$/, "");
    if (u.pathname.indexOf("/painel/") >= 0) return "";
    p = !p || p === "index" ? "inicio" : p;
    return (p + (u.hash.length > 1 ? "#" + u.hash.slice(1).toLowerCase() : "")).slice(0, 60);
  }

  // [tipo, rótulo] do que foi clicado, ou null (não conta)
  function tipoDoClique(alvo) {
    var el = alvo.closest('a[href], [role="tab"], .filtros button');
    if (!el) return null;
    if (el.getAttribute("role") === "tab") return el.getAttribute("aria-selected") === "true" ? null : [el, "aba", (el.id || "").replace(/^tab-/, "")];
    if (el.tagName === "BUTTON") return el.getAttribute("aria-pressed") === "true" ? null : [el, "filtro", el.getAttribute("data-v") || "todos"];
    var u, dh = el.getAttribute("data-href") || "";
    try { u = new URL(el.href, location.href); } catch (e) { return null; }
    var host = u.hostname.replace(/^www\./, "");
    if (el.hasAttribute("data-wa")) return [el, /(^|\.)(wa\.me|whatsapp\.com)$/.test(host) ? "whatsapp" : "orcamento", texto(el)];
    if (u.protocol === "tel:") return [el, "telefone", texto(el)];
    if (u.protocol === "mailto:") return [el, "email", texto(el)];
    if (dh === "google") return [el, "avaliacoes", "Avaliações no Google"];
    if (dh === "mapa" || dh === "waze" || /(^|\.)waze\.com$/.test(host) || /^maps\.(google\.|app\.goo\.gl$)/.test(host) || (/(^|\.)google\.[a-z.]+$/.test(host) && /^\/maps/.test(u.pathname))) return [el, "mapa", texto(el)];
    if (/(^|\.)instagram\.com$/.test(host)) return [el, "instagram", texto(el)];
    if (/(^|\.)(facebook\.com|fb\.com|fb\.me)$/.test(host)) return [el, "facebook", texto(el)];
    if (/(^|\.)(wa\.me|whatsapp\.com)$/.test(host)) return [el, "whatsapp", texto(el)];
    if (u.origin !== location.origin) return null;
    if (u.hash === "#visite") return [el, "mapa", texto(el)];   // "Como chegar" da Loja
    if (u.hash === "#topo" || u.hash === "#conteudo") return null;
    // o menu, a trilha e o rodapé só navegam (as páginas vistas já contam); a lista de soluções conta
    if (el.closest("nav") && !el.closest(".jump")) return null;
    var dest = destinoInterno(u);
    return dest ? [el, "link", dest] : null;
  }

  function evento(tipo, el, rotulo, extra) {
    var dados = { t: "c", p: pagina(), k: tipo, s: secao(el), r: String(rotulo || "").slice(0, 80) };
    if (extra) for (var k in extra) if (Object.prototype.hasOwnProperty.call(extra, k)) dados[k] = extra[k];
    envia(dados);
  }
  // formulário de orçamento (contato.js): só as opções marcadas, nunca o que foi digitado
  DS.contaEvento = function (tipo, el, rotulo, extra) { try { evento(tipo, el, rotulo, extra); } catch (e) { /* segue */ } };

  // um ouvinte só, na fase de captura: vê o clique antes de qualquer outro script mexer na página
  d.addEventListener("click", function (ev) {
    try {
      var c = ev.target && ev.target.closest ? tipoDoClique(ev.target) : null;
      if (c) evento(c[1], c[0], c[2]);
    } catch (e) { /* segue */ }
  }, true);

  // página vista: de onde a pessoa veio (só o domínio) e o utm_source do link, se houver
  function paginaVista() {
    var de = "", fonte = "", m;
    try {
      if (d.referrer) { var r = new URL(d.referrer); de = r.host === location.host ? "@" : r.hostname.slice(0, 100); }
      m = /[?&]utm_source=([^&#]*)/.exec(location.search);
      if (m) fonte = decodeURIComponent(m[1].replace(/\+/g, " ")).slice(0, 40);
    } catch (e) { /* sem origem */ }
    envia({ t: "pv", p: pagina(), o: de, f: fonte });
  }
  // pré-carregada pelo navegador e ainda não aberta: conta quando a pessoa abrir
  if (d.prerendering) d.addEventListener("prerenderingchange", paginaVista, { once: true });
  else paginaVista();
})();

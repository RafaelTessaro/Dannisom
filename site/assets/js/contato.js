/* =====================================================================
   Danni Som — js/contato.js
   Formulário de orçamento: monta a mensagem e abre o WhatsApp ou o
   e-mail de quem visita. Nada é enviado para servidor nem fica guardado.
   ===================================================================== */
(function () {
  "use strict";
  var w = window, d = document, DS = (w.DS = w.DS || {});
  var anterior = DS.pagina;

  DS.pagina = function () {
    if (typeof anterior === "function") anterior();
    var E = DS.E || {}, f = d.getElementById("form-orcamento");
    if (f) formulario(f, E);
    var c = d.querySelector("[data-copiar]");
    if (c) c.addEventListener("click", function () {
      var e = E.endereco || {}, txt = [e.rua, e.bairro, (e.cidade || "") + "-" + (e.uf || ""), e.cep].filter(Boolean).join(", ");
      var ok = function () { c.textContent = "Endereço copiado"; setTimeout(function () { c.textContent = "Copiar endereço"; }, 2200); };
      if (navigator.clipboard) navigator.clipboard.writeText(txt).then(ok, function () {}); else ok();
    });
  };

  function formulario(f, E) {
    var temWa = !!String(E.whatsapp || "").replace(/\D/g, "");
    if (!temWa) f.querySelectorAll("[data-so-wa]").forEach(function (b) { b.hidden = true; });
    if (!E.email) f.querySelectorAll('[value="email"]').forEach(function (b) { b.hidden = true; });
    var nota = f.querySelector(".form__nota");
    var via = "whatsapp";
    f.querySelectorAll('button[type="submit"]').forEach(function (b) { b.addEventListener("click", function () { via = b.value; }); });

    function campo(id, erro) {
      var el = d.getElementById(id), e = d.getElementById(erro), ok = el.value.trim().length > 1;
      el.setAttribute("aria-invalid", ok ? "false" : "true");
      if (e) { e.hidden = ok; if (!ok) el.setAttribute("aria-describedby", erro); else el.removeAttribute("aria-describedby"); }
      return ok;
    }

    f.addEventListener("submit", function (ev) {
      ev.preventDefault();
      var okNome = campo("f-nome", "e-nome"), okMsg = campo("f-msg", "e-msg");
      if (!okNome || !okMsg) { d.getElementById(okNome ? "f-msg" : "f-nome").focus(); nota.textContent = "Faltou preencher um campo."; return; }
      var dados = new FormData(f);
      var lugar = dados.get("lugar") || "", inter = dados.getAll("interesse");
      var linhas = [
        "Olá, Danni Som! Vim pelo site.",
        "",
        "Nome: " + dados.get("nome").trim()
      ];
      var tel = (dados.get("telefone") || "").trim();
      if (tel) linhas.push("Telefone: " + tel);
      if (lugar) linhas.push("Para: " + lugar);
      if (inter.length) linhas.push("Interesse: " + inter.join(", "));
      linhas.push("", dados.get("mensagem").trim());
      var texto = linhas.join("\n");
      var url;
      if (via === "whatsapp" && temWa) url = DS.linkWhats(texto);
      else url = "mailto:" + E.email + "?subject=" + encodeURIComponent("Orçamento pelo site" + (lugar ? " · " + lugar : "")) + "&body=" + encodeURIComponent(texto);
      nota.textContent = via === "whatsapp" && temWa ? "Abrindo o WhatsApp com a sua mensagem…" : "Abrindo o seu e-mail com a mensagem pronta…";
      if (url.indexOf("mailto:") !== 0) { w.open(url, "_blank", "noopener"); return; }
      w.location.href = url;
      // no celular sem programa de e-mail configurado nada abre: oferece copiar a mensagem ou ligar
      setTimeout(function () {
        nota.innerHTML = 'O e-mail não abriu? <button type="button" class="lnk" data-copiar-msg>Copie a mensagem</button> e envie para <b>' + DS.esc(E.email) + "</b>" +
          (E.telefone ? ', ou ligue: <a class="lnk" href="tel:' + DS.esc(E.telefoneLink || "") + '">' + DS.esc(E.telefone) + "</a>." : ".");
        var bt = nota.querySelector("[data-copiar-msg]");
        bt.addEventListener("click", function () {
          var ok = function () { bt.textContent = "Mensagem copiada"; };
          if (navigator.clipboard) navigator.clipboard.writeText(texto).then(ok, function () {}); else ok();
        });
      }, 1400);
    });
  }
})();

/* =====================================================================
   Painel Danni Som — melhorias com JavaScript (tudo funciona sem ele,
   menos o arrastar e soltar): confirmações, prévia do logo (com a
   máscara das marcas e o "remover fundo claro"), ordem por arrastar,
   setas sem recarregar, horário e depoimentos da área Empresa, e o
   "não contar as minhas visitas" das Estatísticas.
   ===================================================================== */
(function () {
  "use strict";
  var d = document;
  var $ = function (s, c) { return (c || d).querySelector(s); };
  var $$ = function (s, c) { return Array.prototype.slice.call((c || d).querySelectorAll(s)); };

  /* ---------- Utilidades ---------- */
  var toastTimer = null;
  function toast(msg, erro) {
    var t = $("[data-toast]");
    if (!t) return;
    t.textContent = msg;
    t.classList.toggle("is-erro", !!erro);
    t.hidden = false;
    clearTimeout(toastTimer);
    toastTimer = setTimeout(function () { t.hidden = true; }, erro ? 7000 : 3500);
  }
  function csrf() { var m = $('meta[name="csrf"]'); return m ? m.content : ""; }
  function enviar(url, dados) {
    if (!dados.has("csrf")) dados.append("csrf", csrf());
    return fetch(url, { method: "POST", body: dados, credentials: "same-origin", headers: { "X-Requested-With": "fetch" } })
      .then(function (r) {
        return r.json().catch(function () { return { ok: false, msg: "Resposta inesperada do servidor. Recarregue a página." }; })
          .then(function (j) {
            if (j.login) setTimeout(function () { location.reload(); }, 2500);
            if (!r.ok || !j.ok) throw new Error(j.msg || "Não deu certo. Recarregue a página e tente de novo.");
            return j;
          });
      });
  }
  function normaliza(s) {
    return String(s || "").toLowerCase().normalize("NFD").replace(/[̀-ͯ]/g, "");
  }

  /* ---------- Menu (no celular rola para os lados): a seção atual fica à vista ---------- */
  var menuAtual = $('.menu [aria-current="page"]');
  if (menuAtual) {
    var faixaMenu = menuAtual.parentNode;
    var rm = menuAtual.getBoundingClientRect(), rf = faixaMenu.getBoundingClientRect();
    if (faixaMenu.scrollWidth > faixaMenu.clientWidth) faixaMenu.scrollLeft += rm.left - rf.left - (rf.width - rm.width) / 2;
  }

  /* ---------- Avisos, senha, copiar ---------- */
  $$("[data-fechar]").forEach(function (b) {
    b.hidden = false;
    b.addEventListener("click", function () { b.closest(".aviso").remove(); });
  });
  var erros = $("[data-erros]");
  if (erros) erros.focus();

  $$("[data-ver-senha]").forEach(function (b) {
    var input = b.parentNode.querySelector("input");
    b.hidden = false;
    b.addEventListener("click", function () {
      var ver = input.type === "password";
      input.type = ver ? "text" : "password";
      b.setAttribute("aria-pressed", ver ? "true" : "false");
      b.setAttribute("aria-label", ver ? "Esconder senha" : "Mostrar senha");
    });
  });

  $$("[data-copiar]").forEach(function (b) {
    if (!navigator.clipboard) return;
    b.hidden = false;
    b.addEventListener("click", function () {
      var el = d.getElementById(b.getAttribute("data-copiar"));
      navigator.clipboard.writeText(el.textContent).then(function () { toast("Copiado."); }, function () { toast("Não consegui copiar: selecione e copie à mão.", true); });
    });
  });

  /* ---------- Estatísticas: não contar as visitas deste aparelho ---------- */
  // O site e o painel são o mesmo endereço (dannisom.com.br): o assets/js/contador.js do site lê esta
  // mesma marca e, com ela, não manda nada. Vale para este navegador, neste aparelho.
  var naoContar = $("[data-nao-contar]"), estadoContar = $("[data-nao-contar-estado]");
  if (naoContar && estadoContar) {
    var MARCA = "ds-nao-contar";
    var lerMarca = function () { try { return localStorage.getItem(MARCA) === "1"; } catch (e) { return null; } };
    var mostrarMarca = function () {
      var fora = lerMarca();
      if (fora === null) {
        naoContar.hidden = true;
        estadoContar.textContent = "Este navegador não deixa guardar a escolha (janela anônima ou dados de sites bloqueados).";
        return;
      }
      naoContar.hidden = false;
      $("span", naoContar).textContent = fora ? "Voltar a contar este aparelho" : "Não contar as minhas visitas";
      estadoContar.textContent = fora ? "Este aparelho não é contado: as suas visitas ao site não entram nas estatísticas."
        : "Este aparelho é contado como qualquer visitante.";
      naoContar.closest("[data-nao-contar-caixa]").classList.toggle("is-fora", fora);
    };
    naoContar.addEventListener("click", function () {
      var fora = !lerMarca();
      try { if (fora) localStorage.setItem(MARCA, "1"); else localStorage.removeItem(MARCA); } catch (e) { /* mostrarMarca avisa */ }
      mostrarMarca();
      if (lerMarca() === fora) toast(fora ? "Pronto: as visitas deste aparelho não são mais contadas." : "Pronto: este aparelho volta a ser contado.");
    });
    mostrarMarca();
  }

  /* ---------- Confirmação antes de excluir / restaurar ---------- */
  var dialogo = $("[data-dialogo]");
  $$("form[data-confirmar]").forEach(function (f) {
    f.addEventListener("submit", function (ev) {
      if (f.dataset.ok) return;
      ev.preventDefault();
      var texto = f.getAttribute("data-confirmar");
      var confirmar = function () {
        var h = d.createElement("input");
        h.type = "hidden"; h.name = "confirmado"; h.value = "1";
        f.appendChild(h);
        f.dataset.ok = "1";
        f.submit();
      };
      if (!dialogo || typeof dialogo.showModal !== "function") { if (window.confirm(texto)) confirmar(); return; }
      $("[data-dialogo-tit]", dialogo).textContent = f.getAttribute("data-confirmar-tit") || "Tem certeza?";
      $("[data-dialogo-txt]", dialogo).textContent = texto;
      $("[data-dialogo-ok]", dialogo).textContent = f.getAttribute("data-confirmar-ok") || "Confirmar";
      dialogo.returnValue = "";
      dialogo.showModal();
      dialogo.addEventListener("close", function fim() {
        dialogo.removeEventListener("close", fim);
        if (dialogo.returnValue === "sim") confirmar();
      });
    });
  });

  /* ---------- Evita envio duplo ---------- */
  $$("form[data-enviar]").forEach(function (f) {
    f.addEventListener("submit", function (ev) {
      if (f.dataset.enviando) { ev.preventDefault(); return; }
      f.dataset.enviando = "1";
      $$('button[type="submit"]:not([name])', f).forEach(function (b) { b.classList.add("is-enviando"); });
    });
  });
  window.addEventListener("pageshow", function () {
    $$("form[data-enviar]").forEach(function (f) { delete f.dataset.enviando; });
    $$(".is-enviando").forEach(function (b) { b.classList.remove("is-enviando"); });
  });

  /* ---------- Lista: busca, setas e arrastar ---------- */
  var lista = $("[data-ordenar]");
  if (lista) {
    var itens = function () { return $$(".item", lista); };
    var ids = function () { return itens().map(function (li) { return li.getAttribute("data-id"); }); };
    var renumera = function () {
      var todos = itens();
      todos.forEach(function (li, i) {
        $("[data-pos]", li).textContent = i + 1;
        $("[data-cima]", li).disabled = i === 0;
        $("[data-baixo]", li).disabled = i === todos.length - 1;
      });
    };
    var salvarOrdem = function (antes) {
      var agora = ids();
      if (agora.join(",") === antes.join(",")) return;
      var fd = new FormData();
      fd.append("ids", agora.join(","));
      lista.classList.add("is-salvando");
      enviar(lista.getAttribute("data-acao"), fd)
        .then(function (j) { toast(j.msg); })
        .catch(function (e) { toast(e.message, true); setTimeout(function () { location.reload(); }, 2500); })
        .then(function () { lista.classList.remove("is-salvando"); });
    };

    // Busca pelo nome
    var caixaFiltro = $("[data-filtro-caixa]");
    var filtro = $("[data-filtro]");
    if (caixaFiltro && filtro && itens().length > 8) {
      caixaFiltro.hidden = false;
      filtro.addEventListener("input", function () {
        var q = normaliza(filtro.value.trim());
        lista.classList.toggle("is-filtrando", q !== "");
        itens().forEach(function (li) { li.hidden = q !== "" && li.getAttribute("data-nome").indexOf(q) < 0; });
      });
    }

    // Setas ↑ ↓ sem recarregar a página
    $$("form[data-mover]", lista).forEach(function (f) {
      f.addEventListener("submit", function (ev) {
        ev.preventDefault();
        var li = f.closest(".item");
        var cima = f.querySelector('[name="dir"]').value === "cima";
        var viz = cima ? li.previousElementSibling : li.nextElementSibling;
        if (!viz) return;
        var botao = f.querySelector("button");
        if (cima) lista.insertBefore(li, viz); else lista.insertBefore(viz, li);
        renumera();
        if (!botao.disabled) botao.focus(); else (cima ? $("[data-baixo]", li) : $("[data-cima]", li)).focus();
        lista.classList.add("is-salvando");
        enviar(f.action, new FormData(f))
          .then(function (j) { toast(j.msg); })
          .catch(function (e) { toast(e.message, true); setTimeout(function () { location.reload(); }, 2500); })
          .then(function () { lista.classList.remove("is-salvando"); });
      });
    });

    // Arrastar pela alça (mouse, dedo ou caneta); setas do teclado na alça também mudam a ordem
    var arr = null;
    itens().forEach(function (li) { $("[data-alca]", li).hidden = false; });
    lista.addEventListener("pointerdown", function (ev) {
      var alca = ev.target.closest("[data-alca]");
      if (!alca || lista.classList.contains("is-filtrando") || lista.classList.contains("is-salvando")) return;
      if (ev.pointerType === "mouse" && ev.button !== 0) return;
      ev.preventDefault();
      var li = alca.closest(".item");
      arr = { li: li, antes: ids(), alca: alca };
      try { alca.setPointerCapture(ev.pointerId); } catch (e) { /* sem captura: segue */ }
      li.classList.add("is-arrastando");
      lista.classList.add("is-arrastando");
    });
    lista.addEventListener("pointermove", function (ev) {
      if (!arr) return;
      ev.preventDefault();
      var y = ev.clientY, li = arr.li;
      var meio = function (el) { var r = el.getBoundingClientRect(); return r.top + r.height / 2; };
      var ant = li.previousElementSibling, prox = li.nextElementSibling;
      if (ant && y < meio(ant)) lista.insertBefore(li, ant);
      else if (prox && y > meio(prox)) lista.insertBefore(prox, li);
      if (y < 70) window.scrollBy(0, -14);
      else if (y > window.innerHeight - 70) window.scrollBy(0, 14);
    });
    var soltar = function () {
      if (!arr) return;
      arr.li.classList.remove("is-arrastando");
      lista.classList.remove("is-arrastando");
      var antes = arr.antes;
      arr = null;
      renumera();
      salvarOrdem(antes);
    };
    lista.addEventListener("pointerup", soltar);
    lista.addEventListener("pointercancel", soltar);
    lista.addEventListener("keydown", function (ev) {
      var alca = ev.target.closest("[data-alca]");
      if (!alca || (ev.key !== "ArrowUp" && ev.key !== "ArrowDown")) return;
      ev.preventDefault();
      var li = alca.closest(".item");
      var antes = ids();
      var viz = ev.key === "ArrowUp" ? li.previousElementSibling : li.nextElementSibling;
      if (!viz) return;
      if (ev.key === "ArrowUp") lista.insertBefore(li, viz); else lista.insertBefore(viz, li);
      renumera();
      alca.focus();
      salvarOrdem(antes);
    });
  }

  /* ---------- Formulário de cliente / marca: prévia do logo ---------- */
  var formLogo = $("[data-form-logo]");
  if (formLogo) {
    var area = formLogo.getAttribute("data-form-logo");
    var input = $("[data-logo-input]", formLogo);
    var nomeArq = $("[data-arquivo-nome]", formLogo);
    var avisoLogo = $("[data-logo-aviso]", formLogo);
    var imgPrevia = $("[data-previa-img]", formLogo);
    var fonte = imgPrevia && imgPrevia.getAttribute("src") ? imgPrevia.getAttribute("src") : "";   // logo atual (mesmo site)
    var urlNova = "";
    var avisa = function (txt) { if (avisoLogo) { avisoLogo.textContent = txt; avisoLogo.hidden = !txt; } };

    // Lê a imagem num canvas (até 600 px) para analisar e, nas marcas, remover o fundo
    var analisar = function (src, cb) {
      var im = new Image();
      im.onload = function () {
        var f = Math.min(1, 600 / Math.max(im.naturalWidth, im.naturalHeight));
        var w = Math.max(1, Math.round(im.naturalWidth * f)), h = Math.max(1, Math.round(im.naturalHeight * f));
        var cv = d.createElement("canvas");
        cv.width = w; cv.height = h;
        var cx = cv.getContext("2d");
        cx.drawImage(im, 0, 0, w, h);
        var dados;
        try { dados = cx.getImageData(0, 0, w, h); } catch (e) { cb(null); return; }
        cb({ cv: cv, cx: cx, dados: dados, w: w, h: h });
      };
      im.onerror = function () { cb(null); };
      im.src = src;
    };
    var corDosCantos = function (a) {
      var px = a.dados.data, cores = [];
      [[0, 0], [a.w - 3, 0], [0, a.h - 3], [a.w - 3, a.h - 3]].forEach(function (c) {
        for (var y = Math.max(0, c[1]); y < Math.min(a.h, c[1] + 3); y++) for (var x = Math.max(0, c[0]); x < Math.min(a.w, c[0] + 3); x++) {
          var i = (y * a.w + x) * 4;
          if (px[i + 3] > 196) cores.push([px[i], px[i + 1], px[i + 2]]);
        }
      });
      if (cores.length < 18) return null;
      var med = [0, 1, 2].map(function (k) { var v = cores.map(function (c) { return c[k]; }).sort(function (p, q) { return p - q; }); return v[v.length >> 1]; });
      var iguais = cores.filter(function (c) { return Math.max(Math.abs(c[0] - med[0]), Math.abs(c[1] - med[1]), Math.abs(c[2] - med[2])) <= 40; }).length;
      return iguais >= cores.length * 0.75 ? med : null;
    };
    var parteTransparente = function (a) {
      var px = a.dados.data, n = 0;
      for (var i = 3; i < px.length; i += 4) if (px[i] < 28) n++;
      return n / (a.w * a.h);
    };
    var quaseBranco = function (a) {
      var px = a.dados.data, vis = 0, claros = 0;
      for (var i = 0; i < px.length; i += 4) {
        if (px[i + 3] < 155) continue;
        vis++;
        if (Math.min(px[i], px[i + 1], px[i + 2]) > 225) claros++;
      }
      return vis > 0 && claros / vis > 0.85;
    };
    var removerFundo = function (a, cor) {   // mesma regra do servidor (lib/imagens.php)
      var px = a.dados.data, t1 = 28, t2 = 90;
      for (var i = 0; i < px.length; i += 4) {
        if (px[i + 3] === 0) continue;
        var dist = Math.max(Math.abs(px[i] - cor[0]), Math.abs(px[i + 1] - cor[1]), Math.abs(px[i + 2] - cor[2]));
        if (dist >= t2) continue;
        px[i + 3] = Math.round(px[i + 3] * (dist <= t1 ? 0 : (dist - t1) / (t2 - t1)));
      }
      a.cx.putImageData(a.dados, 0, 0);
      return a.cv.toDataURL("image/png");
    };
    var mascara = function (url) {
      $$("[data-masc]", formLogo).forEach(function (m) {
        var v = url ? 'url("' + url + '")' : "none";
        m.style.webkitMaskImage = v;
        m.style.maskImage = v;
      });
    };

    var atualizar = function () {
      var src = urlNova || fonte;
      var semLogo = $("[data-op-sem-logo]", formLogo);
      if (area === "clientes") {
        var quadro = $("[data-previa-quadro]", formLogo), nome = $("[data-previa-nome]", formLogo);
        var tirar = semLogo && semLogo.checked && !urlNova;
        var escuro = $("[data-op-escuro]", formLogo);
        quadro.classList.toggle("tile--escuro", !!(escuro && escuro.checked) && !!src && !tirar);
        quadro.classList.toggle("tile--texto", !src || tirar);
        imgPrevia.hidden = !src || tirar;
        nome.hidden = !!src && !tirar;
        if (src && !tirar) imgPrevia.src = src;
        avisa("");
        if (src && !tirar && urlNova) analisar(src, function (a) {
          if (a && quaseBranco(a) && parteTransparente(a) > 0.02 && !(escuro && escuro.checked)) avisa("Este logo parece branco: marque “Logo branco (quadro escuro)” para ele aparecer no site.");
        });
        return;
      }
      // Marcas
      var previa = $("[data-previa-marca]", formLogo);
      var cor = $("[data-op-cor]", formLogo), fundo = $("[data-op-fundo]", formLogo);
      var colorido = cor && cor.checked;
      if (colorido) previa.setAttribute("data-cor", ""); else previa.removeAttribute("data-cor");
      $("[data-op-fundo-caixa]", formLogo).hidden = colorido;
      $("[data-previa-vazio]", formLogo).hidden = !!src;
      imgPrevia.hidden = !src;
      if (src) imgPrevia.src = src;
      avisa("");
      if (!src || colorido) return;
      if (!urlNova && !(fundo && fundo.checked)) return;   // logo atual sem mudança: a máscara já está na página
      analisar(src, function (a) {
        if (!a) { mascara(src); return; }
        if (fundo && fundo.checked) {
          var c = corDosCantos(a);
          if (parteTransparente(a) > 0.5) { mascara(src); return; }
          if (!c) { mascara(src); avisa("Não achei uma cor de fundo lisa nos cantos: o fundo não pode ser removido. Use um PNG com fundo transparente."); return; }
          mascara(removerFundo(a, c));
        } else {
          mascara(src);
          if (parteTransparente(a) < 0.02) avisa("Este logo não tem fundo transparente: no site ele viraria um retângulo. Marque “Remover fundo claro” ou “Mostrar colorido”.");
        }
      });
    };

    input.addEventListener("change", function () {
      var f = input.files && input.files[0];
      if (urlNova) { URL.revokeObjectURL(urlNova); urlNova = ""; }
      nomeArq.textContent = "";
      if (f) {
        var max = +input.getAttribute("data-max") || 0;
        if (["image/png", "image/jpeg", "image/webp"].indexOf(f.type) < 0) {
          input.value = "";
          toast(/svg/i.test(f.type) ? "Logo em SVG não é aceito. Use PNG, JPG ou WebP." : "Escolha uma imagem PNG, JPG ou WebP.", true);
        } else if (max && f.size > max) {
          input.value = "";
          toast("Esse arquivo tem " + (f.size / 1048576).toFixed(1).replace(".", ",") + " MB; o máximo é " + (max / 1048576).toFixed(0) + " MB.", true);
        } else {
          urlNova = URL.createObjectURL(f);
          nomeArq.textContent = "Escolhido: " + f.name + " (o painel corta as margens e reduz ao salvar).";
        }
      }
      atualizar();
    });
    $$("[data-op-escuro], [data-op-cor], [data-op-fundo], [data-op-sem-logo]", formLogo).forEach(function (c) { c.addEventListener("change", atualizar); });
    var campoNome = $("[data-nome]", formLogo), nomePrevia = $("[data-previa-nome]", formLogo);
    if (campoNome && nomePrevia) campoNome.addEventListener("input", function () { nomePrevia.textContent = campoNome.value || "Nome do cliente"; });
    if (area === "marcas") atualizar();
  }

  /* ---------- Empresa ---------- */
  var horario = $("[data-horario]");
  if (horario) {
    var DIAS = { 1: "Segunda", 2: "Terça", 3: "Quarta", 4: "Quinta", 5: "Sexta", 6: "Sábado", 0: "Domingo" };
    var ORDEM = [1, 2, 3, 4, 5, 6, 0];
    var minutos = function (t) {
      var m = /^(\d{1,2})(?:[:h](\d{2})?)?$/.exec(String(t || "").replace(/\s/g, "").toLowerCase());
      if (!m) return null;
      var h = +m[1], mm = +(m[2] || 0);
      return mm > 59 || h > 24 ? null : h * 60 + mm;
    };
    var hora = function (m) { var h = Math.floor(m / 60), mm = m % 60; return h + "h" + (mm ? (mm < 10 ? "0" : "") + mm : ""); };
    var linhaDia = function (dia) {
      var el = $('[data-dia="' + dia + '"]', horario);
      if (!$("[data-aberto]", el).checked) return [];
      var t = $$('input[type="time"]', el).map(function (i) { return minutos(i.value); });
      var iv = [];
      if (t[0] !== null && t[1] !== null && t[0] < t[1]) iv.push([t[0], t[1]]);
      if (t[2] !== null && t[3] !== null && t[2] < t[3]) iv.push([t[2], t[3]]);
      return iv;
    };
    var previaHorario = function () {   // mesma regra de linhasHorario() em lib/empresa.php
      var grupos = [];
      ORDEM.forEach(function (dia) {
        var iv = linhaDia(dia), k = JSON.stringify(iv), u = grupos[grupos.length - 1];
        if (u && u.k === k) u.dias.push(dia); else grupos.push({ k: k, dias: [dia], iv: iv });
      });
      var dl = $("[data-previa-horario]");
      dl.textContent = "";
      grupos.forEach(function (g) {
        var nome = DIAS[g.dias[0]];
        if (g.dias.length > 1) nome += (g.dias.length === 2 ? " e " : " a ") + DIAS[g.dias[g.dias.length - 1]].toLowerCase();
        var dt = d.createElement("dt"), dd = d.createElement("dd");
        dt.textContent = nome;
        dd.textContent = g.iv.length ? g.iv.map(function (x) { return hora(x[0]) + " às " + hora(x[1]); }).join(" e ") : "Fechado";
        dl.appendChild(dt); dl.appendChild(dd);
      });
    };
    $$("[data-dia]", horario).forEach(function (el) {
      var chk = $("[data-aberto]", el);
      chk.addEventListener("change", function () {
        el.classList.toggle("is-fechado", !chk.checked);
        var t = $$('input[type="time"]', el);
        if (chk.checked && !t[0].value && !t[1].value) {   // abriu um dia vazio: começa com o horário de segunda
          var seg = $$('[data-dia="1"] input[type="time"]', horario);
          t.forEach(function (i, k) { i.value = seg[k].value || ["08:00", "18:00", "", ""][k]; });
        }
        previaHorario();
      });
    });
    horario.addEventListener("input", previaHorario);
    horario.addEventListener("change", previaHorario);
    var copiar = $("[data-copiar-semana]");
    if (copiar) {
      copiar.hidden = false;
      copiar.addEventListener("click", function () {
        var seg = $('[data-dia="1"]', horario);
        var aberto = $("[data-aberto]", seg).checked, t = $$('input[type="time"]', seg).map(function (i) { return i.value; });
        [2, 3, 4, 5].forEach(function (dia) {
          var el = $('[data-dia="' + dia + '"]', horario);
          $("[data-aberto]", el).checked = aberto;
          el.classList.toggle("is-fechado", !aberto);
          $$('input[type="time"]', el).forEach(function (i, k) { i.value = t[k]; });
        });
        previaHorario();
        toast("Horário de segunda copiado para terça a sexta. Confira e salve.");
      });
    }
    previaHorario();
  }

  // WhatsApp: link de teste
  var waNum = $("[data-wa-numero]"), waMsg = $("[data-wa-msg]");
  if (waNum && waMsg) {
    var waLink = function () {
      var n = waNum.value.replace(/\D/g, "");
      if (n.length === 10 || n.length === 11) n = "55" + n.replace(/^0+/, "");
      var ok = /^55[1-9]{2}\d{8,9}$/.test(n);
      var url = ok ? "https://wa.me/" + n + "?text=" + encodeURIComponent(waMsg.value.trim() || "Olá!") : "";
      $("[data-wa-caixa]").hidden = !ok;
      var a = $("[data-wa-link]");
      a.href = url || "#";
      a.textContent = url;
    };
    waNum.addEventListener("input", waLink);
    waMsg.addEventListener("input", waLink);
  }

  // Latitude e longitude coladas juntas ("-22.3997744, -47.5705148")
  var geo = $("[data-geo]");
  if (geo) geo.addEventListener("input", function () {
    var m = /^\s*(-?\d{1,3}\.\d+)\s*,\s*(-?\d{1,3}\.\d+)\s*$/.exec(geo.value);
    if (!m) return;
    geo.value = m[1].replace(".", ",");
    var lng = $("#f-lng");
    if (lng) lng.value = m[2].replace(".", ",");
    toast("Latitude e longitude separadas.");
  });

  // Depoimentos: subir, descer, remover e adicionar sem recarregar
  var deps = $("[data-deps]");
  if (deps) {
    var acertaSetas = function () {
      var linhas = $$("[data-dep]", deps);
      linhas.forEach(function (li, i) {
        $("[data-dep-cima]", li).disabled = i === 0;
        $("[data-dep-baixo]", li).disabled = i === linhas.length - 1;
      });
    };
    deps.addEventListener("click", function (ev) {
      var b = ev.target.closest("[data-dep-cima], [data-dep-baixo]");
      if (!b) return;
      ev.preventDefault();
      var li = b.closest("[data-dep]");
      if (b.hasAttribute("data-dep-cima") && li.previousElementSibling) deps.insertBefore(li, li.previousElementSibling);
      else if (b.hasAttribute("data-dep-baixo") && li.nextElementSibling) deps.insertBefore(li.nextElementSibling, li);
      acertaSetas();
      if (!b.disabled) b.focus();
    });
    deps.addEventListener("change", function (ev) {
      if (ev.target.matches("[data-dep-remover]")) ev.target.closest("[data-dep]").classList.toggle("is-removido", ev.target.checked);
    });
    var mais = $("[data-dep-mais]"), modelo = $("[data-dep-modelo]");
    var n = 1000;
    if (mais && modelo) mais.addEventListener("click", function (ev) {
      ev.preventDefault();
      n++;
      var html = modelo.innerHTML.replace(/999/g, String(n));
      var tmp = d.createElement("ol");
      tmp.innerHTML = html;   // modelo do próprio painel (sem dados de fora)
      var li = tmp.firstElementChild;
      deps.appendChild(li);
      acertaSetas();
      $("input", li).focus();
    });
    acertaSetas();
  }
})();

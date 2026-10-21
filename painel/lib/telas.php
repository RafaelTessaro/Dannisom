<?php
/* =====================================================================
   Painel Danni Som — telas (HTML)
   Toda saída de dados passa por e() (htmlspecialchars). Sem scripts nem
   atributos style (a Content-Security-Policy não deixa); o único <style>
   da página leva o nonce da resposta e só tem endereços de logos já
   conferidos (máscara das marcas).
   ===================================================================== */
declare(strict_types=1);
if (!defined('PAINEL')) { http_response_code(404); exit; }

function versaoAsset(string $arq): string
{
    return $arq . '?v=' . (@filemtime(dirname(__DIR__) . '/' . $arq) ?: '1');
}

function icone(string $nome, string $classe = 'i'): string
{
    return '<svg class="' . e($classe) . '" aria-hidden="true" focusable="false"><use href="#i-' . e($nome) . '"/></svg>';
}

function spriteIcones(): void
{
    $s = 'fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"';
    $simbolos = [
        'up' => '<path d="M12 19V5M5 12l7-7 7 7"/>',
        'down' => '<path d="M12 5v14M19 12l-7 7-7-7"/>',
        'edit' => '<path d="M4 20h4L19 9a2.8 2.8 0 0 0-4-4L4 16v4zM13.5 6.5l4 4"/>',
        'trash' => '<path d="M4 7h16M10 11v6M14 11v6M6 7l1 12a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2l1-12M9 7V4h6v3"/>',
        'eye' => '<path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/>',
        'eye-off' => '<path d="M3 3l18 18M10.6 5.1A10.4 10.4 0 0 1 12 5c6.4 0 10 7 10 7a17.6 17.6 0 0 1-3.2 4.1M6.6 6.6C3.8 8.4 2 12 2 12s3.6 7 10 7a9.7 9.7 0 0 0 5.4-1.6M9.9 9.9a3 3 0 0 0 4.2 4.2"/>',
        'plus' => '<path d="M12 5v14M5 12h14"/>',
        'out' => '<path d="M10 4H6a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h4M15 16l4-4-4-4M19 12H9"/>',
        'ext' => '<path d="M14 4h6v6M20 4l-9 9M18 14v5a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V7a1 1 0 0 1 1-1h5"/>',
        'restore' => '<path d="M4 12a8 8 0 1 0 2.4-5.7M4 4v4.5h4.5M12 8v4.5l3 2"/>',
        'user' => '<circle cx="12" cy="8" r="4"/><path d="M4 21c1.2-4 4.3-6 8-6s6.8 2 8 6"/>',
        'users' => '<circle cx="9" cy="8" r="3.5"/><path d="M2.5 20c.9-3.5 3.4-5.5 6.5-5.5s5.6 2 6.5 5.5M15.5 4.8a3.5 3.5 0 0 1 0 6.4M17.5 14.8c2 .6 3.4 2.4 4 5.2"/>',
        'check' => '<path d="M5 12.5l4.5 4.5L19 7.5"/>',
        'alert' => '<path d="M12 3l10 18H2L12 3z"/><path d="M12 10v4.5M12 17.5v.5"/>',
        'image' => '<rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="8.5" cy="9.5" r="1.8"/><path d="M21 16l-5-5-9 9"/>',
        'x' => '<path d="M6 6l12 12M18 6L6 18"/>',
        'lock' => '<rect x="4.5" y="10.5" width="15" height="10" rx="2"/><path d="M8 10.5V7.5a4 4 0 0 1 8 0v3"/>',
        'loja' => '<path d="M4 4h16l1.5 5a2.4 2.4 0 0 1-4.8 0 2.4 2.4 0 0 1-4.7 0 2.4 2.4 0 0 1-4.7 0 2.4 2.4 0 0 1-4.8 0L4 4zM4.5 11.5V20h15v-8.5M10 20v-5h4v5"/>',
        'tag' => '<path d="M3 12V4h8l10 10-8 8L3 12z"/><circle cx="7.5" cy="8" r="1.5"/>',
        'empresa' => '<path d="M4 21V5.5L13 3v18M13 8.5l7 2V21M2.5 21h19M7.5 8v.01M7.5 11.5v.01M7.5 15v.01M9.8 8v.01M9.8 11.5v.01M9.8 15v.01M16.5 13.5v.01M16.5 17v.01"/>',
        'lista' => '<path d="M9 6h11M9 12h11M9 18h11M4.5 6h.01M4.5 12h.01M4.5 18h.01"/>',
        'copiar' => '<rect x="8.5" y="8.5" width="12" height="12" rx="1.5"/><path d="M15.5 8.5V5a1.5 1.5 0 0 0-1.5-1.5H5A1.5 1.5 0 0 0 3.5 5v9A1.5 1.5 0 0 0 5 15.5h3.5"/>',
        'chave' => '<circle cx="8" cy="15" r="4.5"/><path d="M11.2 11.8L20 3M16.5 6.5l2.5 2.5M14 9l2 2"/>',
        'seta' => '<path d="M5 12h14M13 6l6 6-6 6"/>',
        'alca' => '<circle cx="9" cy="6" r="1.2"/><circle cx="15" cy="6" r="1.2"/><circle cx="9" cy="12" r="1.2"/><circle cx="15" cy="12" r="1.2"/><circle cx="9" cy="18" r="1.2"/><circle cx="15" cy="18" r="1.2"/>',
        'casa' => '<path d="M3 11l9-7 9 7M5 9.5V20h5v-6h4v6h5V9.5"/>',
        'relogio' => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
        'whats' => '<path d="M4 20l1.3-3.9A8 8 0 1 1 8 19z"/><path d="M9 9.5c0 3 2.5 5.5 5.5 5.5l1-1.5-2-1-1 .8a4 4 0 0 1-1.8-1.8l.8-1-1-2L9 9.5z"/>',
    ];
    echo '<svg class="sprite" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false">';
    foreach ($simbolos as $id => $d) echo '<symbol id="i-' . $id . '" viewBox="0 0 24 24"><g ' . $s . '>' . $d . '</g></symbol>';
    echo '</svg>';
}

/* ------------------------------------------------------------------
   Moldura das páginas
   ------------------------------------------------------------------ */
function itensMenu(bool $admin): array
{
    $m = ['inicio' => ['index.php', 'casa', 'Início'], 'clientes' => [link_('clientes'), 'loja', 'Clientes'], 'marcas' => [link_('marcas'), 'tag', 'Marcas']];
    if ($admin) {
        $m['empresa'] = [link_('empresa'), 'empresa', 'Empresa'];
        $m['copias'] = [link_('copias'), 'restore', 'Cópias'];
        $m['usuarios'] = [link_('usuarios'), 'users', 'Usuários'];
        $m['atividades'] = [link_('atividades'), 'lista', 'Atividades'];
    }
    return $m;
}

/** $o: menu (item atual), classe (do body), semMenu, estilo (CSS já seguro para o <style> com nonce). */
function pagina(string $titulo, callable $conteudo, array $o = []): void
{
    $eu = usuarioLogado() !== null ? usuarioAtual() : null;
    $comMenu = $eu !== null && empty($o['semMenu']);
    $atual = $o['menu'] ?? '';
    $avisos = pegarAvisos();
    $site = (string) cfg('url_site');
    ?><!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="robots" content="noindex, nofollow, noarchive">
<meta name="theme-color" content="#0A1433">
<?php if ($eu !== null): ?><meta name="csrf" content="<?= e(tokenCsrf()) ?>">
<?php endif; ?>
<title><?= e($titulo) ?> · Painel Danni Som</title>
<link rel="icon" href="<?= e($site) ?>favicon.svg" type="image/svg+xml">
<link rel="preload" href="<?= e($site) ?>assets/fonts/danni-sans.woff2" as="font" type="font/woff2" crossorigin>
<link rel="stylesheet" href="<?= e(versaoAsset('assets/painel.css')) ?>">
<?php if (!empty($o['estilo'])): ?><style nonce="<?= e(nonce()) ?>"><?= $o['estilo'] /* montado só com endereços conferidos */ ?></style>
<?php endif; ?>
<script src="<?= e(versaoAsset('assets/painel.js')) ?>" defer></script>
</head>
<body class="<?= e(trim(($o['classe'] ?? '') . ($comMenu ? ' com-menu' : ''))) ?>">
<?php spriteIcones(); ?>
<a class="pular" href="#conteudo">Pular para o conteúdo</a>
<?php if ($comMenu): ?>
<header class="topo">
  <div class="topo__in">
    <a class="marca" href="index.php"><img src="<?= e($site) ?>assets/img/logo/danni-som-branco.svg" alt="Danni Som" width="132" height="34"><span class="marca__tag">Painel</span></a>
    <div class="topo__acoes">
      <a class="topo__quem" href="<?= e(link_('conta')) ?>"<?= $atual === 'conta' ? ' aria-current="page"' : '' ?> title="Minha conta"><?= icone('user') ?><span><b><?= e($eu['nome']) ?></b><small><?= e(PAPEIS[$eu['papel']]) ?></small></span></a>
      <a class="topo__site" href="<?= e($site) ?>" target="_blank" rel="noopener"><?= icone('ext') ?><span>Ver o site</span></a>
      <form method="post" action="<?= e(link_('sair')) ?>" class="topo__sair">
        <?= campoCsrf() ?>
        <button type="submit"><?= icone('out') ?><span>Sair</span></button>
      </form>
    </div>
    <nav class="menu" aria-label="Seções do painel">
      <?php foreach (itensMenu($eu['papel'] === 'admin') as $k => [$href, $ic, $rot]): ?>
      <a href="<?= e($href) ?>"<?= $atual === $k ? ' aria-current="page"' : '' ?>><?= icone($ic) ?><span><?= e($rot) ?></span></a>
      <?php endforeach; ?>
    </nav>
  </div>
</header>
<?php endif; ?>
<main id="conteudo" class="<?= e($o['main'] ?? 'pagina') ?>" tabindex="-1">
<?php if ($avisos): ?>
  <div class="avisos">
  <?php foreach ($avisos as $a): $f = is_array($a['form'] ?? null) ? $a['form'] : null; ?>
    <div class="aviso aviso--<?= e($a['tipo']) ?>" role="<?= $a['tipo'] === 'erro' ? 'alert' : 'status' ?>">
      <?= icone($a['tipo'] === 'erro' ? 'alert' : 'check') ?>
      <p><?= e($a['msg']) ?><?php if (!empty($a['link'])): ?> <a href="<?= e($a['link']) ?>"<?= !empty($a['externo']) ? ' target="_blank" rel="noopener"' : '' ?>><?= e($a['textoLink'] ?? 'Ver') ?></a><?php endif; ?></p>
      <?php if ($f): ?>
      <form method="post" action="<?= e((string) $f['acao']) ?>" class="aviso__form">
        <?= campoCsrf() ?>
        <?php foreach ((array) ($f['campos'] ?? []) as $k => $v): ?><input type="hidden" name="<?= e($k) ?>" value="<?= e($v) ?>"><?php endforeach; ?>
        <button type="submit" class="btn btn--linha btn--p"><?= icone('restore') ?><?= e((string) ($f['botao'] ?? 'Desfazer')) ?></button>
      </form>
      <?php endif; ?>
      <button type="button" class="aviso__fechar" data-fechar aria-label="Fechar aviso" hidden><?= icone('x') ?></button>
    </div>
  <?php endforeach; ?>
  </div>
<?php endif; ?>
<?php $conteudo(); ?>
</main>
<div class="toast" data-toast role="status" aria-live="polite" hidden></div>
<dialog class="dialogo" data-dialogo aria-labelledby="dialogo-tit">
  <form method="dialog" class="dialogo__in">
    <h2 id="dialogo-tit" data-dialogo-tit>Tem certeza?</h2>
    <p data-dialogo-txt></p>
    <div class="dialogo__bts">
      <button class="btn btn--linha" value="nao">Cancelar</button>
      <button class="btn btn--perigo" value="sim" data-dialogo-ok>Confirmar</button>
    </div>
  </form>
</dialog>
</body>
</html>
<?php
}

function paginaErro(string $titulo, string $texto, int $status = 0): void
{
    if ($status && !headers_sent()) http_response_code($status);
    $f = static function () use ($titulo, $texto): void { ?>
  <section class="vazio-grande">
    <?= icone('alert', 'i i--grande') ?>
    <h1><?= e($titulo) ?></h1>
    <p><?= e($texto) ?></p>
    <p><a class="btn" href="index.php">Voltar ao painel</a></p>
  </section>
<?php };
    try { pagina($titulo, $f); } catch (Throwable $t) { echo '<!doctype html><meta charset="utf-8"><title>Erro</title><p>' . e($texto) . '</p>'; }
}

/** $texto e $acoes já vêm escapados por quem chama. */
function cabecalhoPagina(string $sobre, string $titulo, string $texto = '', string $acoes = ''): void
{
    ?>
  <header class="cab">
    <div class="cab__txt">
      <?php if ($sobre !== ''): ?><p class="sobre"><?= e($sobre) ?></p><?php endif; ?>
      <h1><?= e($titulo) ?></h1>
      <?php if ($texto !== ''): ?><p class="cab__desc"><?= $texto ?></p><?php endif; ?>
    </div>
    <?php if ($acoes !== ''): ?><div class="cab__acoes"><?= $acoes ?></div><?php endif; ?>
  </header>
<?php
}

function resumoErros(array $erros, string $titulo): void
{
    if (!$erros) return; ?>
  <div class="aviso aviso--erro" role="alert" tabindex="-1" data-erros>
    <?= icone('alert') ?>
    <div>
      <p><b><?= e($titulo) ?></b> Confira:</p>
      <ul><?php foreach ($erros as $k => $m): ?><li><a href="#f-<?= e($k === 'geral' ? 'nome' : $k) ?>"><?= e($m) ?></a></li><?php endforeach; ?></ul>
    </div>
  </div>
<?php
}

function erroCampo(array $erros, string $k): string
{
    return isset($erros[$k]) ? '<p class="campo__erro" id="erro-' . e($k) . '">' . icone('alert') . e($erros[$k]) . '</p>' : '';
}

function invalido(array $erros, string $k, string $ajuda = ''): string
{
    $desc = trim((isset($erros[$k]) ? 'erro-' . $k : '') . ' ' . $ajuda);
    return (isset($erros[$k]) ? ' aria-invalid="true"' : '') . ($desc !== '' ? ' aria-describedby="' . e($desc) . '"' : '');
}

/** Campo de texto com rótulo, ajuda e erro. $o: tipo, ajuda, max, auto, ph, obrig, modo (inputmode), attrs (já escapados). */
function campo(string $k, string $rotulo, string $valor, array $erros, array $o = []): void
{
    $id = 'f-' . $k;
    $ajuda = !empty($o['ajuda']) ? $id . '-ajuda' : '';
    ?>
      <div class="campo<?= isset($o['classe']) ? ' ' . e($o['classe']) : '' ?>">
        <label for="<?= e($id) ?>"><?= e($rotulo) ?><?php if (!empty($o['opcional'])): ?> <small>(opcional)</small><?php endif; ?></label>
        <input id="<?= e($id) ?>" name="<?= e($o['nome'] ?? $k) ?>" type="<?= e($o['tipo'] ?? 'text') ?>" value="<?= e($valor) ?>"<?= isset($o['max']) ? ' maxlength="' . (int) $o['max'] . '"' : '' ?> autocomplete="<?= e($o['auto'] ?? 'off') ?>"<?= isset($o['ph']) ? ' placeholder="' . e($o['ph']) . '"' : '' ?><?= !empty($o['obrig']) ? ' required' : '' ?><?= isset($o['modo']) ? ' inputmode="' . e($o['modo']) . '"' : '' ?><?= invalido($erros, $k, $ajuda) ?><?= $o['attrs'] ?? '' ?>>
        <?= erroCampo($erros, $k) ?>
        <?php if ($ajuda !== ''): ?><p class="ajuda" id="<?= e($ajuda) ?>"><?= $o['ajuda'] /* já escapada */ ?></p><?php endif; ?>
      </div>
<?php
}

/* ------------------------------------------------------------------
   Entrar / primeiro acesso / recuperar / criar senha
   ------------------------------------------------------------------ */
function molduraAcesso(string $titulo, callable $miolo, ?callable $rodape = null): void
{
    pagina($titulo, static function () use ($miolo, $rodape): void { ?>
  <div class="acesso">
    <div class="acesso__cartao">
      <p class="acesso__marca"><img src="<?= e(cfg('url_site')) ?>assets/img/logo/danni-som-branco.svg" alt="Danni Som" width="180" height="46"><span class="marca__tag">Área restrita</span></p>
      <?php $miolo(); ?>
    </div>
    <?php if ($rodape): $rodape(); else: ?>
    <p class="acesso__rodape"><a href="<?= e(cfg('url_site')) ?>">← Voltar para o site</a></p>
    <?php endif; ?>
  </div>
<?php }, ['classe' => 'pg-acesso', 'main' => 'principal-acesso', 'semMenu' => true]);
}

function campoSenha(string $id, string $nome, string $rotulo, string $auto, string $ajuda = '', bool $foco = false): void
{
    ?>
      <div class="campo">
        <label for="<?= e($id) ?>"><?= e($rotulo) ?></label>
        <div class="senha">
          <input id="<?= e($id) ?>" name="<?= e($nome) ?>" type="password" autocomplete="<?= e($auto) ?>" required maxlength="200"<?= $foco ? ' autofocus' : '' ?><?= $ajuda ? ' aria-describedby="' . e($id) . '-ajuda"' : '' ?>>
          <button type="button" class="senha__ver" data-ver-senha aria-label="Mostrar senha" aria-pressed="false" hidden><?= icone('eye') ?></button>
        </div>
        <?php if ($ajuda): ?><p class="ajuda" id="<?= e($id) ?>-ajuda"><?= e($ajuda) ?></p><?php endif; ?>
      </div>
<?php
}

function avisoErro(string $erro): void
{
    if ($erro !== ''): ?><div class="aviso aviso--erro" role="alert"><?= icone('alert') ?><p><?= e($erro) ?></p></div><?php endif;
}

/** Passos no Gerenciador de Arquivos da Hostinger até o arquivo primeiro-acesso.php. */
function passosCodigo(): void
{
    $onde = ondeFicaPastaPrivada();
    ?>
      <ol class="passos">
        <li>Entre no <b>hPanel</b> da Hostinger → <b>Arquivos</b> → <b>Gerenciador de Arquivos</b>.</li>
        <?php if (privadaForaDoSite()): ?>
        <li>Suba um nível, saindo da <code>public_html</code> (a pasta do domínio, que tem a <code>public_html</code> dentro), e abra a pasta <code><?= e($onde) ?></code>.</li>
        <?php else: ?>
        <li>Abra a pasta <code><?= e($onde) ?></code>.</li>
        <?php endif; ?>
        <li>Dê dois cliques em <code>primeiro-acesso.php</code> (ou botão direito → <b>Editar</b>) e copie o código. Feche sem salvar.</li>
      </ol>
<?php
}

function telaEntrar(string $erro = '', string $usuario = ''): void
{
    molduraAcesso('Entrar', static function () use ($erro, $usuario): void { ?>
      <h1 class="acesso__tit">Entrar no painel</h1>
      <p class="acesso__sub">Clientes, marcas e dados da empresa do site.</p>
      <?php avisoErro($erro); ?>
      <form method="post" action="<?= e(link_('entrar')) ?>" class="form-acesso" data-enviar>
        <?= campoCsrf() ?>
        <div class="campo">
          <label for="f-usuario">Usuário</label>
          <input id="f-usuario" name="usuario" autocomplete="username" autocapitalize="off" spellcheck="false" required maxlength="40" value="<?= e($usuario) ?>"<?= $usuario === '' ? ' autofocus' : '' ?>>
        </div>
        <?php campoSenha('f-senha', 'senha', 'Senha', 'current-password', '', $usuario !== ''); ?>
        <button class="btn btn--largo" type="submit"><?= icone('lock') ?>Entrar</button>
      </form>
      <details class="acesso__ajuda">
        <summary>Esqueci a senha</summary>
        <?php passosEsqueci(); ?>
      </details>
<?php });
}

function passosEsqueci(): void
{
    ?>
        <p class="ajuda"><b>Tem outro administrador?</b> Peça para ele redefinir a sua senha em <b>Usuários</b>: você recebe uma senha temporária e cria a sua no primeiro acesso.</p>
        <p class="ajuda"><b>É o único administrador?</b> Quem entra no hPanel da Hostinger resolve em 2 minutos:</p>
        <ol class="passos passos--p">
          <li>hPanel → <b>Arquivos</b> → <b>Gerenciador de Arquivos</b>.</li>
          <li>Abra a pasta <code><?= e(ondeFicaPastaPrivada()) ?></code><?= privadaForaDoSite() ? ' (na pasta do domínio, ao lado da <code>public_html</code>)' : '' ?>.</li>
          <li>Crie nela um arquivo vazio chamado <code>recuperar.txt</code> (botão <b>Novo arquivo</b>).</li>
          <li>Abra o painel de novo: aparece a tela <b>Recuperar acesso</b>. O código fica no arquivo <code>primeiro-acesso.php</code>, na mesma pasta.</li>
        </ol>
<?php
}

function telaInstalar(string $erro = '', array $v = []): void
{
    molduraAcesso('Primeiro acesso', static function () use ($erro, $v): void { ?>
      <h1 class="acesso__tit">Primeiro acesso</h1>
      <p class="acesso__sub">Crie o seu usuário de <b>Administrador</b>. Para provar que é você, use o código que o painel acabou de gravar na hospedagem.</p>
      <?php if (usuariosDanificado()): ?><div class="aviso aviso--info" role="status"><?= icone('alert') ?><p>O arquivo de usuários está danificado e não pôde ser lido. Crie um acesso novo: o arquivo antigo fica guardado na pasta privada.</p></div><?php endif; ?>
      <?php passosCodigo(); ?>
      <?php avisoErro($erro); ?>
      <form method="post" action="<?= e(link_('instalar')) ?>" class="form-acesso" data-enviar>
        <?= campoCsrf() ?>
        <div class="campo">
          <label for="f-codigo">Código de primeiro acesso</label>
          <input id="f-codigo" name="codigo" class="codigo" autocomplete="one-time-code" autocapitalize="characters" spellcheck="false" required maxlength="20" placeholder="XXXX-XXXX-XXXX" value="<?= e($v['codigo'] ?? '') ?>" autofocus>
          <p class="ajuda">Vale por 24 horas. Só quem entra no hPanel consegue ler.</p>
        </div>
        <div class="campo">
          <label for="f-nome">Seu nome</label>
          <input id="f-nome" name="nome" autocomplete="name" required maxlength="60" value="<?= e($v['nome'] ?? '') ?>">
        </div>
        <div class="campo">
          <label for="f-usuario">Usuário</label>
          <input id="f-usuario" name="usuario" autocomplete="username" autocapitalize="off" spellcheck="false" required maxlength="40" pattern="[A-Za-z0-9._\-]{3,40}" value="<?= e($v['usuario'] ?? '') ?>" aria-describedby="f-usuario-ajuda">
          <p class="ajuda" id="f-usuario-ajuda">O que você digita para entrar: de 3 a 40 letras sem acento ou números (ex.: daniel).</p>
        </div>
        <?php campoSenha('f-senha', 'senha', 'Senha', 'new-password', 'Pelo menos ' . (int) cfg('senha_minimo') . ' caracteres. Dica: junte 3 ou 4 palavras que só você saiba, com um número.'); ?>
        <?php campoSenha('f-senha2', 'senha2', 'Repita a senha', 'new-password'); ?>
        <button class="btn btn--largo" type="submit"><?= icone('check') ?>Criar acesso e entrar</button>
      </form>
<?php });
}

function telaRecuperar(string $erro = '', array $v = []): void
{
    molduraAcesso('Recuperar acesso', static function () use ($erro, $v): void { ?>
      <h1 class="acesso__tit">Recuperar acesso</h1>
      <p class="acesso__sub"><?= arquivosRecuperacao() ? 'O arquivo <code>recuperar.txt</code> está na pasta privada.' : 'O painel está sem nenhum administrador ativo.' ?> Com o código gravado na hospedagem, crie uma senha nova para um administrador.</p>
      <?php passosCodigo(); ?>
      <?php avisoErro($erro); ?>
      <form method="post" action="<?= e(link_('recuperar')) ?>" class="form-acesso" data-enviar>
        <?= campoCsrf() ?>
        <div class="campo">
          <label for="f-codigo">Código</label>
          <input id="f-codigo" name="codigo" class="codigo" autocomplete="one-time-code" autocapitalize="characters" spellcheck="false" required maxlength="20" placeholder="XXXX-XXXX-XXXX" autofocus>
        </div>
        <div class="campo">
          <label for="f-usuario">Usuário de administrador</label>
          <input id="f-usuario" name="usuario" autocomplete="username" autocapitalize="off" spellcheck="false" required maxlength="40" pattern="[A-Za-z0-9._\-]{3,40}" value="<?= e($v['usuario'] ?? '') ?>" aria-describedby="f-usuario-ajuda">
          <p class="ajuda" id="f-usuario-ajuda">O seu usuário (ganha a senha nova e volta a ficar ativo). Um usuário que não existe é criado como administrador.</p>
        </div>
        <?php campoSenha('f-senha', 'senha', 'Senha nova', 'new-password', 'Pelo menos ' . (int) cfg('senha_minimo') . ' caracteres.'); ?>
        <?php campoSenha('f-senha2', 'senha2', 'Repita a senha nova', 'new-password'); ?>
        <button class="btn btn--largo" type="submit"><?= icone('lock') ?>Recuperar e entrar</button>
      </form>
      <p class="ajuda">Os outros usuários e as cópias de segurança continuam como estão. No fim, o painel apaga o <code>recuperar.txt</code> sozinho.</p>
<?php });
}

function telaCriarSenha(string $erro = ''): void
{
    $eu = usuarioAtual();
    $primeiro = explode(' ', $eu['nome'])[0];
    molduraAcesso('Crie a sua senha', static function () use ($eu, $primeiro, $erro): void { ?>
      <h1 class="acesso__tit">Crie a sua senha</h1>
      <p class="acesso__sub">Olá, <?= e($primeiro) ?>! Você entrou com uma senha temporária. Agora crie a sua: é ela que você usa nas próximas vezes.</p>
      <?php avisoErro($erro); ?>
      <form method="post" action="<?= e(link_('criar-senha')) ?>" class="form-acesso" data-enviar>
        <?= campoCsrf() ?>
        <input type="text" name="usuario" value="<?= e($eu['usuario']) ?>" autocomplete="username" hidden>
        <?php campoSenha('f-nova', 'nova', 'Senha nova', 'new-password', 'Pelo menos ' . (int) cfg('senha_minimo') . ' caracteres, diferente da temporária.', true); ?>
        <?php campoSenha('f-nova2', 'nova2', 'Repita a senha nova', 'new-password'); ?>
        <button class="btn btn--largo" type="submit"><?= icone('check') ?>Criar senha e continuar</button>
      </form>
<?php }, static function (): void { ?>
    <form method="post" action="<?= e(link_('sair')) ?>" class="acesso__rodape">
      <?= campoCsrf() ?>
      <button type="submit"><?= icone('out') ?>Sair sem criar a senha</button>
    </form>
<?php });
}

/* ------------------------------------------------------------------
   Início
   ------------------------------------------------------------------ */
function contagemArea(string $area): ?array
{
    try { $l = lerArea($area)['dados']; } catch (ErroDados $e) { return null; }
    return ['total' => count($l), 'site' => count(array_filter($l, 'visivel'))];
}

function telaInicio(): void
{
    $eu = usuarioAtual();
    $admin = $eu['papel'] === 'admin';
    $atv = array_slice(array_values(array_filter(ultimasAtividades(400), static fn($a) => tipoAtividade($a['evento']) === 'alteracoes'
        && ($admin || strcasecmp($a['usuario'], $eu['usuario']) === 0))), 0, 8);
    $nomes = nomesDosUsuarios();
    pagina('Início', static function () use ($eu, $admin, $atv, $nomes): void {
        cabecalhoPagina('Painel do site', 'Olá, ' . explode(' ', $eu['nome'])[0] . '!', 'O que você muda aqui aparece no site na hora.');
        $cartao = static function (string $href, string $ic, string $tit, string $txt): void { ?>
      <a class="cartao" href="<?= e($href) ?>"><?= icone($ic, 'i cartao__ic') ?><span class="cartao__txt"><b><?= e($tit) ?></b><span><?= e($txt) ?></span></span><?= icone('seta', 'i cartao__seta') ?></a>
<?php   };
        $txt = static function (?array $c, string $um, string $muitos): string {
            if ($c === null) return 'Arquivo com problema: abra para ver.';
            return $c['total'] . ' ' . ($c['total'] === 1 ? $um : $muitos) . ($c['total'] !== $c['site'] ? ' · ' . ($c['total'] - $c['site']) . ' oculto(s)' : '') . '. Logos, ordem e segmentos.';
        }; ?>
  <div class="cartoes">
    <?php $cartao(link_('clientes'), 'loja', 'Clientes', $txt(contagemArea('clientes'), 'cliente', 'clientes')); ?>
    <?php $cartao(link_('marcas'), 'tag', 'Marcas', $txt(contagemArea('marcas'), 'marca', 'marcas')); ?>
    <?php if ($admin): ?>
    <?php $cartao(link_('empresa'), 'empresa', 'Empresa', 'Telefones, WhatsApp, endereço, horário, redes e depoimentos.'); ?>
    <?php $cartao(link_('copias'), 'restore', 'Cópias de segurança', 'Volte clientes, marcas ou dados da empresa para como estavam.'); ?>
    <?php $cartao(link_('usuarios'), 'users', 'Usuários', 'Quem pode entrar no painel e o que cada um pode fazer.'); ?>
    <?php $cartao(link_('atividades'), 'lista', 'Atividades', 'Quem fez o quê, e quando.'); ?>
    <?php endif; ?>
  </div>
  <?php if (!$admin): ?><p class="dica"><?= icone('user') ?><span>Você é <b>Editor</b>: cuida dos clientes e das marcas. Dados da empresa, cópias de segurança e usuários ficam com os administradores.</span></p><?php endif; ?>
  <section class="caixa">
    <h2 class="caixa__tit"><?= $admin ? 'Últimas alterações' : 'Suas últimas alterações' ?></h2>
    <?php if (!$atv): ?><p class="ajuda">Nada alterado ainda.</p><?php else: ?>
    <ol class="atividades" role="list"><?php foreach ($atv as $a) linhaAtividade($a, $nomes, true); ?></ol>
    <?php endif; ?>
    <?php if ($admin): ?><p class="caixa__mais"><a href="<?= e(link_('atividades')) ?>"><?= icone('lista') ?>Ver todas as atividades</a></p><?php endif; ?>
  </section>
<?php
    }, ['menu' => 'inicio', 'classe' => 'pg-inicio']);
}

/* ------------------------------------------------------------------
   Clientes e Marcas: lista
   ------------------------------------------------------------------ */
/** Regras de máscara (marcas) para o <style> com nonce. Só ids e endereços já conferidos. */
function cssMascaras(array $regras): string
{
    $css = '';
    foreach ($regras as $seletor => $logo) {
        if (arquivoLogo($logo) === null) continue;
        $url = str_replace(['"', '\\', "\n"], '', urlLogo($logo));
        $css .= $seletor . '{-webkit-mask-image:url("' . $url . '");mask-image:url("' . $url . '")}';
    }
    return $css;
}

/** O quadro do logo, como no site. */
function quadroLogo(string $area, array $r, string $classe = ''): string
{
    $logo = (string) ($r['logo'] ?? '');
    $nome = (string) ($r['nome'] ?? '');
    if ($area === 'clientes') {
        if ($logo === '') return '<span class="tile tile--texto ' . e($classe) . '"><b>' . e($nome) . '</b></span>';
        return '<span class="tile' . (($r['fundo'] ?? '') === 'escuro' ? ' tile--escuro' : '') . ' ' . e($classe) . '"><img src="' . e(urlLogo($logo)) . '" alt="" loading="lazy" decoding="async"></span>';
    }
    if ($logo === '') return '<span class="tile tile--marca ' . e($classe) . '"><b>' . e($nome) . '</b></span>';
    if (!empty($r['cor']) || arquivoLogo($logo) === null) return '<span class="tile' . (!empty($r['cor']) ? '' : ' tile--marca') . ' ' . e($classe) . '"><img src="' . e(urlLogo($logo)) . '" alt="" loading="lazy" decoding="async"></span>';
    return '<span class="tile tile--marca ' . e($classe) . '"><span class="masc"></span></span>';
}

function telaLista(string $area, array $dados): void
{
    $L = LOGOS[$area];
    $lista = $dados['dados'];
    $noSite = count(array_filter($lista, 'visivel'));
    $mascaras = [];
    if ($area === 'marcas') foreach ($lista as $r) if (empty($r['cor']) && !empty($r['logo'])) $mascaras['#i-' . $r['id'] . ' .masc'] = (string) $r['logo'];
    pagina($L['titulo'], static function () use ($area, $L, $lista, $noSite, $dados): void {
        cabecalhoPagina('Logos no site', $L['titulo'],
            '<span><b>' . count($lista) . '</b> ' . (count($lista) === 1 ? $L['um'] : $L['um'] . 's') . '</span><span><b>' . $noSite . '</b> no site</span>'
            . '<a href="' . e(cfg('url_site') . $L['site']) . '" target="_blank" rel="noopener">' . icone('ext') . 'Ver no site</a>',
            '<a class="btn" href="' . e(link_('novo', ['area' => $area])) . '">' . icone('plus') . e($L['novo']) . '</a>');
        if (!$dados['existe']): ?>
  <div class="aviso aviso--info" role="status"><?= icone('alert') ?><p>O arquivo <code>dados/<?= e(AREAS[$area]['arquivo']) ?></code> ainda não está no servidor. Ao cadastrar, o painel cria o arquivo só com o que for cadastrado aqui.</p></div>
  <?php endif; ?>
  <div class="dica">
    <?= icone('lista') ?>
    <p><span><b>Ordem no site:</b> a mesma desta lista. Arraste pela alça <?= icone('alca', 'i i--mini') ?> ou use as setas.</span>
    <span><b>Logo bom:</b> <?= $area === 'clientes' ? 'PNG com fundo transparente (ou fundo branco).' : 'PNG com fundo transparente: no site a marca é pintada de branco (fundo escuro) ou de azul (fundo claro).' ?> O painel corta as sobras e reduz sozinho.</span></p>
  </div>
  <?php if (!$lista): ?>
  <div class="vazio"><p><b>Nada cadastrado ainda.</b></p><p><a class="btn btn--p" href="<?= e(link_('novo', ['area' => $area])) ?>"><?= icone('plus') ?><?= e($L['novo']) ?></a></p></div>
  <?php else: ?>
  <div class="filtro" data-filtro-caixa hidden>
    <label for="f-busca" class="sr-only">Buscar</label>
    <input id="f-busca" type="search" placeholder="Buscar pelo nome…" data-filtro autocomplete="off">
  </div>
  <ol class="itens" role="list" data-ordenar data-acao="<?= e(link_('ordem', ['area' => $area])) ?>" aria-describedby="ajuda-ordem">
    <?php foreach ($lista as $i => $r) itemLista($area, $r, $i, count($lista)); ?>
  </ol>
  <p class="sr-only" id="ajuda-ordem">Para mudar a ordem com o teclado, use os botões Subir e Descer de cada item.</p>
  <?php endif; ?>
<?php
    }, ['menu' => $area, 'classe' => 'pg-lista pg-' . $area, 'estilo' => cssMascaras($mascaras)]);
}

function itemLista(string $area, array $r, int $i, int $total): void
{
    $id = (string) $r['id'];
    $nome = (string) $r['nome'];
    $vis = visivel($r);
    $editar = link_('editar', ['area' => $area, 'id' => $id]);
    $campos = campoCsrf() . '<input type="hidden" name="id" value="' . e($id) . '">';
    $selos = [];
    if (!$vis) $selos[] = ['Oculto: não aparece no site', 'selo--off'];
    if ((string) ($r['logo'] ?? '') === '') $selos[] = [$area === 'clientes' ? 'Sem logo: aparece só o nome' : 'Sem logo', 'selo--aviso'];
    elseif (($a = arquivoLogo((string) $r['logo'])) !== null && !@is_file($a)) $selos[] = ['Arquivo do logo não encontrado', 'selo--aviso'];
    if ($area === 'clientes' && ($r['fundo'] ?? '') === 'escuro') $selos[] = ['Quadro escuro', ''];
    if ($area === 'marcas' && !empty($r['cor'])) $selos[] = ['Colorido', ''];
    ?>
    <li class="item<?= $vis ? '' : ' is-oculto' ?>" id="i-<?= e($id) ?>" data-id="<?= e($id) ?>" data-nome="<?= e(semAcento($nome)) ?>">
      <button type="button" class="item__alca" data-alca aria-label="Arrastar <?= e($nome) ?>" title="Arraste para mudar a posição" hidden><?= icone('alca') ?></button>
      <span class="item__pos" data-pos><?= $i + 1 ?></span>
      <a class="item__quadro" href="<?= e($editar) ?>" tabindex="-1" aria-hidden="true"><?= quadroLogo($area, $r) ?></a>
      <div class="item__txt">
        <h2 class="item__nome"><a href="<?= e($editar) ?>"><?= e($nome) ?></a></h2>
        <p class="item__grupos"><?= e(textoGrupos($r, $area) ?: 'Sem ' . strtolower(LOGOS[$area]['rotuloGrupos'])) ?></p>
        <?php if ($selos): ?><p class="selos"><?php foreach ($selos as [$t, $c]): ?><span class="selo <?= e($c) ?>"><?= e($t) ?></span> <?php endforeach; ?></p><?php endif; ?>
      </div>
      <div class="item__acoes">
        <form method="post" action="<?= e(link_('mover', ['area' => $area])) ?>" data-mover>
          <?= $campos ?><input type="hidden" name="dir" value="cima">
          <button type="submit" class="ic" aria-label="Subir <?= e($nome) ?>" title="Subir"<?= $i === 0 ? ' disabled' : '' ?> data-cima><?= icone('up') ?></button>
        </form>
        <form method="post" action="<?= e(link_('mover', ['area' => $area])) ?>" data-mover>
          <?= $campos ?><input type="hidden" name="dir" value="baixo">
          <button type="submit" class="ic" aria-label="Descer <?= e($nome) ?>" title="Descer"<?= $i === $total - 1 ? ' disabled' : '' ?> data-baixo><?= icone('down') ?></button>
        </form>
        <form method="post" action="<?= e(link_('visivel', ['area' => $area])) ?>" data-visivel>
          <?= $campos ?><input type="hidden" name="valor" value="<?= $vis ? '0' : '1' ?>">
          <button type="submit" class="btn btn--linha btn--p" aria-label="<?= e(($vis ? 'Ocultar ' : 'Mostrar ') . $nome) ?>"><?= icone($vis ? 'eye-off' : 'eye') ?><span><?= $vis ? 'Ocultar' : 'Mostrar' ?></span></button>
        </form>
        <a class="btn btn--linha btn--p" href="<?= e($editar) ?>"><?= icone('edit') ?><span>Editar</span><span class="sr-only"> <?= e($nome) ?></span></a>
        <form method="post" action="<?= e(link_('excluir', ['area' => $area])) ?>" data-confirmar="<?= e(textoExcluir($area, $r)) ?>" data-confirmar-ok="Excluir" data-confirmar-tit="Excluir?">
          <?= $campos ?>
          <button type="submit" class="ic ic--perigo" aria-label="Excluir <?= e($nome) ?>" title="Excluir"><?= icone('trash') ?></button>
        </form>
      </div>
    </li>
<?php
}

/* ------------------------------------------------------------------
   Clientes e Marcas: formulário
   ------------------------------------------------------------------ */
/** $atual: item sendo editado (null = novo). $v: valores digitados (depois de um erro). */
function telaItem(string $area, array $dados, ?array $atual, array $erros, ?array $v = null): void
{
    $L = LOGOS[$area];
    $novo = $atual === null;
    $v ??= $atual ?? ['nome' => '', $L['campo'] => '', 'logo' => '', 'visivel' => true];
    $logoAtual = (string) ($atual['logo'] ?? '');
    $temLogo = $logoAtual !== '';
    $titulo = $novo ? $L['novo'] : 'Editar ' . $L['um'];
    $acao = link_('salvar', ['area' => $area] + ($novo ? [] : ['id' => (string) $atual['id']]));
    $voltar = linkLista($area, $novo ? '' : (string) $atual['id']);
    $grupos = gruposDe($v, $area);
    $maxB = (int) cfg('logo_max_bytes');
    $estilo = $area === 'marcas' && $temLogo && empty($atual['cor']) ? cssMascaras(['.previa [data-masc]' => $logoAtual]) : '';

    pagina($titulo, static function () use ($area, $L, $novo, $atual, $v, $erros, $logoAtual, $temLogo, $titulo, $acao, $voltar, $grupos, $maxB): void { ?>
  <p class="migalha"><a href="<?= e($voltar) ?>"><?= icone('up', 'i i--esq') ?><?= e($L['titulo']) ?></a></p>
  <?php cabecalhoPagina($novo ? 'Logos no site' : (string) $atual['nome'], $titulo); ?>
  <?php resumoErros($erros, ($novo ? 'Nada foi cadastrado.' : 'Nada foi salvo.')); ?>
  <form class="form-item" method="post" action="<?= e($acao) ?>" enctype="multipart/form-data" data-form-logo="<?= e($area) ?>" data-enviar novalidate>
    <?= campoCsrf() ?>
    <input type="hidden" name="MAX_FILE_SIZE" value="<?= $maxB ?>">
    <fieldset class="caixa caixa--logo">
      <legend>Logo</legend>
      <?php if ($area === 'clientes'): ?>
      <p class="rotulo" id="previa-rot">Como fica no site</p>
      <div class="previa" aria-describedby="previa-rot">
        <div class="tile tile--grande<?= ($v['fundo'] ?? '') === 'escuro' ? ' tile--escuro' : '' ?><?= $temLogo ? '' : ' tile--texto' ?>" data-previa-quadro>
          <img src="<?= $temLogo ? e(urlLogo($logoAtual)) : '' ?>" alt="<?= $temLogo ? 'Logo atual' : '' ?>" data-previa-img<?= $temLogo ? '' : ' hidden' ?>>
          <b data-previa-nome<?= $temLogo ? ' hidden' : '' ?>><?= e((string) $v['nome'] !== '' ? $v['nome'] : 'Nome do cliente') ?></b>
        </div>
      </div>
      <?php else: ?>
      <p class="rotulo" id="previa-rot">Como fica no site</p>
      <div class="previa previa--marca" aria-describedby="previa-rot" data-previa-marca<?= !empty($v['cor']) ? ' data-cor' : '' ?>>
        <figure class="pm pm--escuro" data-mono><span class="pm__q"><span class="masc" data-masc></span></span><figcaption>Seções escuras (branco)</figcaption></figure>
        <figure class="pm pm--claro" data-mono><span class="pm__q"><span class="masc" data-masc></span></span><figcaption>Seções claras (azul)</figcaption></figure>
        <figure class="pm pm--cor" data-colorido><span class="pm__q"><img src="<?= $temLogo ? e(urlLogo($logoAtual)) : '' ?>" alt="" data-previa-img<?= $temLogo ? '' : ' hidden' ?>></span><figcaption>Colorido (quadro branco)</figcaption></figure>
        <p class="previa__vazio" data-previa-vazio<?= $temLogo ? ' hidden' : '' ?>><?= icone('image') ?>Escolha o logo para ver a prévia.</p>
      </div>
      <?php endif; ?>
      <div class="campo">
        <label class="btn btn--linha btn--largo arquivo__btn" for="f-logo"><?= icone('image') ?><span><?= $temLogo ? 'Trocar o logo' : 'Escolher o logo' ?></span></label>
        <input class="arquivo" id="f-logo" type="file" name="logo" accept="image/png,image/jpeg,image/webp" data-logo-input data-max="<?= $maxB ?>"<?= invalido($erros, 'logo', 'f-logo-ajuda') ?>>
        <p class="arquivo__nome" data-arquivo-nome aria-live="polite"></p>
        <p class="campo__aviso" data-logo-aviso role="status" hidden></p>
        <?= erroCampo($erros, 'logo') ?>
        <p class="ajuda" id="f-logo-ajuda">PNG, JPG ou WebP, até <?= e(mb_(min($maxB, limiteEnvio()))) ?>. <b>SVG não</b> (por segurança). O painel corta as margens vazias, reduz e salva em WebP.</p>
      </div>
      <?php if ($area === 'clientes'): ?>
      <label class="opcao"><input type="checkbox" name="escuro" value="1"<?= ($v['fundo'] ?? '') === 'escuro' ? ' checked' : '' ?> data-op-escuro><span><b>Logo branco (quadro escuro)</b> Para logos brancos ou claros: o quadro do site fica azul-escuro.</span></label>
      <?php if ($temLogo): ?><label class="opcao"><input type="checkbox" name="sem_logo" value="1" data-op-sem-logo><span><b>Tirar o logo</b> O site mostra só o nome, num quadro de texto.</span></label><?php endif; ?>
      <?php else: ?>
      <label class="opcao"><input type="checkbox" name="cor" value="1"<?= !empty($v['cor']) ? ' checked' : '' ?> data-op-cor><span><b>Mostrar colorido (quadro branco)</b> Para logos que só funcionam com as cores originais.</span></label>
      <label class="opcao" data-op-fundo-caixa><input type="checkbox" name="remover_fundo" value="1"<?= !empty($v['_remover_fundo']) ? ' checked' : '' ?> data-op-fundo><span><b>Remover fundo claro</b> A cor do fundo (a dos cantos) fica transparente. Use quando o logo vem com fundo branco<?= $temLogo ? '; sem escolher arquivo, o painel refaz o logo atual' : '' ?>.</span></label>
      <?php endif; ?>
    </fieldset>
    <fieldset class="caixa">
      <legend><?= $area === 'clientes' ? 'Cliente' : 'Marca' ?></legend>
      <?php campo('nome', 'Nome', (string) $v['nome'], $erros, ['max' => LIMITE_NOME, 'obrig' => true, 'ph' => $area === 'clientes' ? 'Ex.: Santa Casa de Rio Claro' : 'Ex.: JBL',
          'ajuda' => $area === 'clientes' ? 'Aparece ao passar o mouse no logo e para leitores de tela.' : 'Aparece ao passar o mouse e quando a marca não tem logo.', 'attrs' => ' data-nome']); ?>
      <fieldset class="grupos" id="f-grupos"<?= invalido($erros, 'grupos') ?>>
        <legend><?= e($L['rotuloGrupos']) ?> <small>onde aparece no site</small></legend>
        <?= erroCampo($erros, 'grupos') ?>
        <div class="grupos__lista">
          <?php foreach ($L['grupos'] as $k => $rot): ?>
          <label class="chk"><input type="checkbox" name="grupos[]" value="<?= e($k) ?>"<?= in_array($k, $grupos, true) ? ' checked' : '' ?>><span><?= e($rot) ?></span></label>
          <?php endforeach; ?>
        </div>
      </fieldset>
      <label class="opcao"><input type="checkbox" name="visivel" value="1"<?= ($v['visivel'] ?? true) !== false ? ' checked' : '' ?>><span><b>Aparece no site</b> Desmarcado: fica guardado aqui, mas o site não mostra.</span></label>
      <?= erroCampo($erros, 'geral') ?>
      <?php if ($novo): ?><p class="ajuda">Entra no <b>fim da lista</b>. Depois, arraste ou use as setas para escolher a posição.</p><?php endif; ?>
    </fieldset>
    <div class="barra-salvar">
      <a class="btn btn--linha" href="<?= e($voltar) ?>">Cancelar</a>
      <button class="btn" type="submit"><?= icone('check') ?><span><?= $novo ? 'Cadastrar' : 'Salvar' ?></span></button>
    </div>
  </form>
<?php
    }, ['menu' => $area, 'classe' => 'pg-item pg-' . $area, 'estilo' => $estilo]);
}

function telaDadosIlegiveis(string $area, string $motivo): void
{
    http_response_code(500);
    pagina('Arquivo com problema', static function () use ($area, $motivo): void { ?>
  <section class="vazio-grande">
    <?= icone('alert', 'i i--grande') ?>
    <h1>Não consegui ler dados/<?= e(AREAS[$area]['arquivo']) ?></h1>
    <p>O arquivo não está no formato que o painel grava (<?= e($motivo) ?>). Isso acontece quando ele é editado à mão ou trocado por uma versão errada.</p>
    <?php if (ehAdmin()): ?>
    <p>O jeito mais rápido de resolver é restaurar uma cópia de segurança.</p>
    <p><a class="btn" href="<?= e(link_('copias', ['area' => $area])) ?>"><?= icone('restore') ?>Ver cópias de segurança</a></p>
    <?php else: ?>
    <p>Avise um administrador do painel: ele resolve restaurando uma cópia de segurança.</p>
    <?php endif; ?>
  </section>
<?php }, ['menu' => $area]);
}

function telaConfirmar(string $titulo, string $texto, string $acao, array $campos, string $botao, string $voltar): void
{
    pagina($titulo, static function () use ($titulo, $texto, $acao, $campos, $botao, $voltar): void { ?>
  <section class="confirmar">
    <?= icone('alert', 'i i--grande') ?>
    <h1><?= e($titulo) ?></h1>
    <p><?= e($texto) ?></p>
    <form method="post" action="<?= e($acao) ?>" class="confirmar__bts">
      <?= campoCsrf() ?>
      <?php foreach ($campos as $k => $v): ?><input type="hidden" name="<?= e($k) ?>" value="<?= e($v) ?>"><?php endforeach; ?>
      <input type="hidden" name="confirmado" value="1">
      <a class="btn btn--linha" href="<?= e($voltar) ?>">Cancelar</a>
      <button class="btn btn--perigo" type="submit"><?= e($botao) ?></button>
    </form>
  </section>
<?php });
}

/* ------------------------------------------------------------------
   Empresa
   ------------------------------------------------------------------ */
/** $v: valores da tela; $nota: aviso sem salvar (ex.: ordem mudou); $linhasDep: quantas linhas de depoimento mostrar. */
function telaEmpresa(array $E, array $v, array $erros = [], string $nota = '', ?int $linhasDep = null): void
{
    $dep = $v['dep'];
    $n = max($linhasDep ?? count($dep) + 1, count($dep), 1);
    while (count($dep) < $n) $dep[] = ['nome' => '', 'texto' => ''];
    $digitos = whatsappEmpresa($v['whatsapp'])[1] === null ? whatsappEmpresa($v['whatsapp'])[0] : '';
    $wa = linkWhatsapp($digitos, (string) (limparLinha($v['msg']) ?? ''));
    $preGrade = [];
    foreach ($v['dias'] as $d => $x) {
        $preGrade[$d] = [];
        if (!$x['aberto']) continue;
        $a1 = minutosDoForm($x['a1']); $f1 = minutosDoForm($x['f1']);
        if ($a1 !== null && $f1 !== null && $a1 < $f1) $preGrade[$d][] = [$a1, $f1];
        $a2 = minutosDoForm($x['a2']); $f2 = minutosDoForm($x['f2']);
        if ($a2 !== null && $f2 !== null && $a2 < $f2) $preGrade[$d][] = [$a2, $f2];
    }
    pagina('Empresa', static function () use ($E, $v, $erros, $nota, $dep, $wa, $preGrade): void {
        cabecalhoPagina('Dados do site', 'Empresa', 'Telefones, WhatsApp, endereço, horário e o que mais o site mostra sobre a Danni Som. Ao salvar, o painel guarda uma cópia de segurança de como estava.');
        if ($nota !== ''): ?><div class="aviso aviso--info" role="status"><?= icone('alert') ?><p><?= e($nota) ?></p></div><?php endif;
        resumoErros($erros, 'Nada foi salvo.'); ?>
  <form class="form-empresa" method="post" action="<?= e(link_('empresa-salvar')) ?>" data-enviar novalidate>
    <?= campoCsrf() ?>
    <fieldset class="caixa">
      <legend>Contato</legend>
      <div class="grade-campos">
        <?php campo('tel', 'Telefone', $v['tel'], $erros, ['tipo' => 'tel', 'auto' => 'tel', 'obrig' => true, 'ph' => '(19) 3523-3198', 'ajuda' => 'O link “Ligar” é criado sozinho.']);
        campo('tel2', 'Telefone 2', $v['tel2'], $erros, ['tipo' => 'tel', 'auto' => 'tel', 'opcional' => true, 'ph' => '(19) 3534-2971']); ?>
        <?php campo('whatsapp', 'WhatsApp', $v['whatsapp'], $erros, ['tipo' => 'tel', 'modo' => 'numeric', 'opcional' => true, 'ph' => '5519999999999', 'attrs' => ' data-wa-numero',
            'ajuda' => 'Só números: 55 + DDD + número. Vazio: os botões do site levam para a página de contato.']); ?>
        <?php campo('email', 'E-mail', $v['email'], $erros, ['tipo' => 'email', 'auto' => 'email', 'opcional' => true, 'max' => 120]); ?>
      </div>
      <?php campo('msg', 'Mensagem que já vem escrita no WhatsApp', $v['msg'], $erros, ['max' => 200, 'attrs' => ' data-wa-msg', 'classe' => 'campo--largo']); ?>
      <p class="wa-teste" data-wa-caixa<?= $wa === '' ? ' hidden' : '' ?>><?= icone('whats') ?><span>Link de teste: <a href="<?= e($wa) ?>" target="_blank" rel="noopener" data-wa-link><?= e($wa) ?></a></span></p>
    </fieldset>

    <fieldset class="caixa">
      <legend>Endereço e mapa</legend>
      <div class="grade-campos">
        <?php campo('rua', 'Rua e número', $v['rua'], $erros, ['max' => 100, 'obrig' => true, 'auto' => 'address-line1']);
        campo('bairro', 'Bairro', $v['bairro'], $erros, ['max' => 60, 'opcional' => true]);
        campo('cidade', 'Cidade', $v['cidade'], $erros, ['max' => 60, 'obrig' => true, 'auto' => 'address-level2']); ?>
        <div class="campo campo--duplo">
          <div class="campo">
            <label for="f-uf">Estado</label>
            <select id="f-uf" name="uf"<?= invalido($erros, 'uf') ?>><?php foreach (UFS as $uf): ?><option<?= $v['uf'] === $uf ? ' selected' : '' ?>><?= e($uf) ?></option><?php endforeach; ?></select>
            <?= erroCampo($erros, 'uf') ?>
          </div>
          <?php campo('cep', 'CEP', $v['cep'], $erros, ['modo' => 'numeric', 'max' => 9, 'opcional' => true, 'ph' => '13500-220', 'auto' => 'postal-code']); ?>
        </div>
      </div>
      <?php campo('mapa', 'Link do mapa (Google Maps)', $v['mapa'], $erros, ['tipo' => 'url', 'opcional' => true, 'classe' => 'campo--largo', 'ajuda' => 'No Google Maps: <b>Compartilhar</b> → <b>Copiar link</b>. É o botão “Como chegar” do site.']); ?>
      <div class="grade-campos">
        <?php campo('lat', 'Latitude', $v['lat'], $erros, ['modo' => 'decimal', 'opcional' => true, 'ph' => '-22,3997744', 'attrs' => ' data-geo']);
        campo('lng', 'Longitude', $v['lng'], $erros, ['modo' => 'decimal', 'opcional' => true, 'ph' => '-47,5705148', 'ajuda' => 'No Google Maps, clique com o botão direito na loja: os dois números aparecem no topo do menu (clique neles para copiar e cole na Latitude: o painel separa sozinho). Usados no Waze e no Google.']); ?>
      </div>
    </fieldset>

    <fieldset class="caixa" id="horario">
      <legend>Horário de funcionamento</legend>
      <p class="ajuda">Marque os dias em que a loja abre. O 2º horário é para quem fecha no almoço (ex.: 8:00–12:00 e 13:30–18:00). O site usa isto para o quadro de horários e para o selo “Aberto agora”.</p>
      <div class="horario" data-horario>
        <?php foreach (DIAS as $d => $nomeDia): $x = $v['dias'][$d]; $k = 'h-' . $d; ?>
        <div class="dia<?= $x['aberto'] ? '' : ' is-fechado' ?><?= isset($erros[$k]) ? ' is-erro' : '' ?>" data-dia="<?= $d ?>" id="f-<?= e($k) ?>" tabindex="-1">
          <label class="dia__nome chk"><input type="checkbox" name="h[<?= $d ?>][aberto]" value="1"<?= $x['aberto'] ? ' checked' : '' ?> data-aberto><span><?= e($nomeDia) ?></span></label>
          <span class="dia__fechado" aria-hidden="true">Fechado</span>
          <span class="dia__horas">
            <span class="dia__iv"><label class="sr-only" for="h<?= $d ?>a1"><?= e($nomeDia) ?>: abre</label><input type="time" id="h<?= $d ?>a1" name="h[<?= $d ?>][a1]" value="<?= e($x['a1']) ?>" step="900"><span aria-hidden="true">às</span><label class="sr-only" for="h<?= $d ?>f1"><?= e($nomeDia) ?>: fecha</label><input type="time" id="h<?= $d ?>f1" name="h[<?= $d ?>][f1]" value="<?= e($x['f1']) ?>" step="900"></span>
            <span class="dia__iv dia__iv--2"><span class="dia__e" aria-hidden="true">e</span><label class="sr-only" for="h<?= $d ?>a2"><?= e($nomeDia) ?>: abre de novo (opcional)</label><input type="time" id="h<?= $d ?>a2" name="h[<?= $d ?>][a2]" value="<?= e($x['a2']) ?>" step="900"><span aria-hidden="true">às</span><label class="sr-only" for="h<?= $d ?>f2"><?= e($nomeDia) ?>: fecha de novo (opcional)</label><input type="time" id="h<?= $d ?>f2" name="h[<?= $d ?>][f2]" value="<?= e($x['f2']) ?>" step="900"></span>
          </span>
          <?= erroCampo($erros, $k) ?>
        </div>
        <?php endforeach; ?>
      </div>
      <p><button type="button" class="btn btn--linha btn--p btn--quebra" data-copiar-semana hidden><?= icone('copiar') ?>Copiar o horário de segunda para terça a sexta</button></p>
      <div class="previa-horario">
        <p class="rotulo">Como fica no quadro de horários do site</p>
        <dl data-previa-horario><?php foreach (linhasHorario($preGrade) as $l): ?><dt><?= e($l['dias']) ?></dt><dd><?= e($l['horas']) ?></dd><?php endforeach; ?></dl>
      </div>
    </fieldset>

    <fieldset class="caixa">
      <legend>Redes sociais e Google</legend>
      <div class="grade-campos">
        <?php campo('instagram', 'Instagram', $v['instagram'], $erros, ['opcional' => true, 'ph' => 'https://www.instagram.com/danni_som/', 'ajuda' => 'O link do perfil ou só @perfil. Vazio: o ícone some do site.']);
        campo('facebook', 'Facebook', $v['facebook'], $erros, ['opcional' => true, 'ph' => 'https://www.facebook.com/dannisom/']);
        campo('nota', 'Nota no Google', $v['nota'], $erros, ['modo' => 'decimal', 'max' => 4, 'obrig' => true, 'ph' => '4,8']);
        campo('avaliacoes', 'Quantidade de avaliações', $v['avaliacoes'], $erros, ['modo' => 'numeric', 'max' => 7, 'obrig' => true, 'ph' => '810']); ?>
      </div>
      <?php campo('glink', 'Link das avaliações no Google', $v['glink'], $erros, ['tipo' => 'url', 'opcional' => true, 'classe' => 'campo--largo']); ?>
    </fieldset>

    <fieldset class="caixa" id="f-dep">
      <legend>Depoimentos</legend>
      <p class="ajuda">Avaliações reais do Google: primeiro nome e o texto. A ordem daqui é a ordem do site.</p>
      <?= erroCampo($erros, 'dep') ?>
      <ol class="deps" role="list" data-deps>
        <?php foreach ($dep as $i => $x) linhaDepoimento($i, $x, $erros, count($dep)); ?>
      </ol>
      <template data-dep-modelo><?php linhaDepoimento(999, ['nome' => '', 'texto' => ''], [], 0); ?></template>
      <p><button type="submit" name="mais" value="1" class="btn btn--linha btn--p" data-dep-mais formnovalidate><?= icone('plus') ?>Adicionar depoimento</button></p>
    </fieldset>

    <details class="caixa caixa--fixos">
      <summary>Dados fixos (mudam só com quem cuida do site)</summary>
      <dl class="fixos">
        <?php foreach (CAMPOS_FIXOS as $k => $rot): $x = valorEmpresa($E, $k); ?>
        <dt><?= e($rot) ?></dt><dd><?= e(is_array($x) ? implode(', ', array_filter($x, 'is_string')) : (is_scalar($x) ? (string) $x : '—')) ?></dd>
        <?php endforeach; ?>
      </dl>
    </details>

    <div class="barra-salvar">
      <a class="btn btn--linha" href="<?= e(link_('empresa')) ?>">Descartar mudanças</a>
      <button class="btn" type="submit"><?= icone('check') ?><span>Salvar</span></button>
    </div>
  </form>
<?php
    }, ['menu' => 'empresa', 'classe' => 'pg-empresa']);
}

function linhaDepoimento(int $i, array $x, array $erros, int $total): void
{
    $kn = 'dep-' . $i . '-nome';
    $kt = 'dep-' . $i . '-texto';
    $vazio = $x['nome'] === '' && $x['texto'] === '';
    ?>
        <li class="dep" data-dep>
          <div class="dep__campos">
            <div class="campo">
              <label for="f-<?= e($kn) ?>">Nome</label>
              <input id="f-<?= e($kn) ?>" name="dep[<?= $i ?>][nome]" value="<?= e($x['nome']) ?>" maxlength="40" autocomplete="off"<?= invalido($erros, $kn) ?>>
              <?= erroCampo($erros, $kn) ?>
            </div>
            <div class="campo">
              <label for="f-<?= e($kt) ?>">Texto</label>
              <textarea id="f-<?= e($kt) ?>" name="dep[<?= $i ?>][texto]" rows="3" maxlength="400"<?= invalido($erros, $kt) ?>><?= e($x['texto']) ?></textarea>
              <?= erroCampo($erros, $kt) ?>
            </div>
          </div>
          <div class="dep__acoes">
            <button type="submit" name="mover" value="<?= $i ?>:cima" class="ic" aria-label="Subir este depoimento" title="Subir" formnovalidate data-dep-cima<?= $i === 0 || $vazio ? ' disabled' : '' ?>><?= icone('up') ?></button>
            <button type="submit" name="mover" value="<?= $i ?>:baixo" class="ic" aria-label="Descer este depoimento" title="Descer" formnovalidate data-dep-baixo<?= $i >= $total - 1 || $vazio ? ' disabled' : '' ?>><?= icone('down') ?></button>
            <label class="chk chk--perigo"><input type="checkbox" name="dep[<?= $i ?>][remover]" value="1" data-dep-remover><span>Remover</span></label>
          </div>
        </li>
<?php
}

/* ------------------------------------------------------------------
   Cópias de segurança
   ------------------------------------------------------------------ */
function telaCopias(string $area): void
{
    $copias = listarCopias($area);
    $nomes = nomesDosUsuarios();
    pagina('Cópias de segurança', static function () use ($area, $copias, $nomes): void {
        cabecalhoPagina('Desfazer alterações', 'Cópias de segurança',
            'Antes de cada mudança o painel guarda uma cópia de como o arquivo estava. Ficam sempre as ' . (int) cfg('copias_max') . ' mais recentes e tudo dos últimos '
            . (int) cfg('copias_dias') . ' dias. <b>Restaurar</b> volta a área inteira (com os logos) para como estava e desfaz também o que foi feito depois; o estado atual vira uma cópia, então dá para voltar atrás.'); ?>
  <nav class="abas" aria-label="Cópias de">
    <?php foreach (AREAS as $k => $a): ?>
    <a class="aba" href="<?= e(link_('copias', ['area' => $k])) ?>"<?= $k === $area ? ' aria-current="page"' : '' ?>><?= icone($k === 'clientes' ? 'loja' : ($k === 'marcas' ? 'tag' : 'empresa')) ?><?= e($a['nome']) ?></a>
    <?php endforeach; ?>
  </nav>
  <?php if (!$copias): ?>
  <div class="vazio"><p><b>Ainda não há cópias de <?= e(strtolower(AREAS[$area]['nome'])) ?>.</b> Elas aparecem aqui depois da primeira alteração.</p></div>
  <?php else: ?>
  <ol class="copias" role="list">
    <?php foreach ($copias as $i => $c):
        $txt = 'Voltar ' . ($area === 'empresa' ? 'os dados da empresa' : 'a lista de ' . strtolower(AREAS[$area]['nome'])) . ' para como estava em ' . quando($c['criado'])
            . '? Isso desfaz também o que foi feito depois. O estado atual vira uma cópia, então dá para voltar atrás.'; ?>
    <li class="copia">
      <div class="copia__txt">
        <p class="copia__quando"><?= e(quando($c['criado'])) ?><?php if ($i === 0): ?> <span class="selo selo--novo">Mais recente</span><?php endif; ?></p>
        <p class="copia__motivo"><?= e($c['motivo'] !== '' ? $c['motivo'] : 'Antes de uma alteração') ?><?php if ($c['juntou'] > 1): ?> <span class="copia__mais">e mais <?= $c['juntou'] - 1 ?> do mesmo tipo, até <?= e(date('H:i', $c['ultimo'])) ?></span><?php endif; ?></p>
        <p class="copia__meta"><?= $c['total'] !== null ? (int) $c['total'] . ' itens · ' . (int) $c['visiveis'] . ' no site' : 'Dados da empresa' ?><?= $c['usuario'] !== '' ? ' · por ' . e(nomeDe($c['usuario'], $nomes)) : '' ?></p>
      </div>
      <form method="post" action="<?= e(link_('restaurar')) ?>" data-confirmar="<?= e($txt) ?>" data-confirmar-ok="Restaurar" data-confirmar-tit="Restaurar esta cópia?">
        <?= campoCsrf() ?><input type="hidden" name="copia" value="<?= e($c['nome']) ?>">
        <button class="btn btn--linha btn--p" type="submit"><?= icone('restore') ?>Restaurar</button>
      </form>
    </li>
    <?php endforeach; ?>
  </ol>
  <?php endif;
    }, ['menu' => 'copias', 'classe' => 'pg-copias']);
}

/* ------------------------------------------------------------------
   Atividades
   ------------------------------------------------------------------ */
const EVENTOS_ALERTA = ['senha-errada', 'codigo-errado', 'bloqueado', 'csrf', 'acesso-desativado', 'negado'];
const EVENTOS_ACESSO = ['entrou', 'saiu', 'trocou-senha', 'criou-senha', 'instalou', 'codigo-gerado', 'recuperou-acesso'];

function tipoAtividade(string $ev): string
{
    if (in_array($ev, EVENTOS_ALERTA, true)) return 'alertas';
    if (in_array($ev, EVENTOS_ACESSO, true)) return 'acessos';
    return 'alteracoes';
}

function fraseAtividade(array $a): string
{
    static $nomes = [
        'entrou' => 'Entrou no painel', 'saiu' => 'Saiu do painel', 'senha-errada' => 'Senha errada', 'trocou-senha' => 'Trocou a senha',
        'criou-senha' => 'Criou a própria senha', 'instalou' => 'Criou o primeiro acesso do painel', 'codigo-gerado' => 'O painel gravou um código de acesso (primeiro-acesso.php)',
        'codigo-errado' => 'Código de acesso errado', 'bloqueado' => 'Bloqueio por tentativas erradas', 'csrf' => 'Pedido recusado (página desatualizada)',
        'acesso-desativado' => 'Tentou entrar com o acesso desativado', 'negado' => 'Tentou abrir uma área só de administradores', 'recuperou-acesso' => 'Recuperou o acesso',
    ];
    if (isset($nomes[$a['evento']])) return $nomes[$a['evento']];
    return $a['detalhe'] !== '' ? $a['detalhe'] : $a['evento'];
}

function linhaAtividade(array $a, array $nomes, bool $comData = false): void
{
    $alerta = tipoAtividade($a['evento']) === 'alertas';
    $quem = $a['usuario'] !== '-' && $a['usuario'] !== '' ? nomeDe($a['usuario'], $nomes) : '';
    $mostraDetalhe = isset(array_flip(array_merge(EVENTOS_ALERTA, EVENTOS_ACESSO))[$a['evento']]) && $a['detalhe'] !== '';
    ?>
      <li class="atv<?= $alerta ? ' atv--alerta' : '' ?>">
        <time datetime="<?= e(date('c', $a['quando'])) ?>"><?= e(date($comData ? 'd/m H:i' : 'H:i', $a['quando'])) ?></time>
        <span><?php if ($quem !== ''): ?><b class="atv__quem"><?= e($quem) ?></b> <?php endif; ?><?= e(fraseAtividade($a)) ?><?= $mostraDetalhe ? ' — ' . e($a['detalhe']) : '' ?></span>
        <?php if ($alerta): ?><small>IP <?= e($a['ip']) ?></small><?php endif; ?>
      </li>
<?php
}

function telaAtividades(): void
{
    $tipo = is_string($_GET['tipo'] ?? null) && in_array($_GET['tipo'], ['alteracoes', 'acessos', 'alertas'], true) ? $_GET['tipo'] : '';
    $lista = array_slice(array_values(array_filter(ultimasAtividades(3000), static fn($a) => $tipo === '' || tipoAtividade($a['evento']) === $tipo)), 0, 300);
    $nomes = nomesDosUsuarios();
    $dias = [];
    foreach ($lista as $a) $dias[date('Y-m-d', $a['quando'])][] = $a;
    $semana = ['Domingo', 'Segunda', 'Terça', 'Quarta', 'Quinta', 'Sexta', 'Sábado'];
    pagina('Atividades', static function () use ($tipo, $lista, $nomes, $dias, $semana): void {
        cabecalhoPagina('Registro', 'Atividades', 'Quem fez o quê no painel, das mais novas para as mais antigas (até 300 linhas).'); ?>
  <nav class="abas" aria-label="Tipo de atividade">
    <?php foreach (['' => 'Tudo', 'alteracoes' => 'Alterações', 'acessos' => 'Acessos', 'alertas' => 'Alertas'] as $k => $rot): ?>
    <a class="aba" href="<?= e(link_('atividades', $k !== '' ? ['tipo' => $k] : [])) ?>"<?= $tipo === $k ? ' aria-current="page"' : '' ?>><?= e($rot) ?></a>
    <?php endforeach; ?>
  </nav>
  <?php if (!$lista): ?><div class="vazio"><p>Nada registrado.</p></div><?php endif; ?>
  <?php foreach ($dias as $d => $itens): $ts = strtotime($d) ?: 0;
      $rot = $d === date('Y-m-d') ? 'Hoje' : ($d === date('Y-m-d', strtotime('yesterday')) ? 'Ontem' : $semana[(int) date('w', $ts)]); ?>
  <section class="dia-atv" aria-label="<?= e($rot . ', ' . date('d/m/Y', $ts)) ?>">
    <h2 class="dia-atv__tit"><?= e($rot) ?> <span><?= e(date('d/m/Y', $ts)) ?></span></h2>
    <ol class="atividades" role="list"><?php foreach ($itens as $a) linhaAtividade($a, $nomes); ?></ol>
  </section>
  <?php endforeach;
    }, ['menu' => 'atividades', 'classe' => 'pg-atividades']);
}

/* ------------------------------------------------------------------
   Minha conta
   ------------------------------------------------------------------ */
function telaConta(string $erro = ''): void
{
    $eu = usuarioAtual();
    pagina('Minha conta', static function () use ($eu, $erro): void {
        cabecalhoPagina('Acesso', 'Minha conta', '<span>Você entrou como <b>' . e($eu['nome']) . '</b></span><span>usuário <b>' . e($eu['usuario']) . '</b></span><span>' . e(PAPEIS[$eu['papel']]) . '</span>');
        $recuperar = ehAdmin() ? arquivosRecuperacao() : [];
        if ($recuperar): ?>
  <div class="aviso aviso--erro" role="alert"><?= icone('alert') ?><p><b>O arquivo <?= e(implode(' e ', $recuperar)) ?> ainda está na pasta privada.</b> Enquanto ele existir, quem não está logado vê a tela “Recuperar acesso”. Apague pelo Gerenciador de Arquivos (pasta <code><?= e(ondeFicaPastaPrivada()) ?></code>).</p></div>
  <?php endif; ?>
  <div class="conta">
    <section class="caixa">
      <h2 class="caixa__tit">Trocar senha</h2>
      <?php avisoErro($erro); ?>
      <form method="post" action="<?= e(link_('senha')) ?>" class="form-acesso" data-enviar>
        <?= campoCsrf() ?>
        <input type="text" name="usuario" value="<?= e($eu['usuario']) ?>" autocomplete="username" hidden>
        <?php campoSenha('f-atual', 'atual', 'Senha atual', 'current-password'); ?>
        <?php campoSenha('f-nova', 'nova', 'Senha nova', 'new-password', 'Pelo menos ' . (int) cfg('senha_minimo') . ' caracteres. Ao trocar, os outros aparelhos saem do painel.'); ?>
        <?php campoSenha('f-nova2', 'nova2', 'Repita a senha nova', 'new-password'); ?>
        <button class="btn" type="submit"><?= icone('lock') ?>Trocar senha</button>
      </form>
    </section>
    <section class="caixa caixa--info">
      <h2 class="caixa__tit">Sobre este painel</h2>
      <ul class="info">
        <li>Por segurança, o painel sai sozinho depois de <?= intdiv((int) cfg('inatividade'), 3600) ?> horas sem uso.</li>
        <li>Dados privados (usuários, cópias, lixeira de logos): <code><?= e(ondeFicaPastaPrivada()) ?></code> — <?= privadaForaDoSite() ? 'fora da pasta do site.' : 'dentro do site, protegida.' ?></li>
        <li>Maior logo aceito agora: <b><?= e(mb_(limiteEnvio())) ?></b>. Logos salvos em <b><?= cfg('formato_logo') !== 'png' && function_exists('imagewebp') ? 'WebP' : 'PNG' ?></b>.</li>
        <li>PHP <?= e(PHP_VERSION) ?>.</li>
      </ul>
      <details class="acesso__ajuda">
        <summary>Esqueceu a senha?</summary>
        <?php passosEsqueci(); ?>
      </details>
    </section>
  </div>
<?php
    }, ['menu' => 'conta', 'classe' => 'pg-conta']);
}

/* ------------------------------------------------------------------
   Usuários (só administradores)
   ------------------------------------------------------------------ */
function selosUsuario(array $u): string
{
    $h = '<span class="selo selo--' . ($u['papel'] === 'admin' ? 'admin' : 'editor') . '">' . e(PAPEIS[$u['papel']]) . '</span>';
    if (!$u['ativo']) $h .= ' <span class="selo selo--off">Desativado</span>';
    elseif ($u['trocar_senha']) $h .= ' <span class="selo selo--aviso">Aguardando o 1º acesso</span>';
    return $h;
}

function telaUsuarios(): void
{
    $lista = lerUsuarios();
    pagina('Usuários', static function () use ($lista): void {
        cabecalhoPagina('Acesso', 'Usuários', '', count($lista) < MAX_USUARIOS ? '<a class="btn" href="' . e(link_('usuario-novo')) . '">' . icone('plus') . 'Novo usuário</a>' : ''); ?>
  <div class="papeis-info">
    <p><span class="selo selo--admin">Administrador</span> Faz tudo: clientes, marcas, dados da empresa, cópias de segurança, usuários e atividades.</p>
    <p><span class="selo selo--editor">Editor</span> Cadastra, edita, ordena, oculta e exclui clientes e marcas.</p>
  </div>
  <ol class="usuarios" role="list">
    <?php foreach ($lista as $u): $voce = ehVoce($u); $campos = campoCsrf() . '<input type="hidden" name="u" value="' . e($u['usuario']) . '">'; ?>
    <li class="usu<?= $u['ativo'] ? '' : ' is-oculto' ?>" id="u-<?= e($u['usuario']) ?>">
      <div class="usu__txt">
        <h2 class="usu__nome"><?= e($u['nome']) ?><?= $voce ? ' <span class="usu__voce">(você)</span>' : '' ?></h2>
        <p class="usu__login">usuário <b><?= e($u['usuario']) ?></b> · <?= $u['ultimo_acesso'] ? 'último acesso ' . e(quando($u['ultimo_acesso'])) : 'ainda não entrou' ?></p>
        <p class="selos"><?= selosUsuario($u) ?></p>
      </div>
      <div class="usu__acoes">
        <a class="btn btn--linha btn--p" href="<?= e(link_('usuario-editar', ['u' => $u['usuario']])) ?>"><?= icone('edit') ?><span>Editar</span><span class="sr-only"> <?= e($u['nome']) ?></span></a>
        <?php if (!$voce): ?>
        <form method="post" action="<?= e(link_('usuario-senha')) ?>" data-confirmar="<?= e('Criar uma senha temporária nova para ' . rotuloUsuario($u) . '? A senha atual para de funcionar na hora.') ?>" data-confirmar-ok="Redefinir" data-confirmar-tit="Redefinir a senha?">
          <?= $campos ?><button type="submit" class="btn btn--linha btn--p"><?= icone('chave') ?><span>Redefinir senha</span><span class="sr-only"> de <?= e($u['nome']) ?></span></button>
        </form>
        <form method="post" action="<?= e(link_('usuario-ativo')) ?>">
          <?= $campos ?><input type="hidden" name="valor" value="<?= $u['ativo'] ? '0' : '1' ?>">
          <button type="submit" class="btn btn--linha btn--p"><?= icone($u['ativo'] ? 'eye-off' : 'check') ?><span><?= $u['ativo'] ? 'Desativar' : 'Reativar' ?></span><span class="sr-only"> <?= e($u['nome']) ?></span></button>
        </form>
        <form method="post" action="<?= e(link_('usuario-excluir')) ?>" data-confirmar="<?= e(textoExcluirUsuario($u)) ?>" data-confirmar-ok="Excluir" data-confirmar-tit="Excluir usuário?">
          <?= $campos ?><button type="submit" class="ic ic--perigo" aria-label="Excluir <?= e($u['nome']) ?>" title="Excluir"><?= icone('trash') ?></button>
        </form>
        <?php endif; ?>
      </div>
    </li>
    <?php endforeach; ?>
  </ol>
  <p class="ajuda">Até <?= MAX_USUARIOS ?> usuários. Mudança de papel vale na hora.</p>
<?php
    }, ['menu' => 'usuarios', 'classe' => 'pg-usuarios']);
}

function telaUsuarioForm(?array $atual, array $v = [], array $erros = []): void
{
    $novo = $atual === null;
    $v += $atual ?? ['usuario' => '', 'nome' => '', 'papel' => 'editor'];
    $voce = !$novo && ehVoce($atual);
    $titulo = $novo ? 'Novo usuário' : 'Editar usuário';
    pagina($titulo, static function () use ($novo, $atual, $v, $erros, $voce, $titulo): void { ?>
  <p class="migalha"><a href="<?= e(link_('usuarios')) ?>"><?= icone('up', 'i i--esq') ?>Usuários</a></p>
  <?php cabecalhoPagina($novo ? 'Acesso' : $atual['nome'], $titulo, $novo ? 'A pessoa recebe uma senha temporária (aparece uma vez só) e cria a própria senha no primeiro acesso.' : '');
        resumoErros($erros, $novo ? 'O usuário não foi criado.' : 'Nada foi salvo.'); ?>
  <form class="form-usuario" method="post" action="<?= e(link_('usuario-salvar', $novo ? [] : ['u' => $atual['usuario']])) ?>" data-enviar>
    <?= campoCsrf() ?>
    <fieldset class="caixa">
      <legend>Pessoa</legend>
      <?php campo('nome', 'Nome', (string) $v['nome'], $erros, ['max' => 60, 'obrig' => true, 'ph' => 'Ex.: Márcio Zanão']);
      if ($novo) campo('usuario', 'Usuário (o que a pessoa digita para entrar)', (string) $v['usuario'], $erros, ['max' => 40, 'obrig' => true,
          'ajuda' => 'De 3 a 40 letras sem acento, números, ponto ou hífen (ex.: marcio). Não muda depois.', 'attrs' => ' autocapitalize="off" spellcheck="false" pattern="[A-Za-z0-9._\-]{3,40}"']);
      else echo '<p class="rotulo">Usuário</p><p class="usu__fixo"><b>' . e($atual['usuario']) . '</b> (não muda)</p>'; ?>
    </fieldset>
    <fieldset class="caixa" id="f-papel">
      <legend>Papel</legend>
      <?= erroCampo($erros, 'papel') ?>
      <?php if ($voce): ?><p class="ajuda">Você não pode mudar o seu próprio papel.</p><input type="hidden" name="papel" value="<?= e($atual['papel']) ?>"><?php endif; ?>
      <?php foreach (['editor' => 'Clientes e marcas: cadastrar, editar, ordenar, ocultar e excluir.', 'admin' => 'Tudo: também dados da empresa, cópias de segurança, usuários e atividades.'] as $p => $txt): ?>
      <label class="opcao"><input type="radio" name="papel" value="<?= e($p) ?>"<?= $v['papel'] === $p ? ' checked' : '' ?><?= $voce ? ' disabled' : '' ?>><span><b><?= e(PAPEIS[$p]) ?></b> <?= e($txt) ?></span></label>
      <?php endforeach; ?>
      <?= erroCampo($erros, 'geral') ?>
    </fieldset>
    <div class="barra-salvar">
      <a class="btn btn--linha" href="<?= e(link_('usuarios')) ?>">Cancelar</a>
      <button class="btn" type="submit"><?= icone('check') ?><span><?= $novo ? 'Criar usuário' : 'Salvar' ?></span></button>
    </div>
  </form>
<?php
    }, ['menu' => 'usuarios', 'classe' => 'pg-usuario']);
}

function urlDoPainel(): string
{
    $host = preg_replace('/[^A-Za-z0-9.:\[\]-]/', '', (string) ($_SERVER['HTTP_HOST'] ?? '')) ?? '';
    return (https() ? 'https' : 'http') . '://' . $host . caminhoCookie();
}

function telaSenhaTemporaria(array $f): void
{
    $msg = "Seu acesso ao painel do site da Danni Som:\nEndereço: " . urlDoPainel() . "\nUsuário: " . $f['usuario'] . "\nSenha temporária: " . $f['senha']
        . "\nNo primeiro acesso, o painel pede para você criar a sua senha.";
    pagina('Senha temporária', static function () use ($f, $msg): void {
        cabecalhoPagina($f['novo'] ? 'Usuário criado' : 'Senha redefinida', 'Senha temporária', '<span>Para <b>' . e($f['nome']) . '</b></span><span>usuário <b>' . e($f['usuario']) . '</b></span><span>' . e(PAPEIS[$f['papel']] ?? '') . '</span>'); ?>
  <section class="caixa senha-temp">
    <p class="rotulo">Senha temporária de <?= e($f['nome']) ?></p>
    <p class="senha-temp__valor"><code id="senha-temp" tabindex="0"><?= e($f['senha']) ?></code></p>
    <p class="ajuda">Passe esta senha para a pessoa (pessoalmente ou por mensagem). Por segurança, ela não aparece de novo.</p>
    <h2 class="caixa__tit">Mensagem pronta</h2>
    <pre class="mensagem" id="msg-acesso" tabindex="0"><?= e($msg) ?></pre>
    <p><button type="button" class="btn btn--linha btn--p" data-copiar="msg-acesso" hidden><?= icone('copiar') ?><span>Copiar mensagem</span></button></p>
  </section>
  <p><a class="btn" href="<?= e(link_('usuarios')) ?>"><?= icone('check') ?>Pronto, voltar para Usuários</a></p>
<?php
    }, ['menu' => 'usuarios']);
}

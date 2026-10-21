<?php
/* =====================================================================
   Painel Danni Som — entrada única (área restrita do site)
   Todas as telas e ações passam por aqui: index.php?a=<ação>.
   Instruções para o dono: LEIA-ME.md (nesta pasta).
   ===================================================================== */
declare(strict_types=1);
define('PAINEL', true);

require __DIR__ . '/lib/nucleo.php';
require __DIR__ . '/lib/acesso.php';
require __DIR__ . '/lib/dados.php';
require __DIR__ . '/lib/imagens.php';
require __DIR__ . '/lib/logos.php';
require __DIR__ . '/lib/empresa.php';
require __DIR__ . '/lib/telas.php';

iniciarPainel();
iniciarSessao();
verificarSessao();

const ACOES = ['inicio', 'entrar', 'instalar', 'recuperar', 'sair', 'conta', 'senha', 'criar-senha',
    'clientes', 'marcas', 'novo', 'editar', 'salvar', 'mover', 'ordem', 'visivel', 'excluir', 'desfazer',
    'empresa', 'empresa-salvar', 'copias', 'restaurar', 'atividades',
    'usuarios', 'usuario-novo', 'usuario-editar', 'usuario-salvar', 'usuario-senha', 'usuario-ativo', 'usuario-excluir', 'senha-temporaria'];
const SO_POST = ['sair', 'senha', 'salvar', 'mover', 'ordem', 'visivel', 'excluir', 'desfazer', 'empresa-salvar', 'restaurar',
    'usuario-salvar', 'usuario-senha', 'usuario-ativo', 'usuario-excluir'];
// Só administradores (o papel é lido do arquivo de usuários a cada pedido: mudou, vale na hora)
const SO_ADMIN = ['empresa', 'empresa-salvar', 'copias', 'restaurar', 'atividades',
    'usuarios', 'usuario-novo', 'usuario-editar', 'usuario-salvar', 'usuario-senha', 'usuario-ativo', 'usuario-excluir', 'senha-temporaria'];
// Enquanto a pessoa não cria a própria senha (entrou com a temporária), só estas
const SEM_SENHA_PROPRIA = ['criar-senha', 'sair'];

$acao = is_string($_GET['a'] ?? null) && in_array($_GET['a'], ACOES, true) ? $_GET['a'] : 'inicio';
$post = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';

/** Endereço interno para voltar depois de entrar (ou null se não for seguro). */
function destinoSeguro($url): ?string
{
    if (!is_string($url) || strlen($url) > 300) return null;
    return preg_match('/^index\.php\?a=(clientes|marcas|novo|editar|empresa|copias|usuarios|atividades|conta)(&[A-Za-z0-9_=%.\-]*)*$/', $url) ? $url : null;
}

/** O formulário de onde veio um envio (para voltar a ele). */
function formularioDeOrigem(string $acao): string
{
    $area = is_string($_GET['area'] ?? null) && isset(LOGOS[$_GET['area']]) ? $_GET['area'] : '';
    $id = is_string($_GET['id'] ?? null) && preg_match(RE_ID, $_GET['id']) ? $_GET['id'] : '';
    if ($acao === 'salvar' && $area !== '') return $id !== '' ? link_('editar', ['area' => $area, 'id' => $id]) : link_('novo', ['area' => $area]);
    if ($acao === 'empresa-salvar') return link_('empresa');
    if ($area !== '') return link_($area);
    return 'index.php';
}

// Envio maior que o limite do PHP: o servidor descarta tudo ($_POST e $_FILES vazios)
if ($post && !$_POST && !$_FILES && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
    $msg = 'O envio ficou maior do que o servidor aceita (' . mb_(limiteEnvio()) . '). Use um logo menor e tente de novo.';
    if (pedidoJson()) responderJson(['ok' => false, 'msg' => $msg], 413);
    if (usuarioLogado()) { avisar($msg, 'erro'); redirecionar(formularioDeOrigem($acao)); }
    redirecionar('index.php');
}

/* ------------------------------------------------------------------
   Nenhum usuário ainda (instalação nova): só o primeiro acesso
   ------------------------------------------------------------------ */
if (lerUsuarios() === []) {
    if ($post && $acao === 'instalar') {
        $v = ['codigo' => (string) ($_POST['codigo'] ?? ''), 'nome' => (string) ($_POST['nome'] ?? ''), 'usuario' => trim((string) ($_POST['usuario'] ?? ''))];
        if (!csrfValido()) { http_response_code(403); telaInstalar('A página ficou aberta tempo demais e expirou. Confira os dados e envie de novo.', ['nome' => $v['nome'], 'usuario' => $v['usuario']]); exit; }
        $erro = instalar($v['codigo'], cortar($v['nome'], 100), cortar($v['usuario'], 60), (string) ($_POST['senha'] ?? ''), (string) ($_POST['senha2'] ?? ''));
        if ($erro === null) {
            avisar('Pronto! Seu acesso foi criado, como Administrador. Guarde a senha num lugar seguro. Para outras pessoas usarem o painel, crie um usuário para cada uma em Usuários.');
            redirecionar('index.php');
        }
        http_response_code(bloqueioRestante() ? 429 : 422);
        telaInstalar($erro, $v);
        exit;
    }
    if ($post) redirecionar(link_('instalar'));
    codigoInstalacao();   // garante que o arquivo com o código exista
    telaInstalar();
    exit;
}

/* ------------------------------------------------------------------
   Sem login: entrar (ou "Recuperar acesso", se o dono criou o
   recuperar.txt na pasta privada, ou se não sobrou administrador ativo)
   ------------------------------------------------------------------ */
if (usuarioLogado() === null) {
    $recuperando = arquivosRecuperacao() !== [] || !instalado();
    if ($post && $acao === 'recuperar' && $recuperando) {
        $v = ['usuario' => trim((string) ($_POST['usuario'] ?? ''))];
        if (!csrfValido()) { http_response_code(403); telaRecuperar('A página ficou aberta tempo demais e expirou. Envie de novo.', $v); exit; }
        [$erro, $sobrou] = recuperarAcesso((string) ($_POST['codigo'] ?? ''), cortar($v['usuario'], 60), (string) ($_POST['senha'] ?? ''), (string) ($_POST['senha2'] ?? ''));
        if ($erro === null) {
            avisar('Pronto! O acesso foi recuperado e você já entrou como Administrador.');
            if ($sobrou) avisar('Não consegui apagar o arquivo ' . implode(' e ', $sobrou) . ' da pasta privada. Apague pelo Gerenciador de Arquivos da Hostinger.', 'erro');
            redirecionar('index.php');
        }
        http_response_code(bloqueioRestante() ? 429 : 422);
        telaRecuperar($erro, $v);
        exit;
    }
    if ($post && $acao === 'entrar') {
        $usuario = trim((string) ($_POST['usuario'] ?? ''));
        if (!csrfValido()) { http_response_code(403); telaEntrar('A página ficou aberta tempo demais e expirou. Digite a senha de novo.', $usuario); exit; }
        $voltar = destinoSeguro($_SESSION['voltar'] ?? null);   // a sessão é trocada ao entrar
        $erro = entrar(cortar($usuario, 60), (string) ($_POST['senha'] ?? ''));
        if ($erro === null) redirecionar($voltar ?? 'index.php');
        http_response_code(bloqueioRestante(cortar($usuario, 60)) > 0 ? 429 : 401);
        telaEntrar($erro, $usuario);
        exit;
    }
    if ($post) {
        if (pedidoJson()) responderJson(['ok' => false, 'login' => true, 'msg' => 'Sua sessão terminou. Recarregue a página e entre de novo.'], 401);
        $_SESSION['voltar'] = formularioDeOrigem($acao);
        avisar('Sua sessão terminou e a última ação não foi feita. Entre de novo para continuar' . (in_array($acao, ['salvar', 'empresa-salvar'], true) ? ' (o formulário precisa ser preenchido de novo).' : '.'), 'info');
        redirecionar(link_('entrar'));
    }
    if ($recuperando) {
        codigoInstalacao('recuperar');
        telaRecuperar();
        exit;
    }
    if ($acao !== 'entrar' && $acao !== 'inicio') {
        $_SESSION['voltar'] = 'index.php?' . http_build_query(array_map('strval', array_filter($_GET, 'is_string')));
        redirecionar(link_('entrar'));
    }
    telaEntrar();
    exit;
}

/* ------------------------------------------------------------------
   Logado
   ------------------------------------------------------------------ */
if (in_array($acao, ['entrar', 'instalar', 'recuperar'], true)) redirecionar('index.php');
if ($post && !csrfValido()) {
    registrar('csrf', 'pedido recusado em ' . $acao);
    if (pedidoJson()) responderJson(['ok' => false, 'msg' => 'A página ficou desatualizada. Recarregue e tente de novo.'], 403);
    paginaErro('Ação recusada', 'A página ficou desatualizada (ou o pedido não veio do painel) e nada foi alterado. Volte, recarregue a página e tente de novo.', 403);
    exit;
}
$eu = usuarioAtual();
if ($eu === null) { sair(); redirecionar(link_('entrar')); }
if (precisaCriarSenha($eu) && !in_array($acao, SEM_SENHA_PROPRIA, true)) {
    if (pedidoJson()) responderJson(['ok' => false, 'msg' => 'Antes de continuar, crie a sua senha: recarregue a página.'], 403);
    redirecionar(link_('criar-senha'));
}
if (in_array($acao, SO_ADMIN, true) && $eu['papel'] !== 'admin') {
    registrar('negado', match (true) {
        str_starts_with($acao, 'empresa') => 'Empresa', in_array($acao, ['copias', 'restaurar'], true) => 'Cópias de segurança',
        $acao === 'atividades' => 'Atividades', default => 'Usuários',
    });
    if (pedidoJson()) responderJson(['ok' => false, 'msg' => 'Esta área é só para administradores do painel.'], 403);
    paginaErro('Só para administradores', 'Esta área é só para administradores do painel. Se precisar dela, fale com um administrador.', 403);
    exit;
}
if (!$post && in_array($acao, SO_POST, true)) redirecionar('index.php');

try {
    match ($acao) {
        'inicio'      => telaInicio(),
        'clientes', 'marcas' => acaoLista($acao),
        'novo'        => acaoItemForm(areaLogos(), true),
        'editar'      => acaoItemForm(areaLogos(), false),
        'salvar'      => acaoSalvar(areaLogos()),
        'mover'       => acaoMover(areaLogos()),
        'ordem'       => acaoOrdem(areaLogos()),
        'visivel'     => acaoVisivel(areaLogos()),
        'excluir'     => acaoExcluir(areaLogos()),
        'desfazer'    => acaoDesfazer(areaLogos()),
        'empresa'        => acaoEmpresa(),
        'empresa-salvar' => acaoEmpresaSalvar(),
        'copias'      => telaCopias(is_string($_GET['area'] ?? null) && isset(AREAS[$_GET['area']]) ? $_GET['area'] : 'clientes'),
        'restaurar'   => acaoRestaurar(),
        'atividades'  => telaAtividades(),
        'conta'       => telaConta(),
        'senha'       => acaoSenha(),
        'criar-senha' => acaoCriarSenha($post),
        'sair'        => acaoSair(),
        'usuarios'         => telaUsuarios(),
        'usuario-novo'     => telaUsuarioForm(null),
        'usuario-editar'   => telaUsuarioForm(usuarioDaUrl()),
        'usuario-salvar'   => acaoUsuarioSalvar(),
        'usuario-senha'    => acaoUsuarioSenha(),
        'usuario-ativo'    => acaoUsuarioAtivo(),
        'usuario-excluir'  => acaoUsuarioExcluir(),
        'senha-temporaria' => acaoSenhaTemporaria(),
    };
} catch (ErroDados $e) {
    $area = str_starts_with($acao, 'empresa') ? 'empresa' : (is_string($_GET['area'] ?? null) && isset(AREAS[$_GET['area']]) ? $_GET['area'] : (isset(AREAS[$acao]) ? $acao : 'clientes'));
    if (pedidoJson()) responderJson(['ok' => false, 'msg' => 'Não consegui ler dados/' . AREAS[$area]['arquivo'] . ': ' . $e->getMessage()], 500);
    telaDadosIlegiveis($area, $e->getMessage());
} catch (ErroUsuario $e) {
    if (pedidoJson()) responderJson(['ok' => false, 'msg' => $e->getMessage()], 409);
    avisar($e->getMessage(), 'erro');
    redirecionar(voltarDepoisDoErro($acao));
}
exit;

/* ================================================================== */

function voltarDepoisDoErro(string $acao): string
{
    if (str_starts_with($acao, 'usuario') || $acao === 'senha-temporaria') return link_('usuarios');
    if (in_array($acao, ['copias', 'restaurar'], true)) return link_('copias');
    if (str_starts_with($acao, 'empresa')) return link_('empresa');
    if (in_array($acao, ['conta', 'senha'], true)) return link_('conta');
    $area = is_string($_GET['area'] ?? null) && isset(LOGOS[$_GET['area']]) ? $_GET['area'] : '';
    return $area !== '' ? link_($area) : 'index.php';
}

function acaoRestaurar(): void
{
    $nome = is_string($_POST['copia'] ?? null) ? $_POST['copia'] : '';
    $c = lerCopia($nome);
    $oQue = $c['area'] === 'empresa' ? 'os dados da empresa' : 'a lista de ' . strtolower(AREAS[$c['area']]['nome']);
    if (($_POST['confirmado'] ?? '') !== '1') {
        telaConfirmar('Restaurar esta cópia?', 'Voltar ' . $oQue . ' para como estava em ' . quando((int) $c['criado']) . '? O estado atual vira uma cópia, então dá para voltar atrás.',
            link_('restaurar'), ['copia' => $nome], 'Sim, restaurar', link_('copias', ['area' => $c['area']]));
        return;
    }
    restaurarCopia($nome);
    avisar('Pronto: ' . $oQue . ' voltou para como estava em ' . quando((int) $c['criado']) . '. O estado de antes ficou guardado como a cópia mais recente.', 'ok',
        ['link' => $c['area'] === 'empresa' ? link_('empresa') : link_($c['area']), 'textoLink' => 'Conferir']);
    redirecionar(link_('copias', ['area' => $c['area']]));
}

function acaoCriarSenha(bool $post): void
{
    if (!precisaCriarSenha(usuarioAtual())) redirecionar('index.php');
    if (!$post) { telaCriarSenha(); return; }
    $erro = criarSenhaPropria((string) ($_POST['nova'] ?? ''), (string) ($_POST['nova2'] ?? ''));
    if ($erro !== null) { http_response_code(422); telaCriarSenha($erro); return; }
    avisar('Pronto! Sua senha foi criada. Use ela nas próximas vezes que entrar.');
    redirecionar('index.php');
}

function acaoSenha(): void
{
    $erro = trocarSenha((string) ($_POST['atual'] ?? ''), (string) ($_POST['nova'] ?? ''), (string) ($_POST['nova2'] ?? ''));
    if ($erro !== null) {
        $bloqueado = bloqueioRestante(usuarioLogado() ?? '') > 0;
        http_response_code($bloqueado ? 429 : 422);
        if ($bloqueado) { sair(); avisar($erro, 'erro'); redirecionar(link_('entrar')); }
        telaConta($erro);
        return;
    }
    avisar('Senha trocada. Nos outros aparelhos, será preciso entrar de novo.');
    redirecionar(link_('conta'));
}

function acaoSair(): void
{
    sair();
    avisar('Você saiu do painel.', 'info');
    redirecionar(link_('entrar'));
}

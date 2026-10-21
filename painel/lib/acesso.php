<?php
/* =====================================================================
   Painel Danni Som — acesso e usuários
   Primeiro acesso com código (lido no Gerenciador de Arquivos da
   Hostinger), entrar/sair, limite de tentativas (por internet + usuário),
   expiração por inatividade, troca de senha, senha temporária de quem
   foi criado por um administrador e "Recuperar acesso" (recuperar.txt).
   Usuários (Administrador / Editor) em usuarios.php, na pasta privada:
   só o "hash" das senhas, nunca a senha.
   ===================================================================== */
declare(strict_types=1);
if (!defined('PAINEL')) { http_response_code(404); exit; }

const PAPEIS = ['admin' => 'Administrador', 'editor' => 'Editor'];
const MAX_USUARIOS = 20;
const RE_USUARIO = '/^[A-Za-z0-9._-]{3,40}$/';
const TRAVA_USUARIOS = 'usuarios';
// Hash qualquer, usado quando o usuário digitado não existe (o tempo de resposta fica igual)
const HASH_FALSO = '$2y$10$IgqsZVGiX3Cik9KrSe3Ly.dh.IECcZzg8BsGHrAFpLVjGCqhzsN.K';
const ALFABETO_CODIGO = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';   // sem 0/O e 1/I/L, que se confundem
const SENHAS_FRACAS = ['1234567890', '0123456789', '12345678910', '0987654321', 'qwertyuiop', 'asdfghjkl',
    'abcdefghij', 'senha12345', 'senha123456', 'minhasenha', 'dannisom123', 'dannisom2000', 'danni12345',
    'dannisom.com.br', 'administrador', 'admin12345', 'password12', 'rioclaro123', 'mudar12345', 'trocar12345'];
const PALAVRAS_SENHA = ['areia', 'balde', 'banco', 'barco', 'bolo', 'bota', 'cabra', 'caixa', 'cama', 'canto', 'carta',
    'cinto', 'copo', 'corda', 'dado', 'dente', 'doce', 'escada', 'faca', 'festa', 'figo', 'folha', 'fruta', 'garfo',
    'gato', 'gelo', 'ilha', 'janela', 'jarra', 'lago', 'leite', 'livro', 'manga', 'mapa', 'mesa', 'milho', 'mola',
    'navio', 'neve', 'nuvem', 'onda', 'panela', 'pato', 'pedra', 'pena', 'pera', 'pipa', 'ponte', 'porta', 'prato',
    'quadro', 'queijo', 'rampa', 'rede', 'roda', 'sapo', 'sino', 'tecla', 'telha', 'tigre', 'trigo', 'urso', 'vaso',
    'vela', 'vento', 'verde', 'zebra'];

/* ------------------------------------------------------------------
   Lista de usuários (usuarios.php)
   ------------------------------------------------------------------ */
function normalizarUsuario(array $u): ?array
{
    $login = $u['usuario'] ?? null;
    $hash = $u['hash'] ?? null;
    if (!is_string($login) || !preg_match(RE_USUARIO, $login) || !is_string($hash) || $hash === '') return null;
    $ts = static fn($v): ?int => is_int($v) && $v > 0 ? $v : null;
    $nome = limparLinha($u['nome'] ?? '') ?? '';
    return [
        'usuario'       => $login,
        'nome'          => $nome !== '' ? cortar($nome, 60) : $login,
        'papel'         => ($u['papel'] ?? '') === 'admin' ? 'admin' : 'editor',   // na dúvida, o papel com menos poder
        'hash'          => $hash,
        'ativo'         => ($u['ativo'] ?? true) !== false,
        'trocar_senha'  => ($u['trocar_senha'] ?? false) === true,
        'criado'        => $ts($u['criado'] ?? null) ?? 0,
        'ultimo_acesso' => $ts($u['ultimo_acesso'] ?? null),
    ];
}

function lerUsuarios(): array
{
    $d = lerJsonPrivado('usuarios.php');
    if (!is_array($d) || !is_array($d['usuarios'] ?? null)) return [];
    $out = [];
    foreach ($d['usuarios'] as $u) {
        if (is_array($u) && ($n = normalizarUsuario($u)) !== null) $out[] = $n;
    }
    return $out;
}

/** usuarios.php existe mas não dá para ler (editado à mão, cortado no meio…). */
function usuariosDanificado(): bool
{
    if (!@is_file(caminhoPrivado('usuarios.php'))) return false;
    $d = lerJsonPrivado('usuarios.php');
    return !is_array($d) || !is_array($d['usuarios'] ?? null);
}

/** Antes de começar uma lista nova por cima de um usuarios.php danificado, guarda o arquivo como estava. */
function guardarUsuariosDanificado(): void
{
    if (!usuariosDanificado()) return;
    $bruto = @file_get_contents(caminhoPrivado('usuarios.php'));
    if ($bruto !== false) gravarPrivado('usuarios-danificado-' . date('Ymd-His') . '.php', base64_encode($bruto));
}

/** Só dentro de comTrava(TRAVA_USUARIOS). */
function gravarUsuarios(array $lista): void
{
    gravarJsonPrivado('usuarios.php', ['versao' => 1, 'usuarios' => array_values($lista)]);
}

/** Altera a lista com a trava; $fn recebe a lista por referência. Nunca deixa o painel sem administrador ativo. */
function alterarUsuarios(callable $fn)
{
    return comTrava(TRAVA_USUARIOS, static function () use ($fn) {
        if (usuariosDanificado()) throw new RuntimeException('usuarios.php danificado: nada foi alterado');
        $lista = lerUsuarios();
        $antes = adminsAtivos($lista);
        $r = $fn($lista);
        if ($antes > 0 && adminsAtivos($lista) === 0) {
            throw new ErroUsuario('O painel precisa ter pelo menos um administrador ativo. Nada foi alterado.');
        }
        gravarUsuarios($lista);
        return $r;
    });
}

function acharUsuario(array $lista, string $login): ?int
{
    if ($login === '') return null;
    foreach ($lista as $i => $u) if (strcasecmp($u['usuario'], $login) === 0) return $i;
    return null;
}

function usuarioPorLogin(string $login): ?array
{
    $lista = lerUsuarios();
    $i = acharUsuario($lista, $login);
    return $i === null ? null : $lista[$i];
}

function adminsAtivos(array $lista): int
{
    return count(array_filter($lista, static fn(array $u) => $u['papel'] === 'admin' && $u['ativo']));
}

/** Quem está usando o painel agora (lido do arquivo a cada pedido: papel novo vale na hora). */
function usuarioAtual(): ?array
{
    static $cache = [];
    $login = usuarioLogado();
    if ($login === null) return null;
    return $cache[$login] ??= usuarioPorLogin($login);
}

function ehAdmin(): bool
{
    $eu = usuarioAtual();
    return $eu !== null && $eu['ativo'] && $eu['papel'] === 'admin';
}

function nomesDosUsuarios(): array
{
    $m = [];
    foreach (lerUsuarios() as $u) $m[strtolower($u['usuario'])] = $u['nome'];
    return $m;
}

function nomeDe(string $login, array $nomes): string
{
    return $nomes[strtolower($login)] ?? $login;
}

function novoUsuario(string $login, string $nome, string $papel, string $hash, bool $trocarSenha): array
{
    return ['usuario' => $login, 'nome' => $nome, 'papel' => $papel, 'hash' => $hash, 'ativo' => true,
        'trocar_senha' => $trocarSenha, 'criado' => time(), 'ultimo_acesso' => null];
}

function rotuloUsuario(array $u): string
{
    return $u['nome'] . ' (' . $u['usuario'] . ')';
}

/* ------------------------------------------------------------------
   Sessão
   ------------------------------------------------------------------ */
function instalado(): bool
{
    return adminsAtivos(lerUsuarios()) > 0;
}

/** Muda quando a senha muda: a sessão aberta com a senha antiga termina. */
function impressaoSenha(string $hash): string
{
    return substr(hash('sha256', 'ds-painel|' . $hash), 0, 32);
}

function usuarioLogado(): ?string
{
    return isset($_SESSION['usuario']) && is_string($_SESSION['usuario']) ? $_SESSION['usuario'] : null;
}

/** A cada pedido: encerra a sessão vencida, de senha trocada, de usuário desativado ou excluído. */
function verificarSessao(): void
{
    if (usuarioLogado() === null) return;
    $u = usuarioPorLogin((string) $_SESSION['usuario']);
    $motivo = null;
    if (time() - (int) ($_SESSION['ultimo'] ?? 0) > (int) cfg('inatividade')) {
        $motivo = 'Você ficou mais de ' . intdiv((int) cfg('inatividade'), 3600) . ' horas sem usar o painel e saiu automaticamente. Entre de novo.';
    } elseif ($u === null) {
        $motivo = 'Seu usuário não existe mais no painel. Fale com um administrador.';
    } elseif (!$u['ativo']) {
        $motivo = 'Seu acesso foi desativado por um administrador.';
    } elseif (!hash_equals(impressaoSenha($u['hash']), (string) ($_SESSION['impressao'] ?? ''))) {
        $motivo = $u['trocar_senha'] ? 'Sua senha foi redefinida por um administrador. Entre com a senha nova.' : 'A senha foi trocada. Entre de novo com a senha nova.';
    }
    if ($motivo !== null) {
        $_SESSION = [];
        session_regenerate_id(true);
        avisar($motivo, 'info');
        return;
    }
    $_SESSION['ultimo'] = time();
}

function precisaCriarSenha(?array $eu): bool
{
    return $eu !== null && $eu['trocar_senha'];
}

function abrirSessaoDe(string $usuario, string $hash): void
{
    session_regenerate_id(true);
    $_SESSION = [
        'usuario'   => $usuario,
        'entrou'    => time(),
        'ultimo'    => time(),
        'impressao' => impressaoSenha($hash),
        'csrf'      => bin2hex(random_bytes(32)),
    ];
}

function sair(): void
{
    registrar('saiu');
    $_SESSION = [];
    session_regenerate_id(true);
}

/* ------------------------------------------------------------------
   Limite de tentativas (guardado com hash: não grava o IP puro)
   Conta por internet (IP) + usuário digitado; um teto mais alto, só por
   IP, segura quem tenta em massa.
   ------------------------------------------------------------------ */
function segredoLocal(): string
{
    $s = lerPrivado('chave.php');
    if ($s !== null && strlen(trim($s)) === 64) return trim($s);
    return comTrava('chave', static function (): string {
        $s = lerPrivado('chave.php');
        if ($s !== null && strlen(trim($s)) === 64) return trim($s);
        $s = bin2hex(random_bytes(32));
        gravarPrivado('chave.php', $s);
        return $s;
    });
}

function chaveIp(): string
{
    return substr(hash_hmac('sha256', ip(), segredoLocal()), 0, 32);
}

/** Chave do usuário digitado nesta internet ('' = código do primeiro acesso / recuperar). */
function chaveTentativa(string $login): string
{
    return substr(hash_hmac('sha256', ip() . "\n" . strtolower(trim($login)), segredoLocal()), 0, 32);
}

function bloqueioDe(array $t, string $chave): int
{
    $r = $t[$chave] ?? null;
    $ate = is_array($r) ? (int) ($r['ate'] ?? 0) : 0;
    return $ate > time() ? $ate - time() : 0;
}

/** Segundos que faltam de bloqueio para este usuário nesta internet (0 = liberado). */
function bloqueioRestante(string $login = ''): int
{
    $t = lerJsonPrivado('tentativas.php', []);
    if (!is_array($t)) return 0;
    return max(bloqueioDe($t, chaveTentativa($login)), bloqueioDe($t, chaveIp()));
}

/** Conta uma falha; devolve quantas tentativas ainda restam (0 = acabou de bloquear). */
function registrarFalha(string $login = ''): int
{
    $max = (int) cfg('tentativas_max');
    $teto = max($max, (int) cfg('tentativas_max_ip'));
    $janela = (int) cfg('bloqueio');
    return comTrava('tentativas', static function () use ($login, $max, $teto, $janela): int {
        $t = lerJsonPrivado('tentativas.php', []);
        if (!is_array($t)) $t = [];
        $agora = time();
        foreach ($t as $k => $r) {   // limpa o que já venceu
            if (!is_array($r) || (($r['ate'] ?? 0) < $agora && ($r['desde'] ?? 0) + $janela < $agora)) unset($t[$k]);
        }
        $restam = PHP_INT_MAX;
        foreach ([chaveTentativa($login) => $max, chaveIp() => $teto] as $k => $limite) {
            $r = $t[$k] ?? ['n' => 0, 'desde' => $agora, 'ate' => 0];
            if ($r['ate'] > $agora) { $restam = 0; continue; }       // já bloqueado
            if ($r['ate'] || $r['desde'] + $janela < $agora) $r = ['n' => 0, 'desde' => $agora, 'ate' => 0];
            $r['n']++;
            if ($r['n'] >= $limite) $r['ate'] = $agora + $janela;
            $t[$k] = $r;
            $restam = min($restam, max(0, $limite - $r['n']));
        }
        gravarJsonPrivado('tentativas.php', $t);
        if ($restam === 0) registrar('bloqueado', $login !== '' ? 'usuário digitado: ' . cortar($login, 40) : 'código de acesso');
        return $restam;
    });
}

function limparFalhas(string $login = ''): void
{
    comTrava('tentativas', static function () use ($login): void {
        $t = lerJsonPrivado('tentativas.php', []);
        $k = chaveTentativa($login);
        if (is_array($t) && isset($t[$k])) { unset($t[$k]); gravarJsonPrivado('tentativas.php', $t); }
    });
}

function msgBloqueio(int $segundos): string
{
    $min = max(1, (int) ceil($segundos / 60));
    return 'Muitas tentativas erradas. Por segurança, o acesso ficou bloqueado por ' . $min . ($min === 1 ? ' minuto' : ' minutos')
        . '. Espere e tente de novo.';
}

function msgFalha(string $base, int $restam): string
{
    if ($restam <= 0) return msgBloqueio((int) cfg('bloqueio'));
    return $base . ($restam <= 3 ? ' ' . ($restam === 1 ? 'Resta 1 tentativa' : 'Restam ' . $restam . ' tentativas') . ' antes do bloqueio de ' . intdiv((int) cfg('bloqueio'), 60) . ' minutos.' : '');
}

/* ------------------------------------------------------------------
   Primeiro acesso / recuperar acesso: código no arquivo primeiro-acesso.php
   ------------------------------------------------------------------ */
function gerarCodigo(): string
{
    $c = '';
    for ($i = 0; $i < 12; $i++) {
        if ($i && $i % 4 === 0) $c .= '-';
        $c .= ALFABETO_CODIGO[random_int(0, strlen(ALFABETO_CODIGO) - 1)];
    }
    return $c;
}

/** Código atual (cria um novo se não houver ou se venceu). $para: 'instalar' ou 'recuperar' (muda só o texto). */
function codigoInstalacao(string $para = 'instalar'): array
{
    return comTrava('codigo', static function () use ($para): array {
        $c = lerPrivado('primeiro-acesso.php');
        if ($c !== null && preg_match('/^CODIGO=([A-Z0-9-]{14})$/m', $c, $m) && preg_match('/^CRIADO=(\d+)$/m', $c, $t)
            && (int) $t[1] + (int) cfg('codigo_validade') > time()) {
            return ['codigo' => $m[1], 'criado' => (int) $t[1]];
        }
        $codigo = gerarCodigo();
        $agora = time();
        $vale = quando($agora + (int) cfg('codigo_validade'));
        $rec = $para === 'recuperar';
        gravarPrivado('primeiro-acesso.php', implode("\n", array_merge([
            '==================================================================',
            $rec ? ' PAINEL DANNI SOM — CÓDIGO PARA RECUPERAR O ACESSO' : ' PAINEL DANNI SOM — CÓDIGO DE PRIMEIRO ACESSO',
            '==================================================================',
            '',
            '   Seu código:   ' . $codigo,
            '',
        ], $rec ? [
            ' Digite este código na tela "Recuperar acesso" do painel para criar',
            ' uma senha nova para um administrador. Vale até ' . $vale . '.',
            ' Quando o acesso for recuperado, este arquivo e o recuperar.txt',
            ' são apagados sozinhos.',
        ] : [
            ' Digite este código na tela "Primeiro acesso" do painel para criar',
            ' o seu usuário e a sua senha. Vale até ' . $vale . '.',
            ' Depois disso, basta abrir o painel de novo que um código novo é',
            ' gravado aqui. Quando o usuário for criado, este arquivo é apagado.',
        ], [
            '',
            ' Não compartilhe este código: com ele, qualquer pessoa cria a senha.',
            '',
            '------------------------------------------------------------------',
            'CODIGO=' . $codigo,
            'CRIADO=' . $agora,
            '',
        ])));
        registrar('codigo-gerado', $rec ? 'código para recuperar o acesso' : 'código de primeiro acesso', '-');
        return ['codigo' => $codigo, 'criado' => $agora];
    });
}

/** Confere o código digitado (comparação em tempo constante). Devolve o erro (e conta a falha) ou null. */
function conferirCodigo(string $digitado, string $para): ?string
{
    $atual = codigoInstalacao($para);
    $digitado = preg_replace('/[^A-Z0-9]/', '', strtoupper($digitado)) ?? '';
    if ($digitado !== '' && hash_equals(str_replace('-', '', $atual['codigo']), $digitado)) return null;
    $restam = registrarFalha();
    registrar('codigo-errado', $para === 'recuperar' ? 'recuperar o acesso' : 'primeiro acesso', '-');
    return msgFalha('Código incorreto. Confira no arquivo primeiro-acesso.php (letras maiúsculas e números).', $restam);
}

function validarUsuario(string $u): ?string
{
    if (!preg_match(RE_USUARIO, $u)) return 'Usuário: use de 3 a 40 letras sem acento, números, ponto, hífen ou sublinhado (sem espaços).';
    return null;
}

/** [nome limpo, erro ou null] */
function nomeUsuarioValido($v): array
{
    $n = limparLinha($v);
    if ($n === null) return ['', 'Nome: o texto tem caracteres inválidos. Digite de novo.'];
    if (tamanho($n) < 2) return [$n, 'Nome: escreva o nome da pessoa (pelo menos 2 letras).'];
    if (tamanho($n) > 60) return [cortar($n, 60), 'Nome: use no máximo 60 caracteres.'];
    if (preg_match('/[<>]/', $n)) return [$n, 'Nome: não use os sinais < e >.'];
    return [$n, null];
}

function validarSenhaNova(string $senha, string $repetida, string $usuario): ?string
{
    $min = (int) cfg('senha_minimo');
    if (tamanho($senha) < $min) return 'A senha precisa ter pelo menos ' . $min . ' caracteres. Dica: junte 3 ou 4 palavras que só você saiba, com um número.';
    if (strlen($senha) > 200) return 'Senha longa demais (máximo 200 caracteres).';
    $baixa = strtolower($senha);
    if (in_array($baixa, SENHAS_FRACAS, true) || preg_match('/^(.)\1+$/', $senha) || (preg_match('/^\d+$/', $senha) && count(array_unique(str_split($senha))) < 5)) {
        return 'Essa senha é fácil demais de adivinhar. Escolha outra.';
    }
    if ($usuario !== '' && strlen($usuario) >= 3 && str_contains($baixa, strtolower($usuario))) return 'A senha não pode conter o nome de usuário.';
    if (!hash_equals($senha, $repetida)) return 'As duas senhas digitadas não são iguais.';
    return null;
}

/** Cria o primeiro administrador. Devolve o erro, ou null quando deu certo (e já entra). */
function instalar(string $codigo, string $nome, string $usuario, string $senha, string $repetida): ?string
{
    if ($s = bloqueioRestante()) return msgBloqueio($s);
    if ($erro = conferirCodigo($codigo, 'instalar')) return $erro;
    [$nome, $erroNome] = nomeUsuarioValido($nome);
    if ($erroNome) return $erroNome;
    if ($erro = validarUsuario($usuario)) return $erro;
    if ($erro = validarSenhaNova($senha, $repetida, $usuario)) return $erro;

    $hash = password_hash($senha, PASSWORD_DEFAULT);
    $erro = comTrava(TRAVA_USUARIOS, static function () use ($nome, $usuario, $hash): ?string {
        if (instalado()) return 'O painel já foi configurado. Entre com o seu usuário e senha.';
        guardarUsuariosDanificado();
        $lista = usuariosDanificado() ? [] : lerUsuarios();
        if (acharUsuario($lista, $usuario) !== null) return 'Esse usuário já existe. Escolha outro.';
        $lista[] = novoUsuario($usuario, $nome, 'admin', $hash, false);
        gravarUsuarios($lista);
        @unlink(caminhoPrivado('primeiro-acesso.php'));
        return null;
    });
    if ($erro !== null) return $erro;
    limparFalhas();
    abrirSessaoDe($usuario, $hash);
    registrar('instalou', 'administrador criado: ' . $usuario);
    return null;
}

/* ------------------------------------------------------------------
   Entrar / trocar senha / criar a própria senha
   ------------------------------------------------------------------ */
function entrar(string $usuario, string $senha): ?string
{
    if ($s = bloqueioRestante($usuario)) return msgBloqueio($s);
    $lista = lerUsuarios();
    if (!$lista) return 'O painel ainda não foi configurado.';
    $i = acharUsuario($lista, $usuario);
    $u = $i === null ? null : $lista[$i];
    // Sempre confere uma senha (a falsa, se o usuário não existe): o tempo de resposta fica igual
    $senhaOk = password_verify($senha, $u ? $u['hash'] : HASH_FALSO);
    if ($u && $senhaOk) {
        if (!$u['ativo']) {
            registrar('acesso-desativado', '', $u['usuario']);
            return 'Este acesso está desativado. Fale com um administrador do painel.';
        }
        limparFalhas($usuario);
        $novoHash = password_needs_rehash($u['hash'], PASSWORD_DEFAULT) ? password_hash($senha, PASSWORD_DEFAULT) : null;
        $hash = alterarUsuarios(static function (array &$l) use ($u, $novoHash): string {
            $j = acharUsuario($l, $u['usuario']);
            if ($j === null) return $u['hash'];
            if ($novoHash !== null && hash_equals($l[$j]['hash'], $u['hash'])) $l[$j]['hash'] = $novoHash;
            $l[$j]['ultimo_acesso'] = time();
            return $l[$j]['hash'];
        });
        abrirSessaoDe($u['usuario'], $hash);
        registrar('entrou');
        return null;
    }
    usleep(random_int(250000, 600000));   // atrasa quem tenta adivinhar
    $restam = registrarFalha($usuario);
    registrar('senha-errada', 'usuário digitado: ' . cortar($usuario, 40), '-');
    return msgFalha('Usuário ou senha incorretos.', $restam);
}

function trocarSenha(string $atual, string $nova, string $repetida): ?string
{
    $login = usuarioLogado() ?? '';
    if ($s = bloqueioRestante($login)) return msgBloqueio($s);
    $eu = usuarioAtual();
    if (!$eu || !password_verify($atual, $eu['hash'])) {
        $restam = registrarFalha($login);
        registrar('senha-errada', 'senha atual errada ao tentar trocar');
        return msgFalha('A senha atual não confere.', $restam);
    }
    if ($erro = validarSenhaNova($nova, $repetida, $eu['usuario'])) return $erro;
    if (hash_equals($atual, $nova)) return 'A senha nova precisa ser diferente da atual.';
    $hash = gravarSenhaPropria($eu, password_hash($nova, PASSWORD_DEFAULT));
    abrirSessaoDe($eu['usuario'], $hash);   // novo ID de sessão; outros aparelhos saem
    registrar('trocou-senha');
    return null;
}

/** Quem entrou com a senha temporária cria a própria senha. */
function criarSenhaPropria(string $nova, string $repetida): ?string
{
    $eu = usuarioAtual();
    if (!$eu) return 'Sua sessão terminou. Entre de novo.';
    if ($erro = validarSenhaNova($nova, $repetida, $eu['usuario'])) return $erro;
    if (password_verify($nova, $eu['hash'])) return 'A senha nova precisa ser diferente da senha temporária.';
    $hash = gravarSenhaPropria($eu, password_hash($nova, PASSWORD_DEFAULT));
    abrirSessaoDe($eu['usuario'], $hash);
    registrar('criou-senha');
    return null;
}

function gravarSenhaPropria(array $eu, string $hash): string
{
    alterarUsuarios(static function (array &$l) use ($eu, $hash): void {
        $i = acharUsuario($l, $eu['usuario']);
        if ($i === null) throw new ErroUsuario('Seu usuário não existe mais. Fale com um administrador.');
        $l[$i]['hash'] = $hash;
        $l[$i]['trocar_senha'] = false;
    });
    return $hash;
}

/* ------------------------------------------------------------------
   Recuperar acesso (último recurso de quem entra no hPanel)
   Um arquivo "recuperar.txt" na pasta privada troca a tela de entrar
   pela tela "Recuperar acesso", que usa o código de primeiro-acesso.php.
   ------------------------------------------------------------------ */
function arquivosRecuperacao(): array
{
    $dir = pastaPrivada();
    $achados = [];
    foreach (@scandir($dir) ?: [] as $f) {
        if (in_array(strtolower($f), ['recuperar.txt', 'recuperar'], true) && @is_file($dir . '/' . $f)) $achados[] = $f;
    }
    return $achados;
}

/** Devolve [erro ou null, arquivos que não deu para apagar]. Quando dá certo, já entra. */
function recuperarAcesso(string $codigo, string $usuario, string $senha, string $repetida): array
{
    if ($s = bloqueioRestante()) return [msgBloqueio($s), []];
    if ($erro = conferirCodigo($codigo, 'recuperar')) return [$erro, []];
    if ($erro = validarUsuario($usuario)) return [$erro, []];
    if ($erro = validarSenhaNova($senha, $repetida, $usuario)) return [$erro, []];

    $hash = password_hash($senha, PASSWORD_DEFAULT);
    $r = comTrava(TRAVA_USUARIOS, static function () use ($usuario, $hash): array {
        guardarUsuariosDanificado();
        $lista = usuariosDanificado() ? [] : lerUsuarios();
        $i = acharUsuario($lista, $usuario);
        if ($i !== null && $lista[$i]['papel'] !== 'admin') {
            return ['erro' => '“' . $lista[$i]['usuario'] . '” é Editor. Digite o usuário de um administrador, ou um usuário novo (ele é criado como administrador).'];
        }
        if ($i === null && count($lista) >= MAX_USUARIOS) return ['erro' => 'O painel já tem ' . MAX_USUARIOS . ' usuários. Digite o usuário de um administrador que já existe.'];
        if ($i !== null) {
            $lista[$i]['hash'] = $hash;
            $lista[$i]['ativo'] = true;
            $lista[$i]['trocar_senha'] = false;
            $login = $lista[$i]['usuario'];
        } else {
            $lista[] = novoUsuario($usuario, $usuario, 'admin', $hash, false);
            $login = $usuario;
        }
        gravarUsuarios($lista);
        @unlink(caminhoPrivado('primeiro-acesso.php'));
        return ['login' => $login, 'novo' => $i === null];
    });
    if (isset($r['erro'])) return [$r['erro'], []];
    $sobrou = [];
    foreach (arquivosRecuperacao() as $f) {
        if (!@unlink(pastaPrivada() . '/' . $f)) $sobrou[] = $f;
    }
    limparFalhas();
    abrirSessaoDe($r['login'], $hash);
    registrar('recuperou-acesso', ($r['novo'] ? 'administrador novo: ' : 'senha nova para o administrador ') . $r['login']);
    return [null, $sobrou];
}

/* ------------------------------------------------------------------
   Usuários (só administradores; telas em lib/telas.php)
   ------------------------------------------------------------------ */
/** Três palavras e um número (ex.: prato-verde-tecla-47): fácil de ditar. */
function gerarSenhaTemporaria(): string
{
    $p = [];
    while (count($p) < 3) {
        $w = PALAVRAS_SENHA[random_int(0, count(PALAVRAS_SENHA) - 1)];
        if (!in_array($w, $p, true)) $p[] = $w;
    }
    return implode('-', $p) . '-' . random_int(10, 99);
}

function usuarioDaUrl(string $fonte = 'get'): array
{
    $login = $fonte === 'get' ? ($_GET['u'] ?? '') : ($_POST['u'] ?? '');
    $u = is_string($login) && preg_match(RE_USUARIO, $login) ? usuarioPorLogin($login) : null;
    if ($u === null) throw new ErroUsuario('Usuário não encontrado. Ele pode ter sido excluído.');
    return $u;
}

function ehVoce(array $u): bool
{
    return strcasecmp($u['usuario'], (string) usuarioLogado()) === 0;
}

/** Dentro da trava: quem pediu ainda é administrador ativo? */
function exigirAdminNaLista(array $lista): void
{
    $i = acharUsuario($lista, (string) usuarioLogado());
    if ($i === null || !$lista[$i]['ativo'] || $lista[$i]['papel'] !== 'admin') throw new ErroUsuario('Esta área é só para administradores do painel.');
}

/** Guarda a senha temporária para aparecer UMA vez na próxima tela (nunca vai para o registro). */
function mostrarSenhaUmaVez(array $u, string $senha, bool $novo): never
{
    $_SESSION['senha_temporaria'] = ['usuario' => $u['usuario'], 'nome' => $u['nome'], 'papel' => $u['papel'],
        'ativo' => $u['ativo'], 'senha' => $senha, 'novo' => $novo, 'quando' => time()];
    redirecionar(link_('senha-temporaria'));
}

function acaoSenhaTemporaria(): void
{
    $f = $_SESSION['senha_temporaria'] ?? null;
    unset($_SESSION['senha_temporaria']);   // aparece uma vez só
    if (!is_array($f) || !is_string($f['senha'] ?? null) || time() - (int) ($f['quando'] ?? 0) > 900) {
        avisar('A senha temporária aparece uma vez só. Se ela se perdeu, use “Redefinir senha” de novo.', 'info');
        redirecionar(link_('usuarios'));
    }
    telaSenhaTemporaria($f);
}

/** Cria (sem ?u=) ou edita (com ?u=login) nome e papel. */
function acaoUsuarioSalvar(): void
{
    $atual = isset($_GET['u']) ? usuarioDaUrl() : null;
    [$nome, $erroNome] = nomeUsuarioValido($_POST['nome'] ?? '');
    $papel = is_string($_POST['papel'] ?? null) && isset(PAPEIS[$_POST['papel']]) ? $_POST['papel'] : '';
    $login = $atual ? $atual['usuario'] : trim(is_string($_POST['usuario'] ?? null) ? $_POST['usuario'] : '');
    $erros = [];
    if ($erroNome !== null) $erros['nome'] = $erroNome;
    if (!$atual) {
        $lista = lerUsuarios();
        if ($e = validarUsuario($login)) $erros['usuario'] = $e;
        elseif (acharUsuario($lista, $login) !== null) $erros['usuario'] = 'Usuário: “' . $login . '” já existe. Escolha outro.';
        if (count($lista) >= MAX_USUARIOS) $erros['geral'] = 'O painel já tem ' . MAX_USUARIOS . ' usuários, o máximo. Exclua quem não usa mais.';
    }
    if ($papel === '') $erros['papel'] = 'Papel: escolha Editor ou Administrador.';
    elseif ($atual && ehVoce($atual) && $papel !== $atual['papel']) $erros['papel'] = 'Você não pode mudar o seu próprio papel. Peça a outro administrador.';
    $dados = ['usuario' => $login, 'nome' => $nome, 'papel' => $papel];
    if ($erros) {
        http_response_code(422);
        telaUsuarioForm($atual, $dados, $erros);
        return;
    }

    if (!$atual) {
        $senha = gerarSenhaTemporaria();
        $hash = password_hash($senha, PASSWORD_DEFAULT);
        $novo = alterarUsuarios(static function (array &$l) use ($login, $nome, $papel, $hash): array {
            exigirAdminNaLista($l);
            if (acharUsuario($l, $login) !== null) throw new ErroUsuario('Usuário: “' . $login . '” já existe. Escolha outro.');
            if (count($l) >= MAX_USUARIOS) throw new ErroUsuario('O painel já tem ' . MAX_USUARIOS . ' usuários, o máximo.');
            $u = novoUsuario($login, $nome, $papel, $hash, true);
            $l[] = $u;
            return $u;
        });
        registrar('usuario', 'Criou o usuário ' . rotuloUsuario($novo) . ' como ' . PAPEIS[$papel]);
        mostrarSenhaUmaVez($novo, $senha, true);
    }

    $mudou = [];
    $depois = alterarUsuarios(static function (array &$l) use ($atual, $nome, $papel, &$mudou): array {
        exigirAdminNaLista($l);
        $i = acharUsuario($l, $atual['usuario']);
        if ($i === null) throw new ErroUsuario('Esse usuário foi excluído enquanto você editava.');
        if ($l[$i]['nome'] !== $nome) $mudou[] = 'nome “' . $l[$i]['nome'] . '” → “' . $nome . '”';
        if ($l[$i]['papel'] !== $papel) $mudou[] = 'papel ' . PAPEIS[$l[$i]['papel']] . ' → ' . PAPEIS[$papel];
        $l[$i]['nome'] = $nome;
        $l[$i]['papel'] = $papel;
        return $l[$i];
    });
    if ($mudou) {
        registrar('usuario', 'Editou o usuário ' . rotuloUsuario($depois) . ': ' . implode('; ', $mudou));
        avisar($depois['nome'] . ' foi salvo.' . ($papel !== $atual['papel'] ? ' O papel novo (' . PAPEIS[$papel] . ') já vale.' : ''));
    } else {
        avisar('Nada mudou em ' . $depois['nome'] . '.', 'info');
    }
    redirecionar(link_('usuarios') . '#u-' . $depois['usuario']);
}

/** Redefinir senha: nova senha temporária; a antiga para de funcionar e a sessão da pessoa termina. */
function acaoUsuarioSenha(): void
{
    $u = usuarioDaUrl('post');
    if (ehVoce($u)) throw new ErroUsuario('Para trocar a sua própria senha, use Minha conta.');
    if (($_POST['confirmado'] ?? '') !== '1') {
        telaConfirmar('Redefinir a senha?', 'Criar uma senha temporária nova para ' . rotuloUsuario($u) . '? A senha atual para de funcionar na hora.',
            link_('usuario-senha'), ['u' => $u['usuario']], 'Sim, redefinir', link_('usuarios'));
        return;
    }
    $senha = gerarSenhaTemporaria();
    $hash = password_hash($senha, PASSWORD_DEFAULT);
    $depois = alterarUsuarios(static function (array &$l) use ($u, $hash): array {
        exigirAdminNaLista($l);
        $i = acharUsuario($l, $u['usuario']);
        if ($i === null) throw new ErroUsuario('Esse usuário já tinha sido excluído.');
        $l[$i]['hash'] = $hash;
        $l[$i]['trocar_senha'] = true;
        return $l[$i];
    });
    registrar('usuario', 'Redefiniu a senha de ' . rotuloUsuario($depois));
    mostrarSenhaUmaVez($depois, $senha, false);
}

/** Desativar (valor=0) ou reativar (valor=1). */
function acaoUsuarioAtivo(): void
{
    $u = usuarioDaUrl('post');
    $ativar = ($_POST['valor'] ?? '') === '1';
    if (ehVoce($u)) throw new ErroUsuario('Você não pode desativar o seu próprio acesso.');
    $depois = alterarUsuarios(static function (array &$l) use ($u, $ativar): array {
        exigirAdminNaLista($l);
        $i = acharUsuario($l, $u['usuario']);
        if ($i === null) throw new ErroUsuario('Esse usuário foi excluído.');
        $l[$i]['ativo'] = $ativar;
        return $l[$i];
    });
    registrar('usuario', ($ativar ? 'Reativou' : 'Desativou') . ' o acesso de ' . rotuloUsuario($depois));
    avisar($ativar ? $depois['nome'] . ' pode entrar de novo.' : 'O acesso de ' . $depois['nome'] . ' foi desativado. Se a pessoa estava com o painel aberto, saiu na hora.');
    redirecionar(link_('usuarios') . '#u-' . $depois['usuario']);
}

function acaoUsuarioExcluir(): void
{
    $u = usuarioDaUrl('post');
    if (ehVoce($u)) throw new ErroUsuario('Você não pode excluir o seu próprio usuário.');
    if (($_POST['confirmado'] ?? '') !== '1') {
        telaConfirmar('Excluir usuário?', textoExcluirUsuario($u), link_('usuario-excluir'), ['u' => $u['usuario']], 'Sim, excluir', link_('usuarios'));
        return;
    }
    $removido = alterarUsuarios(static function (array &$l) use ($u): array {
        exigirAdminNaLista($l);
        $i = acharUsuario($l, $u['usuario']);
        if ($i === null) throw new ErroUsuario('Esse usuário já tinha sido excluído.');
        $x = $l[$i];
        array_splice($l, $i, 1);
        return $x;
    });
    registrar('usuario', 'Excluiu o usuário ' . rotuloUsuario($removido));
    avisar('O usuário ' . rotuloUsuario($removido) . ' foi excluído. O que a pessoa fez continua registrado em Atividades.');
    redirecionar(link_('usuarios'));
}

function textoExcluirUsuario(array $u): string
{
    return 'Excluir o usuário ' . rotuloUsuario($u) . '? A pessoa perde o acesso na hora. Para só suspender, use Desativar.';
}

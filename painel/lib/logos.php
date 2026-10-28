<?php
/* =====================================================================
   Painel Danni Som — Clientes e Marcas (listas de logos do site)
   dados/clientes.js: {id, nome, segmento, logo, w, h, fundo?, visivel}
   dados/marcas.js:   {id, nome, categoria, logo, cor?, visivel}
   A ordem da lista é a ordem no site. Campos que o painel não conhece
   ficam como estão. Telas em lib/telas.php.
   ===================================================================== */
declare(strict_types=1);
if (!defined('PAINEL')) { http_response_code(404); exit; }

// Os mesmos nomes de assets/js/core.js do site
const SEGMENTOS = ['saude' => 'Saúde', 'educacao' => 'Educação', 'industria' => 'Indústria', 'igrejas' => 'Instituições religiosas',
    'comercio' => 'Comércio e serviços', 'automotivo' => 'Automotivo', 'publico' => 'Serviços públicos'];
const CATEGORIAS = ['som-automotivo' => 'Som automotivo', 'sonorizacao' => 'Sonorização e áudio', 'imagem' => 'Imagem e vídeo',
    'seguranca' => 'Segurança e CFTV', 'iluminacao' => 'Iluminação', 'estetica' => 'Estética automotiva',
    'eletrica' => 'Materiais elétricos', 'ferramentas' => 'Ferramentas'];
const LOGOS = [
    'clientes' => ['campo' => 'segmento', 'grupos' => SEGMENTOS, 'rotuloGrupos' => 'Segmentos', 'um' => 'cliente', 'o' => 'o',
        'titulo' => 'Clientes', 'novo' => 'Novo cliente', 'site' => 'clientes.html'],
    'marcas'   => ['campo' => 'categoria', 'grupos' => CATEGORIAS, 'rotuloGrupos' => 'Categorias', 'um' => 'marca', 'o' => 'a',
        'titulo' => 'Marcas', 'novo' => 'Nova marca', 'site' => 'loja.html#rack-loja'],
];
const LIMITE_NOME = 80;
const MAX_ITENS = 300;

function areaLogos(): string
{
    $a = $_GET['area'] ?? $_POST['area'] ?? '';
    if (!is_string($a) || !isset(LOGOS[$a])) throw new ErroUsuario('Área inválida.');
    return $a;
}

/** "o cliente “Unimed”" / "a marca “JBL”" */
function oItem(string $area, string $nome): string
{
    return LOGOS[$area]['o'] . ' ' . LOGOS[$area]['um'] . ' “' . $nome . '”';
}

function indicePorId(array $lista, string $id): ?int
{
    foreach ($lista as $i => $r) if (($r['id'] ?? null) === $id) return $i;
    return null;
}

/** [posição, item] pelo id do endereço (GET) ou do formulário (POST). */
function itemDaUrl(array $lista, string $area, string $fonte = 'get'): array
{
    $id = $fonte === 'get' ? ($_GET['id'] ?? '') : ($_POST['id'] ?? '');
    $i = is_string($id) && preg_match(RE_ID, $id) ? indicePorId($lista, $id) : null;
    if ($i === null) throw new ErroUsuario(ucfirst(LOGOS[$area]['o']) . ' ' . LOGOS[$area]['um'] . ' não foi encontrad' . LOGOS[$area]['o'] . '. Pode ter sido excluíd' . LOGOS[$area]['o'] . ' em outro aparelho.');
    return [$i, $lista[$i]];
}

function gruposDe(array $r, string $area): array
{
    $v = $r[LOGOS[$area]['campo']] ?? '';
    return array_values(array_filter(explode(' ', is_string($v) ? $v : ''), static fn($k) => isset(LOGOS[$area]['grupos'][$k])));
}

function textoGrupos(array $r, string $area): string
{
    return implode(' · ', array_map(static fn($k) => LOGOS[$area]['grupos'][$k], gruposDe($r, $area)));
}

function visivel(array $r): bool
{
    return ($r['visivel'] ?? true) !== false;
}

function idUnico(string $base, array $lista): string
{
    $base = $base !== '' ? $base : 'item';
    $usados = array_fill_keys(array_map(static fn($r) => (string) ($r['id'] ?? ''), $lista), true);
    $id = $base;
    for ($n = 2; isset($usados[$id]); $n++) $id = rtrim(substr($base, 0, 36), '-') . '-' . $n;
    return $id;
}

function linkLista(string $area, string $id = ''): string
{
    return link_($area) . ($id !== '' ? '#i-' . $id : '');
}

function linkSiteArea(string $area): array
{
    return ['link' => cfg('url_site') . LOGOS[$area]['site'], 'textoLink' => 'Ver no site', 'externo' => true];
}

/* ------------------------------------------------------------------
   Telas
   ------------------------------------------------------------------ */
function acaoLista(string $area): void
{
    telaLista($area, lerArea($area));
}

function acaoItemForm(string $area, bool $novo): void
{
    $dados = lerArea($area);
    if ($novo) { telaItem($area, $dados, null, []); return; }
    [, $r] = itemDaUrl($dados['dados'], $area);
    telaItem($area, $dados, $r, []);
}

/* ------------------------------------------------------------------
   Salvar (novo ou editar)
   ------------------------------------------------------------------ */
function acaoSalvar(string $area): void
{
    $L = LOGOS[$area];
    $dados = lerArea($area);
    $lista = $dados['dados'];
    $atual = null;
    if (isset($_GET['id'])) [, $atual] = itemDaUrl($lista, $area);

    $erros = [];
    $nome = limparLinha($_POST['nome'] ?? '');
    if ($nome === null) { $nome = ''; $erros['nome'] = 'Nome: há caracteres inválidos. Apague e digite de novo.'; }
    elseif ($nome === '') $erros['nome'] = 'Nome: escreva o nome d' . $L['o'] . ' ' . $L['um'] . '.';
    elseif (tamanho($nome) > LIMITE_NOME) $erros['nome'] = 'Nome: no máximo ' . LIMITE_NOME . ' caracteres (tem ' . tamanho($nome) . ').';
    elseif (preg_match('/[<>]/', $nome)) $erros['nome'] = 'Nome: não use os sinais < e >.';
    else {
        $chave = slug($nome, 200);
        foreach ($lista as $r) {
            if ($atual !== null && $r['id'] === $atual['id']) continue;
            if (slug((string) $r['nome'], 200) === $chave) { $erros['nome'] = 'Nome: já existe ' . oItem($area, (string) $r['nome']) . '.'; break; }
        }
    }
    $marcados = is_array($_POST['grupos'] ?? null) ? array_filter($_POST['grupos'], 'is_string') : [];
    $grupos = array_values(array_filter(array_keys($L['grupos']), static fn($k) => in_array($k, $marcados, true)));
    if (!$grupos) $erros['grupos'] = $L['rotuloGrupos'] . ': marque pelo menos um (é onde ' . $L['o'] . ' ' . $L['um'] . ' aparece no site).';
    $opc = [
        'visivel'  => ($_POST['visivel'] ?? '') === '1',
        'escuro'   => $area === 'clientes' && ($_POST['escuro'] ?? '') === '1',
        'sem_logo' => $area === 'clientes' && ($_POST['sem_logo'] ?? '') === '1',
        'cor'      => $area === 'marcas' && ($_POST['cor'] ?? '') === '1',
    ];
    $opc['remover_fundo'] = $area === 'marcas' && !$opc['cor'] && ($_POST['remover_fundo'] ?? '') === '1';
    $id = $atual ? (string) $atual['id'] : idUnico(slug($nome) ?: $L['um'], $lista);
    $logoAtual = (string) ($atual['logo'] ?? '');

    // Logo
    $novo = null;
    $tmp = null;
    try { $tmp = arquivoEnviado('logo'); } catch (ErroImagem $e) { $erros['logo'] = $e->getMessage(); }
    $origem = $tmp;
    // Marca: "remover fundo claro" sem arquivo novo refaz o logo atual
    if ($origem === null && $opc['remover_fundo'] && ($arq = arquivoLogo($logoAtual)) !== null && @is_file($arq)) $origem = $arq;
    if ($area === 'marcas' && $origem === null && $logoAtual === '' && !isset($erros['logo'])) $erros['logo'] = 'Escolha o arquivo do logo (PNG, JPG ou WebP).';
    if ($origem !== null && !$erros) {
        try {
            $novo = processarLogo($origem, [
                'pasta' => $area, 'id' => $id,
                'caixa' => (array) cfg($area === 'clientes' ? 'caixa_cliente' : 'caixa_marca'),
                'margem' => (float) cfg($area === 'clientes' ? 'margem_cliente' : 'margem_marca'),
                'remover_fundo' => $opc['remover_fundo'],
                'exigir_transparencia' => $area === 'marcas' && !$opc['cor'],
            ]);
        } catch (ErroImagem $e) {
            $erros['logo'] = $e->getMessage();
        }
    } elseif ($tmp !== null && $erros && !isset($erros['logo'])) {
        $erros['logo'] = 'Corrija os outros campos e escolha o logo de novo (por segurança, o navegador não guarda o arquivo escolhido).';
    }
    // Marca que deixa de ser colorida: o logo atual precisa ter fundo transparente
    if (!$erros && $novo === null && $area === 'marcas' && !$opc['cor'] && !empty($atual['cor']) && ($arq = arquivoLogo($logoAtual)) !== null && @is_file($arq)) {
        try {
            if (parteTransparente(abrirLogo($arq)) < 0.02) $erros['logo'] = 'O logo atual não tem fundo transparente: sem o quadro branco ele viraria um retângulo. Marque “Remover fundo claro” (o painel refaz o logo) ou escolha outro arquivo.';
        } catch (ErroImagem $e) { /* logo atual ilegível: segue */ }
    }

    // Monta o item (ao editar, os campos que o painel não conhece ficam como estão)
    $r = $atual ?? ['id' => $id, 'nome' => '', $L['campo'] => '', 'logo' => '', 'visivel' => true];
    $r['nome'] = $nome;
    $r[$L['campo']] = implode(' ', $grupos);
    if ($novo) {
        $r['logo'] = $novo['logo'];
        if ($area === 'clientes') { $r['w'] = $novo['w']; $r['h'] = $novo['h']; }
    } elseif ($opc['sem_logo']) {
        $r['logo'] = '';
        unset($r['w'], $r['h']);
    }
    if ($area === 'clientes') {
        if ($opc['escuro']) $r['fundo'] = 'escuro'; else unset($r['fundo']);
    } else {
        if ($opc['cor']) $r['cor'] = true; else unset($r['cor']);
    }
    $r['visivel'] = $opc['visivel'];

    if ($erros) {
        descartarLogo($novo);
        http_response_code(422);
        telaItem($area, $dados, $atual, $erros, $r + ['_remover_fundo' => $opc['remover_fundo']]);
        return;
    }
    $acao = $atual
        ? ['tipo' => 'editar', 'antes' => 'Antes de: editar ' . oItem($area, (string) $atual['nome']), 'feito' => 'Editou ' . oItem($area, $nome) . ($novo ? ' (logo novo)' : ''), 'alvo' => $id, 'alvoNome' => $nome]
        : ['tipo' => 'cadastrar', 'antes' => 'Antes de: cadastrar ' . oItem($area, $nome), 'feito' => 'Cadastrou ' . oItem($area, $nome), 'alvo' => $id, 'alvoNome' => $nome];
    try {
        $res = alterarArea($area, $acao, static function (array $l) use ($r, $atual, $area): array {
            $i = indicePorId($l, (string) $r['id']);
            if ($atual !== null) {
                if ($i === null) throw new ErroUsuario('Ess' . (LOGOS[$area]['o'] === 'a' ? 'a' : 'e') . ' ' . LOGOS[$area]['um'] . ' foi excluíd' . LOGOS[$area]['o'] . ' (talvez em outro aparelho) enquanto você editava.');
                $l[$i] = $r;
            } else {
                if ($i !== null) $r['id'] = idUnico((string) $r['id'], $l);
                if (count($l) >= MAX_ITENS) throw new ErroUsuario('A lista chegou a ' . MAX_ITENS . ' itens, o máximo do painel.');
                $l[] = $r;
            }
            return $l;
        });
    } catch (ErroUsuario $e) {
        descartarLogo($novo);
        http_response_code(409);
        telaItem($area, $dados, $atual, ['geral' => $e->getMessage()], $r);
        return;
    } catch (Throwable $t) {
        descartarLogo($novo);
        throw $t;
    }
    $final = end($res['dados']);
    $idFinal = $atual ? $id : (string) $final['id'];
    $msg = '“' . $nome . '” ' . ($atual ? 'foi salv' . $L['o'] . '.' : 'foi cadastrad' . $L['o'] . '. Entrou no fim da lista: arraste ou use as setas para mudar a posição.');
    if (!$opc['visivel']) $msg .= ' Está oculto: não aparece no site.';
    avisar($msg, 'ok', $opc['visivel'] ? linkSiteArea($area) : []);
    if ($novo && $novo['claro'] && $area === 'clientes' && !$opc['escuro']) {
        avisar('Atenção: o logo parece ser branco. No quadro branco do site ele quase não aparece: edite e marque “Logo branco (quadro escuro)”.', 'erro');
    }
    redirecionar(linkLista($area, $idFinal));
}

/* ------------------------------------------------------------------
   Ordem, mostrar/ocultar, excluir, desfazer
   ------------------------------------------------------------------ */
function respostaOuVolta(string $area, string $msg, string $id, array $extra = []): never
{
    if (pedidoJson()) responderJson(['ok' => true, 'msg' => $msg] + $extra);
    avisar($msg);
    redirecionar(linkLista($area, $id));
}

/** ↑ ↓: troca de lugar com o vizinho. */
function acaoMover(string $area): void
{
    $dir = ($_POST['dir'] ?? '') === 'cima' ? 'cima' : 'baixo';
    [, $r] = itemDaUrl(lerArea($area)['dados'], $area, 'post');
    $nome = (string) $r['nome'];
    $pos = 0;
    alterarArea($area, ['tipo' => 'ordem', 'antes' => 'Antes de: mudar a ordem d' . LOGOS[$area]['o'] . 's ' . strtolower(LOGOS[$area]['titulo']), 'feito' => 'Mudou a ordem d' . oItem($area, $nome)],
        static function (array $l) use ($r, $dir, $nome, &$pos): array {
            $i = indicePorId($l, (string) $r['id']);
            if ($i === null) throw new ErroUsuario('Não encontrei “' . $nome . '”. Recarregue a página.');
            $k = $dir === 'cima' ? $i - 1 : $i + 1;
            if ($k < 0 || $k >= count($l)) throw new ErroUsuario('“' . $nome . '” já é o ' . ($dir === 'cima' ? 'primeiro' : 'último') . ' da lista.');
            [$l[$i], $l[$k]] = [$l[$k], $l[$i]];
            $pos = $k + 1;
            return $l;
        });
    respostaOuVolta($area, '“' . $nome . '” ' . ($dir === 'cima' ? 'subiu' : 'desceu') . ' para a ' . $pos . 'ª posição.', (string) $r['id'], ['pos' => $pos]);
}

/** Arrastar e soltar: a lista inteira na ordem nova (ids separados por vírgula). */
function acaoOrdem(string $area): void
{
    $ids = is_string($_POST['ids'] ?? null) ? explode(',', $_POST['ids']) : [];
    $ids = array_values(array_filter($ids, static fn($x) => preg_match(RE_ID, $x) === 1));
    $mudou = alterarArea($area, ['tipo' => 'ordem', 'antes' => 'Antes de: mudar a ordem d' . LOGOS[$area]['o'] . 's ' . strtolower(LOGOS[$area]['titulo']), 'feito' => 'Mudou a ordem d' . LOGOS[$area]['o'] . 's ' . strtolower(LOGOS[$area]['titulo'])],
        static function (array $l) use ($ids): array {
            $atuais = array_map(static fn($r) => (string) $r['id'], $l);
            $a = $atuais; $b = $ids;
            sort($a); sort($b);
            if ($a !== $b) throw new ErroUsuario('A lista mudou em outro aparelho. Recarregue a página e arraste de novo.');
            $porId = array_combine($atuais, $l);
            return array_map(static fn($id) => $porId[$id], $ids);
        })['mudou'];
    respostaOuVolta($area, $mudou ? 'Ordem salva. O site já mostra assim.' : 'A ordem não mudou.', '');
}

function acaoVisivel(string $area): void
{
    $valor = ($_POST['valor'] ?? '') === '1';
    [, $r] = itemDaUrl(lerArea($area)['dados'], $area, 'post');
    $nome = (string) $r['nome'];
    alterarArea($area, ['tipo' => 'visivel', 'antes' => 'Antes de: ' . ($valor ? 'mostrar ' : 'ocultar ') . oItem($area, $nome), 'feito' => ($valor ? 'Mostrou ' : 'Ocultou ') . oItem($area, $nome) . ($valor ? ' no site' : ' do site')],
        static function (array $l) use ($r, $valor, $nome): array {
            $i = indicePorId($l, (string) $r['id']);
            if ($i === null) throw new ErroUsuario('Não encontrei “' . $nome . '”. Recarregue a página.');
            $l[$i]['visivel'] = $valor;
            return $l;
        });
    respostaOuVolta($area, $valor ? '“' . $nome . '” voltou a aparecer no site.' : '“' . $nome . '” foi ocultad' . LOGOS[$area]['o'] . ': continua guardad' . LOGOS[$area]['o'] . ' aqui, mas não aparece no site.', (string) $r['id'], ['visivel' => $valor]);
}

function textoExcluir(string $area, array $r): string
{
    return 'Excluir ' . oItem($area, (string) $r['nome']) . '? Sai do site na hora. Logo depois aparece o botão Desfazer, e os administradores ainda podem trazer de volta em Cópias de segurança.';
}

function acaoExcluir(string $area): void
{
    [$i, $r] = itemDaUrl(lerArea($area)['dados'], $area, 'post');
    $nome = (string) $r['nome'];
    if (($_POST['confirmado'] ?? '') !== '1') {
        telaConfirmar('Excluir?', textoExcluir($area, $r), link_('excluir', ['area' => $area]), ['id' => (string) $r['id']], 'Sim, excluir', linkLista($area, (string) $r['id']));
        return;
    }
    $res = alterarArea($area, ['tipo' => 'excluir', 'antes' => 'Antes de: excluir ' . oItem($area, $nome), 'feito' => 'Excluiu ' . oItem($area, $nome), 'alvo' => (string) $r['id'], 'alvoNome' => $nome],
        static function (array $l) use ($r): array {
            $j = indicePorId($l, (string) $r['id']);
            if ($j === null) throw new ErroUsuario('Esse item já tinha sido excluído.');
            array_splice($l, $j, 1);
            return $l;
        });
    if ($res['copia']) {
        guardarDesfazer($res['copia']);
        avisar('“' . $nome . '” foi excluíd' . LOGOS[$area]['o'] . '.', 'ok',
            ['form' => ['acao' => link_('desfazer', ['area' => $area]), 'campos' => ['copia' => $res['copia'], 'id' => (string) $r['id']], 'botao' => 'Desfazer']]);
    }
    redirecionar(linkLista($area));
}

/** O "Desfazer" vale uma vez, nesta sessão, para quem excluiu (administradores: sempre, pelas cópias). */
function guardarDesfazer(string $copia): void
{
    $d = is_array($_SESSION['desfazer'] ?? null) ? $_SESSION['desfazer'] : [];
    $d[$copia] = time();
    $_SESSION['desfazer'] = array_slice($d, -20, null, true);
}

/** Devolve à lista um item excluído, no mesmo lugar e com o logo (que volta da lixeira). */
function acaoDesfazer(string $area): void
{
    $nomeCopia = is_string($_POST['copia'] ?? null) ? $_POST['copia'] : '';
    $id = is_string($_POST['id'] ?? null) && preg_match(RE_ID, $_POST['id']) ? $_POST['id'] : '';
    $copia = lerCopia($nomeCopia);
    if ($copia['area'] !== $area) throw new ErroUsuario('Essa cópia não é desta lista.');
    if (!ehAdmin()) {
        if (!isset($_SESSION['desfazer'][$nomeCopia]) || ($copia['tipo'] ?? '') !== 'excluir' || ($copia['alvo'] ?? '') !== $id
            || strcasecmp((string) ($copia['usuario'] ?? ''), (string) usuarioLogado()) !== 0) {
            throw new ErroUsuario('Esse Desfazer já foi usado ou expirou. Para trazer o item de volta, fale com um administrador (Cópias de segurança).');
        }
    }
    $antiga = $copia['dados'];
    $k = indicePorId($antiga, $id);
    if ($k === null) throw new ErroUsuario('Esse item não está nessa cópia de segurança.');
    $r = $antiga[$k];
    $nome = (string) $r['nome'];
    $pos = 0;
    alterarArea($area, ['tipo' => 'desfazer', 'antes' => 'Antes de: trazer de volta ' . oItem($area, $nome), 'feito' => 'Trouxe de volta ' . oItem($area, $nome), 'alvo' => $id, 'alvoNome' => $nome],
        static function (array $l) use ($antiga, $k, $r, $nome, &$pos): array {
            if (indicePorId($l, (string) $r['id']) !== null) throw new ErroUsuario('“' . $nome . '” já está na lista.');
            // Mesmo lugar: logo depois do vizinho de cima que ainda existir, ou antes do de baixo; senão, no fim
            $onde = null;
            for ($j = $k - 1; $j >= 0 && $onde === null; $j--) {
                $x = indicePorId($l, (string) $antiga[$j]['id']);
                if ($x !== null) $onde = $x + 1;
            }
            for ($j = $k + 1; $j < count($antiga) && $onde === null; $j++) {
                $x = indicePorId($l, (string) $antiga[$j]['id']);
                if ($x !== null) $onde = $x;
            }
            $onde ??= count($l);
            array_splice($l, $onde, 0, [$r]);
            $pos = $onde + 1;
            return $l;
        });
    unset($_SESSION['desfazer'][$nomeCopia]);
    $arq = arquivoLogo((string) ($r['logo'] ?? ''));
    $semLogo = $arq !== null && !@is_file($arq);
    avisar('“' . $nome . '” voltou para a lista, na ' . $pos . 'ª posição' . ($semLogo ? '. O logo não estava mais na lixeira: escolha outro em Editar.' : '.'), $semLogo ? 'erro' : 'ok');
    redirecionar(linkLista($area, $id));
}

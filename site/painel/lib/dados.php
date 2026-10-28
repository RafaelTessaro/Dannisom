<?php
/* =====================================================================
   Painel Danni Som — arquivos de dados do site, cópias e lixeira
   Lê e grava dados/empresa.js, dados/clientes.js e dados/marcas.js no
   formato que o site (assets/js/core.js) lê:
       <comentário do topo>window.DANNI_X = <JSON>;
   O comentário do topo e o que vem depois do JSON ficam exatamente como
   estão; o JSON sai com 2 espaços, UTF-8, sem escapar acentos nem "/".
   Nada é executado: o JSON é recortado e conferido antes de gravar.
   Antes de cada gravação: cópia de segurança (pasta privada copias/).
   Logos que saem da lista vão para a lixeira privada (e voltam se uma
   cópia for restaurada).
   ===================================================================== */
declare(strict_types=1);
if (!defined('PAINEL')) { http_response_code(404); exit; }

final class ErroDados extends RuntimeException {}   // arquivo de dados existe mas não está no formato

const AREAS = [
    'clientes' => ['arquivo' => 'clientes.js', 'var' => 'DANNI_CLIENTES', 'lista' => true,  'nome' => 'Clientes'],
    'marcas'   => ['arquivo' => 'marcas.js',   'var' => 'DANNI_MARCAS',   'lista' => true,  'nome' => 'Marcas'],
    'empresa'  => ['arquivo' => 'empresa.js',  'var' => 'DANNI_EMPRESA',  'lista' => false, 'nome' => 'Empresa'],
];
const RE_ID = '/^[a-z0-9]+(?:-[a-z0-9]+)*$/';
// Logo administrado pelo painel: midia/clientes/<arquivo> ou midia/marcas/<arquivo> (sem pastas, sem "..")
const RE_LOGO = '#^midia/(clientes|marcas)/([a-z0-9][a-z0-9_-]{0,100}(?:\.[a-z0-9_-]+)*\.(?:webp|png|jpe?g))$#';
const RE_COPIA = '/^(clientes|marcas|empresa)-(\d{8})-(\d{6})-\d{3}-[0-9a-f]{4}-([a-z]{3,12})\.php$/';
const TIPOS_JUNTAR = ['ordem', 'visivel'];
const JSON_SITE = JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION;

function arquivoArea(string $area): string
{
    return cfg('pasta_site') . '/dados/' . AREAS[$area]['arquivo'];
}

/** Topo usado só quando clientes.js / marcas.js ainda não existem no servidor. */
function prefixoPadrao(string $area): string
{
    $txt = $area === 'clientes'
        ? "CLIENTES — logos do mural \"Quem confia no Danni Som\"\n   Ordem = ordem do site. Gravado pelo painel (área restrita)."
        : "MARCAS — fornecedores que o Danni Som vende (e instala; no som automotivo, a instalação é por parceiros)\n   Ordem = ordem do site. Gravado pelo painel (área restrita).";
    return "/* =====================================================================\n   " . $txt
        . "\n   ===================================================================== */\nwindow." . AREAS[$area]['var'] . ' = ';
}

/* ------------------------------------------------------------------
   Formato do arquivo
   ------------------------------------------------------------------ */
/** Recorta e confere o JSON depois de "window.DANNI_X =". Devolve [prefixo, dados, sufixo]. */
function interpretarArea(string $area, string $src): array
{
    $info = AREAS[$area];
    if (!preg_match('/(?:^|[\n;])[ \t]*window\.' . $info['var'] . '[ \t]*=[ \t]*/', $src, $m, PREG_OFFSET_CAPTURE)) {
        throw new ErroDados('não achei "window.' . $info['var'] . ' ="');
    }
    $ini = $m[0][1] + strlen($m[0][0]);
    $abre = $info['lista'] ? '[' : '{';
    if (($src[$ini] ?? '') !== $abre) throw new ErroDados('window.' . $info['var'] . ' não começa com ' . $abre);
    // Acha o fim do JSON contando colchetes/chaves fora das aspas
    $prof = 0; $str = false; $fim = -1; $n = strlen($src);
    for ($i = $ini; $i < $n; $i++) {
        $c = $src[$i];
        if ($str) {
            if ($c === '\\') $i++;
            elseif ($c === '"') $str = false;
            continue;
        }
        if ($c === '"') $str = true;
        elseif ($c === '[' || $c === '{') $prof++;
        elseif ($c === ']' || $c === '}') { $prof--; if ($prof === 0) { $fim = $i; break; } }
    }
    if ($fim < 0) throw new ErroDados('o JSON não termina (falta fechar ' . ($info['lista'] ? ']' : '}') . ')');
    $sufixo = substr($src, $fim + 1);
    if (!preg_match('/^\s*;?\s*$/', $sufixo)) throw new ErroDados('há texto depois dos dados');
    try {
        $dados = json_decode(substr($src, $ini, $fim - $ini + 1), false, 64, JSON_THROW_ON_ERROR);
    } catch (JsonException $e) {
        throw new ErroDados('JSON inválido (' . $e->getMessage() . ')');
    }
    if ($info['lista']) {
        if (!is_array($dados) || !array_is_list($dados)) throw new ErroDados('window.' . $info['var'] . ' precisa ser uma lista [ … ]');
        $vistos = [];
        foreach ($dados as $i => $r) {
            if (!$r instanceof stdClass) throw new ErroDados('o item ' . ($i + 1) . ' não é um objeto { … }');
            $r = get_object_vars($r);
            if (!is_string($r['id'] ?? null) || !preg_match(RE_ID, $r['id'])) throw new ErroDados('o item ' . ($i + 1) . ' não tem um "id" válido');
            if (isset($vistos[$r['id']])) throw new ErroDados('o id "' . $r['id'] . '" aparece duas vezes');
            if (!is_string($r['nome'] ?? null)) throw new ErroDados('o item ' . ($i + 1) . ' não tem "nome"');
            if (array_key_exists('logo', $r) && !is_string($r['logo'])) throw new ErroDados('o "logo" do item ' . ($i + 1) . ' não é texto');
            $vistos[$r['id']] = true;
            $dados[$i] = $r;
        }
    } else {
        if (!$dados instanceof stdClass) throw new ErroDados('window.' . $info['var'] . ' precisa ser um objeto { … }');
        $dados = get_object_vars($dados);
    }
    return [substr($src, 0, $ini), $dados, $sufixo];
}

/** JSON como o site espera: 2 espaços de recuo (o PHP usa 4), acentos e "/" sem escape. */
function jsonDoSite($dados): string
{
    $j = json_encode($dados, JSON_SITE | JSON_THROW_ON_ERROR);
    return preg_replace_callback('/^(?: {4})+/m', static fn(array $m): string => str_repeat(' ', intdiv(strlen($m[0]), 2)), $j) ?? $j;
}

function montarArea(string $prefixo, $dados, string $sufixo): string
{
    return $prefixo . jsonDoSite($dados) . $sufixo;
}

/** Mesmo conteúdo? (compara o JSON compacto) */
function mesmosDados($a, $b): bool
{
    return json_encode($a, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION)
        === json_encode($b, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION);
}

/** ['dados', 'prefixo', 'sufixo', 'bruto', 'existe']. Lista sem arquivo: vazia. */
function lerArea(string $area): array
{
    $arq = arquivoArea($area);
    if (!@is_file($arq)) {
        if (!AREAS[$area]['lista']) throw new ErroDados('o arquivo dados/' . AREAS[$area]['arquivo'] . ' não está no servidor');
        $prefixo = prefixoPadrao($area);
        return ['dados' => [], 'prefixo' => $prefixo, 'sufixo' => ";\n", 'bruto' => montarArea($prefixo, [], ";\n"), 'existe' => false];
    }
    $src = @file_get_contents($arq);
    if ($src === false) throw new ErroDados('sem permissão para ler dados/' . AREAS[$area]['arquivo']);
    [$prefixo, $dados, $sufixo] = interpretarArea($area, $src);
    return ['dados' => $dados, 'prefixo' => $prefixo, 'sufixo' => $sufixo, 'bruto' => $src, 'existe' => true];
}

function gravarArquivoArea(string $area, string $texto): void
{
    $arq = arquivoArea($area);
    if (!@is_dir(dirname($arq))) @mkdir(dirname($arq), 0755, true);
    if ((@is_file($arq) && !@is_writable($arq)) || !@is_writable(dirname($arq))) {
        throw new ErroUsuario('O servidor não deixa gravar dados/' . AREAS[$area]['arquivo'] . '. Confira as permissões no Gerenciador de Arquivos (arquivo 644, pasta dados 755).');
    }
    gravarAtomico($arq, $texto, 0644);
    protegerPastaDados();
}

/**
 * dados/.htaccess faz o navegador conferir os dados sempre (sem ele, o .htaccess da raiz daria
 * 1 ano de cache aos .js e quem já visitou veria telefone, clientes e marcas antigos). Recria se sumir.
 */
function protegerPastaDados(): void
{
    $arq = cfg('pasta_site') . '/dados/.htaccess';
    if (@is_file($arq)) return;
    @file_put_contents($arq, "# Dados do site (gravados pelo painel): o navegador sempre confere se mudou.\n"
        . "<IfModule mod_headers.c>\n  Header set Cache-Control \"no-cache\"\n</IfModule>\n"
        . "<IfModule mod_expires.c>\n  ExpiresActive Off\n</IfModule>\n");
}

/**
 * Altera os dados com segurança: trava → lê → $fn(dados) devolve os dados novos → confere →
 * cópia de segurança do estado anterior → grava (temporário + rename) → acerta a lixeira de logos.
 * $acao = ['tipo' => 'excluir'…, 'antes' => 'Antes de: …', 'feito' => 'Excluiu …', 'alvo' => id, 'alvoNome' => nome]
 * Devolve ['dados' => novos, 'copia' => nome da cópia ou null, 'mudou' => bool].
 * $fn(array $dados, array &$acao): array — pode completar a frase do registro em $acao['feito'].
 */
function alterarArea(string $area, array $acao, callable $fn): array
{
    return comTrava('dados-' . $area, static function () use ($area, $acao, $fn): array {
        $antes = lerArea($area);
        $novos = $fn($antes['dados'], $acao);   // $fn pode completar $acao['feito'] (parâmetro por referência)
        if (AREAS[$area]['lista']) $novos = array_values($novos);
        $texto = montarArea($antes['prefixo'], $novos, $antes['sufixo']);
        // Confere o que vai ser gravado, lendo como se fosse o arquivo do site
        if (!mesmosDados(interpretarArea($area, $texto)[1], $novos)) throw new RuntimeException('conferência dos dados de ' . $area . ' falhou');
        if ($antes['existe'] && $texto === $antes['bruto']) return ['dados' => $novos, 'copia' => null, 'mudou' => false];
        $copia = fazerCopia($area, $antes['bruto'], $acao);
        gravarArquivoArea($area, $texto);
        if (AREAS[$area]['lista']) sincronizarLogos($antes['dados'], $novos);
        registrar($area, (string) ($acao['feito'] ?? ''));
        return ['dados' => $novos, 'copia' => $copia, 'mudou' => true];
    });
}

/* ------------------------------------------------------------------
   Cópias de segurança (pasta privada copias/<area>-AAAAMMDD-HHMMSS-…-tipo.php)
   Guardam o arquivo inteiro, exatamente como estava.
   ------------------------------------------------------------------ */
function fazerCopia(string $area, string $bruto, array $acao): string
{
    $tipo = preg_match('/^[a-z]{3,12}$/', (string) ($acao['tipo'] ?? '')) ? (string) $acao['tipo'] : 'outro';
    $micro = microtime(true);
    $agora = (int) $micro;
    $usuario = usuarioLogado() ?? '';
    // Mudanças seguidas do mesmo tipo (ordem, mostrar/ocultar) pela mesma pessoa: uma cópia só
    if (in_array($tipo, TIPOS_JUNTAR, true)) {
        $ultima = arquivosCopias($area)[0] ?? null;
        $d = $ultima ? lerJsonPrivado('copias/' . basename($ultima)) : null;
        $janela = max(1, (int) cfg('copias_juntar_min')) * 60;
        if (is_array($d) && ($d['tipo'] ?? '') === $tipo && ($d['usuario'] ?? '') === $usuario && (int) ($d['ultimo'] ?? 0) >= $agora - $janela) {
            $d['ultimo'] = $agora;
            $d['juntou'] = (int) ($d['juntou'] ?? 1) + 1;
            gravarJsonPrivado('copias/' . basename($ultima), $d);
            return basename($ultima);
        }
    }
    $nome = $area . '-' . date('Ymd-His', $agora) . '-' . sprintf('%03d', min(999, (int) (($micro - $agora) * 1000))) . '-' . bin2hex(random_bytes(2)) . '-' . $tipo . '.php';
    gravarJsonPrivado('copias/' . $nome, [
        'versao' => 1, 'area' => $area, 'criado' => $agora, 'ultimo' => $agora, 'usuario' => $usuario, 'tipo' => $tipo,
        'motivo' => (string) ($acao['antes'] ?? ''), 'feito' => (string) ($acao['feito'] ?? ''), 'juntou' => 1,
        'alvo' => (string) ($acao['alvo'] ?? ''), 'alvoNome' => (string) ($acao['alvoNome'] ?? ''),
        'bruto' => $bruto,
    ]);
    podarCopias($area);
    limparLixeira();
    return $nome;
}

/** Caminhos das cópias de uma área, mais novas primeiro. */
function arquivosCopias(string $area): array
{
    $lista = glob(pastaPrivada() . '/copias/' . $area . '-[0-9]*.php') ?: [];
    $lista = array_filter($lista, static fn($a) => (bool) preg_match(RE_COPIA, basename($a)));
    rsort($lista, SORT_STRING);
    return array_values($lista);
}

/** As N mais recentes ficam sempre; além delas, as dos últimos X dias (até o teto). */
function podarCopias(string $area): void
{
    $sempre = max(1, (int) cfg('copias_max'));
    $dias = max(0, (int) cfg('copias_dias')) * 86400;
    $teto = max($sempre, (int) cfg('copias_teto'));
    foreach (arquivosCopias($area) as $i => $arq) {
        if ($i < $sempre) continue;
        preg_match(RE_COPIA, basename($arq), $m);
        $t = DateTime::createFromFormat('YmdHis', $m[2] . $m[3]);
        $idade = time() - ($t ? $t->getTimestamp() : 0);
        if ($i < $teto && $idade < $dias) continue;
        @unlink($arq);
    }
}

function listarCopias(string $area): array
{
    $out = [];
    foreach (arquivosCopias($area) as $a) {
        $d = lerJsonPrivado('copias/' . basename($a));
        if (!is_array($d) || !is_string($d['bruto'] ?? null)) continue;
        try { [, $dados] = interpretarArea($area, $d['bruto']); } catch (ErroDados $e) { continue; }
        $out[] = [
            'nome' => basename($a), 'criado' => (int) ($d['criado'] ?? 0), 'ultimo' => (int) ($d['ultimo'] ?? $d['criado'] ?? 0),
            'usuario' => (string) ($d['usuario'] ?? ''), 'tipo' => (string) ($d['tipo'] ?? ''), 'motivo' => (string) ($d['motivo'] ?? ''),
            'juntou' => max(1, (int) ($d['juntou'] ?? 1)), 'total' => AREAS[$area]['lista'] ? count($dados) : null,
            'visiveis' => AREAS[$area]['lista'] ? count(array_filter($dados, static fn($r) => ($r['visivel'] ?? true) !== false)) : null,
        ];
    }
    return $out;
}

/** Cópia conferida: ['area', 'criado', 'bruto', 'dados', …]. */
function lerCopia(string $nome): array
{
    if (!preg_match(RE_COPIA, $nome, $m)) throw new ErroUsuario('Cópia inválida.');
    $d = lerJsonPrivado('copias/' . $nome);
    if (!is_array($d) || !is_string($d['bruto'] ?? null)) throw new ErroUsuario('Essa cópia não foi encontrada (pode ter sido apagada por ser antiga).');
    try { [, $d['dados']] = interpretarArea($m[1], $d['bruto']); }
    catch (ErroDados $e) { throw new ErroUsuario('Essa cópia está danificada (' . $e->getMessage() . ').'); }
    $d['area'] = $m[1];
    return $d;
}

/** Volta a área inteira para como estava na cópia. O estado atual vira uma cópia (dá para desfazer). */
function restaurarCopia(string $nome): array
{
    $c = lerCopia($nome);
    $area = $c['area'];
    comTrava('dados-' . $area, static function () use ($c, $area): void {
        $oQue = $area === 'empresa' ? 'os dados da empresa' : 'a lista de ' . strtolower(AREAS[$area]['nome']);
        $acao = ['tipo' => 'restaurar', 'antes' => 'Antes de: voltar ' . $oQue . ' para como estava em ' . quando((int) $c['criado']),
            'feito' => 'Voltou ' . $oQue . ' para como estava em ' . quando((int) $c['criado']) . ' (restaurou uma cópia)'];
        $antesDados = [];
        try {
            $antes = lerArea($area);
            $antesDados = $antes['dados'];
            fazerCopia($area, $antes['bruto'], $acao);
        } catch (ErroDados $e) {
            // Arquivo atual ilegível: guarda como estava (não aparece na lista) e grava a cópia por cima
            $bruto = @file_get_contents(arquivoArea($area));
            if ($bruto !== false) gravarPrivado('copias/ilegivel-' . $area . '-' . date('Ymd-His') . '.php', base64_encode($bruto));
        }
        gravarArquivoArea($area, $c['bruto']);
        if (AREAS[$area]['lista']) sincronizarLogos($antesDados, $c['dados']);
        registrar($area, $acao['feito']);
    });
    return $c;
}

/* ------------------------------------------------------------------
   Logos: arquivos em midia/ e lixeira privada (lixeira/<pasta>--<arquivo>.php)
   ------------------------------------------------------------------ */
/** Caminho no servidor de um logo administrado pelo painel (null: fora do padrão, o painel não mexe). */
function arquivoLogo(string $logo): ?string
{
    if (!preg_match(RE_LOGO, $logo, $m) || str_contains($logo, '..')) return null;
    return cfg('pasta_site') . '/midia/' . $m[1] . '/' . $m[2];
}

function urlLogo(string $logo): string
{
    $arq = arquivoLogo($logo);
    return cfg('url_site') . implode('/', array_map('rawurlencode', explode('/', $logo))) . ($arq && @is_file($arq) ? '?v=' . (int) @filemtime($arq) : '');
}

function nomeNaLixeira(string $logo): ?string
{
    return preg_match(RE_LOGO, $logo, $m) ? 'lixeira/' . $m[1] . '--' . $m[2] . '.php' : null;
}

function logosUsados(array $lista): array
{
    $u = [];
    foreach ($lista as $r) {
        if (is_array($r) && is_string($r['logo'] ?? null) && arquivoLogo($r['logo']) !== null) $u[$r['logo']] = true;
    }
    return $u;
}

function moverParaLixeira(string $logo): void
{
    $arq = arquivoLogo($logo);
    $lix = nomeNaLixeira($logo);
    if ($arq === null || $lix === null || !@is_file($arq)) return;
    $bytes = @file_get_contents($arq);
    if ($bytes === false) return;
    gravarPrivado($lix, base64_encode($bytes));
    @unlink($arq);
}

function voltarDaLixeira(string $logo): bool
{
    $arq = arquivoLogo($logo);
    $lix = nomeNaLixeira($logo);
    if ($arq === null || $lix === null || @is_file($arq)) return false;
    $c = lerPrivado($lix);
    $bytes = $c === null ? false : base64_decode($c, true);
    if ($bytes === false || $bytes === '') return false;
    protegerPastasMidia();
    gravarAtomico($arq, $bytes, 0644);
    @unlink(caminhoPrivado($lix));
    return true;
}

/** Logo que saiu da lista → lixeira; logo da lista que não está na pasta → volta da lixeira. */
function sincronizarLogos(array $antes, array $depois): void
{
    $a = logosUsados($antes);
    $d = logosUsados($depois);
    foreach (array_diff_key($a, $d) as $logo => $_) moverParaLixeira($logo);
    foreach ($d as $logo => $_) {
        $arq = arquivoLogo($logo);
        if ($arq !== null && !@is_file($arq)) voltarDaLixeira($logo);
    }
}

/** Apaga da lixeira os logos que nenhuma cópia (nem a lista atual) usa mais (no máximo 1 vez por hora). */
function limparLixeira(bool $agora = false): void
{
    $arqs = glob(pastaPrivada() . '/lixeira/*--*.php') ?: [];
    $velhos = array_filter($arqs, static fn($a) => (int) @filemtime($a) < time() - 3600);
    if (!$velhos) return;
    $marca = caminhoPrivado('lixeira-conferida.php');
    if (!$agora && @is_file($marca) && (int) @filemtime($marca) > time() - 3600) return;
    @file_put_contents($marca, PREFIXO_PRIVADO);
    $usados = [];
    foreach (['clientes', 'marcas'] as $area) {
        try { $usados += logosUsados(lerArea($area)['dados']); } catch (Throwable $t) { return; }   // lista ilegível: não apaga nada
        foreach (arquivosCopias($area) as $c) {
            $d = lerJsonPrivado('copias/' . basename($c));
            if (!is_string($d['bruto'] ?? null)) continue;
            try { $usados += logosUsados(interpretarArea($area, $d['bruto'])[1]); } catch (ErroDados $e) { continue; }
        }
    }
    $naLixeira = [];
    foreach (array_keys($usados) as $logo) $naLixeira[(string) nomeNaLixeira($logo)] = true;
    foreach ($velhos as $a) {
        if (!isset($naLixeira['lixeira/' . basename($a)])) @unlink($a);
    }
}

function htaccessMidia(): string
{
    return "# Gerado pelo painel. Esta pasta só guarda imagens: nenhum script roda aqui.\n"
        . "Options -Indexes\n"
        . "<FilesMatch \"(?i)(\\.(php\\d?|phtml|phar|pht|cgi|pl|py|sh|shtml|asp|aspx|jsp|html?|svgz?|js|htaccess)(\\.|$)|^\\.)\">\n"
        . "  <IfModule mod_authz_core.c>\n    Require all denied\n  </IfModule>\n"
        . "  <IfModule !mod_authz_core.c>\n    Order allow,deny\n    Deny from all\n  </IfModule>\n"
        . "</FilesMatch>\n"
        . "<IfModule mod_php.c>\n  php_flag engine off\n</IfModule>\n"
        . "<IfModule mod_php7.c>\n  php_flag engine off\n</IfModule>\n"
        . "<IfModule mod_headers.c>\n  Header set X-Content-Type-Options \"nosniff\"\n</IfModule>\n";
}

/** Cria as pastas de logos (se faltarem) com o .htaccess que impede rodar scripts. */
function protegerPastasMidia(): void
{
    foreach (['clientes', 'marcas'] as $pasta) {
        $dir = cfg('pasta_site') . '/midia/' . $pasta;
        if (!@is_dir($dir)) @mkdir($dir, 0755, true);
        if (@is_dir($dir) && !@is_file($dir . '/.htaccess')) @file_put_contents($dir . '/.htaccess', htaccessMidia());
    }
}

<?php
/* =====================================================================
   Painel Danni Som — estatísticas de visitas do site
   O contador do site (assets/js/contador.js) manda cada página vista e
   cada clique que interessa para painel/visita.php, que soma aqui nos
   totais do dia. Sem cookie, nada guardado no aparelho de quem visita,
   e o IP nunca é gravado. Na pasta privada:
     estatisticas/AAAA-MM-DD.php             totais do dia (JSON); ficam
                                             MESES_ESTATISTICAS meses
     estatisticas/visitantes-AAAA-MM-DD.php  o "sal" do dia (código
                                             aleatório) e os códigos dos
                                             visitantes do dia: hash de
                                             sal + IP + navegador, para
                                             contar cada pessoa uma vez e
                                             limitar abusos. É apagado no
                                             dia seguinte: sem o sal, o
                                             código não pode ser refeito.
   Tela em lib/telas.php (telaEstatisticas).
   ===================================================================== */
declare(strict_types=1);
if (!defined('PAINEL')) { http_response_code(404); exit; }

const VERSAO_ESTATISTICAS = 1;
const TRAVA_ESTATISTICAS = 'estatisticas';
const MESES_ESTATISTICAS = 25;      // dá para comparar os últimos 12 meses com os 12 anteriores (o privacidade.html diz o mesmo)
const LIMITE_ENVIO_VISITA = 1500;   // bytes de um envio do contador (o maior, o do formulário, tem uns 300)
const MAX_VISITANTES_DIA = 20000;   // códigos de visitante por dia; depois disso o dia para de contar (protege os arquivos)
const MAX_EVENTOS_DIA = 500;        // por visitante: páginas vistas + cliques
const MAX_EVENTOS_MINUTO = 40;
// Chaves diferentes por dia em cada lista (uma chave nova além disso vai para a "sobra" ou é ignorada)
const MAX_CHAVES = ['cliques' => 300, 'origens' => 40, 'cidades' => 80, 'regioes' => 40, 'paises' => 40];
const MAPAS_DIA = ['paginas', 'cliques', 'formulario', 'origens', 'dispositivos', 'cidades', 'regioes', 'paises'];
// Robôs, prévias de link e programas (o contador também não roda sem JavaScript, o que já tira a maioria)
const RE_ROBO = '/bot|crawl|spider|slurp|preview|headless|lighthouse|pagespeed|gtmetrix|pingdom|uptime|monitor|facebookexternalhit|'
    . 'embedly|whatsapp|telegram|discord|skype|curl|wget|python|java\/|go-http|okhttp|axios|node-fetch|undici|httpclient|libwww|'
    . 'scrapy|phantom|selenium|puppeteer|playwright|semrush|ahrefs|yandex|baidu|archive/i';

/* ------------------------------------------------------------------
   O que o site manda (as mesmas chaves das páginas e do contador.js)
   ------------------------------------------------------------------ */
const PAGINAS_SITE = ['inicio' => 'Início', 'solucoes' => 'Soluções', 'loja' => 'Loja', 'clientes' => 'Clientes', 'empresa' => 'Empresa',
    'contato' => 'Contato', 'privacidade' => 'Privacidade', '404' => 'Página não encontrada'];
const TIPOS_CLIQUE = ['whatsapp' => 'WhatsApp', 'orcamento' => 'Botão de orçamento (sem WhatsApp no site)', 'telefone' => 'Telefone',
    'email' => 'E-mail', 'mapa' => 'Mapa / Como chegar', 'instagram' => 'Instagram', 'facebook' => 'Facebook',
    'avaliacoes' => 'Avaliações no Google', 'formulario' => 'Formulário de orçamento enviado',
    'aba' => 'Abas “Para cada lugar”', 'filtro' => 'Filtros', 'link' => 'Links'];
const CONTATOS = ['whatsapp', 'orcamento', 'telefone', 'email', 'formulario', 'mapa', 'instagram', 'facebook', 'avaliacoes'];
// Abas "Para cada lugar" do início (id="tab-…") e filtros (data-v) dos murais de clientes e de marcas
const ABAS_SITE = ['carro', 'casa', 'comercio', 'escola', 'igreja', 'empresa'];
const FILTROS_SITE = ['todos', 'saude', 'educacao', 'industria', 'igrejas', 'comercio', 'automotivo', 'publico',
    'som-automotivo', 'sonorizacao', 'imagem', 'seguranca', 'iluminacao', 'estetica', 'eletrica', 'ferramentas',
    'home-theater', 'acessorios', 'redes'];
// Formulário de orçamento (contato.html): valor da opção → chave guardada. Nada do que a pessoa digita vem para cá.
const LUGARES_FORM = ['carro' => 'carro', 'casa' => 'casa', 'comercio' => 'comercio', 'escola' => 'escola',
    'instituicao-religiosa' => 'igreja', 'empresa' => 'empresa', 'outro' => 'outro'];
const INTERESSES_FORM = ['som-automotivo' => 'som-automotivo', 'sonorizacao' => 'sonorizacao', 'home-theater' => 'home-theater',
    'painel-de-led' => 'led', 'cameras' => 'cameras', 'iluminacao' => 'iluminacao', 'produto-da-loja' => 'loja'];

/* ------------------------------------------------------------------
   Nomes para a tela
   ------------------------------------------------------------------ */
const ASSUNTOS = ['som-automotivo' => 'Som automotivo', 'sonorizacao' => 'Sonorização', 'home-theater' => 'Home theater',
    'led' => 'Painéis de LED', 'cameras' => 'Câmeras', 'iluminacao' => 'Iluminação', 'imagem' => 'Imagem e vídeo',
    'estetica' => 'Estética automotiva', 'eletrica' => 'Materiais elétricos', 'ferramentas' => 'Ferramentas',
    'acessorios' => 'Acessórios', 'audio-video' => 'Áudio, vídeo e redes', 'redes' => 'Redes e informática',
    'loja' => 'Loja (produtos em geral)'];
const LUGARES = ['carro' => 'Carro', 'casa' => 'Casa', 'comercio' => 'Comércio', 'escola' => 'Escolas e educação',
    'igreja' => 'Instituições religiosas', 'empresa' => 'Empresas', 'saude' => 'Saúde', 'industria' => 'Indústria',
    'automotivo' => 'Automotivo', 'publico' => 'Serviços públicos', 'outro' => 'Outro lugar'];
const SECOES_SITE = ['inicio' => 'Topo', 'topo' => 'Topo', 'manifesto' => 'Quem somos', 'solucoes' => 'Soluções',
    'lugares' => 'Para cada lugar', 'processo' => 'Como trabalhamos', 'clientes' => 'Clientes', 'marcas' => 'Marcas',
    'historia' => 'Nossa história', 'vamos-conversar' => 'Faixa “Vamos conversar”', 'rodape' => 'Rodapé',
    'cabecalho' => 'Cabeçalho', 'menu' => 'Menu do celular', 'botao-flutuante' => 'Botão flutuante',
    'barra-de-atalhos' => 'Barra de atalhos (celular)', 'som-automotivo' => 'Som automotivo', 'sonorizacao' => 'Sonorização',
    'home-theater' => 'Home theater', 'led' => 'Painéis de LED', 'cftv' => 'Câmeras', 'iluminacao' => 'Iluminação',
    'perguntas' => 'Perguntas frequentes', 'categorias' => 'Categorias', 'som' => 'Som automotivo',
    'estetica' => 'Estética automotiva', 'eletrica' => 'Materiais elétricos', 'ferramentas' => 'Ferramentas',
    'acessorios' => 'Acessórios', 'audio-video' => 'Áudio, vídeo e redes', 'visite' => 'Visite a loja',
    'orcamento' => 'Formulário de orçamento', 'saude' => 'Saúde', 'educacao' => 'Educação', 'industria' => 'Indústria',
    'igrejas' => 'Instituições religiosas', 'automotivo' => 'Automotivo', 'publico' => 'Serviços públicos',
    'comercio' => 'Comércio e serviços'];
// Partes que aparecem em todas as páginas (um clique ali não diz o assunto da página)
const AREAS_COMUNS = ['cabecalho', 'menu', 'rodape', 'vamos-conversar', 'botao-flutuante', 'barra-de-atalhos'];
const SECAO_SOLUCAO = ['som-automotivo' => 'som-automotivo', 'sonorizacao' => 'sonorizacao', 'home-theater' => 'home-theater',
    'led' => 'led', 'cftv' => 'cameras', 'iluminacao' => 'iluminacao'];
// "O que mais interessou": [grupo, chave] de cada filtro e de cada destino de link
const FILTRO_INTERESSE = ['saude' => ['lugar', 'saude'], 'educacao' => ['lugar', 'escola'], 'industria' => ['lugar', 'industria'],
    'igrejas' => ['lugar', 'igreja'], 'comercio' => ['lugar', 'comercio'], 'automotivo' => ['lugar', 'automotivo'],
    'publico' => ['lugar', 'publico'], 'som-automotivo' => ['assunto', 'som-automotivo'], 'sonorizacao' => ['assunto', 'sonorizacao'],
    'imagem' => ['assunto', 'imagem'], 'seguranca' => ['assunto', 'cameras'], 'iluminacao' => ['assunto', 'iluminacao'],
    'estetica' => ['assunto', 'estetica'], 'eletrica' => ['assunto', 'eletrica'], 'ferramentas' => ['assunto', 'ferramentas'],
    'home-theater' => ['assunto', 'home-theater'], 'acessorios' => ['assunto', 'acessorios'], 'redes' => ['assunto', 'redes']];
const DESTINO_INTERESSE = ['solucoes#som-automotivo' => ['assunto', 'som-automotivo'], 'solucoes#sonorizacao' => ['assunto', 'sonorizacao'],
    'solucoes#home-theater' => ['assunto', 'home-theater'], 'solucoes#led' => ['assunto', 'led'], 'solucoes#cftv' => ['assunto', 'cameras'],
    'solucoes#iluminacao' => ['assunto', 'iluminacao'], 'loja' => ['assunto', 'loja'], 'loja#categorias' => ['assunto', 'loja'],
    'loja#marcas' => ['assunto', 'loja'], 'loja#som' => ['assunto', 'som-automotivo'], 'loja#estetica' => ['assunto', 'estetica'],
    'loja#iluminacao' => ['assunto', 'iluminacao'], 'loja#eletrica' => ['assunto', 'eletrica'], 'loja#ferramentas' => ['assunto', 'ferramentas'],
    'loja#acessorios' => ['assunto', 'acessorios'], 'loja#audio-video' => ['assunto', 'audio-video'],
    'clientes#saude' => ['lugar', 'saude'], 'clientes#educacao' => ['lugar', 'escola'], 'clientes#industria' => ['lugar', 'industria'],
    'clientes#igrejas' => ['lugar', 'igreja'], 'clientes#automotivo' => ['lugar', 'automotivo'], 'clientes#publico' => ['lugar', 'publico'],
    'clientes#comercio' => ['lugar', 'comercio']];
const ORIGENS = ['google' => 'Google', 'instagram' => 'Instagram', 'facebook' => 'Facebook', 'whatsapp' => 'WhatsApp',
    'direto' => 'Direto', 'outros-sites' => 'Outros sites'];
const ORIGENS_HOSTS = [
    'google'    => '/(^|\.)google(\.[a-z]{2,3}){1,2}$|^com\.google\.android\.googlequicksearchbox$/',
    'instagram' => '/(^|\.)instagram\.com$|^com\.instagram\.android$/',
    'facebook'  => '/(^|\.)(facebook\.com|fb\.com|fb\.me)$|^com\.facebook\.(katana|orca)$/',
    'whatsapp'  => '/(^|\.)(whatsapp\.com|whatsapp\.net|wa\.me)$|^com\.whatsapp$/',
];
// utm_source dos links divulgados (ex.: a bio do Instagram com ?utm_source=instagram)
const FONTES_CAMPANHA = ['google' => '/^(google|gmb|gbp|maps|google-maps|perfil-google)$/', 'instagram' => '/^(instagram|ig)$/',
    'facebook' => '/^(facebook|fb|meta)$/', 'whatsapp' => '/^(whatsapp|wa|zap)$/'];
const DISPOSITIVOS = ['celular' => 'Celular', 'computador' => 'Computador', 'tablet' => 'Tablet'];
const PERIODOS = ['hoje' => 'Hoje', 'ontem' => 'Ontem', '7dias' => '7 dias', '30dias' => '30 dias', 'mes' => 'Este mês', '12meses' => '12 meses'];
const MESES_PT = [1 => 'janeiro', 'fevereiro', 'março', 'abril', 'maio', 'junho', 'julho', 'agosto', 'setembro', 'outubro', 'novembro', 'dezembro'];
const SEMANA_PT = ['dom', 'seg', 'ter', 'qua', 'qui', 'sex', 'sáb'];
const SEMANA_LONGA = ['domingo', 'segunda-feira', 'terça-feira', 'quarta-feira', 'quinta-feira', 'sexta-feira', 'sábado'];

/* ------------------------------------------------------------------
   Arquivos do dia
   ------------------------------------------------------------------ */
function arquivoDia(string $dia): string
{
    return 'estatisticas/' . $dia . '.php';
}

function arquivoVisitantes(string $dia): string
{
    return 'estatisticas/visitantes-' . $dia . '.php';
}

function diaVazio(): array
{
    return ['v' => VERSAO_ESTATISTICAS, 'visitantes' => 0, 'visualizacoes' => 0, 'paginas' => [], 'cliques' => [], 'formulario' => [],
        'origens' => [], 'dispositivos' => [], 'cidades' => [], 'regioes' => [], 'paises' => [], 'horas' => array_fill(0, 24, 0)];
}

/** Só contagens (inteiros positivos) nos lugares certos: um arquivo estragado ou editado à mão não quebra a tela. */
function normalizarDia($d): array
{
    $n = diaVazio();
    if (!is_array($d)) return $n;
    foreach (['visitantes', 'visualizacoes'] as $k) $n[$k] = is_int($d[$k] ?? null) ? max(0, $d[$k]) : 0;
    foreach (MAPAS_DIA as $k) {
        if (!is_array($d[$k] ?? null)) continue;
        foreach ($d[$k] as $c => $v) if (is_int($v) && $v > 0) $n[$k][(string) $c] = $v;
    }
    if (is_array($d['horas'] ?? null)) for ($h = 0; $h < 24; $h++) $n['horas'][$h] = is_int($d['horas'][$h] ?? null) ? max(0, $d['horas'][$h]) : 0;
    return $n;
}

function lerDia(string $dia): array
{
    return normalizarDia(lerJsonPrivado(arquivoDia($dia)));
}

/** Soma 1 em $mapa[$chave]. Chave nova além do $limite vai para a $sobra (ou é ignorada, sem sobra). */
function somarChave(array &$mapa, string $chave, int $limite, ?string $sobra = null): void
{
    if (!isset($mapa[$chave]) && count($mapa) >= $limite) {
        if ($sobra === null) return;
        $chave = $sobra;
    }
    $mapa[$chave] = ($mapa[$chave] ?? 0) + 1;
}

/** Apaga os visitantes de outros dias (com o sal deles) e os totais com mais de MESES_ESTATISTICAS meses. Só com a trava. */
function limparEstatisticas(string $hoje): void
{
    $pasta = pastaPrivada() . '/estatisticas';
    foreach (glob($pasta . '/visitantes-*.php') ?: [] as $a) {
        if (basename($a) !== basename(arquivoVisitantes($hoje))) @unlink($a);
    }
    $limite = (new DateTimeImmutable($hoje))->modify('-' . MESES_ESTATISTICAS . ' months')->format('Y-m-d');
    foreach (glob($pasta . '/[0-9][0-9][0-9][0-9]-[0-9][0-9]-[0-9][0-9].php') ?: [] as $a) {
        if (substr(basename($a), 0, 10) < $limite) @unlink($a);
    }
}

/** Sal e códigos dos visitantes de hoje. Dia novo: sal novo, e o de ontem é apagado. Só com a trava. */
function visitantesDoDia(string $dia): array
{
    $V = lerJsonPrivado(arquivoVisitantes($dia));
    if (is_array($V) && ($V['dia'] ?? '') === $dia && is_string($V['sal'] ?? null) && strlen($V['sal']) === 64 && is_array($V['v'] ?? null)) return $V;
    limparEstatisticas($dia);
    return ['dia' => $dia, 'sal' => bin2hex(random_bytes(32)), 'v' => []];
}

/** Dia (AAAA-MM-DD) do arquivo de totais mais antigo: desde quando o site conta. */
function inicioDaContagem(): ?string
{
    $lista = glob(pastaPrivada() . '/estatisticas/[0-9][0-9][0-9][0-9]-[0-9][0-9]-[0-9][0-9].php') ?: [];
    if (!$lista) return null;
    sort($lista, SORT_STRING);
    return substr(basename($lista[0]), 0, 10);
}

/* ------------------------------------------------------------------
   Receber um envio do contador (painel/visita.php)
   ------------------------------------------------------------------ */
function hostDoSite(): string
{
    $h = preg_replace('/:\d+$/', '', strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''))) ?? '';
    return str_starts_with($h, 'www.') ? substr($h, 4) : $h;
}

function ehRobo(string $ua): bool
{
    return trim($ua) === '' || (bool) preg_match(RE_ROBO, $ua);
}

/* Faixas de endereço do Cloudflare (https://www.cloudflare.com/ips/). Só um pedido que chega de uma
   delas pode trazer o endereço do visitante (CF-Connecting-IP) e a cidade (CF-IPCity…): vindo direto ao
   servidor, quem quisesse poderia inventar esses cabeçalhos. Se o Cloudflare mudar as faixas, atualize aqui. */
const FAIXAS_CLOUDFLARE = ['173.245.48.0/20', '103.21.244.0/22', '103.22.200.0/22', '103.31.4.0/22', '141.101.64.0/18',
    '108.162.192.0/18', '190.93.240.0/20', '188.114.96.0/20', '197.234.240.0/22', '198.41.128.0/17', '162.158.0.0/15',
    '104.16.0.0/13', '104.24.0.0/14', '172.64.0.0/13', '131.0.72.0/22',
    '2400:cb00::/32', '2606:4700::/32', '2803:f800::/32', '2405:b500::/32', '2405:8100::/32', '2a06:98c0::/29', '2c0f:f248::/32'];

function ipNaFaixa(string $ip, string $faixa): bool
{
    [$rede, $bits] = explode('/', $faixa);
    $a = @inet_pton($ip); $b = @inet_pton($rede);
    if ($a === false || $b === false || strlen($a) !== strlen($b)) return false;
    $bits = (int) $bits; $bytes = intdiv($bits, 8); $resto = $bits % 8;
    if (strncmp($a, $b, $bytes) !== 0) return false;
    if ($resto === 0) return true;
    $m = (0xFF << (8 - $resto)) & 0xFF;
    return (ord($a[$bytes]) & $m) === (ord($b[$bytes]) & $m);
}

/** O pedido chegou pelo Cloudflare? (decidido pelo endereço de quem conectou, que não dá para inventar) */
function viaCloudflare(): bool
{
    static $sim = null;
    if ($sim === null) {
        $r = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
        $sim = false;
        foreach (FAIXAS_CLOUDFLARE as $f) if (ipNaFaixa($r, $f)) { $sim = true; break; }
    }
    return $sim;
}

/** Endereço que entra no código do dia (nunca é gravado). */
function ipDoVisitante(): string
{
    // Atrás do Cloudflare, REMOTE_ADDR é o endereço do Cloudflare e o do visitante vem em CF-Connecting-IP
    // (só aceito quando o pedido vem mesmo do Cloudflare)
    $cf = (string) ($_SERVER['HTTP_CF_CONNECTING_IP'] ?? '');
    if (viaCloudflare() && $cf !== '' && strlen($cf) <= 45 && filter_var($cf, FILTER_VALIDATE_IP)) return $cf;
    return ip();
}

function dispositivo(string $ua): string
{
    if (preg_match('/iPad|Tablet|Kindle|Silk|PlayBook|Android(?!.*Mobile)/i', $ua)) return 'tablet';
    if (preg_match('/Mobi|iPhone|iPod|Android|Windows Phone|BlackBerry|Opera Mini/i', $ua)) return 'celular';
    return 'computador';
}

/** Cidade, estado e país aproximados, quando o Cloudflare informa (só os nomes; o IP não entra). */
function geoDoVisitante(): array
{
    if (!viaCloudflare()) return ['cidade' => '', 'regiao' => '', 'pais' => ''];
    $t = static function (string $h, int $max): string {
        $v = (string) ($_SERVER[$h] ?? '');
        if ($v === '' || strlen($v) > 200) return '';
        if (!preg_match('//u', $v) && function_exists('mb_convert_encoding')) $v = (string) mb_convert_encoding($v, 'UTF-8', 'ISO-8859-1');
        $v = trim(preg_replace("/[^\\p{L}\\p{N} '().-]+/u", '', limparLinha($v) ?? '') ?? '');
        return cortar($v, $max);
    };
    $cidade = $t('HTTP_CF_IPCITY', 60);
    $regiao = $t('HTTP_CF_REGION', 60);
    $uf = strtoupper($t('HTTP_CF_REGION_CODE', 3));
    if (!preg_match('/^[A-Z0-9]{1,3}$/', $uf)) $uf = '';
    $pais = strtoupper((string) ($_SERVER['HTTP_CF_IPCOUNTRY'] ?? ''));
    if (!preg_match('/^[A-Z]{2}$/', $pais) || $pais === 'XX' || $pais === 'T1') $pais = '';
    return [
        'cidade' => $cidade === '' ? '' : $cidade . ($uf !== '' ? ' · ' . $uf : ($regiao !== '' ? ' · ' . $regiao : '')),
        'regiao' => $regiao,
        'pais'   => $pais,
    ];
}

/** Grupo da origem de quem chegou: google, instagram…, "site:dominio", "campanha:nome"; null = veio de outra página do site. */
function grupoOrigem(string $de, string $fonte, string $ua): ?string
{
    if ($fonte !== '') {
        $f = slug($fonte, 30);
        foreach (FONTES_CAMPANHA as $grupo => $re) if (preg_match($re, $f)) return $grupo;
        if ($f !== '') return 'campanha:' . $f;
    }
    if ($de === '@') return null;
    $host = strtolower($de);
    if (str_starts_with($host, 'www.')) $host = substr($host, 4);
    if ($host === '') {
        // sem origem: endereço digitado, favorito, link de aplicativo (WhatsApp, e-mail)…
        // os navegadores de dentro do Instagram e do Facebook se identificam
        if (preg_match('/\bInstagram\b/', $ua)) return 'instagram';
        if (preg_match('/FBAN|FBAV|FB_IAB/', $ua)) return 'facebook';
        return 'direto';
    }
    if (strlen($host) > 80 || !preg_match('/^[a-z0-9-]+(\.[a-z0-9-]+)+$/', $host)) return 'outros-sites';
    if ($host === hostDoSite()) return null;
    foreach (ORIGENS_HOSTS as $grupo => $re) if (preg_match($re, $host)) return $grupo;
    return 'site:' . $host;
}

/** Um campo de texto do envio, até $max bytes ('' se faltar). null = não é texto ou passou do tamanho: o envio todo é ignorado. */
function campoVisita(array $d, string $k, int $max): ?string
{
    $v = $d[$k] ?? '';
    return is_string($v) && strlen($v) <= $max ? $v : null;
}

/** Rótulo de um botão: só letras, números e pontuação simples, até 60 caracteres. null = inválido. */
function rotuloClique(string $v): ?string
{
    $v = limparLinha($v);
    if ($v === null) return null;
    $v = trim(preg_replace('/\s+/u', ' ', preg_replace('/[^\p{L}\p{N} ()\-.,@_+:·]+/u', '', $v) ?? '') ?? '');
    $v = trim(cortar($v, 60));
    return $v === '' ? '-' : $v;
}

/** O envio conferido campo a campo (só valores conhecidos e curtos), ou null para ignorar. */
function conferirVisita(array $d, string $ua): ?array
{
    $p = $d['p'] ?? null;
    if (!is_string($p) || !isset(PAGINAS_SITE[$p])) return null;
    if (($d['t'] ?? null) === 'pv') {
        $de = campoVisita($d, 'o', 100);
        $fonte = campoVisita($d, 'f', 160);
        return $de === null || $fonte === null ? null : ['tipo' => 'pv', 'pagina' => $p, 'origem' => grupoOrigem($de, $fonte, $ua)];
    }
    if (($d['t'] ?? null) !== 'c') return null;
    $k = $d['k'] ?? null;
    if (!is_string($k) || !isset(TIPOS_CLIQUE[$k])) return null;
    $s = campoVisita($d, 's', 240);
    $r = campoVisita($d, 'r', 240);
    if ($s === null || $r === null) return null;
    $s = slug($s, 40);
    $form = [];
    switch ($k) {
        case 'aba':
            if (!in_array($r, ABAS_SITE, true)) return null;
            break;
        case 'filtro':
            if (!in_array($r, FILTROS_SITE, true)) return null;
            break;
        case 'link':   // destino: "pagina" ou "pagina#ancora"
            if (!preg_match('/^([a-z0-9]+)(?:#[a-z0-9][a-z0-9-]{0,39})?$/', $r, $m) || !isset(PAGINAS_SITE[$m[1]]) || $m[1] === '404') return null;
            break;
        case 'formulario':   // só as opções marcadas (valores fixos da página), nunca o que foi digitado
            if (!in_array($r, ['whatsapp', 'email'], true)) return null;
            $lugar = campoVisita($d, 'l', 240);
            $lista = $d['i'] ?? [];
            if ($lugar === null || !is_array($lista) || !array_is_list($lista) || count($lista) > count(INTERESSES_FORM)) return null;
            if (isset(LUGARES_FORM[slug($lugar, 40)])) $form[] = 'lugar:' . LUGARES_FORM[slug($lugar, 40)];
            foreach ($lista as $i) {
                if (!is_string($i) || strlen($i) > 120) return null;
                if (isset(INTERESSES_FORM[slug($i, 40)])) $form[] = 'interesse:' . INTERESSES_FORM[slug($i, 40)];
            }
            $form = array_values(array_unique($form));
            break;
        default:   // contatos: o texto do botão
            $r = rotuloClique($r);
            if ($r === null) return null;
    }
    return ['tipo' => 'c', 'pagina' => $p, 'chave' => implode('|', [$k, $p, $s !== '' ? $s : '-', $r]), 'formulario' => $form];
}

/** O envio deste pedido, ou null (não é do site, é robô, grande demais, campos estranhos…). */
function visitaDoPedido(): ?array
{
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') return null;
    if ((int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > LIMITE_ENVIO_VISITA) return null;
    // O navegador diz de onde veio o envio: só vale o do próprio site (outro site não suja a contagem)
    $sfs = (string) ($_SERVER['HTTP_SEC_FETCH_SITE'] ?? '');
    if ($sfs !== '' && $sfs !== 'same-origin') return null;
    $origem = (string) ($_SERVER['HTTP_ORIGIN'] ?? '');
    if ($origem !== '' && $origem !== 'null') {
        $host = parse_url($origem, PHP_URL_HOST);
        $porta = parse_url($origem, PHP_URL_PORT);
        if (!is_string($host) || strtolower($host . ($porta ? ':' . $porta : '')) !== strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''))) return null;
    }
    $ua = (string) ($_SERVER['HTTP_USER_AGENT'] ?? '');
    if (strlen($ua) > 512 || ehRobo($ua)) return null;
    $corpo = file_get_contents('php://input', false, null, 0, LIMITE_ENVIO_VISITA + 1);
    if (!is_string($corpo) || $corpo === '' || strlen($corpo) > LIMITE_ENVIO_VISITA) return null;
    $d = json_decode($corpo, true, 4);
    if (!is_array($d) || array_is_list($d)) return null;
    $ev = conferirVisita($d, $ua);
    if ($ev !== null) $ev['ua'] = $ua;
    return $ev;
}

/** Soma o envio nos totais de hoje (com trava: um envio por vez). */
function registrarVisita(array $ev): void
{
    comTrava(TRAVA_ESTATISTICAS, static function () use ($ev): void {
        $hoje = date('Y-m-d');
        $V = visitantesDoDia($hoje);
        $codigo = substr(hash_hmac('sha256', ipDoVisitante() . "\n" . $ev['ua'], $V['sal']), 0, 16);
        $minuto = intdiv(time(), 60);
        // [envios hoje, minuto do último envio, envios nesse minuto, origem já contada]
        $r = $V['v'][$codigo] ?? null;
        $novo = !is_array($r) || count($r) !== 4;
        if ($novo) {
            if (count($V['v']) >= MAX_VISITANTES_DIA) return;
            $r = [0, $minuto, 0, 0];
        }
        $r = array_map('intval', $r);
        if ($r[1] !== $minuto) { $r[1] = $minuto; $r[2] = 0; }
        if ($r[0] >= MAX_EVENTOS_DIA || $r[2] >= MAX_EVENTOS_MINUTO) return;
        $r[0]++;
        $r[2]++;

        $T = lerDia($hoje);
        if ($novo) {   // cada pessoa entra uma vez por dia nestas contas
            $T['visitantes']++;
            somarChave($T['dispositivos'], dispositivo($ev['ua']), 3);
            $g = geoDoVisitante();
            if ($g['cidade'] !== '') somarChave($T['cidades'], $g['cidade'], MAX_CHAVES['cidades'], 'outras');
            if ($g['regiao'] !== '') somarChave($T['regioes'], $g['regiao'], MAX_CHAVES['regioes'], 'outras');
            if ($g['pais'] !== '') somarChave($T['paises'], $g['pais'], MAX_CHAVES['paises'], 'outros');
        }
        if ($ev['tipo'] === 'pv') {
            $T['visualizacoes']++;
            somarChave($T['paginas'], $ev['pagina'], count(PAGINAS_SITE));
            $T['horas'][(int) date('G')]++;
            // de onde veio: na primeira chegada do dia de cada visitante (navegar dentro do site não conta)
            if (!$r[3] && $ev['origem'] !== null) {
                $o = $ev['origem'];
                somarChave($T['origens'], $o, isset(ORIGENS[$o]) ? PHP_INT_MAX : MAX_CHAVES['origens'], 'outros-sites');
                $r[3] = 1;
            }
        } else {
            [$tipo, $pagina] = explode('|', $ev['chave'], 3);
            // além do limite, o clique ainda conta no tipo e na página, sem o lugar e o rótulo
            somarChave($T['cliques'], $ev['chave'], MAX_CHAVES['cliques'], $tipo . '|' . $pagina . '|-|-');
            foreach ($ev['formulario'] as $f) somarChave($T['formulario'], $f, PHP_INT_MAX);
        }
        $V['v'][$codigo] = $r;
        gravarJsonPrivado(arquivoDia($hoje), $T);
        gravarJsonPrivado(arquivoVisitantes($hoje), $V);
    });
}

/* ------------------------------------------------------------------
   Ler um período (tela Estatísticas)
   ------------------------------------------------------------------ */
function enxugarMapa(array &$m, string $mapa, int $max = 2000): void
{
    if (count($m) <= $max) return;
    $resto = $mapa === 'cliques' ? 'link|outros|-|-' : (in_array($mapa, ['cidades', 'regioes'], true) ? 'outras' : 'outros');
    arsort($m);
    $soma = array_sum(array_slice($m, $max, null, true));
    $m = array_slice($m, 0, $max, true);
    $m[$resto] = ($m[$resto] ?? 0) + $soma;
}

function somarDia(array &$a, array $b): void
{
    $a['visitantes'] += $b['visitantes'];
    $a['visualizacoes'] += $b['visualizacoes'];
    foreach (MAPAS_DIA as $k) foreach ($b[$k] as $c => $n) $a[$k][(string) $c] = ($a[$k][(string) $c] ?? 0) + $n;
    for ($h = 0; $h < 24; $h++) $a['horas'][$h] += $b['horas'][$h];
}

/** Totais do período (de/até inclusive) e os números de cada dia: ['total' => dia somado, 'dias' => [AAAA-MM-DD => [visitantes, páginas]]]. */
function resumoEstatisticas(DateTimeImmutable $de, DateTimeImmutable $ate): array
{
    $total = diaVazio();
    $dias = [];
    for ($d = $de; $d <= $ate; $d = $d->modify('+1 day')) {
        $x = lerDia($d->format('Y-m-d'));
        $dias[$d->format('Y-m-d')] = [$x['visitantes'], $x['visualizacoes']];
        somarDia($total, $x);
        // períodos longos: cada lista fica com as 2.000 chaves mais vistas (o resto, que a tela nem mostra,
        // vira um total só), para a memória não crescer com chaves diferentes dia após dia
        if (substr($d->format('d'), -1) === '0') foreach (MAPAS_DIA as $k) enxugarMapa($total[$k], $k);
    }
    foreach (MAPAS_DIA as $k) enxugarMapa($total[$k], $k);
    return ['total' => $total, 'dias' => $dias];
}

/** O período escolhido (?periodo=…, ou ?periodo=datas&de=…&ate=…) e o anterior, do mesmo tamanho, para comparar. */
function periodoEstatisticas(array $q): array
{
    $hoje = new DateTimeImmutable('today');
    $minimo = $hoje->modify('-' . MESES_ESTATISTICAS . ' months');
    $chave = is_string($q['periodo'] ?? null) && (isset(PERIODOS[$q['periodo']]) || $q['periodo'] === 'datas') ? $q['periodo'] : '7dias';
    $aviso = '';
    $data = static function ($v) use ($hoje, $minimo): ?DateTimeImmutable {
        if (!is_string($v) || !preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $v, $m) || !checkdate((int) $m[2], (int) $m[3], (int) $m[1])) return null;
        $d = new DateTimeImmutable($v);
        return $d > $hoje ? $hoje : ($d < $minimo ? $minimo : $d);
    };
    if ($chave === 'datas') {
        $de = $data($q['de'] ?? null);
        $ate = $data($q['ate'] ?? null);
        if ($de === null || $ate === null) { $chave = '7dias'; $aviso = 'Escolha as duas datas (de e até) para ver outro período.'; }
        elseif ($de > $ate) [$de, $ate] = [$ate, $de];
    }
    $antes = static fn(DateTimeImmutable $de, DateTimeImmutable $ate): array => [
        $de->modify('-' . ((int) $de->diff($ate)->days + 1) . ' days'), $de->modify('-1 day')];
    switch ($chave) {
        case 'hoje':
            $de = $ate = $hoje;
            [$antDe, $antAte] = $antes($de, $ate);
            $titulo = 'Hoje';
            $comp = 'Ontem, no dia todo';
            break;
        case 'ontem':
            $de = $ate = $hoje->modify('-1 day');
            [$antDe, $antAte] = $antes($de, $ate);
            $titulo = 'Ontem';
            $comp = 'vs. anteontem';
            break;
        case '30dias':
            $de = $hoje->modify('-29 days');
            $ate = $hoje;
            [$antDe, $antAte] = $antes($de, $ate);
            $titulo = 'Últimos 30 dias';
            $comp = 'vs. os 30 dias anteriores';
            break;
        case 'mes':
            $de = $hoje->modify('first day of this month');
            $ate = $hoje;
            $antDe = $de->modify('-1 month');
            $antAte = min($antDe->modify('+' . ((int) $de->diff($ate)->days) . ' days'), $de->modify('-1 day'));
            $titulo = 'Este mês (' . MESES_PT[(int) $hoje->format('n')] . ')';
            $comp = 'vs. ' . ($antAte->format('j') === $antAte->format('t') ? MESES_PT[(int) $antDe->format('n')] . ' inteiro'
                : 'o mesmo período de ' . MESES_PT[(int) $antDe->format('n')]);
            break;
        case '12meses':
            $de = $hoje->modify('first day of this month')->modify('-11 months');
            $ate = $hoje;
            $antDe = $de->modify('-12 months');
            $antAte = min($ate->modify('-1 year'), $de->modify('-1 day'));   // até o mesmo dia do ano passado
            $titulo = 'Últimos 12 meses';
            $comp = 'vs. os 12 meses anteriores';
            break;
        case 'datas':
            [$antDe, $antAte] = $antes($de, $ate);
            $n = (int) $de->diff($ate)->days + 1;
            $titulo = $n === 1 ? 'Dia escolhido' : 'Período escolhido';
            $comp = $n === 1 ? 'vs. o dia anterior' : 'vs. os ' . $n . ' dias anteriores';
            break;
        default:   // 7dias
            $de = $hoje->modify('-6 days');
            $ate = $hoje;
            [$antDe, $antAte] = $antes($de, $ate);
            $titulo = 'Últimos 7 dias';
            $comp = 'vs. os 7 dias anteriores';
    }
    $dias = (int) $de->diff($ate)->days + 1;
    return ['chave' => $chave, 'de' => $de, 'ate' => $ate, 'antDe' => $antDe, 'antAte' => $antAte, 'titulo' => $titulo,
        'comparacao' => $comp, 'parcial' => $chave === 'hoje', 'dias' => $dias, 'porMes' => $dias > 62, 'aviso' => $aviso,
        'minimo' => $minimo, 'hoje' => $hoje];
}

/** [tipo, página, seção, rótulo] de uma chave de clique. */
function partesClique(string $k): array
{
    return explode('|', $k, 4) + ['', '', '-', '-'];
}

function cliquesPorTipo(array $T): array
{
    $out = array_fill_keys(array_keys(TIPOS_CLIQUE), 0);
    foreach ($T['cliques'] as $k => $n) {
        $tipo = partesClique((string) $k)[0];
        if (isset($out[$tipo])) $out[$tipo] += $n;
    }
    return $out;
}

/** [grupo ('assunto' ou 'lugar'), chave] do que um clique mostra de interesse, ou null. */
function assuntoDoClique(string $tipo, string $pagina, string $secao, string $rotulo): ?array
{
    if ($tipo === 'aba') return isset(LUGARES[$rotulo]) ? ['lugar', $rotulo] : null;
    if ($tipo === 'filtro') return FILTRO_INTERESSE[$rotulo] ?? null;
    if ($tipo === 'link') return DESTINO_INTERESSE[$rotulo] ?? null;
    if (!in_array($tipo, ['whatsapp', 'orcamento', 'telefone', 'email'], true)) return null;
    // pediu contato de dentro de uma solução (ex.: "Orçar som para o carro") ou na página da loja
    if ($pagina === 'solucoes' && isset(SECAO_SOLUCAO[$secao])) return ['assunto', SECAO_SOLUCAO[$secao]];
    if ($pagina === 'loja' && !in_array($secao, AREAS_COMUNS, true)) return ['assunto', 'loja'];
    return null;
}

/** "O que mais interessou": ['assunto' => [chave => [total, abriu, contato, formulario]], 'lugar' => […], 'links' => [destino => n]]. */
function interessesDoPeriodo(array $T): array
{
    $g = ['assunto' => [], 'lugar' => [], 'links' => []];
    $soma = static function (string $grupo, string $chave, string $como, int $n) use (&$g): void {
        $g[$grupo][$chave] ??= ['total' => 0, 'abriu' => 0, 'contato' => 0, 'formulario' => 0];
        $g[$grupo][$chave][$como] += $n;
        $g[$grupo][$chave]['total'] += $n;
    };
    foreach ($T['cliques'] as $k => $n) {
        [$tipo, $pagina, $secao, $rotulo] = partesClique((string) $k);
        $a = assuntoDoClique($tipo, $pagina, $secao, $rotulo);
        if ($a !== null) $soma($a[0], $a[1], in_array($tipo, ['aba', 'filtro', 'link'], true) ? 'abriu' : 'contato', $n);
        elseif ($tipo === 'link') $g['links'][$rotulo] = ($g['links'][$rotulo] ?? 0) + $n;
    }
    foreach ($T['formulario'] as $k => $n) {
        [$campo, $v] = explode(':', (string) $k, 2) + ['', ''];
        if ($campo === 'interesse' && isset(ASSUNTOS[$v])) $soma('assunto', $v, 'formulario', $n);
        if ($campo === 'lugar' && isset(LUGARES[$v])) $soma('lugar', $v, 'formulario', $n);
    }
    foreach (['assunto', 'lugar'] as $grupo) uasort($g[$grupo], static fn($x, $y) => $y['total'] <=> $x['total']);
    arsort($g['links']);
    return $g;
}

/** Contatos por tipo, com onde foram clicados: [tipo => ['total' => n, 'onde' => [[página, seção, rótulo, n], …]]]. */
function contatosDoPeriodo(array $T): array
{
    $out = [];
    foreach ($T['cliques'] as $k => $n) {
        [$tipo, $pagina, $secao, $rotulo] = partesClique((string) $k);
        if (!in_array($tipo, CONTATOS, true)) continue;
        $out[$tipo] ??= ['total' => 0, 'onde' => []];
        $out[$tipo]['total'] += $n;
        $out[$tipo]['onde'][] = [$pagina, $secao, $rotulo, $n];
    }
    foreach ($out as &$c) usort($c['onde'], static fn($x, $y) => $y[3] <=> $x[3]);
    unset($c);
    return array_replace(array_intersect_key(array_flip(CONTATOS), $out), $out);
}

function nomePagina(string $p): string
{
    return PAGINAS_SITE[$p] ?? $p;
}

function nomeSecao(string $s): string
{
    if ($s === '' || $s === '-') return '';
    return SECOES_SITE[$s] ?? ucfirst(str_replace('-', ' ', $s));
}

/** "solucoes#led" → "Soluções › Painéis de LED". */
function nomeDestino(string $d): string
{
    $p = explode('#', $d, 2);
    return nomePagina($p[0]) . (isset($p[1]) ? ' › ' . nomeSecao($p[1]) : '');
}

function nomeOrigem(string $k): string
{
    if (str_starts_with($k, 'site:')) return substr($k, 5);
    if (str_starts_with($k, 'campanha:')) return 'Link divulgado “' . substr($k, 9) . '”';
    return ORIGENS[$k] ?? $k;
}

function nomePais(string $cc): string
{
    if ($cc === 'outros') return 'Outros países';
    if (class_exists('Locale')) {
        $n = Locale::getDisplayRegion('und-' . $cc, 'pt_BR');
        if (is_string($n) && $n !== '' && $n !== $cc) return $n;
    }
    return $cc;
}

/** Visitantes e páginas de hoje, para o cartão do Início (null se não der para ler). */
function numerosDeHoje(): ?array
{
    try { $T = lerDia(date('Y-m-d')); } catch (Throwable $t) { return null; }
    return [$T['visitantes'], $T['visualizacoes']];
}

/* ------------------------------------------------------------------
   Ação
   ------------------------------------------------------------------ */
function acaoEstatisticas(): void
{
    // abrir a tela também apaga os visitantes de ontem (se ninguém visitou o site hoje, ainda estariam lá)
    try { comTrava(TRAVA_ESTATISTICAS, static fn() => limparEstatisticas(date('Y-m-d'))); }
    catch (Throwable $t) { /* fica para o próximo envio do contador ou a próxima vez que a tela abrir */ }
    $P = periodoEstatisticas($_GET);
    $atual = resumoEstatisticas($P['de'], $P['ate']);
    $antes = resumoEstatisticas($P['antDe'], $P['antAte'])['total'];
    telaEstatisticas($P, $atual, $antes, inicioDaContagem());
}

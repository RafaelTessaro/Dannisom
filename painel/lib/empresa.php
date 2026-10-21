<?php
/* =====================================================================
   Painel Danni Som — Empresa (dados/empresa.js; só administradores)
   Telefones, WhatsApp, e-mail, endereço, mapa, localização, horário,
   redes, Google e depoimentos. O horário de cada dia gera as duas
   formas que o site usa: "grade" (minutos, para o "Aberto agora") e
   "horarios" (as linhas do quadro: "Segunda a sexta" — "8h às 18h").
   Os outros campos (nome, CNPJ, sócios…) e os que o painel não conhece
   ficam exatamente como estão. Tela em lib/telas.php.
   ===================================================================== */
declare(strict_types=1);
if (!defined('PAINEL')) { http_response_code(404); exit; }

const DIAS = [1 => 'Segunda', 2 => 'Terça', 3 => 'Quarta', 4 => 'Quinta', 5 => 'Sexta', 6 => 'Sábado', 0 => 'Domingo'];
const UFS = ['AC', 'AL', 'AP', 'AM', 'BA', 'CE', 'DF', 'ES', 'GO', 'MA', 'MT', 'MS', 'MG', 'PA', 'PB', 'PR', 'PE', 'PI', 'RJ', 'RN', 'RS', 'RO', 'RR', 'SC', 'SP', 'SE', 'TO'];
const MAX_DEPOIMENTOS = 20;
const CAMPOS_FIXOS = ['nome' => 'Nome', 'slogan' => 'Slogan', 'razaoSocial' => 'Razão social', 'cnpj' => 'CNPJ', 'desde' => 'Desde', 'socios' => 'Sócios'];

/** Valor de E por caminho ("endereco.rua"), com objetos ou listas no meio. */
function valorEmpresa(array $E, string $caminho)
{
    $v = $E;
    foreach (explode('.', $caminho) as $k) {
        if (is_array($v) && array_key_exists($k, $v)) $v = $v[$k];
        elseif ($v instanceof stdClass && property_exists($v, $k)) $v = $v->$k;
        else return null;
    }
    return $v;
}

function textoEmpresa(array $E, string $caminho): string
{
    $v = valorEmpresa($E, $caminho);
    return is_string($v) || is_int($v) || is_float($v) ? (string) $v : '';
}

/** Objeto com $campos trocados; os outros campos dele ficam como estavam. */
function comCampos($atual, array $campos): stdClass
{
    $o = $atual instanceof stdClass ? clone $atual : (object) (is_array($atual) && !array_is_list($atual) ? $atual : []);
    foreach ($campos as $k => $v) $o->$k = $v;
    return $o;
}

/* ------------------------------------------------------------------
   Horário
   ------------------------------------------------------------------ */
function horaTexto(int $min): string
{
    $h = intdiv($min, 60);
    $m = $min % 60;
    return $h . 'h' . ($m ? sprintf('%02d', $m) : '');
}

function horaCampo(int $min): string
{
    return sprintf('%02d:%02d', intdiv($min, 60), $min % 60);
}

/** "08:30", "8:30", "8h30", "8h", "8" → minutos; null = inválido. */
function minutosDoForm(string $t): ?int
{
    $t = strtolower(str_replace(' ', '', $t));
    if (!preg_match('/^(\d{1,2})(?:[:h](\d{2})?)?$/', $t, $m)) return null;
    $h = (int) $m[1];
    $min = (int) ($m[2] ?? 0);
    if ($min > 59 || $h > 24 || ($h === 24 && $min > 0)) return null;
    return $h * 60 + $min;
}

/** Nome de um grupo de dias seguidos: "Segunda", "Sábado e domingo", "Segunda a sexta". */
function nomeDias(array $dias): string
{
    $primeiro = DIAS[$dias[0]];
    if (count($dias) === 1) return $primeiro;
    $ultimo = mb_strtolower(DIAS[$dias[count($dias) - 1]], 'UTF-8');
    return $primeiro . (count($dias) === 2 ? ' e ' : ' a ') . $ultimo;
}

/** As linhas do quadro de horários: dias seguidos com o mesmo horário ficam juntos (segunda → domingo). */
function linhasHorario(array $grade): array
{
    $grupos = [];
    foreach (array_keys(DIAS) as $d) {
        $iv = $grade[$d] ?? [];
        $k = json_encode($iv);
        $u = count($grupos) - 1;
        if ($u >= 0 && $grupos[$u]['k'] === $k) $grupos[$u]['dias'][] = $d;
        else $grupos[] = ['k' => $k, 'dias' => [$d], 'iv' => $iv];
    }
    return array_map(static fn(array $g): array => [
        'dias'  => nomeDias($g['dias']),
        'horas' => $g['iv'] ? implode(' e ', array_map(static fn(array $x): string => horaTexto($x[0]) . ' às ' . horaTexto($x[1]), $g['iv'])) : 'Fechado',
    ], $grupos);
}

/** Grade do arquivo ({"1": [[480, 1080]], …}) → [0 => [...], …, 6 => [...]] (dias sem horário: []). */
function gradeDaEmpresa(array $E): array
{
    $g = valorEmpresa($E, 'grade');
    $g = $g instanceof stdClass ? get_object_vars($g) : (is_array($g) ? $g : []);
    $out = [];
    for ($d = 0; $d <= 6; $d++) {
        $out[$d] = [];
        foreach (is_array($g[$d] ?? $g[(string) $d] ?? null) ? ($g[$d] ?? $g[(string) $d]) : [] as $iv) {
            if (is_array($iv) && count($iv) === 2 && is_int($iv[0]) && is_int($iv[1])) $out[$d][] = [$iv[0], $iv[1]];
        }
    }
    return $out;
}

/* ------------------------------------------------------------------
   Valores da tela (do arquivo ou do formulário)
   ------------------------------------------------------------------ */
function valoresDaEmpresa(array $E): array
{
    $geo = valorEmpresa($E, 'geo');
    $num = static fn($x): string => is_int($x) || is_float($x) ? str_replace('.', ',', (string) $x) : '';
    $dias = [];
    foreach (gradeDaEmpresa($E) as $d => $iv) {
        $dias[$d] = ['aberto' => (bool) $iv, 'a1' => isset($iv[0]) ? horaCampo($iv[0][0]) : '', 'f1' => isset($iv[0]) ? horaCampo($iv[0][1]) : '',
            'a2' => isset($iv[1]) ? horaCampo($iv[1][0]) : '', 'f2' => isset($iv[1]) ? horaCampo($iv[1][1]) : ''];
    }
    $dep = [];
    foreach (is_array(valorEmpresa($E, 'depoimentos')) ? valorEmpresa($E, 'depoimentos') : [] as $x) {
        $x = $x instanceof stdClass ? get_object_vars($x) : (array) $x;
        $dep[] = ['nome' => is_string($x['nome'] ?? null) ? $x['nome'] : '', 'texto' => is_string($x['texto'] ?? null) ? $x['texto'] : ''];
    }
    $aval = valorEmpresa($E, 'google.avaliacoes');
    return [
        'tel' => textoEmpresa($E, 'telefone'), 'tel2' => textoEmpresa($E, 'telefone2'),
        'whatsapp' => textoEmpresa($E, 'whatsapp'), 'msg' => textoEmpresa($E, 'whatsappMensagem'), 'email' => textoEmpresa($E, 'email'),
        'rua' => textoEmpresa($E, 'endereco.rua'), 'bairro' => textoEmpresa($E, 'endereco.bairro'), 'cidade' => textoEmpresa($E, 'endereco.cidade'),
        'uf' => textoEmpresa($E, 'endereco.uf'), 'cep' => textoEmpresa($E, 'endereco.cep'), 'mapa' => textoEmpresa($E, 'mapa'),
        'lat' => is_array($geo) ? $num($geo[0] ?? null) : '', 'lng' => is_array($geo) ? $num($geo[1] ?? null) : '',
        'dias' => $dias,
        'instagram' => textoEmpresa($E, 'redes.instagram'), 'facebook' => textoEmpresa($E, 'redes.facebook'),
        'nota' => textoEmpresa($E, 'google.nota'), 'avaliacoes' => is_int($aval) ? (string) $aval : textoEmpresa($E, 'google.avaliacoes'),
        'glink' => textoEmpresa($E, 'google.link'),
        'dep' => $dep,
    ];
}

function campoPost(array $p, string $k, int $max = 600): string
{
    return is_string($p[$k] ?? null) ? cortar($p[$k], $max) : '';
}

function valoresDoPost(array $p): array
{
    $v = [];
    foreach (['tel', 'tel2', 'whatsapp', 'msg', 'email', 'rua', 'bairro', 'cidade', 'uf', 'cep', 'mapa', 'lat', 'lng', 'instagram', 'facebook', 'nota', 'avaliacoes', 'glink'] as $k) {
        $v[$k] = campoPost($p, $k);
    }
    $h = is_array($p['h'] ?? null) ? $p['h'] : [];
    foreach (array_keys(DIAS) as $d) {
        $x = is_array($h[$d] ?? null) ? $h[$d] : [];
        $v['dias'][$d] = ['aberto' => ($x['aberto'] ?? '') === '1'];
        foreach (['a1', 'f1', 'a2', 'f2'] as $k) $v['dias'][$d][$k] = campoPost($x, $k, 8);
    }
    ksort($v['dias']);
    $v['dep'] = [];
    foreach (is_array($p['dep'] ?? null) ? $p['dep'] : [] as $x) {
        if (!is_array($x) || ($x['remover'] ?? '') === '1') continue;
        $l = ['nome' => campoPost($x, 'nome', 100), 'texto' => campoPost($x, 'texto', 1000)];
        if (trim($l['nome']) === '' && trim($l['texto']) === '') continue;
        $v['dep'][] = $l;
    }
    return $v;
}

/** Ajudante sem JavaScript: botão ↑ ↓ de um depoimento (mover=3:cima). Devolve a lista mudada ou null. */
function moverDepoimento(array $dep, $mover): ?array
{
    if (!is_string($mover) || !preg_match('/^(\d{1,2}):(cima|baixo)$/', $mover, $m)) return null;
    $i = (int) $m[1];
    $k = $m[2] === 'cima' ? $i - 1 : $i + 1;
    if (!isset($dep[$i], $dep[$k])) return $dep;
    [$dep[$i], $dep[$k]] = [$dep[$k], $dep[$i]];
    return $dep;
}

/* ------------------------------------------------------------------
   Conferência de cada campo: [valor limpo, erro ou null]
   ------------------------------------------------------------------ */
function linhaEmpresa(string $v, string $rotulo, int $min, int $max): array
{
    $t = limparLinha($v);
    if ($t === null) return ['', $rotulo . ': há caracteres inválidos. Apague e digite de novo.'];
    if ($t === '') return ['', $min > 0 ? $rotulo . ': preencha este campo.' : null];
    if (tamanho($t) < $min) return [$t, $rotulo . ': escreva pelo menos ' . $min . ' caracteres.'];
    if (tamanho($t) > $max) return [$t, $rotulo . ': no máximo ' . $max . ' caracteres (tem ' . tamanho($t) . ').'];
    if (preg_match('/[<>]/', $t)) return [$t, $rotulo . ': não use os sinais < e >.'];
    return [$t, null];
}

/** Telefone com DDD → [só os dígitos sem o 55, erro]. Aceita "(19) 3523-3198", "19 99999-9999", "+55 19 …". */
function telefoneEmpresa(string $v, string $rotulo, bool $obrig): array
{
    $t = trim($v);
    if ($t === '') return ['', $obrig ? $rotulo . ': preencha com o DDD (ex.: (19) 3523-3198).' : null];
    if (preg_match('/[^\d\s()+.\-]/', $t)) return ['', $rotulo . ': use só números, com o DDD (ex.: (19) 3523-3198).'];
    $d = ltrim(preg_replace('/\D+/', '', $t) ?? '', '0');
    if ((strlen($d) === 12 || strlen($d) === 13) && str_starts_with($d, '55')) $d = substr($d, 2);
    if (strlen($d) < 10 || strlen($d) > 11 || !preg_match('/^[1-9]{2}/', $d)) return ['', $rotulo . ': confira o número, com o DDD (ex.: (19) 3523-3198 ou (19) 99999-9999).'];
    if (strlen($d) === 11 && $d[2] !== '9') return ['', $rotulo . ': celular com 9 dígitos começa com 9 (ex.: (19) 99999-9999).'];
    return [$d, null];
}

function formatarTelefone(string $d): string
{
    if (strlen($d) === 11) return '(' . substr($d, 0, 2) . ') ' . substr($d, 2, 5) . '-' . substr($d, 7);
    return '(' . substr($d, 0, 2) . ') ' . substr($d, 2, 4) . '-' . substr($d, 6);
}

/** WhatsApp: só dígitos, 55 + DDD + número. '' = sem WhatsApp. */
function whatsappEmpresa(string $v): array
{
    $t = trim($v);
    if ($t === '') return ['', null];
    $d = preg_replace('/\D+/', '', $t) ?? '';
    if (strlen($d) === 10 || strlen($d) === 11) $d = '55' . ltrim($d, '0');
    if (!preg_match('/^55[1-9]{2}\d{8,9}$/', $d)) return [$d, 'WhatsApp: use 55 + DDD + número, só números (ex.: 5519999999999).'];
    return [$d, null];
}

function linkEmpresa(string $v, string $rotulo, array $hosts = []): array
{
    $t = limparLinha($v) ?? '';
    if ($t === '') return ['', null];
    if (!preg_match('~^[a-z][a-z0-9+.-]*://~i', $t)) $t = 'https://' . $t;
    elseif (preg_match('~^http://~i', $t)) $t = 'https://' . substr($t, 7);
    $p = parse_url($t);
    if (strlen($t) > 500 || preg_match('/[\s"\'<>\\\\`{}|^]/', $t) || !is_array($p) || strtolower((string) ($p['scheme'] ?? '')) !== 'https'
        || empty($p['host']) || isset($p['user']) || isset($p['pass'])) {
        return [$t, $rotulo . ': o link precisa começar com https:// e não pode ter espaços. Copie de novo.'];
    }
    $host = strtolower($p['host']);
    if ($hosts && !in_array($host, $hosts, true)) return [$t, $rotulo . ': use o link da página em ' . $hosts[0] . '.'];
    return [$t, null];
}

function numeroGeo(string $v, float $lim, string $rotulo): array
{
    $t = str_replace(',', '.', trim($v));
    if (!preg_match('/^-?\d{1,3}(?:\.\d+)?$/', $t) || abs((float) $t) > $lim) return [null, $rotulo . ': use um número como -22,3997744 (entre -' . (int) $lim . ' e ' . (int) $lim . ').'];
    return [round((float) $t, 7), null];
}

/** Confere o formulário inteiro. Devolve [campos novos para gravar, erros por campo]. */
function conferirEmpresa(array $v): array
{
    $err = [];
    $ok = static function (array $r, string $k) use (&$err) { if ($r[1] !== null) $err[$k] = $r[1]; return $r[0]; };
    $tel = $ok(telefoneEmpresa($v['tel'], 'Telefone', true), 'tel');
    $tel2 = $ok(telefoneEmpresa($v['tel2'], 'Telefone 2', false), 'tel2');
    $wa = $ok(whatsappEmpresa($v['whatsapp']), 'whatsapp');
    $msg = $ok(linhaEmpresa($v['msg'], 'Mensagem do WhatsApp', 0, 200), 'msg');
    $email = strtolower(trim($v['email']));
    if ($email !== '' && (strlen($email) > 120 || !filter_var($email, FILTER_VALIDATE_EMAIL))) $err['email'] = 'E-mail: confira o endereço (ex.: contato@dannisom.com.br).';
    $rua = $ok(linhaEmpresa($v['rua'], 'Rua e número', 3, 100), 'rua');
    $bairro = $ok(linhaEmpresa($v['bairro'], 'Bairro', 0, 60), 'bairro');
    $cidade = $ok(linhaEmpresa($v['cidade'], 'Cidade', 2, 60), 'cidade');
    $uf = strtoupper(trim($v['uf']));
    if (!in_array($uf, UFS, true)) $err['uf'] = 'Estado: escolha a sigla (ex.: SP).';
    $cep = preg_replace('/\D+/', '', $v['cep']) ?? '';
    if ($cep !== '' && strlen($cep) !== 8) $err['cep'] = 'CEP: use os 8 números (ex.: 13500-220).';
    $cep = $cep !== '' ? substr($cep, 0, 5) . '-' . substr($cep, 5) : '';
    $mapa = $ok(linkEmpresa($v['mapa'], 'Link do mapa'), 'mapa');
    $geo = null;
    if (trim($v['lat']) !== '' || trim($v['lng']) !== '') {
        $lat = $ok(numeroGeo($v['lat'], 90, 'Latitude'), 'lat');
        $lng = $ok(numeroGeo($v['lng'], 180, 'Longitude'), 'lng');
        if ($lat !== null && $lng !== null) $geo = [$lat, $lng];
    }

    // Horário
    $grade = [];
    foreach ($v['dias'] as $d => $x) {
        $grade[$d] = [];
        if (!$x['aberto']) continue;
        $nome = DIAS[$d];
        $a1 = minutosDoForm($x['a1']); $f1 = minutosDoForm($x['f1']);
        if ($a1 === null || $f1 === null) { $err['h-' . $d] = $nome . ': preencha a hora de abrir e de fechar (ex.: 08:00 e 18:00), ou marque Fechado.'; continue; }
        if ($a1 >= $f1) { $err['h-' . $d] = $nome . ': a hora de fechar precisa ser depois da de abrir.'; continue; }
        $grade[$d][] = [$a1, $f1];
        if (trim($x['a2']) !== '' || trim($x['f2']) !== '') {
            $a2 = minutosDoForm($x['a2']); $f2 = minutosDoForm($x['f2']);
            if ($a2 === null || $f2 === null) { $err['h-' . $d] = $nome . ': no 2º horário, preencha abrir e fechar (ou deixe os dois vazios).'; continue; }
            if ($a2 < $f1 || $a2 >= $f2) { $err['h-' . $d] = $nome . ': o 2º horário precisa começar depois do 1º e fechar depois de abrir.'; continue; }
            $grade[$d][] = [$a2, $f2];
        }
    }
    if (!isset($err['h-1']) && !array_filter($grade) && !preg_grep('/^h-/', array_keys($err))) $err['h-1'] = 'Horário: todos os dias estão como Fechado. Marque os dias em que a loja abre.';

    $ig = $ok(linkEmpresa(preg_match('/^@?([A-Za-z0-9._]{1,30})$/', trim($v['instagram']), $m) && !str_contains(trim($v['instagram']), '.com') ? 'https://www.instagram.com/' . $m[1] . '/' : $v['instagram'],
        'Instagram', ['www.instagram.com', 'instagram.com']), 'instagram');
    $fb = $ok(linkEmpresa($v['facebook'], 'Facebook', ['www.facebook.com', 'facebook.com', 'm.facebook.com', 'fb.com', 'www.fb.com']), 'facebook');
    $igUsuario = '';
    if ($ig !== '' && preg_match('~^https://(?:www\.)?instagram\.com/([A-Za-z0-9._]{1,30})/?~', $ig, $m)) $igUsuario = '@' . $m[1];

    $nota = str_replace('.', ',', trim($v['nota']));
    if (!preg_match('/^[1-5](?:,\d)?$/', $nota) || (float) str_replace(',', '.', $nota) > 5) $err['nota'] = 'Nota do Google: use um número de 1,0 a 5,0 (ex.: 4,8).';
    elseif (!str_contains($nota, ',')) $nota .= ',0';
    $aval = preg_replace('/[\s.]/', '', $v['avaliacoes']) ?? '';
    if (!preg_match('/^\d{1,6}$/', $aval)) $err['avaliacoes'] = 'Avaliações: use só o número (ex.: 810).';
    $glink = $ok(linkEmpresa($v['glink'], 'Link das avaliações'), 'glink');

    $dep = [];
    foreach ($v['dep'] as $i => $x) {
        $n = $ok(linhaEmpresa($x['nome'], 'Depoimento ' . ($i + 1) . ', nome', 2, 40), 'dep-' . $i . '-nome');
        $t = $ok(linhaEmpresa($x['texto'], 'Depoimento ' . ($i + 1) . ', texto', 10, 400), 'dep-' . $i . '-texto');
        $dep[] = ['nome' => $n, 'texto' => $t];
    }
    if (count($dep) > MAX_DEPOIMENTOS) $err['dep'] = 'Depoimentos: no máximo ' . MAX_DEPOIMENTOS . '.';

    $novos = [
        'telefone' => $tel !== '' ? formatarTelefone($tel) : '', 'telefoneLink' => $tel !== '' ? '+55' . $tel : '',
        'telefone2' => $tel2 !== '' ? formatarTelefone($tel2) : '', 'telefone2Link' => $tel2 !== '' ? '+55' . $tel2 : '',
        'whatsapp' => $wa, 'whatsappMensagem' => $msg, 'email' => $email,
        'endereco' => ['rua' => $rua, 'bairro' => $bairro, 'cidade' => $cidade, 'uf' => $uf, 'cep' => $cep],
        'mapa' => $mapa, 'geo' => $geo,
        'grade' => $grade, 'horarios' => linhasHorario($grade),
        'redes' => ['instagram' => $ig, 'facebook' => $fb], 'instagramUsuario' => $igUsuario,
        'google' => ['nota' => $nota, 'avaliacoes' => (int) $aval, 'link' => $glink],
        'depoimentos' => $dep,
    ];
    return [$novos, $err];
}

/** Aplica os campos conferidos sobre os dados atuais (o resto fica igual). */
function aplicarEmpresa(array $E, array $n): array
{
    foreach (['telefone', 'telefoneLink', 'telefone2', 'telefone2Link', 'whatsapp', 'whatsappMensagem', 'email', 'mapa', 'instagramUsuario'] as $k) $E[$k] = $n[$k];
    $E['endereco'] = comCampos($E['endereco'] ?? null, $n['endereco']);
    $E['redes'] = comCampos($E['redes'] ?? null, $n['redes']);
    $E['google'] = comCampos($E['google'] ?? null, $n['google']);
    $E['horarios'] = $n['horarios'];
    $g = new stdClass();
    foreach ($n['grade'] as $d => $iv) if ($iv) $g->{(string) $d} = $iv;
    $E['grade'] = $g;
    if ($n['geo'] !== null) $E['geo'] = $n['geo']; else unset($E['geo']);
    $E['depoimentos'] = $n['depoimentos'];
    return $E;
}

/** O que mudou, em palavras ("contato, horário"). */
function mudancasEmpresa(array $antes, array $depois): string
{
    $partes = [
        'contato' => ['telefone', 'telefone2', 'whatsapp', 'whatsappMensagem', 'email'],
        'endereço' => ['endereco', 'mapa', 'geo'],
        'horário' => ['grade', 'horarios'],
        'redes e Google' => ['redes', 'instagramUsuario', 'google'],
        'depoimentos' => ['depoimentos'],
    ];
    $m = [];
    foreach ($partes as $nome => $ks) {
        foreach ($ks as $k) if (!mesmosDados($antes[$k] ?? null, $depois[$k] ?? null)) { $m[] = $nome; break; }
    }
    return implode(', ', $m);
}

function linkWhatsapp(string $digitos, string $msg): string
{
    return $digitos === '' ? '' : 'https://wa.me/' . $digitos . '?text=' . rawurlencode($msg !== '' ? $msg : 'Olá!');
}

/* ------------------------------------------------------------------
   Ações
   ------------------------------------------------------------------ */
function acaoEmpresa(): void
{
    $E = lerArea('empresa')['dados'];
    telaEmpresa($E, valoresDaEmpresa($E));
}

function acaoEmpresaSalvar(): void
{
    $E = lerArea('empresa')['dados'];
    $v = valoresDoPost($_POST);
    $movida = moverDepoimento($v['dep'], $_POST['mover'] ?? null);
    if ($movida !== null) {   // ↑ ↓ sem JavaScript: só muda a ordem na tela
        $v['dep'] = $movida;
        telaEmpresa($E, $v, [], 'A ordem dos depoimentos mudou, mas ainda não foi salva. Toque em Salvar para gravar.');
        return;
    }
    if (($_POST['mais'] ?? '') === '1') {   // "Adicionar depoimento" sem JavaScript
        telaEmpresa($E, $v, [], '', count($v['dep']) + 1);
        return;
    }
    [$novos, $erros] = conferirEmpresa($v);
    if ($erros) {
        http_response_code(422);
        telaEmpresa($E, $v, $erros);
        return;
    }
    $mudou = '';
    $res = alterarArea('empresa', ['tipo' => 'editar', 'antes' => 'Antes de: alterar os dados da empresa', 'feito' => 'Alterou os dados da empresa'],
        static function (array $atual, array &$acao) use ($novos, &$mudou): array {
            $depois = aplicarEmpresa($atual, $novos);
            $mudou = mudancasEmpresa($atual, $depois);
            if ($mudou !== '') {
                $acao['feito'] .= ' (' . $mudou . ')';
                $acao['antes'] = 'Antes de: alterar os dados da empresa (' . $mudou . ')';
            }
            return $depois;
        });
    if (!$res['mudou']) {
        avisar('Nada mudou nos dados da empresa.', 'info');
    } else {
        avisar('Dados da empresa salvos' . ($mudou !== '' ? ' (' . $mudou . ')' : '') . '. O site já mostra assim.', 'ok',
            ['link' => cfg('url_site') . 'contato.html', 'textoLink' => 'Ver no site', 'externo' => true]);
    }
    redirecionar(link_('empresa'));
}

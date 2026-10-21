<?php
/* =====================================================================
   Painel Danni Som — logos (imagens enviadas)
   Recebe PNG, JPG ou WebP (até 5 MB; SVG não, por segurança), confere o
   tipo pelo conteúdo (finfo + getimagesize), abre com GD e REDESENHA a
   imagem (nada do arquivo original sobrevive: metadados, código
   escondido): corrige a rotação do celular, corta as margens vazias
   (transparentes ou da cor do fundo), põe uma margem, reduz para caber
   na caixa do site e salva em WebP com transparência (PNG se o servidor
   não gravar WebP). Opcional: "remover fundo claro" (a cor dos cantos
   vira transparente), para as marcas que o site pinta pela transparência.
   ===================================================================== */
declare(strict_types=1);
if (!defined('PAINEL')) { http_response_code(404); exit; }

final class ErroImagem extends RuntimeException {}

const TIPOS_IMAGEM = ['image/png' => IMAGETYPE_PNG, 'image/jpeg' => IMAGETYPE_JPEG, 'image/webp' => IMAGETYPE_WEBP];

function bytesIni(string $v): int
{
    $v = trim($v);
    if ($v === '' || $v === '-1') return -1;
    $n = (int) $v;
    switch (strtolower(substr($v, -1))) {
        case 'g': $n *= 1024; // segue
        case 'm': $n *= 1024; // segue
        case 'k': $n *= 1024;
    }
    return $n;
}

/** Maior arquivo que o servidor aceita receber agora (menor entre o painel e o PHP). */
function limiteEnvio(): int
{
    $lim = [(int) cfg('logo_max_bytes')];
    foreach (['upload_max_filesize', 'post_max_size'] as $k) {
        $b = bytesIni((string) ini_get($k));
        if ($b > 0) $lim[] = $b;
    }
    return min($lim);
}

function mb_(int $bytes): string
{
    return str_replace('.', ',', (string) round($bytes / 1048576, 1)) . ' MB';
}

function garantirMemoria(int $pixels): void
{
    $precisa = (int) ($pixels * 4 * 2.6) + 48 * 1048576;
    $atual = bytesIni((string) ini_get('memory_limit'));
    if ($atual !== -1 && $atual < $precisa) @ini_set('memory_limit', (string) $precisa);
}

/** Arquivo enviado no campo $campo: caminho temporário, ou null se nenhum foi escolhido. */
function arquivoEnviado(string $campo): ?string
{
    $arq = $_FILES[$campo] ?? null;
    if (!is_array($arq) || !isset($arq['error'], $arq['tmp_name']) || is_array($arq['error'])) return null;
    switch ((int) $arq['error']) {
        case UPLOAD_ERR_OK: break;
        case UPLOAD_ERR_NO_FILE: return null;
        case UPLOAD_ERR_FORM_SIZE: throw new ErroImagem('O arquivo passa de ' . mb_((int) cfg('logo_max_bytes')) . ', o máximo para logos. Use um arquivo menor (logo não precisa ser grande).');
        case UPLOAD_ERR_INI_SIZE: throw new ErroImagem('O arquivo é maior do que o servidor aceita (' . mb_(limiteEnvio()) . '). Use um logo menor.');
        case UPLOAD_ERR_PARTIAL: throw new ErroImagem('O envio foi interrompido. Tente de novo.');
        default: throw new ErroImagem('O servidor não conseguiu receber o arquivo (erro ' . (int) $arq['error'] . '). Tente de novo.');
    }
    $tmp = (string) $arq['tmp_name'];
    if (!is_uploaded_file($tmp)) throw new ErroImagem('Envio de arquivo inválido.');
    return $tmp;
}

/* ------------------------------------------------------------------
   Rotação gravada pelo celular (EXIF Orientation), sem a extensão exif
   ------------------------------------------------------------------ */
function orientacaoExif(string $arquivo): int
{
    $f = @fopen($arquivo, 'rb');
    if (!$f) return 1;
    $d = (string) fread($f, 262144);
    fclose($f);
    if (strncmp($d, "\xFF\xD8", 2) !== 0) return 1;
    $p = 2;
    $n = strlen($d);
    while ($p + 4 <= $n) {
        if ($d[$p] !== "\xFF") return 1;
        $m = ord($d[$p + 1]);
        if ($m === 0xFF) { $p++; continue; }
        if ($m === 0x01 || ($m >= 0xD0 && $m <= 0xD8)) { $p += 2; continue; }
        if ($m === 0xDA || $m === 0xD9) return 1;
        $len = unpack('n', substr($d, $p + 2, 2))[1];
        if ($len < 2) return 1;
        if ($m === 0xE1 && substr($d, $p + 4, 6) === "Exif\0\0") return orientacaoTiff(substr($d, $p + 10, $len - 8));
        $p += 2 + $len;
    }
    return 1;
}

function orientacaoTiff(string $t): int
{
    $tam = strlen($t);
    if ($tam < 8) return 1;
    $ordem = substr($t, 0, 2);
    if ($ordem === 'II') { $u16 = 'v'; $u32 = 'V'; }
    elseif ($ordem === 'MM') { $u16 = 'n'; $u32 = 'N'; }
    else return 1;
    $ifd = unpack($u32, substr($t, 4, 4))[1];
    if ($ifd < 8 || $ifd + 2 > $tam) return 1;
    $num = unpack($u16, substr($t, $ifd, 2))[1];
    for ($i = 0; $i < $num && $i < 256; $i++) {
        $e = $ifd + 2 + $i * 12;
        if ($e + 12 > $tam) break;
        if (unpack($u16, substr($t, $e, 2))[1] === 0x0112) {
            $v = unpack($u16, substr($t, $e + 8, 2))[1];
            return ($v >= 1 && $v <= 8) ? $v : 1;
        }
    }
    return 1;
}

function aplicarOrientacao(GdImage $img, int $o): GdImage
{
    $girar = static function (GdImage $i, int $graus): GdImage {
        $r = imagerotate($i, $graus, imagecolorallocatealpha($i, 0, 0, 0, 127));
        if (!$r) throw new ErroImagem('Não consegui girar a imagem.');
        imagealphablending($r, false);
        imagesavealpha($r, true);
        return $r;
    };
    switch ($o) {
        case 2: imageflip($img, IMG_FLIP_HORIZONTAL); return $img;
        case 3: return $girar($img, 180);
        case 4: imageflip($img, IMG_FLIP_VERTICAL); return $img;
        case 5: $r = $girar($img, 270); imageflip($r, IMG_FLIP_HORIZONTAL); return $r;
        case 6: return $girar($img, 270);
        case 7: $r = $girar($img, 90); imageflip($r, IMG_FLIP_HORIZONTAL); return $r;
        case 8: return $girar($img, 90);
    }
    return $img;
}

/* ------------------------------------------------------------------
   Abrir com segurança
   ------------------------------------------------------------------ */
/** O servidor tem o que o painel precisa para tratar logos? Devolve o que falta ('' = nada). */
function faltaParaImagens(): string
{
    $falta = [];
    if (!function_exists('imagecreatetruecolor')) $falta[] = 'gd';
    if (!class_exists('finfo')) $falta[] = 'fileinfo';
    return implode(' e ', $falta);
}

function abrirLogo(string $arq): GdImage
{
    if (($falta = faltaParaImagens()) !== '') {
        throw new ErroImagem('O PHP do servidor está sem a extensão ' . $falta . '. Ligue em hPanel → Avançado → Configuração do PHP → Extensões do PHP e tente de novo.');
    }
    $max = (int) cfg('logo_max_bytes');
    $tam = (int) @filesize($arq);
    if ($tam <= 0) throw new ErroImagem('O arquivo chegou vazio.');
    if ($tam > $max) throw new ErroImagem('O arquivo tem ' . mb_($tam) . '; o máximo é ' . mb_($max) . '. Logo não precisa ser grande: use um menor.');

    $mime = (string) (new finfo(FILEINFO_MIME_TYPE))->file($arq);
    $inicio = (string) @file_get_contents($arq, false, null, 0, 1024);
    if (in_array($mime, ['image/svg+xml', 'image/svg', 'text/xml', 'application/xml'], true) || preg_match('/^\s*(<\?xml|<svg|<!DOCTYPE\s+svg)/i', $inicio)) {
        throw new ErroImagem('Logo em SVG não é aceito (por segurança). Peça o logo em PNG, de preferência com fundo transparente.');
    }
    if (in_array($mime, ['image/heic', 'image/heif', 'image/heic-sequence', 'image/heif-sequence'], true)) {
        throw new ErroImagem('Esse arquivo está em HEIC (formato de foto do iPhone). Salve o logo como PNG ou JPG e escolha de novo.');
    }
    if ($mime === 'application/pdf') throw new ErroImagem('Esse arquivo é um PDF. Salve o logo como PNG (ou tire um print dele) e escolha de novo.');
    if ($mime === 'image/gif') throw new ErroImagem('GIF não é aceito. Salve o logo como PNG e escolha de novo.');
    if (!isset(TIPOS_IMAGEM[$mime])) throw new ErroImagem('Esse arquivo não é uma imagem PNG, JPG ou WebP.');
    $info = @getimagesize($arq);
    if (!is_array($info) || ($info[2] ?? null) !== TIPOS_IMAGEM[$mime] || ($info['mime'] ?? '') !== $mime) {
        throw new ErroImagem('Esse arquivo não é uma imagem válida (ou está danificado).');
    }
    [$w, $h] = $info;
    $min = max(1, (int) cfg('logo_min_largura'));
    if ($w < $min || $h < 16) throw new ErroImagem('O logo é pequeno demais (' . $w . ' × ' . $h . ' px). Use um com pelo menos ' . $min . ' px de largura (o ideal é 400 px ou mais).');
    if ($w > 8000 || $h > 8000 || $w * $h > (int) cfg('logo_max_pixels')) {
        throw new ErroImagem('O logo tem resolução alta demais (' . $w . ' × ' . $h . ' px). Reduza para até 3000 px de largura e escolha de novo.');
    }

    garantirMemoria($w * $h);
    $img = match ($mime) {
        'image/jpeg' => @imagecreatefromjpeg($arq),
        'image/png'  => @imagecreatefrompng($arq),
        'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($arq) : false,
    };
    if (!$img) throw new ErroImagem('Não consegui abrir a imagem (arquivo danificado, animado ou formato não suportado). Salve de novo como PNG e tente outra vez.');
    if (!imageistruecolor($img)) imagepalettetotruecolor($img);
    imagealphablending($img, false);
    imagesavealpha($img, true);
    if ($mime === 'image/jpeg') $img = aplicarOrientacao($img, orientacaoExif($arq));
    return $img;
}

/* ------------------------------------------------------------------
   Análise (numa miniatura): fundo, área do desenho, transparência
   ------------------------------------------------------------------ */
function miniatura(GdImage $img, int $max): GdImage
{
    $w = imagesx($img); $h = imagesy($img);
    $f = min(1, $max / max($w, $h));
    $tw = max(1, (int) round($w * $f)); $th = max(1, (int) round($h * $f));
    $t = imagecreatetruecolor($tw, $th);
    imagealphablending($t, false);
    imagesavealpha($t, true);
    imagefill($t, 0, 0, imagecolorallocatealpha($t, 255, 255, 255, 127));
    imagecopyresampled($t, $img, 0, 0, 0, 0, $tw, $th, $w, $h);
    return $t;
}

/** Diferença entre duas cores (o maior canal), de 0 a 255. */
function distanciaCor(int $c, array $rgb): int
{
    return max(abs((($c >> 16) & 255) - $rgb[0]), abs((($c >> 8) & 255) - $rgb[1]), abs(($c & 255) - $rgb[2]));
}

function corMediana(array $cores): array
{
    $canal = static function (int $desloca) use ($cores): int {
        $v = array_map(static fn(int $c): int => ($c >> $desloca) & 255, $cores);
        sort($v);
        return $v[intdiv(count($v), 2)];
    };
    return [$canal(16), $canal(8), $canal(0)];
}

/**
 * ['fundo' => 'transparente' | [r, g, b] (cor lisa) | null (sem fundo uniforme),
 *  'cantos' => [r, g, b] | null (cor dos 4 cantos, se forem iguais),
 *  'caixa' => [x0, y0, x1, y1] do desenho em pixels da imagem, 'claro' => desenho quase todo branco]
 */
function analisarLogo(GdImage $img): array
{
    $t = miniatura($img, 400);
    $tw = imagesx($t); $th = imagesy($t);
    $borda = [];
    for ($x = 0; $x < $tw; $x++) { $borda[] = imagecolorat($t, $x, 0); $borda[] = imagecolorat($t, $x, $th - 1); }
    for ($y = 1; $y < $th - 1; $y++) { $borda[] = imagecolorat($t, 0, $y); $borda[] = imagecolorat($t, $tw - 1, $y); }
    $nb = count($borda);
    $transp = count(array_filter($borda, static fn(int $c): bool => (($c >> 24) & 0x7F) > 100));
    $opacos = array_values(array_filter($borda, static fn(int $c): bool => (($c >> 24) & 0x7F) < 30));
    $fundo = null;
    if ($transp >= $nb * 0.5) {
        $fundo = 'transparente';
    } elseif (count($opacos) >= $nb * 0.5) {
        $cor = corMediana($opacos);
        $iguais = count(array_filter($opacos, static fn(int $c): bool => distanciaCor($c, $cor) <= 40));
        if ($iguais >= $nb * 0.6) $fundo = $cor;
    }
    // Cor dos cantos (3 × 3 px em cada canto), para "remover fundo"
    $cantos = [];
    foreach ([[0, 0], [$tw - 3, 0], [0, $th - 3], [$tw - 3, $th - 3]] as [$cx, $cy]) {
        for ($y = max(0, $cy); $y < min($th, $cy + 3); $y++) for ($x = max(0, $cx); $x < min($tw, $cx + 3); $x++) {
            $c = imagecolorat($t, $x, $y);
            if ((($c >> 24) & 0x7F) < 30) $cantos[] = $c;
        }
    }
    $corCantos = null;
    if (count($cantos) >= 18) {
        $m = corMediana($cantos);
        if (count(array_filter($cantos, static fn(int $c): bool => distanciaCor($c, $m) <= 40)) >= count($cantos) * 0.75) $corCantos = $m;
    }

    $x0 = $tw; $y0 = $th; $x1 = -1; $y1 = -1; $vis = 0; $claros = 0;
    for ($y = 0; $y < $th; $y++) {
        for ($x = 0; $x < $tw; $x++) {
            $c = imagecolorat($t, $x, $y);
            if ((($c >> 24) & 0x7F) > 100) continue;
            if (is_array($fundo) && distanciaCor($c, $fundo) <= 32) continue;
            if ($x < $x0) $x0 = $x; if ($x > $x1) $x1 = $x;
            if ($y < $y0) $y0 = $y; if ($y > $y1) $y1 = $y;
            $vis++;
            if (min(($c >> 16) & 255, ($c >> 8) & 255, $c & 255) > 225) $claros++;
        }
    }
    if ($x1 < 0) throw new ErroImagem('O logo parece estar vazio (todo transparente ou de uma cor só). Confira o arquivo e escolha de novo.');
    // De volta à escala da imagem, com 1 px de folga da miniatura; depois, a borda exata no tamanho real
    $w = imagesx($img); $h = imagesy($img);
    $sx = $w / $tw; $sy = $h / $th;
    $caixa = [max(0, (int) floor(($x0 - 1) * $sx)), max(0, (int) floor(($y0 - 1) * $sy)), min($w, (int) ceil(($x1 + 2) * $sx)), min($h, (int) ceil(($y1 + 2) * $sy))];
    $caixa = refinarCaixa($img, $caixa, $fundo);
    return ['fundo' => $fundo, 'cantos' => is_array($fundo) ? $fundo : $corCantos, 'caixa' => $caixa,
        'claro' => $fundo === 'transparente' && $vis > 0 && $claros / $vis > 0.85];
}

/** Pixel faz parte do desenho? (não é transparente nem da cor do fundo) */
function ehDesenho(GdImage $img, int $x, int $y, $fundo): bool
{
    $c = imagecolorat($img, $x, $y);
    if ((($c >> 24) & 0x7F) > 120) return false;   // borda suave (quase transparente) ainda conta
    return !(is_array($fundo) && distanciaCor($c, $fundo) <= 24);
}

/** Aperta a caixa achada na miniatura até a primeira linha/coluna com desenho, no tamanho real. */
function refinarCaixa(GdImage $img, array $c, $fundo): array
{
    [$x0, $y0, $x1, $y1] = $c;
    $linha = static function (int $y) use ($img, &$x0, &$x1, $fundo): bool {
        for ($x = $x0; $x < $x1; $x++) if (ehDesenho($img, $x, $y, $fundo)) return true;
        return false;
    };
    $coluna = static function (int $x) use ($img, &$y0, &$y1, $fundo): bool {
        for ($y = $y0; $y < $y1; $y++) if (ehDesenho($img, $x, $y, $fundo)) return true;
        return false;
    };
    while ($y0 < $y1 - 1 && !$linha($y0)) $y0++;
    while ($y1 - 1 > $y0 && !$linha($y1 - 1)) $y1--;
    while ($x0 < $x1 - 1 && !$coluna($x0)) $x0++;
    while ($x1 - 1 > $x0 && !$coluna($x1 - 1)) $x1--;
    return [$x0, $y0, $x1, $y1];
}

/** A cor $rgb (e as parecidas) vira transparente; perto do limite, meio transparente (borda suave). */
function removerFundo(GdImage $img, array $rgb, int $t1 = 28, int $t2 = 90): void
{
    imagealphablending($img, false);
    $w = imagesx($img); $h = imagesy($img);
    for ($y = 0; $y < $h; $y++) {
        for ($x = 0; $x < $w; $x++) {
            $c = imagecolorat($img, $x, $y);
            $a = ($c >> 24) & 0x7F;
            if ($a === 127) continue;
            $d = distanciaCor($c, $rgb);
            if ($d >= $t2) continue;
            $k = $d <= $t1 ? 0.0 : ($d - $t1) / ($t2 - $t1);
            imagesetpixel($img, $x, $y, ((127 - (int) round((127 - $a) * $k)) << 24) | ($c & 0xFFFFFF));
        }
    }
}

/** Parte transparente da imagem (0 a 1), contada numa miniatura. */
function parteTransparente(GdImage $img): float
{
    $t = miniatura($img, 200);
    $tw = imagesx($t); $th = imagesy($t);
    $n = 0;
    for ($y = 0; $y < $th; $y++) for ($x = 0; $x < $tw; $x++) if (((imagecolorat($t, $x, $y) >> 24) & 0x7F) > 100) $n++;
    return $n / ($tw * $th);
}

/* ------------------------------------------------------------------
   Processar e salvar
   ------------------------------------------------------------------ */
/**
 * $op = ['pasta' => 'clientes'|'marcas', 'id' => id, 'caixa' => [w, h], 'margem' => 0.04,
 *        'remover_fundo' => bool, 'exigir_transparencia' => bool]
 * Devolve ['logo' => 'midia/<pasta>/<arquivo>', 'w', 'h', 'claro' => bool].
 */
function processarLogo(string $arq, array $op): array
{
    if (!preg_match(RE_ID, (string) $op['id']) || !in_array($op['pasta'], ['clientes', 'marcas'], true)) throw new ErroImagem('Identificador inválido para o logo.');
    $img = abrirLogo($arq);
    $an = analisarLogo($img);
    [$x0, $y0, $x1, $y1] = $an['caixa'];
    $cw = max(1, $x1 - $x0); $ch = max(1, $y1 - $y0);
    $remover = !empty($op['remover_fundo']);
    if ($remover && $an['fundo'] !== 'transparente' && $an['cantos'] === null) {
        throw new ErroImagem('Não achei uma cor de fundo lisa nos cantos do logo para remover. Use um logo com fundo transparente (PNG) ou com fundo de uma cor só.');
    }

    $p = (int) round(max(0, (float) ($op['margem'] ?? 0)) * max($cw, $ch));
    [$bw, $bh] = $op['caixa'];
    $f = min(1, $bw / ($cw + 2 * $p), $bh / ($ch + 2 * $p));
    $ow = max(1, (int) round(($cw + 2 * $p) * $f));
    $oh = max(1, (int) round(($ch + 2 * $p) * $f));
    $pp = (int) round($p * $f);

    // Redesenha numa tela nova (nada do arquivo original sobrevive)
    $out = imagecreatetruecolor($ow, $oh);
    imagealphablending($out, false);
    imagesavealpha($out, true);
    $fundoLiso = is_array($an['fundo']) && !$remover;
    imagefill($out, 0, 0, $fundoLiso ? imagecolorallocatealpha($out, $an['fundo'][0], $an['fundo'][1], $an['fundo'][2], 0) : imagecolorallocatealpha($out, 255, 255, 255, 127));
    imagecopyresampled($out, $img, $pp, $pp, $x0, $y0, max(1, $ow - 2 * $pp), max(1, $oh - 2 * $pp), $cw, $ch);
    imagedestroy($img);

    if ($remover && $an['fundo'] !== 'transparente') removerFundo($out, $an['cantos']);
    if (!empty($op['exigir_transparencia']) && parteTransparente($out) < 0.02) {
        throw new ErroImagem('Este logo não tem fundo transparente: no site ele viraria um retângulo. Marque “Remover fundo claro” ou “Mostrar colorido (quadro branco)”, ou use um PNG com fundo transparente.');
    }
    $nome = salvarLogo($out, $op['pasta'], $op['id'] . '-' . bin2hex(random_bytes(3)));
    return ['logo' => 'midia/' . $op['pasta'] . '/' . $nome, 'w' => $ow, 'h' => $oh, 'claro' => $an['claro']];
}

/** Grava em midia/<pasta>/ (WebP com transparência; PNG se o servidor não gravar WebP). Devolve o nome do arquivo. */
function salvarLogo(GdImage $img, string $pasta, string $base): string
{
    protegerPastasMidia();
    $dir = cfg('pasta_site') . '/midia/' . $pasta;
    if (!@is_dir($dir) || !@is_writable($dir)) throw new ErroImagem('Não consigo gravar na pasta midia/' . $pasta . ' do site. Confira as permissões no Gerenciador de Arquivos (pasta 755).');
    $q = max(50, min(100, (int) cfg('logo_qualidade')));
    $formatos = cfg('formato_logo') !== 'png' && function_exists('imagewebp') ? ['webp', 'png'] : ['png'];
    foreach ($formatos as $ext) {
        $nome = $base . '.' . $ext;
        $tmp = $dir . '/.' . $nome . '.' . bin2hex(random_bytes(4)) . '.tmp';
        $ok = $ext === 'webp' ? @imagewebp($img, $tmp, $q) : @imagepng($img, $tmp, 9);
        clearstatcache(true, $tmp);
        if ($ok && @filesize($tmp) > 0 && @rename($tmp, $dir . '/' . $nome)) {
            @chmod($dir . '/' . $nome, 0644);
            return $nome;
        }
        @unlink($tmp);
    }
    throw new ErroImagem('Não consegui salvar o logo no servidor (sem espaço ou sem permissão na pasta midia/' . $pasta . ').');
}

/** Apaga um logo recém-salvo que acabou não entrando na lista. */
function descartarLogo(?array $novo): void
{
    if ($novo && ($arq = arquivoLogo((string) $novo['logo'])) !== null) @unlink($arq);
}

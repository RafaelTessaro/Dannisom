<?php
/* =====================================================================
   Painel Danni Som — contador de visitas do site (endereço público)
   Recebe do assets/js/contador.js, por navigator.sendBeacon, uma página
   vista ou um clique, e soma nos totais do dia (lib/estatisticas.php).
   Fica fora do index.php de propósito: não abre sessão (nem cookie) e
   responde sempre 204, sem corpo, dê certo ou não — nada de erro,
   caminho de arquivo ou detalhe para quem chama.
   ===================================================================== */
declare(strict_types=1);
define('PAINEL', true);

ini_set('display_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);
header_remove('X-Powered-By');
http_response_code(204);
header('Cache-Control: no-store, max-age=0');
header('X-Content-Type-Options: nosniff');
header('X-Robots-Tag: noindex, nofollow');

try {
    require __DIR__ . '/lib/nucleo.php';
    require __DIR__ . '/lib/estatisticas.php';
    date_default_timezone_set((string) cfg('fuso'));
    if (function_exists('mb_internal_encoding')) mb_internal_encoding('UTF-8');
    set_error_handler(static function (int $nivel, string $msg, string $arq = '', int $linha = 0): bool {
        if (!(error_reporting() & $nivel)) return false;   // chamada com @
        if ($nivel & (E_DEPRECATED | E_USER_DEPRECATED)) return false;
        throw new ErrorException($msg, 0, $nivel, $arq, $linha);
    });
    $visita = visitaDoPedido();
    if ($visita !== null) {
        // a resposta sai agora; a gravação continua no servidor (quem visita não espera o disco)
        ignore_user_abort(true);
        if (function_exists('fastcgi_finish_request')) fastcgi_finish_request();
        elseif (function_exists('litespeed_finish_request')) litespeed_finish_request();
        registrarVisita($visita);
    }
} catch (Throwable $ex) {
    // fica só no registro de erros da pasta privada (o código de referência ajuda a achar)
    try { if (function_exists('registrarErro')) registrarErro('VISITA-' . strtoupper(bin2hex(random_bytes(2))), $ex); } catch (Throwable $t) { /* nada a fazer */ }
}
exit;

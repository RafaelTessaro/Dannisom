<?php
/* =====================================================================
   Painel Danni Som — CONFIGURAÇÃO (o único lugar com caminhos e limites)
   ---------------------------------------------------------------------
   No servidor (Hostinger) fica assim:
     .../dannisom.com.br/public_html/painel/   ← este painel
     .../dannisom.com.br/public_html/dados/    ← empresa.js, clientes.js, marcas.js
     .../dannisom.com.br/public_html/midia/    ← clientes/ e marcas/ (logos)
     .../dannisom.com.br/painel-dannisom-privado/  ← usuários, cópias, lixeira (fora do site)
   Se um dia as pastas mudarem de lugar, ajuste só aqui.
   ===================================================================== */
if (!defined('PAINEL')) { http_response_code(404); exit; }

return [
    // Pasta do site (public_html), vista pelo servidor. Tem dados/ e midia/.
    'pasta_site'         => dirname(__DIR__),
    // O mesmo site visto pelo navegador, a partir do endereço do painel (/painel/)
    'url_site'           => '../',

    // Dados privados (usuários e senhas, cópias de segurança, lixeira de logos, sessões, registro).
    // null = automático: uma pasta FORA do public_html (na pasta do domínio, ao lado da public_html)
    // e, se não der para criar lá, a pasta painel/privado/ (protegida).
    // Para escolher outro lugar, troque null por um caminho absoluto entre aspas.
    'pasta_privada'      => null,
    'nome_pasta_privada' => 'painel-dannisom-privado',

    'fuso'               => 'America/Sao_Paulo',
    'inatividade'        => 2 * 60 * 60,   // sai sozinho após 2 h sem uso
    'tentativas_max'     => 5,             // 5 senhas erradas seguidas (mesmo usuário, mesma internet)…
    'bloqueio'           => 15 * 60,       // …bloqueiam por 15 minutos
    'tentativas_max_ip'  => 20,            // 20 erros somando todos os usuários bloqueiam a internet inteira
    'codigo_validade'    => 24 * 60 * 60,  // código de primeiro acesso vale 24 h
    'senha_minimo'       => 10,            // tamanho mínimo da senha

    // Cópias de segurança (por área): as 30 mais recentes ficam sempre; além delas,
    // tudo dos últimos 14 dias (até 200). Mudanças seguidas de ordem / mostrar-ocultar
    // feitas em menos de 10 minutos pela mesma pessoa viram uma cópia só.
    'copias_max'         => 30,
    'copias_dias'        => 14,
    'copias_teto'        => 200,
    'copias_juntar_min'  => 10,

    // Logos (clientes e marcas): PNG, JPG ou WebP (SVG não), redesenhados pelo servidor
    'logo_max_bytes'     => 5 * 1024 * 1024,   // 5 MB
    'logo_max_pixels'    => 25000000,      // até 25 megapixels
    'logo_min_largura'   => 60,            // pelo menos 60 px de largura
    'caixa_cliente'      => [560, 240],    // logo de cliente cabe em 560 × 240 px…
    'margem_cliente'     => 0.04,          // …com uma margem de 4% em volta do desenho
    'caixa_marca'        => [640, 180],    // logo de marca cabe em 640 × 180 px…
    'margem_marca'       => 0,             // …sem margem (o quadro do site já tem a dele)
    'logo_qualidade'     => 90,            // qualidade WebP (0–100)
    // 'webp' (recomendado) ou 'png'. Sem a função imagewebp no servidor, o painel usa PNG sozinho.
    'formato_logo'       => 'webp',
];

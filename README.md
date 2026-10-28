# Site · Danni Som — Muito mais que som

Site institucional do **Danni Som** (Rio Claro-SP, desde 2000): som automotivo, sonorização de ambientes, home theater, painéis de LED, câmeras de segurança, iluminação e a loja (estética automotiva, materiais elétricos, acessórios e ferramentas).

Hospedagem: **Hostinger** (`dannisom.com.br`, com Cloudflare na frente). Guia de publicação: [`docs/PUBLICAR-NA-HOSTINGER.md`](docs/PUBLICAR-NA-HOSTINGER.md).

Demonstração: a **Vercel** publica a pasta `site/` a cada envio para o GitHub (`vercel.json`). Lá o painel não funciona (não há PHP): o endereço `/painel` volta para o início, o contador de visitas fica desligado e a cópia não aparece no Google.

## Páginas

| Endereço | O que é |
|---|---|
| `/` | Início: topo com as 4 barras do logo (fotos dos serviços), serviços em colunas-equalizador, "Para cada lugar" (abas), como trabalhamos, clientes + avaliações do Google, marcas, história |
| `/solucoes.html` | Sonorização, som automotivo, home theater, painéis de LED, câmeras (CFTV), iluminação, perguntas |
| `/loja.html` | Categorias da loja com fotos reais, marcas por categoria, visite a loja |
| `/clientes.html` | 40 clientes por segmento + avaliações |
| `/empresa.html` | História, sócios, números, valores, fotos da loja, dados da empresa |
| `/contato.html` | Telefones, e-mail, formulário de orçamento (monta a mensagem e abre o WhatsApp ou o e-mail), endereço, horário |
| `/privacidade.html`, `/404.html` | Privacidade (sem cookies) e página "Sem sinal." |
| `/painel/` | Área restrita para mudar clientes, marcas e dados da empresa e ver as estatísticas de visitas (ver `painel/LEIA-ME.md`) |

## Identidade

- Cores do logo oficial: azul **#1D3478**, amarelo **#FDB717** e branco, com uma escala de azuis-escuros derivada.
- Fonte: **Danni Sans** (Mona Sans recortada e hospedada no site, licença SIL OFL — `src/assets/fonts/LEIA-ME.txt`).
- Elemento da marca: as **4 barras inclinadas** do "O" do logo (10,4°). Viram as janelas com fotos do topo, o ícone do site, o menu, os marcadores das seções e a "faixa de áudio" do rodapé.
- Seções marcadas por frequência (31 Hz → 16 kHz): a página é uma faixa de som que começa no grave (o som automotivo) e vai até o agudo.

## Como funciona

```
src/                  FONTES (edite aqui)
  paginas/            corpo de cada página (com um bloco <!--meta {...} --> no topo: título, descrição, CSS/JS da página)
  partials/           partes comuns: head, cabeçalho + menu, rodapé, ícones (sprite) e o logo em SVG
  assets/css/         base.css (todas as páginas), home.css (início), paginas.css (abas)
  assets/js/          core.js (todas), contador.js (contagem anônima de visitas), home.js (início), contato.js (formulário)
  assets/fonts/       Danni Sans (woff2) + licença
  assets/img/         logo, fotos (WebP em 480/760/800/1100/1400-1600 px), imagem de compartilhamento
  assets/js/vendor/   GSAP e Lenis (no próprio site, sem depender de CDN)
  dados/              empresa.js, clientes.js, marcas.js  ← o painel grava aqui
  midia/              logos de clientes e de marcas        ← o painel grava aqui
  hospedagem/         .htaccess, robots.txt, favicons, manifest
painel/               área restrita (PHP 8.1+, sem banco de dados)
ferramentas/montar.py gera a pasta site/ e os pacotes .zip
ferramentas/fotos_larguras.py  (opcional, precisa do Pillow) gera as larguras de uma foto nova
site/                 SITE PRONTO (gerado; não edite à mão) = conteúdo da public_html
docs/                 guias
```

Os textos de contato (telefone, WhatsApp, endereço, horário, redes, nota do Google, avaliações) e os murais de logos vêm de `dados/*.js`. Assim, o que o painel muda aparece no site na hora, sem mexer no HTML.

**Gerar o site** (Python 3, sem instalar nada):

```bash
python3 ferramentas/montar.py                 # recria site/
python3 ferramentas/montar.py --zip           # + danni-som-site.zip (1ª publicação, completo)
python3 ferramentas/montar.py --atualizacao   # + danni-som-atualizacao.zip (sem dados/*.js, midia/ e painel/config.php)
```

O gerador junta as partes, marca a aba atual no menu, põe `?v=…` nos CSS, JS e imagens, gera o `sitemap.xml` e o JSON-LD (dados da empresa para o Google), escreve nas páginas uma cópia dos dados (nomes dos clientes e marcas, telefones, horários) para quem abre sem JavaScript e para os buscadores, e **confere** links, imagens, âncoras, ícones e o formato dos arquivos de dados. Se algo estiver errado, ele para e a `site/` anterior fica como estava.

**Ver no computador:** `cd site && python3 -m http.server 8000` e abra `http://localhost:8000`.

## Tecnologia

HTML, CSS e JavaScript puros. Animações com GSAP (ScrollTrigger, SplitText, Flip) e rolagem suave com Lenis, hospedados no próprio site; sem elas (ou com "reduzir movimento" ligado, ou sem JavaScript) o site funciona igual, só sem animação. Sem cookies e sem rastreadores de terceiros: as visitas são contadas por um contador próprio e anônimo (`assets/js/contador.js` → `painel/visita.php`, explicado em `privacidade.html`). Fontes e imagens no próprio servidor.

## A confirmar com o Danni Som

- **Número do WhatsApp** (os dois telefones conhecidos são fixos). Enquanto não houver, os botões levam para o formulário de orçamento. Para ligar: painel → Empresa → WhatsApp; vale na hora. Na próxima atualização do site (com os dados trazidos do servidor), as páginas já saem com os botões do WhatsApp prontos.
- Uso das **fotos da loja** (vieram do Perfil da Empresa no Google).
- Clientes identificados com menos certeza: **DPI** (sem logo, aparece só o nome), **Almeida** (Cerâmica Almeida), **Salomé** (Salomé Maison), **CAF** (CAF Máquinas), **Futura**, **Agropecuária Sartori** (sem logo) e **Escola Marasca** (sem logo).
- Logos das igrejas **Nazareno** (usamos o do Colégio Nazareno) e **Novo** (a marca encontrada é só o nome): se a igreja tiver logo próprio, basta trocar no painel.
- **CEP no Perfil da Empresa do Google**: o correto da Rua 9, 2419 (Santana) é **13500-220**; vale conferir o que aparece lá.

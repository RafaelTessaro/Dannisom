# Como colocar o site do Danni Som no ar (Hostinger)

O domínio **dannisom.com.br** já está na Hostinger (o e-mail do site é `vendas@dannisom.com.br`: confira em hPanel → **E-mails** que essa caixa existe) e usa o **Cloudflare** na frente (servidores de nome `john.ns.cloudflare.com` e `wally.ns.cloudflare.com`). Nada disso muda: só trocamos os arquivos do site.

O site é 100% estático (HTML, CSS, JS e imagens). O painel (área restrita) é PHP, sem banco de dados.

---

## 1. Antes de começar

- Tenha o arquivo **`danni-som-site.zip`** (gerado por `python3 ferramentas/montar.py --zip`, na raiz do projeto).
- Acesse o **hPanel** da Hostinger com a conta que administra o domínio.

## 2. Guardar o site antigo (1 minuto)

1. hPanel → **Sites** → `dannisom.com.br` → **Gerenciador de Arquivos**.
2. Abra a pasta **`public_html`**.
3. Selecione tudo o que estiver lá (o site antigo "em construção": **`index.php`**, `css/`, `img/`, `js/`, `favicon.ico`, outros `.php` de formulário…) → botão direito → **Compactar** → salve como `site-antigo.zip`.
4. Baixe o `site-antigo.zip` para o seu computador (por segurança) e depois **exclua** os arquivos antigos da `public_html`, **inclusive o próprio `site-antigo.zip`** (deixado lá, qualquer pessoa poderia baixá-lo pelo endereço do site). Não esqueça o **`index.php`**: se ele ficar, `dannisom.com.br/index.php` continua mostrando a página antiga (o `.htaccess` novo já manda esse endereço para o início, mas o certo é apagar).

## 3. Enviar o site novo

1. Ainda dentro da `public_html`, clique em **Enviar arquivos** (ícone de nuvem) e envie o **`danni-som-site.zip`**.
2. Botão direito no zip → **Extrair** → destino: a própria **`public_html`** (não crie subpasta).
3. Confira que ficou assim:

```
public_html/
├── index.html  solucoes.html  loja.html  clientes.html  empresa.html  contato.html
├── privacidade.html  404.html  sitemap.xml  robots.txt  site.webmanifest
├── favicon.svg  favicon.ico  apple-touch-icon.png  icon-192.png  icon-512.png
├── .htaccess                ← fica escondido: Configurações → "Mostrar arquivos ocultos"
├── assets/  (css, js, fontes, imagens do site)
├── dados/   (empresa.js, clientes.js, marcas.js e um .htaccess)
├── midia/   (logos de clientes e de marcas)
└── painel/  (área restrita)
```

4. Apague o `danni-som-site.zip` da `public_html` (não precisa ficar lá).

## 4. PHP (para o painel)

hPanel → **Avançado** → **Configuração do PHP** → escolha **PHP 8.2** ou **8.3** → Salvar. Na aba **Extensões do PHP**, confira que **gd** e **fileinfo** estão marcadas (vêm marcadas por padrão).

## 5. HTTPS e Cloudflare

- hPanel → **Segurança** → **SSL**: o certificado do domínio deve estar **Ativo**.
- No **Cloudflare** (painel da conta que cuida do domínio): **SSL/TLS** → modo **Full (strict)**. Não use "Flexible" (o `.htaccess` já força HTTPS e funciona atrás do Cloudflare sem loop, mas "Full (strict)" é o correto).
- Depois de publicar: Cloudflare → **Caching** → **Purge Everything** (limpa a versão antiga guardada pelo Cloudflare).
- Cloudflare → **Caching** → **Configuration** → **Caching Level**: deixe em **Standard**. (Em "Ignore query string" o Cloudflare ignoraria o `?v=…` dos arquivos e poderia entregar CSS/JS antigos depois de uma atualização.)
- Cloudflare → **Scrape Shield** → **Email Address Obfuscation**: pode **desligar**. O site já escreve o e-mail pelo JavaScript; ligado, o Cloudflare só acrescenta um script a mais em cada página.
- Opcional: Cloudflare → **Rules** → **Redirect Rules** → `www.dannisom.com.br/*` → `https://dannisom.com.br/${1}` (301). O `.htaccess` já faz isso, mas pelo Cloudflare o visitante economiza uma ida ao servidor.

## 6. Testar

Abra no celular e no computador:

- `https://dannisom.com.br/` e cada aba: Soluções, Loja, Clientes, Empresa, Contato.
- `https://www.dannisom.com.br/` → deve ir para `https://dannisom.com.br/`.
- `https://dannisom.com.br/contato` (sem `.html`) → abre a página de contato.
- `https://dannisom.com.br/qualquer-coisa` → página "Sem sinal." (404).
- Rodapé → **Área restrita** → abre o painel (veja `painel/LEIA-ME.md` para o primeiro acesso).

## 7. Google

1. Google Search Console → adicione a propriedade `dannisom.com.br` → envie o sitemap `https://dannisom.com.br/sitemap.xml`.
2. No **Perfil da Empresa no Google**, troque o site para `https://dannisom.com.br/` (hoje aponta para o Instagram) e confira o endereço: o CEP certo da Rua 9, 2419 é **13500-220** (o Google mostra 13500-145, que é da Rua 9 só até o nº 1108).

## 8. Atualizar o site depois (sem perder o que foi mudado no painel)

O painel grava em `dados/` (clientes, marcas, dados da empresa) e em `midia/` (logos). Para publicar uma versão nova do site **sem apagar essas mudanças**:

1. Gere o pacote de atualização: `python3 ferramentas/montar.py --atualizacao` → `danni-som-atualizacao.zip` (não leva os dados `dados/*.js`, a `midia/` nem o `painel/config.php`). **Antes**, traga para o projeto os `dados/*.js` e a `midia/` do servidor (veja `painel/LEIA-ME.md`, parte 7): as páginas levam uma cópia desses dados para quem abre sem JavaScript e para o Google.
2. Envie e extraia na `public_html`, aceitando substituir os arquivos.
3. Limpe o cache do Cloudflare.

Se a versão nova precisar de um campo novo nos dados, ajuste pelo painel (ou envie só o arquivo de dados alterado, depois de conferir com o que está no servidor).

## Problemas comuns

| O que aparece | O que fazer |
|---|---|
| Página antiga ainda aparece | Limpe o cache do Cloudflare (item 5) e do navegador (Ctrl+F5). |
| Erro "too many redirects" | Cloudflare em "Flexible": mude para **Full (strict)**. |
| Site sem estilo, só texto | A pasta `assets/` não foi extraída na `public_html` ou ficou numa subpasta. |
| `/contato` sem `.html` dá 404 | O `.htaccess` não foi enviado (arquivo oculto). Extraia o zip de novo. |
| Painel com erro de PHP | Confira a versão do PHP (item 4). |

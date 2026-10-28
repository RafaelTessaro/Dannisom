# Painel do site · Danni Som

O painel (a **área restrita** do site) serve para a própria loja cuidar, sozinha e pelo celular ou pelo computador, do que muda no site:

- **Clientes** – os logos do mural “Quem confia no Danni Som” (página inicial e página Clientes): cadastrar, trocar o logo, escolher os segmentos, mudar a ordem, ocultar e excluir.
- **Marcas** – as marcas que a loja vende (página inicial, Loja e Soluções): as mesmas coisas, com a prévia de como a marca fica no site.
- **Empresa** – telefones, WhatsApp, e-mail, endereço, mapa, horário de funcionamento, redes sociais, nota do Google e depoimentos.
- **Estatísticas** – quantas pessoas visitaram o site, quais páginas viram e onde clicaram (WhatsApp, telefone, mapa, soluções, categorias da loja…), sem cookies e sem guardar o IP de ninguém.
- **Cópias de segurança**, **Usuários** e **Atividades** – para voltar atrás, dar acesso a outras pessoas e ver quem fez o quê.

O que você salva aparece no site **na hora**. Não tem banco de dados nem plugin: é PHP puro, pensado para a hospedagem da **Hostinger**.

- Endereço: **https://dannisom.com.br/painel/** (no rodapé do site, o link discreto **Área restrita**). O painel não aparece no Google.
- Cada pessoa tem o seu usuário: **Administrador** (faz tudo) ou **Editor** (Clientes, Marcas e Estatísticas).
- Antes de **cada** mudança, o painel guarda uma **cópia de segurança** de como o arquivo estava. Excluiu sem querer? Toque em **Desfazer** no aviso que aparece logo depois.

---

## 1. Como fica no servidor

```
/home/u123456789/domains/dannisom.com.br/
├── public_html/                  ← o site
│   ├── index.html, clientes.html, loja.html…
│   ├── dados/
│   │   ├── empresa.js            ← dados da empresa (o painel lê e grava)
│   │   ├── clientes.js           ← lista de clientes (o painel lê e grava)
│   │   └── marcas.js             ← lista de marcas (o painel lê e grava)
│   ├── midia/
│   │   ├── clientes/             ← logos dos clientes (o painel salva aqui; tem .htaccess que bloqueia scripts)
│   │   └── marcas/               ← logos das marcas (idem)
│   └── painel/                   ← ESTE painel
│       ├── index.php             ← a única página
│       ├── visita.php            ← recebe a contagem de visitas do site (público; não abre sessão nem cookie)
│       ├── config.php            ← caminhos e limites (o único lugar com configuração)
│       ├── lib/                  ← código (bloqueado para o navegador)
│       ├── assets/               ← visual (CSS e JS)
│       ├── .htaccess, .user.ini  ← proteção e limites do PHP
│       └── LEIA-ME.md            ← este guia (bloqueado para o navegador)
└── painel-dannisom-privado/      ← DADOS PRIVADOS (o painel cria sozinho, FORA da public_html)
    ├── usuarios.php              ← usuários e papéis (só o "hash" das senhas: a senha não fica escrita)
    ├── primeiro-acesso.php       ← código do primeiro acesso / recuperar acesso (some depois de usado)
    ├── copias/                   ← cópias de segurança de clientes, marcas e empresa
    ├── lixeira/                  ← logos trocados ou excluídos (voltam se uma cópia for restaurada)
    ├── sessoes/                  ← quem está com o painel aberto
    ├── registro.php              ← atividades (quem entrou, o que mudou)
    ├── estatisticas/             ← totais de visitas de cada dia (25 meses) e os códigos do dia (apagados no dia seguinte)
    └── tentativas.php, chave.php, trava-*.php…   ← controle de senha errada e de gravação
```

Todo arquivo da pasta privada é `.php` e começa com `<?php exit; ?>`: mesmo que alguém descubra o endereço, o servidor não mostra nada. A pasta também tem um `.htaccess` que nega tudo.

Se a hospedagem não deixar criar a pasta fora da `public_html`, o painel usa `public_html/painel/privado/` (com as mesmas proteções). A tela de primeiro acesso e a página **Minha conta** mostram onde ela ficou. Em algumas contas da Hostinger a estrutura é `/home/u123456789/public_html`; aí a pasta privada fica em `/home/u123456789/painel-dannisom-privado`.

---

## 2. Configuração na Hostinger (uma vez só)

1. **Versão do PHP.** hPanel → **Sites** → *dannisom.com.br* → **Gerenciar** → **Avançado** → **Configuração do PHP** → aba **Versão do PHP** → escolha **8.2** ou **8.3** → Salvar. (Funciona a partir do 8.1.)
2. **Extensões.** Na mesma tela, aba **Extensões do PHP**: confira que **gd** e **fileinfo** estão marcadas (normalmente já vêm). Sem elas o painel não consegue tratar os logos e avisa.
3. **Tamanho de envio.** Aba **Opções do PHP**: `upload_max_filesize` de pelo menos **8M** e `post_max_size` de pelo menos **10M** (o padrão da Hostinger já é maior). O painel aceita logos de até 5 MB e mostra, em **Minha conta**, o limite que vale de verdade.
4. **HTTPS.** hPanel → **Segurança** → **SSL**: certificado ativo e **Forçar HTTPS** ligado (o `.htaccess` da raiz do site também redireciona). Use o painel só com o cadeado no navegador.
5. **Arquivos ocultos.** No Gerenciador de Arquivos, os arquivos que começam com ponto (`.htaccess`, `.user.ini`) às vezes ficam escondidos: use a opção de mostrar arquivos ocultos para conferir que estão lá:
   - `public_html/.htaccess` (o do site), `public_html/painel/.htaccess` e `public_html/painel/lib/.htaccess`;
   - `public_html/midia/clientes/.htaccess` e `public_html/midia/marcas/.htaccess` (o painel cria sozinho se faltarem);
   - `public_html/dados/.htaccess` (faz o navegador sempre buscar os dados novos; o painel recria se faltar).
6. **Permissões** (normalmente já ficam assim): pastas **755**, arquivos **644**. A pasta `public_html/dados` e os arquivos dentro dela, e as pastas `midia/clientes` e `midia/marcas`, precisam poder ser gravados pela sua conta (é o padrão da Hostinger).

---

## 3. Primeiro acesso (criar o primeiro administrador)

1. Abra **https://dannisom.com.br/painel/**. Aparece a tela **Primeiro acesso**.
2. Nesse momento o painel grava um **código** num arquivo que só quem entra no hPanel consegue ler.
3. No hPanel → **Arquivos** → **Gerenciador de Arquivos**. Ele abre na `public_html`. **Suba um nível** (para a pasta `dannisom.com.br`, a que tem a `public_html` dentro) e abra a pasta **`painel-dannisom-privado`**.
4. Dê dois cliques em **`primeiro-acesso.php`** (ou botão direito → **Editar**). Copie o código que aparece em “Seu código” (ex.: `7KQ4-M9TX-2HPA`) e feche **sem salvar**.
5. Volte ao painel, cole o código, escreva **seu nome** (ex.: `Daniel Pedroso`), escolha o **usuário** (ex.: `daniel`) e uma **senha com pelo menos 10 caracteres**. Dica: junte 3 ou 4 palavras que só você saiba, com um número.
6. Pronto: você já entra, como **Administrador**. O arquivo do código é apagado sozinho.

O código vale 24 horas. Se vencer, abra o painel de novo: um código novo é gravado no mesmo arquivo.

---

## 4. No dia a dia

### Clientes
A lista tem a mesma ordem do site, cada logo no quadro do jeito que aparece lá (branco; azul-escuro para logos brancos; só o nome quando não há logo).

- **Novo cliente**: nome, **segmentos** (Saúde, Educação, Indústria, Instituições religiosas, Comércio e serviços, Automotivo, Serviços públicos — é onde ele aparece na página Clientes) e o **logo**.
- **Logo**: PNG, JPG ou WebP até **5 MB**. **SVG não é aceito** (pode carregar código escondido). O melhor é **PNG com fundo transparente**; fundo branco também serve. O painel redesenha a imagem, corrige a rotação de foto de celular, **corta as margens vazias**, põe uma margem pequena, reduz para caber em 400 × 200 px (o site mostra os logos pequenos) e salva em **WebP** com um nome novo (ex.: `midia/clientes/santa-casa-3f9a1c.webp`).
- **Logo branco (quadro escuro)**: para logos brancos ou claros. Se o logo parecer branco e a opção estiver desmarcada, o painel avisa.
- **Tirar o logo**: o site mostra só o nome, num quadro de texto. Cliente sem logo é permitido.
- **Aparece no site**: desmarcado, o cliente fica guardado no painel mas some do site. Na lista, use **Ocultar** / **Mostrar**.
- **Ordem**: arraste pela **alça** ⠿ (no computador ou no celular) ou use as setas ↑ ↓. Salva sozinho. Com o teclado: foque a alça e use as setas do teclado.
- **Excluir** pede confirmação; o logo vai para a lixeira privada. Logo depois aparece **Desfazer**: o cliente volta no mesmo lugar, com o logo.
- Cliente novo entra no **fim da lista**.

### Marcas
Igual aos clientes, com as **categorias** (Som automotivo, Sonorização e áudio, Home theater, Imagem e vídeo, Segurança e CFTV, Iluminação, Estética automotiva, Materiais elétricos, Ferramentas, Acessórios, Redes e informática). Marque **todas** as partes do site onde a marca deve aparecer: a mesma marca pode ficar em várias (a JBL, por exemplo, está em Som automotivo, Sonorização e Home theater). Em cada parte, aparecem primeiro as marcas que têm aquela como a primeira categoria da lista.

No site as marcas aparecem **coloridas, como o fabricante manda**, cada uma num quadro branco. Por isso:

- **Mostrar colorido (quadro branco)** já vem marcado para a marca nova. Envie o logo oficial (PNG com fundo transparente é o ideal; JPG com fundo branco também serve).
- O painel corta as sobras, centraliza o logo e deixa todos com um **tamanho visual parecido** (o largo fica mais baixo, o quadrado mais alto), para o mural continuar equilibrado com qualquer quantidade de marcas.
- Se preferir o logo pintado na cor do site (branco nas seções escuras, azul nas claras), desmarque **Mostrar colorido**. Aí o logo precisa ter fundo transparente, ou marque **Remover fundo claro**.

### Estatísticas (todos os usuários)
O site conta as visitas sozinho, com um contador próprio: **sem cookies, sem guardar o IP** e sem nenhuma empresa de fora (a página **Privacidade** do site explica para quem visita).

- **Período**: Hoje, Ontem, 7 dias, 30 dias, Este mês, 12 meses ou **Escolher as datas**. Os números do topo (**Visitantes**, **Páginas vistas**, **Cliques no WhatsApp** e **Ligações**) vêm com a comparação com o período anterior do mesmo tamanho; em **Hoje**, que ainda não acabou, aparece só o número de ontem inteiro.
- **Visitante** é cada pessoa num dia (a mesma internet com o mesmo navegador). Em mais de um dia, os visitantes de cada dia são **somados**: quem voltou em outro dia conta de novo.
- **Visitantes por dia** (por mês nos períodos longos; num dia só, as páginas vistas por hora). Passe o mouse numa barra ou toque em **Ver os números** para a tabela.
- **Páginas mais vistas** e **De onde vieram**: Google, Instagram, Facebook, WhatsApp, outros sites ou **Direto** (endereço digitado, favoritos ou links abertos de aplicativos, como o WhatsApp, que não dizem de onde a pessoa veio). Com o Cloudflare configurado (veja `docs/PUBLICAR-NA-HOSTINGER.md`), aparecem também as **cidades** aproximadas.
- **O que mais interessou**: por assunto (Som automotivo, Sonorização, Home theater, Painéis de LED, Câmeras, Iluminação, categorias da loja…) e por lugar (Carro, Casa, Comércio, Escolas…). Soma os cliques nas soluções, abas e filtros, os pedidos de contato feitos dentro de cada solução e as opções marcadas no **formulário de orçamento** (o texto da mensagem nunca é guardado).
- **Contatos**: WhatsApp, telefone, e-mail, mapa, Instagram, Facebook e avaliações do Google, cada um com o lugar do site onde o botão foi tocado (ex.: “Início › Botão flutuante”).
- **Celular × computador** e **Horários de pico**.
- **Não contar as suas visitas**: quem é da loja e abre o site toda hora toca em **Não contar as minhas visitas**, no fim da tela, em cada celular e computador que usa (vale para aquele navegador; se limpar os dados do navegador, faça de novo).
- **Dica**: para saber quem veio de um link que vocês divulgam, ponha `?utm_source=` no fim do link. Ex.: na bio do Instagram, `https://dannisom.com.br/?utm_source=instagram`; no Perfil da Empresa no Google, `https://dannisom.com.br/?utm_source=google`. Outro nome qualquer (`?utm_source=panfleto`) aparece como “Link divulgado”.
- Não entram robôs (como o do Google), quem usa “Não rastrear” no navegador, quem abre o site sem JavaScript e os aparelhos marcados para não contar: os números são um retrato aproximado. Os totais ficam guardados por **25 meses**.

### Empresa (só administradores)
Um formulário só, dividido em partes. Ao salvar, o painel confere tudo; se algo estiver errado, mostra o que corrigir e **não grava nada**.

- **Telefones**: digite com o DDD, do jeito que quiser — o painel formata `(19) 3523-3198` e cria sozinho o link “Ligar”.
- **WhatsApp**: só números, `55` + DDD + número (ex.: `5519999999999`; se digitar `(19) 99999-9999`, o painel completa). Logo abaixo aparece o **link de teste** — toque para conferir se abre a conversa certa. Vazio: os botões do site levam para a página de contato.
- **Mensagem do WhatsApp**: o texto que já vem escrito quando o cliente abre a conversa.
- **Endereço, mapa e localização**: link do Google Maps (**Compartilhar → Copiar link**) e latitude/longitude (no Google Maps, clique com o botão direito na loja e clique nos números para copiar; cole na Latitude que o painel separa sozinho).
- **Horário**: marque os dias em que a loja abre e preencha abre/fecha. O **2º horário** é para quem fecha no almoço. O painel monta sozinho o quadro do site (“Segunda a sexta — 8h às 18h”, “Sábado — 8h às 12h”, “Domingo — Fechado”) e o selo **Aberto agora**. A prévia mostra o quadro enquanto você digita. O botão **Copiar o horário de segunda para terça a sexta** ajuda.
- **Redes e Google**: links do Instagram (ou só `@perfil`) e do Facebook (vazio: o ícone some), nota do Google com vírgula (`4,8`), quantidade de avaliações e o link delas.
- **Depoimentos**: primeiro nome e texto. ↑ ↓ mudam a ordem, **Remover** tira, **Adicionar depoimento** cria uma linha nova.
- Nome, razão social, CNPJ, sócios e o ano de fundação ficam em **Dados fixos** (só quem cuida do site muda).

### Cópias de segurança (só administradores)
Uma aba para **Clientes**, **Marcas** e **Empresa**. Ficam sempre as **30 mais recentes** e tudo dos **últimos 14 dias**. Mudanças seguidas de ordem ou de mostrar/ocultar, em menos de 10 minutos, viram uma cópia só.

**Restaurar** volta a área inteira para como estava naquele momento — inclusive os logos, que voltam da lixeira — e desfaz também o que foi feito depois. Antes, o estado atual vira uma cópia: se restaurou a errada, restaure a mais recente (“Antes de: voltar…”).

### Usuários (só administradores)
- **Novo usuário**: nome, usuário e papel. Aparece uma **senha temporária** (uma vez só) e uma mensagem pronta para mandar à pessoa. No primeiro acesso, ela cria a própria senha.
- **Editor**: Clientes e Marcas. **Administrador**: tudo.
- **Redefinir senha** (para quem esqueceu), **Desativar** / **Reativar** (a pessoa sai na hora) e **Excluir**. O painel nunca fica sem um administrador ativo, e ninguém muda o próprio papel.

### Atividades (só administradores) e Minha conta
- **Atividades**: quem entrou, o que mudou e quando, com filtros (Alterações, Acessos, Alertas). Alertas são senhas erradas, bloqueios e tentativas de abrir áreas de administrador.
- **Minha conta** (seu nome, no alto): trocar a senha (os outros aparelhos saem), onde fica a pasta privada e o limite de envio.
- Por segurança, o painel **sai sozinho depois de 2 horas** sem uso. Cinco senhas erradas seguidas **bloqueiam** aquele usuário por 15 minutos (naquela internet).

---

## 5. Esqueci a senha / recuperar o acesso

- **Tem outro administrador?** Peça para ele usar **Usuários → Redefinir senha**.
- **É o único administrador** (ou ninguém consegue entrar):
  1. hPanel → **Arquivos** → **Gerenciador de Arquivos** → pasta **`painel-dannisom-privado`** (um nível acima da `public_html`).
  2. Crie nela um arquivo vazio chamado **`recuperar.txt`** (botão **Novo arquivo**).
  3. Abra o painel: aparece **Recuperar acesso**. O código fica em **`primeiro-acesso.php`**, na mesma pasta.
  4. Digite o código, o **seu usuário de administrador** e uma senha nova. (Um usuário que ainda não existe é criado como administrador.)
  5. O painel apaga o `recuperar.txt` e o código sozinho. Os outros usuários e as cópias continuam como estão.

---

## 6. Problemas comuns

| O que aparece | O que fazer |
|---|---|
| “O servidor não deixa gravar dados/…” | No Gerenciador de Arquivos, permissões: pasta `dados` **755**, arquivos `.js` **644**. |
| “Não consigo gravar na pasta midia/…” | Pastas `midia/clientes` e `midia/marcas` com permissão **755**. |
| “O PHP do servidor está sem a extensão gd/fileinfo” | hPanel → Avançado → Configuração do PHP → **Extensões do PHP** → marque **gd** e **fileinfo**. |
| “O envio ficou maior do que o servidor aceita” | Use um logo menor, ou aumente `upload_max_filesize` / `post_max_size` em **Opções do PHP**. |
| “Logo em SVG não é aceito” | Peça o logo em PNG ao cliente, ou abra o SVG no navegador e tire um print. |
| “HEIC” (foto de iPhone) | Salve como PNG ou JPG e envie de novo. |
| “Não consegui ler dados/clientes.js” | O arquivo foi editado à mão ou trocado. **Cópias de segurança** → Restaurar a mais recente. |
| “A página ficou desatualizada” / “Ação recusada” | A página ficou aberta muito tempo. Recarregue e faça de novo. |
| “Muitas tentativas erradas” | Espere 15 minutos (ou entre por outra internet). |
| Mudou no painel mas o site não mostra | Recarregue o site (no celular, puxe a página para baixo). Os dados não ficam em cache. |
| Estatísticas sempre zeradas | O contador não conta no computador de quem cuida do site (`localhost`) nem na cópia de demonstração; abra pelo endereço de verdade. Confira se este aparelho não está marcado em **Não contar as minhas visitas**, se o navegador não está com “Não rastrear” ligado e se o arquivo `public_html/painel/visita.php` foi enviado. |
| Tela em branco / erro 500 | Confira a versão do PHP (8.1 ou mais nova). Os erros ficam em `painel-dannisom-privado/erros.php` (o código de referência do aviso aparece lá). |

---

## 7. Para quem cuida do site (parte técnica)

- **Publicar atualizações do site** (depois que o painel estiver em uso), nesta ordem:
  1. **Traga do servidor o que o painel mudou**: baixe `public_html/dados/*.js` e `public_html/midia/` e copie para `src/dados/` e `src/midia/`. As páginas levam uma cópia desses dados (nomes dos clientes, marcas, telefones, horários, dados para o Google) para quem abre sem JavaScript; montar com dados velhos publicaria essa cópia velha.
  2. Rode `python3 ferramentas/montar.py --atualizacao` → `danni-som-atualizacao.zip`.
  3. Envie e extraia na `public_html`. O pacote **não leva** `dados/*.js`, `midia/` nem `painel/config.php` (que no servidor pode ter ajustes próprios, como a pasta privada); leva o `dados/.htaccess`.
  - O pacote completo (`--zip`) é só para a primeira publicação: depois, ele apagaria o que o painel gravou no servidor.
  - Mudou algo no `painel/config.php` do projeto? Faça a mesma mudança no do servidor, à mão.
- **Formato dos dados**: o painel lê o JSON depois de `window.DANNI_X =` (sem executar nada), confere e grava de volta no mesmo formato (o comentário do topo e o final do arquivo ficam exatamente como estão; JSON com 2 espaços, UTF-8, sem escapar acentos nem barras). A gravação é atômica (arquivo temporário + troca) e com trava (uma por vez). Campos que o painel não conhece ficam como estão.
- **Logos**: o nome do arquivo é `<id>-<6 letras aleatórias>.webp` (ou `.png` se o servidor não gravar WebP ou se `config.php` tiver `'formato_logo' => 'png'`): cada envio tem nome novo, então o cache do navegador nunca mostra o logo antigo e as cópias de segurança apontam sempre para o arquivo certo. Em `clientes.js` o painel grava também `w` e `h` (tamanho do arquivo).
- **Segurança**: senhas com `password_hash`; sessão própria na pasta privada (cookie `HttpOnly`, `SameSite=Lax` e `Secure` com HTTPS; ID novo a cada login); token CSRF em todo envio (e conferência de origem); limite de tentativas por usuário + internet; código de primeiro acesso comparado em tempo constante; cabeçalhos `Content-Security-Policy`, `X-Frame-Options`, `noindex`; toda saída escapada; envios conferidos pelo conteúdo (finfo + getimagesize) e redesenhados com GD; nomes de arquivo gerados pelo painel; scripts bloqueados nas pastas de logos.
- **Configuração**: tudo em `painel/config.php` (pastas, limites, tempos, tamanhos dos logos).
- **Estatísticas**: o `assets/js/contador.js` do site manda, com `navigator.sendBeacon`, uma página vista por carregamento e os cliques que interessam para `painel/visita.php` (separado do `index.php`: não abre sessão nem cookie e responde sempre 204, sem corpo). Ele só aceita POST de até 1,5 KB do próprio site, confere cada campo (páginas, tipos, abas, filtros e opções do formulário numa lista fixa; textos curtos), ignora robôs e limita os envios por visitante (40 por minuto, 500 por dia), os visitantes por dia (20 mil) e as chaves diferentes por dia (ex.: 300 cliques). Grava com trava em `estatisticas/AAAA-MM-DD.php` (totais) na pasta privada; o visitante do dia é um hash de um sal aleatório do dia + IP + navegador, guardado em `estatisticas/visitantes-AAAA-MM-DD.php` e apagado (com o sal) na primeira visita ou abertura das Estatísticas do dia seguinte. Atrás do Cloudflare, o IP vem do `CF-Connecting-IP`, e as cidades dos cabeçalhos `CF-IPCity` / `CF-Region` / `CF-IPCountry`, quando existem. Totais com mais de 25 meses são apagados sozinhos. O contador não manda nada com “Não rastrear”/GPC, em `localhost`, em `*.vercel.app` nem com a marca `ds-nao-contar` (localStorage) que a tela Estatísticas grava. Seção, aba, filtro ou opção do formulário nova no site: acrescente nas listas do começo de `painel/lib/estatisticas.php` (`ABAS_SITE`, `FILTROS_SITE`, `SECOES_SITE`, `DESTINO_INTERESSE`…), senão o clique é ignorado ou aparece sem nome bonito.

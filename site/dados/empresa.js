/* =====================================================================
   DADOS DA EMPRESA — Danni Som
   Tudo o que o site mostra sobre a empresa vem daqui: telefone, WhatsApp,
   e-mail, endereço, mapa, horários, redes sociais e textos curtos.
   O painel (área restrita) grava este arquivo; quem editar à mão deve
   manter o formato JSON (aspas duplas, sem vírgula sobrando).

   Campos
     telefone / telefone2      como aparecem no site
     telefoneLink / telefone2Link   +55 DDD número, só dígitos (para "Ligar")
     whatsapp                  só dígitos: 55 + DDD + número ("" = sem WhatsApp:
                               os botões levam para a página de contato)
     whatsappMensagem          mensagem que já vem escrita no WhatsApp
     horarios                  linhas do quadro de horários { dias, horas }
     grade                     mesmos horários em minutos, por dia da semana
                               (0 = domingo … 6 = sábado), para o "Aberto agora"
     redes                     links completos ("" = não aparece)
     google                    nota (com vírgula), quantidade de avaliações e link
     depoimentos               avaliações reais do Google (primeiro nome + texto)
     geo                       latitude e longitude da loja (para o Google)
   ===================================================================== */
window.DANNI_EMPRESA = {
  "nome": "Danni Som",
  "slogan": "Muito mais que som",
  "razaoSocial": "Pedroso & Zanão Ltda",
  "cnpj": "03.911.560/0001-96",
  "desde": 2000,
  "fundacao": "2000-07-04",
  "socios": [
    "Daniel Luis Pedroso",
    "Márcio Rogério Zanão"
  ],
  "telefone": "(19) 3523-3198",
  "telefoneLink": "+551935233198",
  "telefone2": "(19) 3534-2971",
  "telefone2Link": "+551935342971",
  "whatsapp": "",
  "whatsappMensagem": "Olá! Vim pelo site da Danni Som e gostaria de um orçamento.",
  "email": "contato@dannisom.com.br",
  "endereco": {
    "rua": "Rua 9, 2419",
    "bairro": "Santana",
    "cidade": "Rio Claro",
    "uf": "SP",
    "cep": "13500-220"
  },
  "mapa": "https://www.google.com/maps/search/?api=1&query=Danni%20Som%20Rua%209%202419%20Rio%20Claro&query_place_id=ChIJ9_3iyQbbx5QR-wP4sZV8vnQ",
  "horarios": [
    {
      "dias": "Segunda a sexta",
      "horas": "8h às 18h"
    },
    {
      "dias": "Sábado",
      "horas": "8h às 12h"
    },
    {
      "dias": "Domingo",
      "horas": "Fechado"
    }
  ],
  "grade": {
    "1": [
      [
        480,
        1080
      ]
    ],
    "2": [
      [
        480,
        1080
      ]
    ],
    "3": [
      [
        480,
        1080
      ]
    ],
    "4": [
      [
        480,
        1080
      ]
    ],
    "5": [
      [
        480,
        1080
      ]
    ],
    "6": [
      [
        480,
        720
      ]
    ]
  },
  "redes": {
    "instagram": "https://www.instagram.com/danni_som/",
    "facebook": "https://www.facebook.com/dannisom/"
  },
  "google": {
    "nota": "4,8",
    "avaliacoes": 810,
    "link": "https://search.google.com/local/reviews?placeid=ChIJ9_3iyQbbx5QR-wP4sZV8vnQ"
  },
  "geo": [
    -22.3997744,
    -47.5705148
  ],
  "instagramUsuario": "@danni_som",
  "depoimentos": [
    {
      "nome": "Beto",
      "texto": "Sou cliente há 23 anos e mesmo morando a 560 km, sempre que vou a Rio Claro, compro aí. Melhor preço e qualidade, fora o atendimento nota mil!"
    },
    {
      "nome": "Anderson",
      "texto": "Um bom atendimento: conseguem entender e até mesmo demonstrar alternativas melhores a cada necessidade."
    },
    {
      "nome": "Cristianê",
      "texto": "Atendimento ótimo. Sempre que precisei de algo de som para o carro, encontrei lá. Super indico."
    },
    {
      "nome": "Icaro",
      "texto": "Atendimento rápido e dedicado, tem de tudo na loja."
    },
    {
      "nome": "Rafael",
      "texto": "Atendimento bom e prestativo, tive ajuda com o que precisava."
    }
  ]
}

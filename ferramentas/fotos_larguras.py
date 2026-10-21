#!/usr/bin/env python3
"""
Gera as larguras intermediárias das fotos do site (precisa do Pillow: pip install pillow).

  python3 ferramentas/fotos_larguras.py

Cada foto existe em várias larguras (nome-480.webp, nome-800.webp…) e o navegador escolhe
a menor que fica nítida na tela de quem visita (srcset + sizes no HTML):
  - fotos com 1400/1600 px: ganha 480 e 1100 (celular comum, iPhone 3x, cartões no computador);
  - recortes "-r-" (1200 px): ganha 480 e 760 (760 cobre o celular 2x, que pede ~710 px).
Cada largura nova sai da maior que já existe; nada é ampliado. Arquivos já gerados ficam como estão.
As pílulas do hero (pill-*) têm recorte próprio e ficam de fora.

  python3 ferramentas/fotos_larguras.py --refazer-800   # também regrava os -800 (q74) a partir do maior

No fim confere que todas as larguras de cada foto são mesmo a mesma imagem (miniaturas iguais):
um arquivo trocado faria o celular ver uma foto e o computador outra.
"""
import re
import sys
from io import BytesIO
from pathlib import Path

try:
    from PIL import Image
except ImportError:
    sys.exit("Precisa do Pillow: pip install pillow")

PASTA = Path(__file__).resolve().parent.parent / "src" / "assets" / "img" / "fotos"
QUALIDADE = 74

familias = {}
for p in PASTA.glob("*.webp"):
    m = re.fullmatch(r"(.+)-(\d+)\.webp", p.name)
    if m:
        familias.setdefault(m.group(1), {})[int(m.group(2))] = p

novos = 0
for nome, larguras in sorted(familias.items()):
    if nome.startswith("pill-"):
        continue
    maior = max(larguras)
    alvos = (480, 760) if nome.endswith("-r") else (480, 1100)
    origem = Image.open(larguras[maior])
    for alvo in alvos:
        if alvo in larguras or alvo >= maior:
            continue
        altura = round(origem.height * alvo / origem.width)
        destino = PASTA / f"{nome}-{alvo}.webp"
        origem.resize((alvo, altura), Image.LANCZOS).save(destino, "WEBP", quality=QUALIDADE, method=6)
        novos += 1
        print(f"{destino.name}: {alvo}x{altura}, {destino.stat().st_size // 1024} KB")
    if "--refazer-800" in sys.argv and 800 in larguras and maior > 800:
        buf = BytesIO()
        origem.resize((800, round(origem.height * 800 / origem.width)), Image.LANCZOS).save(buf, "WEBP", quality=QUALIDADE, method=6)
        if buf.tell() < larguras[800].stat().st_size:
            larguras[800].write_bytes(buf.getvalue())
            print(f"{nome}-800.webp: regravado, {buf.tell() // 1024} KB")
print(f"{novos} arquivo(s) novo(s).")

# todas as larguras de uma foto têm que ser a mesma imagem
familias = {}
for p in PASTA.glob("*.webp"):
    m = re.fullmatch(r"(.+)-(\d+)\.webp", p.name)
    if m:
        familias.setdefault(m.group(1), {})[int(m.group(2))] = p
problemas = 0
for nome, larguras in sorted(familias.items()):
    ref = Image.open(larguras[max(larguras)]).convert("L").resize((48, 48))
    for w, p in larguras.items():
        im = Image.open(p).convert("L").resize((48, 48))
        dif = sum(abs(a - b) for a, b in zip(ref.tobytes(), im.tobytes())) / (48 * 48)
        if dif > 6:
            problemas += 1
            print(f"ATENÇÃO: {p.name} não parece a mesma foto que {larguras[max(larguras)].name} (diferença {dif:.0f})")
print("Larguras conferidas: " + ("tudo certo." if not problemas else f"{problemas} problema(s)."))

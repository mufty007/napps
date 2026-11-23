"""Crop the official NAPPS banner into seal, icon and favicon assets."""

from pathlib import Path

from PIL import Image, ImageDraw

src = Path(r"C:\Users\gadda\.cursor\projects\c-Users-gadda-napps\assets\image-f73f4ac2-852a-455c-8d51-97b71883ea61.png")
out = Path(r"c:\Users\gadda\napps\public\images")
out.mkdir(parents=True, exist_ok=True)

im = Image.open(src).convert("RGBA")
w, h = im.size
print("size", w, h)

im.save(out / "napps-logo-full.png", optimize=True)

# Left seal (circle + motto ribbon). Slightly wider than tall to keep the ribbon.
seal_w = min(int(h * 1.12), w)
seal = im.crop((0, 0, seal_w, h))
seal.save(out / "napps-seal.png", optimize=True)
print("seal", seal.size)

# Circular icon for favicon / compact brand mark — centre of the emblem.
cx, cy = seal.size[0] // 2, int(seal.size[1] * 0.40)
r = int(min(seal.size) * 0.40)
box = (
    max(0, cx - r),
    max(0, cy - r),
    min(seal.size[0], cx + r),
    min(seal.size[1], cy + r),
)
icon = seal.crop(box).resize((512, 512), Image.Resampling.LANCZOS)

mask = Image.new("L", (512, 512), 0)
ImageDraw.Draw(mask).ellipse((8, 8, 504, 504), fill=255)
circ = Image.new("RGBA", (512, 512), (0, 0, 0, 0))
circ.paste(icon, (0, 0), mask)
circ.save(out / "napps-icon.png", optimize=True)

icons = [circ.resize(s, Image.Resampling.LANCZOS) for s in ((16, 16), (32, 32), (48, 48))]
icons[0].save(
    out.parent / "favicon.ico",
    format="ICO",
    sizes=[(i.width, i.height) for i in icons],
    append_images=icons[1:],
)
circ.resize((180, 180), Image.Resampling.LANCZOS).save(out / "apple-touch-icon.png", optimize=True)
circ.resize((32, 32), Image.Resampling.LANCZOS).save(out / "favicon-32.png", optimize=True)
circ.resize((16, 16), Image.Resampling.LANCZOS).save(out / "favicon-16.png", optimize=True)

print("done")
for p in sorted(out.glob("*")):
    print(p.name, p.stat().st_size)
print("favicon.ico", (out.parent / "favicon.ico").stat().st_size)

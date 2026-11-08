"""Images de test du lab (aucune photo réelle)."""
import sys
from PIL import Image, ImageDraw

out = sys.argv[1] if len(sys.argv) > 1 else "seed"


def make(name, w, h, c1, c2, label):
    im = Image.new("RGB", (w, h), c1)
    d = ImageDraw.Draw(im)
    for y in range(h):
        t = y / h
        d.line([(0, y), (w, y)], fill=tuple(int(c1[i] * (1 - t) + c2[i] * t) for i in range(3)))
    d.ellipse([w * 0.3, h * 0.3, w * 0.7, h * 0.75], fill=(236, 226, 210))
    d.ellipse([w * 0.36, h * 0.36, w * 0.64, h * 0.5], fill=(200, 85, 61))
    d.text((24, 24), label, fill=(255, 255, 255))
    im.save(f"{out}/{name}", quality=85)


make("bol-paysage.jpg", 1600, 1000, (60, 64, 80), (20, 22, 28), "Lab : image paysage")
make("bol-portrait.jpg", 900, 1300, (120, 70, 50), (30, 20, 18), "Lab : image portrait")
make("bol-carre.jpg", 1000, 1000, (40, 90, 80), (12, 30, 28), "Lab : image carrée")
print("ok")

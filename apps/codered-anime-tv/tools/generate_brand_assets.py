from __future__ import annotations

from pathlib import Path

from PIL import Image, ImageDraw, ImageFilter, ImageOps


ROOT = Path(__file__).resolve().parents[1]
RES = ROOT / "app" / "src" / "main" / "res"

MARK_SOURCE = Path(r"E:\CodeRED sin Fondo.png")
FULL_SOURCE = Path(r"E:\code_red_desing.png")

BG = (4, 6, 13, 255)
SURFACE = (13, 21, 36, 255)
ACCENT = (255, 46, 99, 255)
GOLD = (229, 205, 153, 255)


def ensure_dirs(*paths: Path) -> None:
    for path in paths:
        path.mkdir(parents=True, exist_ok=True)


def contain(image: Image.Image, size: tuple[int, int], padding: int = 0) -> Image.Image:
    target = (size[0] - padding * 2, size[1] - padding * 2)
    fitted = ImageOps.contain(image, target, Image.Resampling.LANCZOS)
    canvas = Image.new("RGBA", size, (0, 0, 0, 0))
    canvas.alpha_composite(fitted, ((size[0] - fitted.width) // 2, (size[1] - fitted.height) // 2))
    return canvas


def cover(image: Image.Image, size: tuple[int, int]) -> Image.Image:
    return ImageOps.fit(image, size, Image.Resampling.LANCZOS, centering=(0.5, 0.45))


def rounded_mask(size: tuple[int, int], radius: int) -> Image.Image:
    mask = Image.new("L", size, 0)
    draw = ImageDraw.Draw(mask)
    draw.rounded_rectangle((0, 0, size[0] - 1, size[1] - 1), radius=radius, fill=255)
    return mask


def make_launcher(mark: Image.Image, size: int, round_icon: bool = False) -> Image.Image:
    canvas = Image.new("RGBA", (size, size), BG)
    draw = ImageDraw.Draw(canvas)

    glow = Image.new("RGBA", (size, size), (0, 0, 0, 0))
    glow_draw = ImageDraw.Draw(glow)
    glow_draw.ellipse(
        (int(size * 0.10), int(size * 0.07), int(size * 0.90), int(size * 0.92)),
        fill=(255, 46, 99, 72),
    )
    glow = glow.filter(ImageFilter.GaussianBlur(max(2, size // 10)))
    canvas.alpha_composite(glow)

    draw.rounded_rectangle(
        (int(size * 0.08), int(size * 0.08), int(size * 0.92), int(size * 0.92)),
        radius=int(size * 0.22),
        fill=SURFACE,
        outline=(255, 255, 255, 34),
        width=max(1, size // 96),
    )

    logo = contain(mark, (size, size), padding=int(size * 0.12))
    shadow = Image.new("RGBA", (size, size), (0, 0, 0, 0))
    shadow.alpha_composite(logo)
    shadow = shadow.filter(ImageFilter.GaussianBlur(max(1, size // 32)))
    tinted_shadow = Image.new("RGBA", (size, size), (0, 0, 0, 130))
    shadow.putalpha(shadow.getchannel("A"))
    canvas.alpha_composite(Image.composite(tinted_shadow, Image.new("RGBA", (size, size)), shadow.getchannel("A")), (0, max(1, size // 42)))
    canvas.alpha_composite(logo)

    if round_icon:
        mask = Image.new("L", (size, size), 0)
        ImageDraw.Draw(mask).ellipse((0, 0, size - 1, size - 1), fill=255)
        rounded = Image.new("RGBA", (size, size), (0, 0, 0, 0))
        rounded.alpha_composite(canvas)
        rounded.putalpha(mask)
        return rounded

    return canvas


def make_adaptive_foreground(mark: Image.Image) -> Image.Image:
    return contain(mark, (432, 432), padding=64)


def make_tv_banner(mark: Image.Image, full: Image.Image) -> Image.Image:
    size = (1280, 720)
    canvas = Image.new("RGBA", size, BG)
    draw = ImageDraw.Draw(canvas)

    for x in range(size[0]):
        ratio = x / max(1, size[0] - 1)
        r = int(10 + ratio * 20)
        g = int(8 + ratio * 2)
        b = int(20 + ratio * 25)
        draw.line((x, 0, x, size[1]), fill=(r, g, b, 255))

    glow = Image.new("RGBA", size, (0, 0, 0, 0))
    glow_draw = ImageDraw.Draw(glow)
    glow_draw.ellipse((-210, 110, 620, 900), fill=(255, 46, 99, 92))
    glow_draw.ellipse((760, -180, 1530, 520), fill=(109, 40, 217, 58))
    glow = glow.filter(ImageFilter.GaussianBlur(90))
    canvas.alpha_composite(glow)

    full_logo = contain(full, (520, 520), padding=20)
    canvas.alpha_composite(full_logo, (60, 92))

    mark_logo = contain(mark, (520, 520), padding=18)
    canvas.alpha_composite(mark_logo, (730, 82))

    draw.rounded_rectangle((610, 130, 1210, 590), radius=42, outline=(229, 205, 153, 70), width=3)
    draw.text((620, 604), "CodeRED Anime", fill=GOLD)

    return canvas.convert("RGB")


def save_png(image: Image.Image, path: Path) -> None:
    path.parent.mkdir(parents=True, exist_ok=True)
    image.save(path, optimize=True)


def main() -> None:
    if not MARK_SOURCE.exists():
        raise SystemExit(f"No existe logo sin fondo: {MARK_SOURCE}")
    if not FULL_SOURCE.exists():
        raise SystemExit(f"No existe logo completo: {FULL_SOURCE}")

    mark = Image.open(MARK_SOURCE).convert("RGBA")
    full = Image.open(FULL_SOURCE).convert("RGBA")

    densities = {
        "mipmap-mdpi": 48,
        "mipmap-hdpi": 72,
        "mipmap-xhdpi": 96,
        "mipmap-xxhdpi": 144,
        "mipmap-xxxhdpi": 192,
    }
    for directory, size in densities.items():
        save_png(make_launcher(mark, size), RES / directory / "ic_launcher.png")
        save_png(make_launcher(mark, size, round_icon=True), RES / directory / "ic_launcher_round.png")

    save_png(make_adaptive_foreground(mark), RES / "drawable" / "ic_launcher_foreground.png")
    save_png(contain(mark, (512, 512), padding=28), RES / "drawable-nodpi" / "codered_mark.png")
    save_png(contain(full, (1024, 1024), padding=16), RES / "drawable-nodpi" / "codered_full_logo.png")
    save_png(make_tv_banner(mark, full), RES / "drawable-nodpi" / "banner.png")
    save_png(make_tv_banner(mark, full).resize((320, 180), Image.Resampling.LANCZOS), RES / "drawable-nodpi" / "banner_small.png")


if __name__ == "__main__":
    main()

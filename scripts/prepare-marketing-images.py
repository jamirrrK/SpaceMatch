"""Prepare the supplied illustrative pack. Requires Pillow locally; never runs on hosting."""
import hashlib
import json
from pathlib import Path

from PIL import Image, ImageOps

ROOT = Path(__file__).resolve().parents[1]
SOURCE = ROOT / "SpaceMatch_Images"
DEST = ROOT / "public/assets/images/spacematch"
NAMES = [
    "homepage_hero_manila", "featured_bedroom", "city_manila", "city_cebu",
    "city_davao", "city_baguio", "city_iloilo", "student_friendly_banner",
    "professionals_banner", "quality_rentals_banner", "community_banner",
    "category_bedspace", "category_dormitory", "category_apartment",
    "category_private_room", "category_shared_room", "next_home_promo",
]


def prepare():
    DEST.mkdir(parents=True, exist_ok=True)
    manifest = {}
    for number, name in enumerate(NAMES, 1):
        source = SOURCE / f"{number:02d}_{name}.jpg"
        if source.is_symlink() or not source.is_file():
            raise ValueError(f"Missing or unsafe source: {source.name}")
        original = source.read_bytes()
        with Image.open(source) as check:
            check.verify()
        with Image.open(source) as raw:
            image = ImageOps.exif_transpose(raw).convert("RGB")
            # Remove baked-in mock controls, logo and unverified status claims.
            if number == 1:
                image = image.crop((480, 0, 865, 245))
            width, height = image.size
            variants = []
            for size in sorted({width, *[x for x in (240, 400, 640) if x < width]}):
                resized = image.resize((size, round(height * size / width)), Image.Resampling.LANCZOS)
                paths = {}
                for extension, options in [("webp", {"quality": 80, "method": 6}), ("jpg", {"quality": 82, "optimize": True, "progressive": True})]:
                    filename = f"{name}-{size}.{extension}"
                    resized.save(DEST / filename, **options)
                    paths[extension] = f"assets/images/spacematch/{filename}"
                variants.append({"width": size, **paths})
            manifest[name] = {"width": width, "height": height, "source_sha256": hashlib.sha256(original).hexdigest(), "variants": variants}
        if source.read_bytes() != original:
            raise RuntimeError("Source unexpectedly changed")
    target = ROOT / "resources/data/marketing-images.json"
    target.parent.mkdir(parents=True, exist_ok=True)
    target.write_text(json.dumps(manifest, indent=2) + "\n", encoding="utf-8")
    print(f"Prepared {len(manifest)} illustrations; preserved source files.")


if __name__ == "__main__":
    prepare()

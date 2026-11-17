from pathlib import Path
import sys

from PIL import Image


if len(sys.argv) != 2:
    raise SystemExit("Usage: python inspect-exif.py <jpeg-file>")

image_path = Path(sys.argv[1]).resolve()
if not image_path.is_file():
    raise SystemExit(f"File not found: {image_path}")

with Image.open(image_path) as image:
    exif = image.getexif()
    print("Description:", exif.get(0x010E))
    print("Software:", exif.get(0x0131))
    print("Size:", image.size)

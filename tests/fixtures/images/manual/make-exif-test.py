from pathlib import Path
from PIL import Image

base = Path(__file__).resolve().parent
source = base / "blue_umbrella-01-outer.jpg"
target = base / "manual-exif-metadata.jpg"

with Image.open(source) as image:
    exif = image.getexif()
    exif[0x010E] = "SIMULATED_EXIF_METADATA_ONLY"
    exif[0x0131] = "CampusTrace R02 Test"
    image.save(target, format="JPEG", quality=92, exif=exif)

with Image.open(target) as image:
    saved_exif = image.getexif()
    print("Description:", saved_exif.get(0x010E))
    print("Software:", saved_exif.get(0x0131))
    print("Size:", image.size)

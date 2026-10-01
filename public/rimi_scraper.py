import csv
import os
import re
import shutil
import subprocess
import sys
import time
from decimal import Decimal, InvalidOperation
from pathlib import Path

import requests
from bs4 import BeautifulSoup

URL = "https://www.rimi.lv/e-veikals/lv/akcijas-piedavajumi"
PAGE_SIZE = 100  # the largest page size the site accepts
MAX_PAGES = 100
STORE = "rimi.lv"
OUTPUT_FILE = Path(__file__).with_name("rimi_products.csv")
OFFER_SELECTOR = ".product-grid__item"
# Fewer offers than this means the page did not load properly; keep the previous data instead.
MIN_PRODUCTS = int(os.environ.get("RIMI_MIN_PRODUCTS", "500"))


def parse_price(value: str) -> Decimal | None:
    normalized = value.replace(",", ".")
    match = re.search(r"\d+(?:\.\d{1,2})?", normalized)
    if match is None:
        return None

    try:
        return Decimal(match.group())
    except InvalidOperation:
        return None


def get_unit_size(title: str) -> tuple[Decimal | None, str | None]:
    match = re.search(r"(\d+(?:[.,]\d+)?)\s*(kg|g|l|ml)\b", title, re.IGNORECASE)
    if match is None:
        return None, None

    quantity = Decimal(match.group(1).replace(",", "."))
    unit = match.group(2).lower()

    if unit == "g":
        return quantity / Decimal("1000"), "kg"
    if unit == "ml":
        return quantity / Decimal("1000"), "l"

    return quantity, unit


def text(item, selector: str) -> str:
    element = item.select_one(selector)
    return element.get_text(" ", strip=True) if element else ""


def extract_product(item) -> dict[str, str]:
    title = text(item, ".card__name")
    card_price = parse_price(text(item, ".card__price .sr-only"))

    # Either a plain sale (card price + crossed-out regular price) or a label price
    # (Mans Rimi card / "2 un vairāk") shown over the image, with the card price as the regular one.
    label_major = text(item, ".price-label__price .major")
    if label_major:
        current_price = parse_price(f"{label_major}.{text(item, '.price-label__price .cents') or '00'}")
        original_price = parse_price(text(item, ".price-label__old-price")) or card_price
        site_unit_price = text(item, ".price-label .price-per-unit")
    else:
        current_price = card_price
        original_price = parse_price(text(item, ".card__old-price .sr-only"))
        site_unit_price = text(item, ".card__price-per .sr-only")

    # Prefer Rimi's own unit price (it covers loose "kg" goods). Only read the size from the title when
    # Rimi gives none, since titles like "Huggies 6-11kg" hold sizes that aren't the pack weight.
    unit_match = re.search(r"(\d+(?:[.,]\d+)?)\s*€/(kg|l)\b", site_unit_price)
    if unit_match:
        unit_price, unit = parse_price(unit_match.group(1)), unit_match.group(2)
    elif site_unit_price:
        unit_price, unit = None, None  # priced per piece ("€/gab.")
    else:
        size, unit = get_unit_size(title)
        unit_price = current_price / size if current_price and size else None

    image = item.select_one(".card__image-wrapper img")
    image_url = (image.get("data-src") or image.get("src") or "").strip() if image else ""

    return {
        "title": title,
        "store": STORE,
        "original_price": f"{original_price:.2f}" if original_price and original_price != current_price else "",
        "current_price": f"{current_price:.2f}" if current_price else "",
        "unit_price": f"{unit_price:.2f}" if unit_price and unit else "",
        "unit": f"€/{unit}" if unit_price and unit else "",
        "image_url": image_url,
    }


session = requests.Session()
session.headers.update({
    "User-Agent": "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0 Safari/537.36",
})

items = []
for page in range(1, MAX_PAGES + 1):
    response = session.get(URL, params={"pageSize": PAGE_SIZE, "currentPage": page}, timeout=60)
    response.raise_for_status()
    batch = BeautifulSoup(response.text, "html.parser").select(OFFER_SELECTOR)
    items.extend(batch)
    print(f"Fetched {len(items)} Rimi offers...", flush=True)
    if len(batch) < PAGE_SIZE:
        break
    time.sleep(0.5)  # be gentle with the shop

products = {}
for item in items:
    product = extract_product(item)
    if product["title"] and product["current_price"]:
        products[product["title"]] = product  # the same offer can appear on two pages

if len(products) < MIN_PRODUCTS:
    # Exit before touching the CSV or importing, so products:scrape reports the failure.
    sys.exit(
        f"Only {len(products)} Rimi offers loaded (expected at least {MIN_PRODUCTS}); "
        "Rimi's site probably changed or failed. Nothing saved or imported."
    )

with OUTPUT_FILE.open("w", newline="", encoding="utf-8") as csvfile:
    fieldnames = [
        "title",
        "store",
        "original_price",
        "current_price",
        "unit_price",
        "unit",
        "image_url",
    ]
    writer = csv.DictWriter(csvfile, fieldnames=fieldnames)
    writer.writeheader()
    writer.writerows(products.values())

print(f"Saved {len(products)} Rimi products to {OUTPUT_FILE.name}")

php_executable = shutil.which("php")
if php_executable is None:
    laragon_php_versions = sorted(Path("C:/laragon/bin/php").glob("*/php.exe"))
    if laragon_php_versions:
        php_executable = str(laragon_php_versions[-1])
    else:
        raise RuntimeError("PHP executable not found. Add PHP to PATH before running the scraper.")

subprocess.run(
    [
        php_executable,
        str(OUTPUT_FILE.parent.parent / "artisan"),
        "products:import",
        str(OUTPUT_FILE),
    ],
    check=True,
)

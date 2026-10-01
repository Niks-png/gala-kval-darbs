import csv
import json
import os
import re
import shutil
import subprocess
import sys
from decimal import Decimal, InvalidOperation
from pathlib import Path

import requests
from bs4 import BeautifulSoup

# Lidl Latvia has no online grocery shop; its weekly offers are rendered into these
# pages with each product's data as JSON in a data-grid-data attribute.
OFFER_PAGES = [
    "https://www.lidl.lv/c/aktualie-piedavajumi/a10028219",
    "https://www.lidl.lv/c/lidl-top-preces/a10054629",
]
# The food category tabs on https://www.lidl.lv/c/edieni-un-dzerieni/s10068374 load their products
# from this search endpoint (same product JSON as above, under gridbox.data). The parent food
# category returns only part of its products, so each tab is asked for separately.
SEARCH_URL = "https://www.lidl.lv/q/api/search"
FOOD_CATEGORIES = {
    10071012: "Augļi un dārzeņi",
    10095752: "Gaļa un putnu gaļa",
    10095761: "Sieri, piena produkti un olas",
    10096086: "Lidl maiznīca",
    10096095: "Bakalēja",
    10096110: "Eļļas, piedevas un mērces",
    10071020: "Gatavie ēdieni",
    10096153: "Brokastu pārslas un smērēšanas produkti",
    10096205: "Saldumi un uzkodas",
    10071022: "Dzērieni",
    10071683: "Kafija, tēja un kakao",
    10071049: "Saldēti produkti",
}
STORE = "lidl.lv"
OUTPUT_FILE = Path(__file__).with_name("lidl_products.csv")
# Fewer offers than this means the pages did not load properly; keep the previous data instead.
MIN_PRODUCTS = int(os.environ.get("LIDL_MIN_PRODUCTS", "20"))


def to_decimal(value) -> Decimal | None:
    if value in (None, ""):
        return None

    try:
        return Decimal(str(value))
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


def extract_product(data: dict) -> dict[str, str]:
    # Some variants are listed as "- Cūkgaļas lāpstiņa" under a group heading.
    title = str(data.get("fullTitle") or data.get("title") or "").strip().lstrip("- ").strip()
    price = data.get("price") or {}
    current_price = to_decimal(price.get("price"))
    original_price = to_decimal(price.get("oldPrice"))

    # The pack size ("500 g") sits apart from the title, so check it first.
    packaging = str((price.get("packaging") or {}).get("text") or "")
    size, unit = get_unit_size(packaging)
    if size is None:
        size, unit = get_unit_size(title)
    unit_price = current_price / size if current_price and size else None

    return {
        "title": title,
        "store": STORE,
        "original_price": f"{original_price:.2f}" if original_price and original_price != current_price else "",
        "current_price": f"{current_price:.2f}" if current_price else "",
        "unit_price": f"{unit_price:.2f}" if unit_price and unit else "",
        "unit": f"€/{unit}" if unit_price and unit else "",
        "image_url": str(data.get("image") or "").strip(),
    }


session = requests.Session()
session.headers.update({
    "User-Agent": "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0 Safari/537.36",
})

products = {}


def add_product(data: dict) -> None:
    product = extract_product(data)
    # Upcoming offers have no price yet; skip them until they start.
    if product["title"] and product["current_price"]:
        products[product["title"]] = product


for url in OFFER_PAGES:
    response = session.get(url, timeout=60)
    response.raise_for_status()

    for element in BeautifulSoup(response.text, "html.parser").select("[data-grid-data]"):
        try:
            add_product(json.loads(element["data-grid-data"]))
        except ValueError:
            continue

    print(f"Fetched {len(products)} Lidl products...", flush=True)

for category_id, category_name in FOOD_CATEGORIES.items():
    response = session.get(SEARCH_URL, timeout=60, params={
        "category.id": category_id,
        "offset": 0,
        "fetchsize": 1000,
        "locale": "lv_LV",
        "assortment": "LV",
        "version": "v2.0.0",
    })
    response.raise_for_status()

    for item in response.json().get("items", []):
        data = (item.get("gridbox") or {}).get("data")
        if data:
            add_product(data)

    print(f"Fetched {len(products)} Lidl products (after {category_name})...", flush=True)

if len(products) < MIN_PRODUCTS:
    # Exit before touching the CSV or importing, so products:scrape reports the failure.
    sys.exit(
        f"Only {len(products)} Lidl offers loaded (expected at least {MIN_PRODUCTS}); "
        "Lidl's site probably changed or failed. Nothing saved or imported."
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

print(f"Saved {len(products)} Lidl products to {OUTPUT_FILE.name}")

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

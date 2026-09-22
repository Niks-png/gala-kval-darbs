import csv
import re
import shutil
import subprocess
from decimal import Decimal, InvalidOperation
from pathlib import Path

import requests

PAGE_URL = "https://etop.lv/lv/visi-akcijas-produkti"
API_URL = "https://etop.lv/v1/Products/GetPromotionProducts"
store = "etop.lv"
output_file = Path(__file__).with_name("top_products.csv")


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


def extract_product(item: dict) -> dict[str, str]:
    title = str(item.get("name") or "").strip()

    try:
        current_price = Decimal(str(item.get("discountedPrice") or item.get("price") or ""))
    except InvalidOperation:
        current_price = None

    reg_min = item.get("regPriceMin") or 0
    reg_max = item.get("regPriceMax") or 0
    original_price = f"{reg_min:.2f} € - {reg_max:.2f} €" if reg_min and reg_max else ""

    size, unit = get_unit_size(title)
    unit_price = current_price / size if current_price and size else None

    image_url = str(item.get("largeImageUrl") or item.get("normalImageUrl") or "").strip()

    return {
        "title": title,
        "store": store,
        "original_price": original_price,
        "current_price": f"{current_price:.2f}" if current_price is not None else "",
        "unit_price": f"{unit_price:.2f}" if unit_price and unit else "",
        "unit": f"€/{unit}" if unit_price and unit else "",
        "image_url": image_url,
    }


session = requests.Session()
session.headers.update({
    "User-Agent": "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0 Safari/537.36",
})
session.get(PAGE_URL)

xsrf_token = session.cookies.get("XSRF-TOKEN")
if not xsrf_token:
    raise RuntimeError("Could not obtain XSRF-TOKEN cookie from etop.lv")

response = session.post(
    API_URL,
    headers={
        "Accept": "application/json",
        "Content-Type": "application/json",
        "Referer": PAGE_URL,
        "X-XSRF-TOKEN": xsrf_token,
    },
    json={"page": 1, "pageSize": 5000},
)
response.raise_for_status()

items = response.json().get("list", [])
print(f"Found {len(items)} product items")

all_items = [extract_product(item) for item in items]

with output_file.open("w", newline="", encoding="utf-8") as csvfile:
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
    writer.writerows(item for item in all_items if item["title"])

print(f"Saved {len(all_items)} products to {output_file.name}")

project_root = output_file.parent.parent
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
        str(project_root / "artisan"),
        "products:import",
        str(output_file),
        "--store",
        store,
    ],
    check=True,
)

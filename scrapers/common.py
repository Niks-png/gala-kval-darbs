"""Shared helpers for the store scrapers.

A scraper only fetches and parses its shop's offers and saves them to a CSV.
`php artisan products:scrape` runs it and then imports that CSV, so a scraper
never calls back into Laravel.

Usage: python <store>_scraper.py [output.csv]
"""

import csv
import re
import sys
from decimal import Decimal, InvalidOperation
from pathlib import Path

import requests

# The columns products:import expects.
CSV_COLUMNS = ["title", "store", "original_price", "current_price", "unit_price", "unit", "image_url"]

BROWSER_USER_AGENT = (
    "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0 Safari/537.36"
)


def output_path(default_name: str) -> Path:
    """Where to save the CSV: the path Laravel passes in, or scrapers/<default_name> when run by hand."""
    if len(sys.argv) > 1:
        return Path(sys.argv[1])

    return Path(__file__).with_name(default_name)


def new_session(headers: dict[str, str] | None = None) -> requests.Session:
    session = requests.Session()
    session.headers.update({"User-Agent": BROWSER_USER_AGENT, **(headers or {})})

    return session


def parse_price(value) -> Decimal | None:
    """The first price in a piece of text or a JSON number: "1,29 €" -> 1.29."""
    if value is None:
        return None

    match = re.search(r"\d+(?:\.\d{1,2})?", str(value).replace(",", "."))
    if match is None:
        return None

    try:
        return Decimal(match.group())
    except InvalidOperation:
        return None


def get_unit_size(text: str) -> tuple[Decimal | None, str | None]:
    """Pack size in kg or l from text like "500 g" or "1,5L"."""
    match = re.search(r"(\d+(?:[.,]\d+)?)\s*(kg|g|l|ml)\b", text, re.IGNORECASE)
    if match is None:
        return None, None

    quantity = Decimal(match.group(1).replace(",", "."))
    unit = match.group(2).lower()

    if unit == "g":
        return quantity / Decimal("1000"), "kg"
    if unit == "ml":
        return quantity / Decimal("1000"), "l"

    return quantity, unit


def unit_price_from_size(current_price: Decimal | None, *texts: str) -> tuple[Decimal | None, str | None]:
    """Price per kg or l, from the first text that holds a pack size."""
    for text in texts:
        size, unit = get_unit_size(text)
        if size:
            return (current_price / size, unit) if current_price else (None, None)

    return None, None


def product_row(
    title: str,
    store: str,
    current_price: Decimal | None,
    original_price: Decimal | str | None = None,
    unit_price: Decimal | None = None,
    unit: str | None = None,
    image_url: str = "",
) -> dict[str, str]:
    """One CSV row. original_price may be text (top! gives a range such as "1.09 € - 1.39 €")."""
    if isinstance(original_price, Decimal):
        original_price = f"{original_price:.2f}" if original_price != current_price else ""

    return {
        "title": title.strip(),
        "store": store,
        "original_price": original_price or "",
        # No offer costs 0; a 0 is a banner like "-30% on all socks" without a single price.
        "current_price": f"{current_price:.2f}" if current_price else "",
        "unit_price": f"{unit_price:.2f}" if unit_price and unit else "",
        "unit": f"€/{unit}" if unit_price and unit else "",
        "image_url": (image_url or "").strip(),
    }


def save_products(products: list[dict[str, str]], output_file: Path, shop: str, min_products: int) -> None:
    """Write the CSV, or exit with an error when too few offers loaded so the old prices are kept."""
    # Untitled rows are useless, and the same offer can appear twice (the last one wins).
    unique = {product["title"]: product for product in products if product["title"]}

    if len(unique) < min_products:
        sys.exit(
            f"Only {len(unique)} {shop} offers loaded (expected at least {min_products}); "
            f"{shop}'s site probably changed or failed. Nothing saved or imported."
        )

    output_file.parent.mkdir(parents=True, exist_ok=True)
    with output_file.open("w", newline="", encoding="utf-8") as csvfile:
        writer = csv.DictWriter(csvfile, fieldnames=CSV_COLUMNS)
        writer.writeheader()
        writer.writerows(unique.values())

    print(f"Saved {len(unique)} {shop} products to {output_file.name}", flush=True)

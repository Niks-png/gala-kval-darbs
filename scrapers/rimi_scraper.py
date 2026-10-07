import os
import re
import time

from bs4 import BeautifulSoup

from common import new_session, output_path, parse_price, product_row, save_products, unit_price_from_size

URL = "https://www.rimi.lv/e-veikals/lv/akcijas-piedavajumi"
PAGE_SIZE = 100  # the largest page size the site accepts
MAX_PAGES = 100
STORE = "rimi.lv"
OFFER_SELECTOR = ".product-grid__item"
# Fewer offers than this means the page did not load properly; keep the previous data instead.
MIN_PRODUCTS = int(os.environ.get("RIMI_MIN_PRODUCTS", "500"))


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
        unit_price, unit = unit_price_from_size(current_price, title)

    image = item.select_one(".card__image-wrapper img")
    image_url = (image.get("data-src") or image.get("src") or "") if image else ""

    return product_row(title, STORE, current_price, original_price, unit_price, unit, image_url)


def main() -> None:
    session = new_session()

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

    products = [extract_product(item) for item in items]
    save_products([product for product in products if product["current_price"]], output_path("rimi_products.csv"), "Rimi", MIN_PRODUCTS)


if __name__ == "__main__":
    main()

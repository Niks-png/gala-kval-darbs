import os
from urllib.parse import urljoin

import requests
from bs4 import BeautifulSoup

from common import new_session, output_path, parse_price, product_row, save_products, unit_price_from_size

URL = "https://www.maxima.lv/bukleti"
BASE_URL = "https://www.maxima.lv"
# The page loads more offers on scroll from this endpoint; calling it directly
# takes seconds instead of minutes and needs no browser.
LOAD_MORE_URL = "https://www.maxima.lv/ajax/salesloadmore"
BATCH_SIZE = 100
MAX_BATCHES = 50
STORE = "maxima.lv"
OFFER_SELECTOR = ".offer-item"
# Fewer offers than this means the page did not load properly; keep the previous data instead.
MIN_PRODUCTS = int(os.environ.get("MAXIMA_MIN_PRODUCTS", "100"))


def extract_product(item) -> dict[str, str]:
    title = item.select_one(".item-title-text, .text .title")
    title_value = title.get_text(" ", strip=True) if title else ""

    sale_price = item.select_one(".item-price-new .price-value")
    cents = item.select_one(".item-price-new .cents-value")
    current_price = parse_price(
        f"{sale_price.get_text(strip=True) if sale_price else ''}."
        f"{cents.get_text(strip=True) if cents else '00'}"
    )

    old_price = item.select_one(".info-old-price .price-value")
    original_price = parse_price(old_price.get_text(strip=True)) if old_price else None
    unit_price, unit = unit_price_from_size(current_price, title_value)

    image = item.select_one('img[src*="/uploads/"]') or item.select_one("img")
    image_src = image.get("src", "").strip() if image else ""
    image_url = urljoin(BASE_URL, image_src) if image_src else ""

    return product_row(title_value, STORE, current_price, original_price, unit_price, unit, image_url)


def fetch_batch(session: requests.Session, offset: int) -> list:
    """One batch of offers from the endpoint the page's infinite scroll uses."""
    response = session.post(LOAD_MORE_URL, params={"sort_by": "newest", "limit": BATCH_SIZE, "search": ""},
                            data={"offset": offset}, timeout=60)
    response.raise_for_status()

    try:
        html = response.json().get("html", "")
    except ValueError:
        html = response.text

    return BeautifulSoup(html, "html.parser").select(OFFER_SELECTOR)


def main() -> None:
    session = new_session({"User-Agent": "Mozilla/5.0", "X-Requested-With": "XMLHttpRequest"})
    session.get(URL, timeout=60).raise_for_status()  # same session/cookies as a browser visit

    items = []
    for _ in range(MAX_BATCHES):
        batch = fetch_batch(session, len(items))
        items.extend(batch)
        print(f"Fetched {len(items)} Maxima offers...", flush=True)
        if len(batch) < BATCH_SIZE:
            break

    save_products([extract_product(item) for item in items], output_path("maxima_products.csv"), "Maxima", MIN_PRODUCTS)


if __name__ == "__main__":
    main()

import json
import os

from bs4 import BeautifulSoup

from common import new_session, output_path, parse_price, product_row, save_products, unit_price_from_size

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
# Fewer offers than this means the pages did not load properly; keep the previous data instead.
MIN_PRODUCTS = int(os.environ.get("LIDL_MIN_PRODUCTS", "20"))


def extract_product(data: dict) -> dict[str, str]:
    # Some variants are listed as "- Cūkgaļas lāpstiņa" under a group heading.
    title = str(data.get("fullTitle") or data.get("title") or "").strip().lstrip("- ")
    price = data.get("price") or {}
    current_price = parse_price(price.get("price"))
    original_price = parse_price(price.get("oldPrice"))

    # The pack size ("500 g") sits apart from the title, so check it first.
    packaging = str((price.get("packaging") or {}).get("text") or "")
    unit_price, unit = unit_price_from_size(current_price, packaging, title)

    return product_row(title, STORE, current_price, original_price, unit_price, unit, str(data.get("image") or ""))


def add_product(products: list[dict[str, str]], data: dict) -> None:
    product = extract_product(data)
    # Upcoming offers have no price yet; skip them until they start.
    if product["title"] and product["current_price"]:
        products.append(product)


def main() -> None:
    session = new_session()
    products: list[dict[str, str]] = []

    for url in OFFER_PAGES:
        response = session.get(url, timeout=60)
        response.raise_for_status()

        for element in BeautifulSoup(response.text, "html.parser").select("[data-grid-data]"):
            try:
                add_product(products, json.loads(element["data-grid-data"]))
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
                add_product(products, data)

        print(f"Fetched {len(products)} Lidl products (after {category_name})...", flush=True)

    save_products(products, output_path("lidl_products.csv"), "Lidl", MIN_PRODUCTS)


if __name__ == "__main__":
    main()

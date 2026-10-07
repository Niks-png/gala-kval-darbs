import os

from common import new_session, output_path, parse_price, product_row, save_products, unit_price_from_size

PAGE_URL = "https://etop.lv/lv/visi-akcijas-produkti"
API_URL = "https://etop.lv/v1/Products/GetPromotionProducts"
STORE = "etop.lv"
OUTPUT_FILE = output_path("top_products.csv")
# Fewer offers than this means the page did not load properly; keep the previous data instead.
MIN_PRODUCTS = int(os.environ.get("TOP_MIN_PRODUCTS", "100"))


def extract_product(item: dict) -> dict[str, str]:
    title = str(item.get("name") or "")
    current_price = parse_price(item.get("discountedPrice") or item.get("price"))

    # top! gives the regular price as a range over its shops.
    reg_min = item.get("regPriceMin") or 0
    reg_max = item.get("regPriceMax") or 0
    original_price = f"{reg_min:.2f} € - {reg_max:.2f} €" if reg_min and reg_max else ""

    unit_price, unit = unit_price_from_size(current_price, title)

    return product_row(
        title,
        STORE,
        current_price,
        original_price,
        unit_price,
        unit,
        str(item.get("largeImageUrl") or item.get("normalImageUrl") or ""),
    )


session = new_session()
session.get(PAGE_URL, timeout=60)

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
    timeout=60,
)
response.raise_for_status()

items = response.json().get("list", [])
print(f"Found {len(items)} product items", flush=True)

save_products([extract_product(item) for item in items], OUTPUT_FILE, "top!", MIN_PRODUCTS)

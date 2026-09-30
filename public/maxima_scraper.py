import csv
import os
import re
import shutil
import subprocess
import sys
from decimal import Decimal, InvalidOperation
from pathlib import Path
from urllib.parse import urljoin

from bs4 import BeautifulSoup
from selenium import webdriver
from selenium.common.exceptions import TimeoutException
from selenium.webdriver.common.by import By
from selenium.webdriver.support import expected_conditions as EC
from selenium.webdriver.support.ui import WebDriverWait

URL = "https://www.maxima.lv/bukleti"
BASE_URL = "https://www.maxima.lv"
STORE = "maxima.lv"
OUTPUT_FILE = Path(__file__).with_name("maxima_products.csv")
OFFER_SELECTOR = ".offer-item"
LOAD_WAIT_SECONDS = 10
IDLE_SCROLLS_TO_STOP = 3
# Fewer offers than this means the page did not load properly; keep the previous data instead.
MIN_PRODUCTS = int(os.environ.get("MAXIMA_MIN_PRODUCTS", "100"))


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
    size, unit = get_unit_size(title_value)
    unit_price = current_price / size if current_price and size else None

    image = item.select_one('img[src*="/uploads/"]') or item.select_one("img")
    image_src = image.get("src", "").strip() if image else ""
    image_url = urljoin(BASE_URL, image_src) if image_src else ""

    return {
        "title": title_value,
        "store": STORE,
        "original_price": f"{original_price:.2f}" if original_price else "",
        "current_price": f"{current_price:.2f}" if current_price else "",
        "unit_price": f"{unit_price:.2f}" if unit_price and unit else "",
        "unit": f"€/{unit}" if unit_price and unit else "",
        "image_url": image_url,
    }


chrome_options = webdriver.ChromeOptions()
chrome_options.add_argument("--headless=new")
chrome_options.add_argument("--window-size=1920,1080")
chrome_options.add_experimental_option(
    "prefs", {"profile.managed_default_content_settings.images": 2}
)

def count_offers(browser) -> int:
    return len(browser.find_elements(By.CSS_SELECTOR, OFFER_SELECTOR))


def scroll_to_bottom(browser) -> None:
    # Step back up a little first so the infinite-scroll trigger at the bottom fires again.
    browser.execute_script("window.scrollBy(0, -600);")
    browser.execute_script("window.scrollTo(0, document.body.scrollHeight);")


driver = webdriver.Chrome(options=chrome_options)
try:
    driver.get(URL)
    WebDriverWait(driver, 30).until(EC.presence_of_element_located((By.CSS_SELECTOR, OFFER_SELECTOR)))

    # Maxima loads more offers when you scroll to the bottom. Keep scrolling until
    # several scrolls in a row add nothing, so one slow load does not end the scrape.
    idle_scrolls = 0
    while idle_scrolls < IDLE_SCROLLS_TO_STOP:
        offers_before = count_offers(driver)
        scroll_to_bottom(driver)
        try:
            WebDriverWait(driver, LOAD_WAIT_SECONDS).until(
                lambda browser: count_offers(browser) > offers_before
            )
            idle_scrolls = 0
        except TimeoutException:
            idle_scrolls += 1
            print(f"No new offers after scrolling ({offers_before} loaded, {idle_scrolls}/{IDLE_SCROLLS_TO_STOP})", file=sys.stderr)

    page_source = driver.page_source
finally:
    driver.quit()

soup = BeautifulSoup(page_source, "html.parser")
products = [extract_product(item) for item in soup.select(OFFER_SELECTOR)]

if len(products) < MIN_PRODUCTS:
    # Exit before touching the CSV or importing, so products:scrape reports the failure.
    sys.exit(
        f"Only {len(products)} Maxima offers loaded (expected at least {MIN_PRODUCTS}); "
        "the page probably did not finish loading. Nothing saved or imported."
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
    writer.writerows(product for product in products if product["title"])

print(f"Saved {len(products)} Maxima products to {OUTPUT_FILE.name}")

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

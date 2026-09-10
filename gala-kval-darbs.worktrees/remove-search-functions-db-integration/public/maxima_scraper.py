import csv
import re
import shutil
import subprocess
from decimal import Decimal, InvalidOperation
from pathlib import Path

from bs4 import BeautifulSoup
from selenium import webdriver
from selenium.common.exceptions import TimeoutException
from selenium.webdriver.common.by import By
from selenium.webdriver.support import expected_conditions as EC
from selenium.webdriver.support.ui import WebDriverWait

URL = "https://www.maxima.lv/bukleti"
STORE = "maxima.lv"
OUTPUT_FILE = Path(__file__).with_name("maxima_products.csv")
OFFER_SELECTOR = ".offer-item"


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

    return {
        "title": title_value,
        "store": STORE,
        "original_price": f"{original_price:.2f}" if original_price else "",
        "current_price": f"{current_price:.2f}" if current_price else "",
        "unit_price": f"{unit_price:.2f}" if unit_price and unit else "",
        "unit": f"€/{unit}" if unit_price and unit else "",
    }


chrome_options = webdriver.ChromeOptions()
chrome_options.add_argument("--headless=new")
chrome_options.add_argument("--window-size=1920,1080")
chrome_options.add_experimental_option(
    "prefs", {"profile.managed_default_content_settings.images": 2}
)

driver = webdriver.Chrome(options=chrome_options)
wait = WebDriverWait(driver, 10)
driver.get(URL)
wait.until(EC.presence_of_element_located((By.CSS_SELECTOR, OFFER_SELECTOR)))

while True:
    offers_before = len(driver.find_elements(By.CSS_SELECTOR, OFFER_SELECTOR))
    buttons = driver.find_elements(By.CSS_SELECTOR, ".lv-load-more .lv-loader")
    if not buttons:
        break

    driver.execute_script("arguments[0].scrollIntoView({block: 'center'});", buttons[0])
    driver.execute_script("arguments[0].click();", buttons[0])

    try:
        wait.until(
            lambda browser: len(browser.find_elements(By.CSS_SELECTOR, OFFER_SELECTOR))
            > offers_before
        )
    except TimeoutException:
        break

page_source = driver.page_source
driver.quit()

soup = BeautifulSoup(page_source, "html.parser")
products = [extract_product(item) for item in soup.select(OFFER_SELECTOR)]

with OUTPUT_FILE.open("w", newline="", encoding="utf-8") as csvfile:
    fieldnames = [
        "title",
        "store",
        "original_price",
        "current_price",
        "unit_price",
        "unit",
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

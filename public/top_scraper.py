import csv
import shutil
import subprocess
from pathlib import Path

from bs4 import BeautifulSoup
from selenium import webdriver
from selenium.common.exceptions import TimeoutException
from selenium.webdriver.common.by import By
from selenium.webdriver.support import expected_conditions as EC
from selenium.webdriver.support.ui import WebDriverWait

url = "https://etop.lv/lv/visi-akcijas-produkti"
store = "etop.lv"
output_file = Path(__file__).with_name("top_products.csv")
product_card_class = "product-card-wrap"

chrome_options = webdriver.ChromeOptions()
chrome_options.add_argument("--headless=new")
chrome_options.add_argument("--window-size=1920,1080")
chrome_options.add_experimental_option(
    "prefs", {"profile.managed_default_content_settings.images": 2}
)

driver = webdriver.Chrome(options=chrome_options)
wait = WebDriverWait(driver, 10)

driver.get(url)
wait.until(EC.presence_of_element_located((By.CLASS_NAME, product_card_class)))

load_more_count = 0

while True:
    try:
        load_more_button = WebDriverWait(driver, 5).until(
            EC.element_to_be_clickable((By.CLASS_NAME, "load-more-button"))
        )
    except TimeoutException:
        break

    product_count = len(driver.find_elements(By.CLASS_NAME, product_card_class))
    driver.execute_script("arguments[0].scrollIntoView({block: 'center'});", load_more_button)
    driver.execute_script("arguments[0].click();", load_more_button)

    try:
        wait.until(
            lambda browser: len(
                browser.find_elements(By.CLASS_NAME, product_card_class)
            )
            > product_count
        )
    except TimeoutException:
        break

    load_more_count += 1

print(f"Loaded more products {load_more_count} times")

page_source = driver.page_source
driver.quit()

soup = BeautifulSoup(page_source, "html.parser")
products = soup.find_all("div", class_=product_card_class)
print(f"Found {len(products)} product items")

all_items = []

for product in products:
    name_elem = product.find("p", class_="product-name")
    title = name_elem.get_text(strip=True) if name_elem else "N/A"

    current_price = "N/A"
    discounted_price_elem = product.find("div", class_="discounted-price")
    if discounted_price_elem:
        euros_elem = discounted_price_elem.find("div", class_="euros")
        cents_elem = discounted_price_elem.find("div", class_="cents")
        if euros_elem and cents_elem:
            euros = euros_elem.get_text(strip=True)
            sup_elem = cents_elem.find("sup")
            cents_val = sup_elem.get_text(strip=True) if sup_elem else "00"
            current_price = f"{euros}.{cents_val}"

    original_price = "N/A"
    old_price_elem = product.find("div", class_="product-old-price")
    if old_price_elem:
        price_span = old_price_elem.find("span")
        if price_span:
            original_price = price_span.get_text(strip=True)

    all_items.append(
        {
            "title": title,
            "store": store,
            "original_price": original_price,
            "current_price": current_price,
        }
    )

with output_file.open("w", newline="", encoding="utf-8") as csvfile:
    fieldnames = ["title", "store", "original_price", "current_price"]
    writer = csv.DictWriter(csvfile, fieldnames=fieldnames)
    writer.writeheader()
    writer.writerows(all_items)

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

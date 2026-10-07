import csv
import sys
import tempfile
import unittest
from decimal import Decimal
from pathlib import Path
from unittest import mock

from common import (
    CSV_COLUMNS,
    get_unit_size,
    output_path,
    parse_price,
    product_row,
    save_products,
    unit_price_from_size,
)


class ParsePriceTest(unittest.TestCase):
    def test_reads_the_first_price_in_text(self):
        self.assertEqual(parse_price("1,29 €"), Decimal("1.29"))
        self.assertEqual(parse_price("Cena: 12.5 EUR"), Decimal("12.5"))

    def test_reads_json_numbers(self):
        self.assertEqual(parse_price(2.49), Decimal("2.49"))
        self.assertEqual(parse_price(3), Decimal("3"))

    def test_returns_none_without_a_price(self):
        self.assertIsNone(parse_price(None))
        self.assertIsNone(parse_price(""))
        self.assertIsNone(parse_price("nav cenas"))


class UnitSizeTest(unittest.TestCase):
    def test_converts_grams_and_millilitres(self):
        self.assertEqual(get_unit_size("Siers 500 g"), (Decimal("0.5"), "kg"))
        self.assertEqual(get_unit_size("Sula 330ml"), (Decimal("0.33"), "l"))

    def test_keeps_kilograms_and_litres(self):
        self.assertEqual(get_unit_size("Piens 2,5% 1L"), (Decimal("1"), "l"))
        self.assertEqual(get_unit_size("Kartupeļi 2,5 kg"), (Decimal("2.5"), "kg"))

    def test_returns_none_without_a_size(self):
        self.assertEqual(get_unit_size("Maize rupjmaize"), (None, None))

    def test_unit_price_uses_the_first_text_with_a_size(self):
        self.assertEqual(unit_price_from_size(Decimal("2.00"), "Siers", "500 g"), (Decimal("4"), "kg"))
        self.assertEqual(unit_price_from_size(None, "500 g"), (None, None))
        self.assertEqual(unit_price_from_size(Decimal("2.00"), "Maize"), (None, None))


class ProductRowTest(unittest.TestCase):
    def test_formats_prices_and_unit_price(self):
        row = product_row(" Piens ", "rimi.lv", Decimal("0.99"), Decimal("1.29"), Decimal("0.99"), "l", " https://x/p.jpg ")

        self.assertEqual(row, {
            "title": "Piens",
            "store": "rimi.lv",
            "original_price": "1.29",
            "current_price": "0.99",
            "unit_price": "0.99",
            "unit": "€/l",
            "image_url": "https://x/p.jpg",
        })

    def test_drops_an_original_price_equal_to_the_current_one(self):
        self.assertEqual(product_row("Piens", "rimi.lv", Decimal("0.99"), Decimal("0.99"))["original_price"], "")

    def test_keeps_a_text_original_price(self):
        row = product_row("Piens", "etop.lv", Decimal("0.99"), "1.09 € - 1.39 €")

        self.assertEqual(row["original_price"], "1.09 € - 1.39 €")

    def test_a_zero_or_missing_price_is_empty(self):
        self.assertEqual(product_row("-30% zeķēm", "maxima.lv", Decimal("0"))["current_price"], "")
        self.assertEqual(product_row("Piens", "maxima.lv", None)["current_price"], "")

    def test_no_unit_without_a_unit_price(self):
        row = product_row("Piens", "rimi.lv", Decimal("0.99"), unit_price=None, unit="l")

        self.assertEqual((row["unit_price"], row["unit"]), ("", ""))


class SaveProductsTest(unittest.TestCase):
    def setUp(self):
        self.dir = tempfile.TemporaryDirectory()
        self.path = Path(self.dir.name) / "out" / "shop_products.csv"

    def tearDown(self):
        self.dir.cleanup()

    def rows(self, *titles):
        return [product_row(title, "rimi.lv", Decimal("1.00")) for title in titles]

    def test_writes_the_columns_products_import_expects(self):
        with mock.patch("builtins.print"):
            save_products(self.rows("Piens", "Maize"), self.path, "Rimi", min_products=1)

        with self.path.open(encoding="utf-8") as file:
            reader = csv.DictReader(file)
            self.assertEqual(reader.fieldnames, CSV_COLUMNS)
            self.assertEqual([row["title"] for row in reader], ["Piens", "Maize"])

    def test_merges_duplicates_and_skips_untitled_rows(self):
        products = self.rows("Piens", "", "Piens")
        products[2]["current_price"] = "0.89"

        with mock.patch("builtins.print"):
            save_products(products, self.path, "Rimi", min_products=1)

        with self.path.open(encoding="utf-8") as file:
            rows = list(csv.DictReader(file))
        self.assertEqual([(row["title"], row["current_price"]) for row in rows], [("Piens", "0.89")])

    def test_too_few_products_exits_without_writing(self):
        with self.assertRaises(SystemExit) as error:
            save_products(self.rows("Piens"), self.path, "Rimi", min_products=2)

        self.assertIn("Only 1 Rimi offers loaded", str(error.exception))
        self.assertFalse(self.path.exists())


class OutputPathTest(unittest.TestCase):
    def test_uses_the_path_laravel_passes(self):
        with mock.patch.object(sys, "argv", ["rimi_scraper.py", "C:/tmp/rimi.csv"]):
            self.assertEqual(output_path("rimi_products.csv"), Path("C:/tmp/rimi.csv"))

    def test_defaults_to_the_scrapers_folder(self):
        with mock.patch.object(sys, "argv", ["rimi_scraper.py"]):
            path = output_path("rimi_products.csv")

        self.assertEqual((path.parent.name, path.name), ("scrapers", "rimi_products.csv"))


if __name__ == "__main__":
    unittest.main()

"""Each shop's parsing, run on small samples shaped like the shop's real pages and API responses."""

import unittest

from bs4 import BeautifulSoup

import lidl_scraper
import maxima_scraper
import rimi_scraper
import top_scraper


def element(html: str):
    return BeautifulSoup(html, "html.parser").select_one("div")


class MaximaTest(unittest.TestCase):
    def test_reads_an_offer(self):
        item = element("""
            <div class="offer-item">
                <img src="/uploads/products/piens.jpg">
                <div class="item-title-text">Piens Rasa 2,5% 1L</div>
                <div class="item-price-new"><span class="price-value">0</span><span class="cents-value">99</span></div>
                <div class="info-old-price"><span class="price-value">1,29</span></div>
            </div>
        """)

        self.assertEqual(maxima_scraper.extract_product(item), {
            "title": "Piens Rasa 2,5% 1L",
            "store": "maxima.lv",
            "original_price": "1.29",
            "current_price": "0.99",
            "unit_price": "0.99",
            "unit": "€/l",
            "image_url": "https://www.maxima.lv/uploads/products/piens.jpg",
        })

    def test_unit_prices_are_taken_out_of_the_title(self):
        item = element("""
            <div class="offer-item">
                <div class="item-title-text">Saldkrējuma sviests LATGALE, 200 g, 82,5% (5,95 €/kg); (14,95 €/kg)</div>
                <div class="item-price-new"><span class="price-value">1</span><span class="cents-value">19</span></div>
            </div>
        """)

        product = maxima_scraper.extract_product(item)

        self.assertEqual(product["title"], "Saldkrējuma sviests LATGALE, 200 g, 82,5%")
        # Maxima's own sale price per kg, not the regular one after it.
        self.assertEqual((product["unit_price"], product["unit"]), ("5.95", "€/kg"))

    def test_a_per_piece_unit_price_is_removed_but_not_used(self):
        title, unit_price, unit = maxima_scraper.split_title("Hig. tamponi TAMPAX 16gab. (0.25 €/gab); (0.36 €/gab)")

        self.assertEqual((title, unit_price, unit), ("Hig. tamponi TAMPAX 16gab.", None, None))

    def test_a_from_price_is_removed_but_not_used(self):
        title, unit_price, unit = maxima_scraper.split_title("Musli SANTE, 350 g, ar riekstiem vai tumšo šokolādi (no 8,54 €/kg)")

        self.assertEqual((title, unit_price, unit), ("Musli SANTE, 350 g, ar riekstiem vai tumšo šokolādi", None, None))

    def test_empty_unit_prices_are_removed(self):
        self.assertEqual(maxima_scraper.split_title("Pieni RASĒNS UHT 1,5% 24x200ml (€/l); (€/l)")[0], "Pieni RASĒNS UHT 1,5% 24x200ml")

    def test_a_category_banner_has_no_price(self):
        item = element('<div class="offer-item"><div class="item-title-text">Apakšveļai un pidžamām</div></div>')

        self.assertEqual(maxima_scraper.extract_product(item)["current_price"], "")


class RimiTest(unittest.TestCase):
    def test_reads_a_plain_sale_with_rimis_unit_price(self):
        item = element("""
            <div class="product-grid__item">
                <div class="card__image-wrapper"><img data-src="https://rimi/img.jpg" src="placeholder.gif"></div>
                <p class="card__name">Siers Dzintars 300g</p>
                <div class="card__price"><span class="sr-only">2,49 €</span></div>
                <div class="card__old-price"><span class="sr-only">3,19 €</span></div>
                <div class="card__price-per"><span class="sr-only">8,30 €/kg</span></div>
            </div>
        """)

        self.assertEqual(rimi_scraper.extract_product(item), {
            "title": "Siers Dzintars 300g",
            "store": "rimi.lv",
            "original_price": "3.19",
            "current_price": "2.49",
            "unit_price": "8.30",
            "unit": "€/kg",
            "image_url": "https://rimi/img.jpg",
        })

    def test_a_label_price_uses_the_card_price_as_the_regular_one(self):
        item = element("""
            <div class="product-grid__item">
                <p class="card__name">Kafija Paulig 500g</p>
                <div class="card__price"><span class="sr-only">7,99 €</span></div>
                <div class="price-label">
                    <div class="price-label__price"><span class="major">5</span><span class="cents">49</span></div>
                </div>
            </div>
        """)

        product = rimi_scraper.extract_product(item)

        self.assertEqual((product["current_price"], product["original_price"]), ("5.49", "7.99"))
        # No unit price from Rimi, so it comes from the 500g in the title.
        self.assertEqual((product["unit_price"], product["unit"]), ("10.98", "€/kg"))

    def test_goods_priced_per_piece_get_no_unit_price(self):
        item = element("""
            <div class="product-grid__item">
                <p class="card__name">Autiņbiksītes Huggies 6-11kg</p>
                <div class="card__price"><span class="sr-only">12,99 €</span></div>
                <div class="card__price-per"><span class="sr-only">0,25 €/gab.</span></div>
            </div>
        """)

        product = rimi_scraper.extract_product(item)

        self.assertEqual((product["unit_price"], product["unit"]), ("", ""))


class TopTest(unittest.TestCase):
    def test_reads_an_offer_with_a_regular_price_range(self):
        product = top_scraper.extract_product({
            "name": "PIENS OPĀ 2.5% 0.9L",
            "discountedPrice": 0.89,
            "price": 1.15,
            "regPriceMin": 1.09,
            "regPriceMax": 1.39,
            "largeImageUrl": "https://etop/piens.jpg",
        })

        self.assertEqual(product, {
            "title": "PIENS OPĀ 2.5% 0.9L",
            "store": "etop.lv",
            "original_price": "1.09 € - 1.39 €",
            "current_price": "0.89",
            "unit_price": "0.99",
            "unit": "€/l",
            "image_url": "https://etop/piens.jpg",
        })

    def test_falls_back_to_the_normal_price_and_image(self):
        product = top_scraper.extract_product({"name": "MAIZE", "price": 1.5, "normalImageUrl": "https://etop/m.jpg"})

        self.assertEqual((product["current_price"], product["original_price"], product["image_url"]), ("1.50", "", "https://etop/m.jpg"))


class LidlTest(unittest.TestCase):
    def test_reads_the_pack_size_before_the_title(self):
        product = lidl_scraper.extract_product({
            "fullTitle": "- Cūkgaļas lāpstiņa 1kg",
            "price": {"price": 3.99, "oldPrice": 4.99, "packaging": {"text": "500 g"}},
            "image": "https://lidl/img.jpg",
        })

        self.assertEqual(product, {
            "title": "Cūkgaļas lāpstiņa 1kg",
            "store": "lidl.lv",
            "original_price": "4.99",
            "current_price": "3.99",
            "unit_price": "7.98",
            "unit": "€/kg",
            "image_url": "https://lidl/img.jpg",
        })

    def test_upcoming_offers_without_a_price_are_skipped(self):
        products = []

        lidl_scraper.add_product(products, {"title": "Piens", "price": {}})
        lidl_scraper.add_product(products, {"title": "Mango", "price": {"price": 1.79}})

        self.assertEqual([product["title"] for product in products], ["Mango"])


if __name__ == "__main__":
    unittest.main()

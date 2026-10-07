# Cenu ceļvedis

Compares current offers from Latvian grocery shops (Maxima, top! and Rimi). Users can search and filter products, follow a product to get an alert when its price drops, build shared shopping lists, match a recipe's ingredients to the cheapest products, and find shops on a map.

Built with Laravel 13, Livewire and Flux. The prices are collected by Python scrapers in [`scrapers/`](scrapers).

## Requirements

- PHP 8.3+ and Composer
- MySQL (or SQLite)
- Node.js 20+
- Python 3.10+

On Laragon all of these are included.

## Setup

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
```

In `.env`, set the database (`DB_CONNECTION`, `DB_DATABASE`, ...), then:

```bash
php artisan migrate
php artisan db:seed --class=StoreSeeder   # shop locations for the map

# Python packages for the scrapers. Use the same Python that SCRAPER_PYTHON in .env points to.
python -m pip install -r scrapers/requirements.txt
```

### Email

New users must confirm their email address before they can use the app. With `MAIL_MAILER=log`, the emails are written to `storage/logs/laravel.log` instead of being sent (copy the link from there). To send real emails through Gmail:

```dotenv
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=you@gmail.com
MAIL_PASSWORD=your-16-letter-app-password   # myaccount.google.com/apppasswords
MAIL_FROM_ADDRESS="you@gmail.com"
```

### Admin account

Register normally, then:

```bash
php artisan users:admin you@example.com
```

The admin panel is at `/admin`. From there you can update prices and manage users.

## Running

```bash
composer run dev
```

This starts the web server, Vite and the queue listener (needed for price updates started from the admin panel). The site runs at http://localhost:8000.

### Daily price updates

Prices are scraped and imported every day at 06:00 (`SCRAPER_DAILY_AT`), but only while Laravel's scheduler is running. During development, run this in a second terminal:

```bash
php artisan schedule:work
```

On a server, run `php artisan schedule:run` every minute instead (cron, or Windows Task Scheduler).

To update prices by hand:

```bash
php artisan products:scrape            # every shop
php artisan products:scrape rimi top   # only these shops
```

Each scraper saves the shop's current offers to `scrapers/<shop>_products.csv`, and the command imports it. Products that are no longer in a shop's offers are marked as ended and hidden from product lists. If a shop's site fails and too few offers load, nothing is imported and the previous prices are kept. Every run is shown in the admin panel.

Which shops are scraped is set by `SCRAPER_STORES` (default `maxima,top,rimi`). A Lidl scraper exists but is off: lidl.lv currently publishes its offers only as leaflet images, not as product listings, so it finds almost nothing. Add `lidl` to `SCRAPER_STORES` to try it again.

## Tests

```bash
php artisan test

# The scrapers' parsing, on sample pages (no internet needed)
python -m unittest discover -s scrapers/tests -t scrapers
```

## Production

In `.env`, set `APP_ENV=production` and `APP_DEBUG=false`. That turns on strict password rules and hides error details from visitors.

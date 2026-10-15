# Paper & Ink: a Gift Vouchers demo

A small, made-up stationery shop built with [Statamic](https://statamic.com), [Cargo](https://builtwithcargo.dev) and [Gift Vouchers](https://github.com/1994limited/gift-vouchers-statamic). Every screenshot in the Gift Vouchers docs comes from it. Run it to try Gift Vouchers before you build with it, or copy its templates.

![Paper & Ink's gift voucher page, with the live preview](https://raw.githubusercontent.com/1994limited/gift-vouchers-statamic/main/docs/images/shop-voucher.png)

It shows:

- **A voucher where the shopper chooses the amount**, with quick amounts, four designs (Classic, Birthday, Thank you, Christmas) and the live preview.
- **A £25 voucher**, for a set amount, next to ordinary products.
- **The basket**, with a box for codes, vouchers taken off the total, and each voucher's recipient and message.
- **Cargo's prebuilt checkout**, with the Gift Vouchers partials added.
- **A balance checker.**
- **The Control Panel**, with a few weeks of made-up orders: vouchers bought as presents, spent over several orders, refunded, expired, disabled, scheduled and made by hand.

## Running it

You need PHP 8.3+ and Composer.

```bash
git clone https://github.com/1994limited/gift-vouchers-statamic-demo.git
cd gift-vouchers-statamic-demo
composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate
php artisan demo:users
php artisan demo:seed
php artisan serve
```

Open http://localhost:8000.

- `demo:users` makes `admin@example.com`, for the Control Panel at `/cp`, and `ada@example.com`, a shopper. Both use the password `password`, so it only runs with `APP_ENV=local`.
- `demo:seed` makes the orders and vouchers. `demo:seed --fresh` clears them and makes them again.
- Checkout uses Cargo's dummy gateway: nothing is charged.
- Running Statamic Pro, Cargo and Gift Vouchers locally needs no licences. The Control Panel shows a trial notice.

Emails go to `storage/logs/laravel.log`. To see them properly, use a mail catcher such as Herd's HELO or Mailpit (see [Emails](https://github.com/1994limited/gift-vouchers-statamic/blob/main/docs/emails.md#testing-emails-locally)).

## Where to look

| File | Shows |
| --- | --- |
| `resources/views/products/show.antlers.html` | The voucher form, quick amounts, designs and live preview |
| `resources/views/basket.antlers.html` | The code box, vouchers with the totals, and voucher details from line items |
| `resources/views/balance.antlers.html` | The balance checker |
| `resources/views/checkout/components/_cart_summary.antlers.html` | Cargo's prebuilt checkout, with `cargo-checkout` and `cargo-checkout-lines` |
| `resources/views/checkout/confirmation.antlers.html` | The vouchers bought, on the confirmation page |
| `resources/addons/gift-vouchers-statamic.yaml` | The Gift Vouchers settings: colour, terms and the four designs |
| `app/Console/Commands/DemoSeed.php` | Orders made through Cargo's real checkout |
| `public/css/shop.css` | The shop's styles |

`routes/web.php` has a few routes for taking screenshots (signing in as the demo admin, a ready-made basket, the email in the browser, the cover image). They only exist when `APP_ENV=local`.

## Licence

The demo's own code, templates, styles and product illustrations are [MIT licensed](LICENSE.md): copy whatever helps.

Gift Vouchers itself is a commercial addon, sold on the [Statamic Marketplace](https://statamic.com/marketplace), and needs a licence on a live site. The checkout views in `resources/views/checkout/` are Cargo's prebuilt checkout (© Double Three Digital Limited, under [Cargo's licence](https://github.com/duncanmcclean/statamic-cargo/blob/main/LICENSE)), with the Gift Vouchers partials added.

<?php

namespace App\Console\Commands;

use DuncanMcClean\Cargo\Facades\Cart;
use DuncanMcClean\Cargo\Facades\Order;
use DuncanMcClean\Cargo\Facades\PaymentGateway;
use DuncanMcClean\Cargo\Orders\Actions\CreateOrderFromCart;
use DuncanMcClean\Cargo\Orders\OrderStatus;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use NineteenNinetyFour\GiftVouchers\Cargo\CartVouchers;
use NineteenNinetyFour\GiftVouchers\Cargo\OrderVouchers;
use NineteenNinetyFour\GiftVouchers\Cargo\Products;
use NineteenNinetyFour\GiftVouchers\GiftVouchers;
use NineteenNinetyFour\GiftVouchers\Voucher;
use Statamic\Facades\User;

/**
 * Fills Paper & Ink with a few weeks of made-up orders, so the Control
 * Panel has vouchers, balances and history to look at. Every order goes
 * through Cargo's real checkout with the dummy gateway.
 */
class DemoSeed extends Command
{
    protected $signature = 'demo:seed {--fresh : Remove the demo orders and vouchers first}';

    protected $description = 'Fill the demo shop with made-up orders and gift vouchers';

    public function handle(): int
    {
        if (! app()->environment('local')) {
            $this->components->error('The demo data is only for local sites.');

            return self::FAILURE;
        }

        // Nobody is emailed while seeding.
        config(['mail.default' => 'array']);

        if (! $this->option('fresh') && Voucher::query()->exists()) {
            $this->components->warn('The demo data is already there. Run `php artisan demo:seed --fresh` to make it again.');

            return self::SUCCESS;
        }

        if ($this->option('fresh')) {
            Order::all()->each->delete();
            Cart::all()->each->delete();
            Voucher::query()->delete();
        }

        $customers = [
            ['name' => 'Ada Lovelace', 'email' => 'ada@example.com'],
            ['name' => 'Alan Turing', 'email' => 'alan@example.com'],
            ['name' => 'Grace Hopper', 'email' => 'grace@example.com'],
            ['name' => 'Katherine Johnson', 'email' => 'katherine@example.com'],
            ['name' => 'Tim Berners-Lee', 'email' => 'tim@example.com'],
        ];

        // Vouchers bought as presents.
        $birthday = $this->order(28, $customers[0], [[
            'product' => 'gift-voucher', Products::AMOUNT => 10000, Products::DESIGN => 'birthday',
            Products::RECIPIENT_NAME => 'Mary Somerville', Products::RECIPIENT_EMAIL => 'mary@example.com',
            Products::SENDER_NAME => 'Ada', Products::MESSAGE => "Happy birthday, Mary!\nTreat yourself to a proper pen.",
        ]]);
        $thanks = $this->order(21, $customers[1], [[
            'product' => 'gift-voucher', Products::AMOUNT => 5000, Products::DESIGN => 'thank-you',
            Products::RECIPIENT_NAME => 'Joan Clarke', Products::RECIPIENT_EMAIL => 'joan@example.com',
            Products::SENDER_NAME => 'Alan', Products::MESSAGE => 'Thank you for everything this year.',
        ], ['product' => 'notebook']]);
        $this->order(16, $customers[2], [['product' => 'gift-voucher-25', 'quantity' => 2, Products::SENDER_NAME => 'Grace', Products::DESIGN => 'classic']]);
        $xmas = $this->order(9, $customers[3], [[
            'product' => 'gift-voucher', Products::AMOUNT => 10000, Products::DESIGN => 'christmas',
            Products::RECIPIENT_NAME => 'Dorothy Vaughan', Products::RECIPIENT_EMAIL => 'dorothy@example.com',
            Products::SENDER_NAME => 'Katherine', Products::SEND_ON => now()->addDays(20)->toDateString(),
        ]]);

        // Spent over later orders.
        $marys = Voucher::where('order_id', $birthday->id())->first();
        $this->order(19, ['name' => 'Mary Somerville', 'email' => 'mary@example.com'], [['product' => 'fountain-pen']], [$marys]);
        $joans = Voucher::where('order_id', $thanks->id())->first();
        $this->order(12, ['name' => 'Joan Clarke', 'email' => 'joan@example.com'], [['product' => 'ink'], ['product' => 'notebook']], [$joans]);

        // A refund back onto a voucher.
        $refunded = $this->order(6, ['name' => 'Joan Clarke', 'email' => 'joan@example.com'], [['product' => 'wax-seal']], [$joans->fresh()]);
        $this->at(5, fn () => app(OrderVouchers::class)->refund(Order::find($refunded->id()), app(\NineteenNinetyFour\GiftVouchers\Ledger::class)->outstandingForOrder($refunded->id())->sum(), null, 'Seal arrived cracked'));

        // Made by hand in the Control Panel.
        $vouchers = app(GiftVouchers::class);
        $this->at(14, fn () => $vouchers->create(1500, 'GBP', ['recipient_name' => 'Tim Berners-Lee', 'recipient_email' => 'tim@example.com'], note: 'Sorry about the late delivery (order #1004)', design: 'classic'));
        $this->at(3, fn () => $vouchers->create(5000, 'GBP', ['recipient_name' => 'Raffle winner'], code: 'SPRING-FAIR-2026', note: 'Spring fair raffle prize', design: 'classic'));

        // One that ran out of time, and one that was cancelled.
        $this->at(400, fn () => $vouchers->create(2000, 'GBP', ['recipient_name' => 'Charles Babbage'], expiresAt: now()->addMonths(12), design: 'classic'));
        $this->at(2, fn () => $vouchers->disable($vouchers->create(3000, 'GBP', ['recipient_name' => 'Duplicate order'], note: 'Bought twice by mistake', design: 'classic')));

        $this->components->info(sprintf('Made %d orders and %d gift vouchers.', Order::query()->count(), Voucher::count()));

        return self::SUCCESS;
    }

    /**
     * An order placed $daysAgo, paid with the dummy gateway (or entirely by voucher).
     */
    protected function order(int $daysAgo, array $customer, array $lines, array $vouchers = []): \DuncanMcClean\Cargo\Contracts\Orders\Order
    {
        return $this->at($daysAgo, function () use ($customer, $lines, $vouchers) {
            $cart = Cart::make()
                ->customer($customer)
                ->set('billing_address', ['line_1' => '1 Demo Street', 'city' => 'London', 'postcode' => 'N1 1AA', 'country' => 'GBR'])
                ->set('shipping_address', ['line_1' => '1 Demo Street', 'city' => 'London', 'postcode' => 'N1 1AA', 'country' => 'GBR'])
                ->set('shipping_method', 'free_shipping')
                ->set('shipping_option', 'free_shipping');

            foreach ($lines as $line) {
                $cart->lineItems()->create(array_merge(['quantity' => 1], $line));
            }

            $cart->save();

            foreach ($vouchers as $voucher) {
                app(CartVouchers::class)->add($cart, $voucher->code());
            }

            $cart = Cart::find($cart->id());
            $order = app(CreateOrderFromCart::class)->handle($cart, $cart->isPaid() ? PaymentGateway::find('dummy') : null);

            if ($order->status() === OrderStatus::PaymentReceived && collect($lines)->contains(fn ($l) => ! str_starts_with($l['product'], 'gift-voucher'))) {
                $order->status(OrderStatus::Shipped)->save();
            }

            $cart->delete();

            return $order;
        });
    }

    protected function at(int $daysAgo, \Closure $callback): mixed
    {
        Carbon::setTestNow(now()->subDays($daysAgo)->setTime(rand(9, 18), rand(0, 59)));

        try {
            return $callback();
        } finally {
            Carbon::setTestNow();
        }
    }
}

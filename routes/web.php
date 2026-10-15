<?php

use Illuminate\Support\Facades\Route;

// Route::statamic('example', 'example-view', [
//    'title' => 'Example'
// ]);

Route::statamic('checkout', 'checkout.index', ['title' => 'Checkout', 'layout' => 'checkout.layout'])
    ->name('checkout');

Route::statamic('checkout/confirmation', 'checkout.confirmation', ['title' => 'Order Confirmation', 'layout' => 'checkout.layout'])
    ->name('checkout.confirmation')
    ->middleware('signed');

Route::statamic('shop', 'shop', ['title' => 'Shop']);
Route::statamic('basket', 'basket', ['title' => 'Your basket']);
Route::statamic('gift-vouchers/balance', 'balance', ['title' => 'Check a gift voucher']);

// Demo only: sign in as the demo admin, for screenshots. Local sites only.
if (app()->environment('local')) {
    Route::get('demo-login', function (\Illuminate\Http\Request $request) {
        $user = \Statamic\Facades\User::findByEmail($request->query('as', 'admin@example.com')) ?? abort(404);
        \Illuminate\Support\Facades\Auth::login($user);
        $to = (string) $request->query('to', '/cp');
        $to = str_starts_with($to, '/') && ! str_starts_with($to, '//') ? $to : '/cp';

        // Snooze Statamic's trial licensing notice in this browser, so it isn't in the screenshots.
        return response('<script>localStorage.setItem("statamic.snooze_license_banner", Date.now() + 864e5); location.replace('.json_encode($to).');</script>');
    })->middleware('web');

    // A basket with a pen, a voucher for a friend and a voucher used, for screenshots.
    Route::get('demo-cart', function (\Illuminate\Http\Request $request) {
        $cart = \DuncanMcClean\Cargo\Facades\Cart::make()
            ->customer(['name' => 'Ada Lovelace', 'email' => 'ada@example.com'])
            ->set('billing_address', ['line_1' => '1 Demo Street', 'city' => 'London', 'postcode' => 'N1 1AA', 'country' => 'GBR'])
            ->set('shipping_address', ['line_1' => '1 Demo Street', 'city' => 'London', 'postcode' => 'N1 1AA', 'country' => 'GBR'])
            ->set('shipping_method', 'free_shipping')->set('shipping_option', 'free_shipping');
        $cart->lineItems()->create(['product' => 'fountain-pen', 'quantity' => 1]);
        $cart->lineItems()->create(['product' => 'letter-set', 'quantity' => 1]);
        $cart->lineItems()->create(['product' => 'gift-voucher', 'quantity' => 1, 'voucher_amount' => 2500, 'voucher_design' => 'thank-you', 'voucher_recipient_name' => 'Joan Clarke', 'voucher_recipient_email' => 'joan@example.com', 'voucher_message' => 'Thank you for everything this year.']);
        $cart->save();
        \DuncanMcClean\Cargo\Facades\Cart::setCurrent($cart);
        $voucher = \NineteenNinetyFour\GiftVouchers\Voucher::where('recipient_name', 'Raffle winner')->first();
        app(\NineteenNinetyFour\GiftVouchers\Cargo\CartVouchers::class)->add($cart, $voucher->code());
        $to = (string) $request->query('to', '/basket');

        return redirect(str_starts_with($to, '/') && ! str_starts_with($to, '//') ? $to : '/basket');
    })->middleware('web');

    // The cover image: two real vouchers on an ink background.
    Route::get('demo-cover', function () {
        $presenter = app(\NineteenNinetyFour\GiftVouchers\Presenter::class);
        $cards = \NineteenNinetyFour\GiftVouchers\Voucher::whereIn('recipient_name', ['Dorothy Vaughan', 'Mary Somerville'])->orderBy('recipient_name')->get()
            ->map(fn ($v) => view('gift-vouchers::partials.card', array_merge($presenter->forDocument($v), ['serial' => $v->code_hint, 'forPdf' => false, 'preview' => false]))->render());

        return '<!doctype html><meta charset="utf-8"><style>body{margin:0;background:#1d2a44;width:1600px;height:900px;overflow:hidden;font-family:Georgia,serif;position:relative}
            .t{position:absolute;left:96px;top:96px;color:#f7f3ea}.t h1{font-weight:normal;font-size:76px;margin:0 0 18px;line-height:1}.t p{font:26px/1.4 -apple-system,Helvetica,sans-serif;color:#c9d0de;margin:0;max-width:520px}
            .a,.b{position:absolute;width:880px}.a{left:640px;top:120px;transform:rotate(-5deg)}.b{left:600px;top:420px;transform:rotate(3deg)}
            .a .gv-card,.b .gv-card{box-shadow:0 40px 80px -30px rgba(0,0,0,.7)}</style>
            <div class="t"><h1>Gift Vouchers</h1><p>For Statamic and Cargo. Engraved vouchers, emailed with a PDF, spent at checkout.</p></div>
            <div class="a">'.$cards[0].'</div><div class="b">'.$cards[1].'</div>';
    });

    // A voucher's email, in the browser, for screenshots.
    Route::get('demo-email/{voucher}', function (string $voucher) {
        $voucher = \NineteenNinetyFour\GiftVouchers\Voucher::findOrFail($voucher);
        $html = (new \NineteenNinetyFour\GiftVouchers\Mail\VoucherMail($voucher))->render();
        $png = app(\NineteenNinetyFour\GiftVouchers\Designs::class)->find($voucher->design)->emblemPng();

        // In a real email the emblem is attached; here it's inlined.
        return $png ? preg_replace('/src="cid:[^"]+"/', 'src="data:image/png;base64,'.base64_encode($png).'"', $html) : $html;
    });
}

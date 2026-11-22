<?php

use App\Contracts\PaymentGateway;
use App\Enums\GatewayStatus;
use App\Events\PaymentConfirmed;
use App\Models\GatewayTransaction;
use App\Models\User;
use App\Services\Payments\FakeGateway;
use App\Services\Payments\PaymentService;
use App\Services\Payments\PaystackGateway;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;

function startPayment(int $kobo = 2_500_000, string $purpose = 'test.purpose'): GatewayTransaction
{
    $payer = User::query()->firstWhere('phone', '+2348031234567')
        ?? User::factory()->phoneOnly()->create(['phone' => '+2348031234567']);

    return app(PaymentService::class)->start($purpose, $kobo, $payer, null, '/somewhere', ['foo' => 'bar']);
}

function usePaystack(): void
{
    config(['napps.payments.paystack.secret_key' => 'sk_test_secret']);
    app()->instance(PaymentGateway::class, new PaystackGateway('sk_test_secret', 'https://api.paystack.co'));
}

it('starts a Paystack checkout with the amount in kobo and a fallback email', function () {
    usePaystack();
    Http::fake(['api.paystack.co/transaction/initialize' => Http::response([
        'status' => true, 'data' => ['authorization_url' => 'https://checkout.paystack.com/abc123'],
    ])]);

    $transaction = startPayment();

    expect($transaction->status)->toBe(GatewayStatus::Pending)
        ->and($transaction->gateway)->toBe('paystack')
        ->and($transaction->authorization_url)->toBe('https://checkout.paystack.com/abc123')
        ->and($transaction->meta('return_url'))->toBe('/somewhere');

    Http::assertSent(fn (HttpRequest $r) => $r['amount'] === 2_500_000
        && $r['reference'] === $transaction->reference
        && $r['email'] === '2348031234567@members.napps.example'
        && $r->hasHeader('Authorization', 'Bearer sk_test_secret'));
});

it('confirms a verified Paystack payment exactly once', function () {
    usePaystack();
    Event::fake([PaymentConfirmed::class]);
    Http::fake([
        'api.paystack.co/transaction/initialize' => Http::response(['status' => true, 'data' => ['authorization_url' => 'https://checkout.paystack.com/x']]),
        'api.paystack.co/transaction/verify/*' => Http::response(['status' => true, 'data' => [
            'status' => 'success', 'amount' => 2_500_000, 'currency' => 'NGN', 'channel' => 'bank_transfer',
            'paid_at' => '2026-11-22T10:00:00.000Z', 'gateway_response' => 'Approved', 'reference' => 'x',
        ]]),
    ]);

    $transaction = startPayment();
    $payments = app(PaymentService::class);

    $payments->refresh($transaction);
    $payments->refresh($transaction->fresh());

    $transaction->refresh();
    expect($transaction->status)->toBe(GatewayStatus::Success)
        ->and($transaction->channel)->toBe('bank_transfer')
        ->and($transaction->paid_at)->not->toBeNull();

    Event::assertDispatchedTimes(PaymentConfirmed::class, 1);
});

it('holds a payment whose amount does not match for review', function () {
    usePaystack();
    Event::fake([PaymentConfirmed::class]);
    Http::fake([
        'api.paystack.co/transaction/initialize' => Http::response(['status' => true, 'data' => ['authorization_url' => 'https://checkout.paystack.com/x']]),
        'api.paystack.co/transaction/verify/*' => Http::response(['status' => true, 'data' => [
            'status' => 'success', 'amount' => 100, 'currency' => 'NGN',
        ]]),
    ]);

    $transaction = app(PaymentService::class)->refresh(startPayment());

    expect($transaction->status)->toBe(GatewayStatus::Failed)
        ->and($transaction->gateway_response)->toContain('mismatch');
    Event::assertNotDispatched(PaymentConfirmed::class);
});

it('treats an unknown Paystack reference as still pending', function () {
    usePaystack();
    Http::fake([
        'api.paystack.co/transaction/initialize' => Http::response(['status' => true, 'data' => ['authorization_url' => 'https://checkout.paystack.com/x']]),
        'api.paystack.co/transaction/verify/*' => Http::response(['status' => false, 'message' => 'Transaction reference not found'], 400),
    ]);

    expect(app(PaymentService::class)->refresh(startPayment())->status)->toBe(GatewayStatus::Pending);
});

it('accepts only correctly signed Paystack webhooks and verifies on the queue', function () {
    usePaystack();
    Http::fake([
        'api.paystack.co/transaction/initialize' => Http::response(['status' => true, 'data' => ['authorization_url' => 'https://checkout.paystack.com/x']]),
        'api.paystack.co/transaction/verify/*' => Http::response(['status' => true, 'data' => [
            'status' => 'success', 'amount' => 2_500_000, 'currency' => 'NGN',
        ]]),
    ]);
    $transaction = startPayment();
    $payload = json_encode(['event' => 'charge.success', 'data' => ['reference' => $transaction->reference]]);

    $this->call('POST', '/webhooks/payments', [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_X_PAYSTACK_SIGNATURE' => 'forged',
    ], $payload)->assertStatus(401);
    expect($transaction->fresh()->status)->toBe(GatewayStatus::Pending);

    $this->call('POST', '/webhooks/payments', [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_X_PAYSTACK_SIGNATURE' => hash_hmac('sha512', $payload, 'sk_test_secret'),
    ], $payload)->assertOk();

    // QUEUE_CONNECTION=sync in tests, so the verify job has already run.
    expect($transaction->fresh()->status)->toBe(GatewayStatus::Success);
});

it('runs the local checkout and returns the payer with the outcome', function (string $outcome, GatewayStatus $status, string $flash) {
    $transaction = startPayment();

    expect($transaction->authorization_url)->toBe(route('payments.fake.show', $transaction->reference));

    $this->get($transaction->authorization_url)->assertOk()->assertSee('₦25,000');

    $this->post(route('payments.fake.complete', $transaction->reference), ['outcome' => $outcome])
        ->assertRedirect(route('payments.callback', ['reference' => $transaction->reference]));

    $this->get(route('payments.callback', ['reference' => $transaction->reference]))
        ->assertRedirect('/somewhere')
        ->assertSessionHas($flash);

    expect($transaction->fresh()->status)->toBe($status);
})->with([
    ['success', GatewayStatus::Success, 'status'],
    ['failed', GatewayStatus::Failed, 'error'],
    ['abandoned', GatewayStatus::Abandoned, 'warning'],
]);

it('reconciles payments that never came back and closes stale ones', function () {
    Event::fake([PaymentConfirmed::class]);
    $paid = startPayment();
    $stale = startPayment();
    $paid->forceFill(['created_at' => now()->subMinutes(10)])->save();
    $stale->forceFill(['created_at' => now()->subDays(5)])->save();
    app(PaymentGateway::class)->setOutcome($paid->reference, GatewayStatus::Success);

    $this->artisan('payments:reconcile')->assertSuccessful();

    expect($paid->fresh()->status)->toBe(GatewayStatus::Success)
        ->and($stale->fresh()->status)->toBe(GatewayStatus::Abandoned);
    Event::assertDispatchedTimes(PaymentConfirmed::class, 1);
});

it('never runs the fake gateway in production', function () {
    app()->forgetInstance(PaymentGateway::class);
    app()->detectEnvironment(fn () => 'production');

    expect(fn () => app(PaymentGateway::class))->toThrow(RuntimeException::class);
});

it('uses the fake gateway in tests', function () {
    expect(app(PaymentGateway::class))->toBeInstanceOf(FakeGateway::class);
});

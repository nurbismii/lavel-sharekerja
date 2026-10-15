<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\CreditEntry;
use App\Models\Package;
use App\Models\Purchase;
use App\Models\Role;
use App\Models\User;
use App\Services\MidtransPayments;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class MidtransPaymentsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.midtrans.server_key' => 'test-key', 'services.midtrans.merchant_id' => 'test-merchant', 'services.midtrans.is_production' => false]);
        Http::preventStrayRequests();
    }

    private function purchase(): Purchase
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::firstOrCreate(['name' => Role::RECRUITER]));
        Company::factory()->create(['owner_user_id' => $user->id]);
        $package = Package::create(['name' => 'Paket uji', 'price' => 100000, 'credits' => 3, 'active' => true]);

        return Purchase::create(['reference' => (string) Str::uuid(), 'user_id' => $user->id, 'package_id' => $package->id, 'package_name' => $package->name, 'amount' => $package->price, 'credits' => $package->credits, 'provider' => 'midtrans']);
    }

    /**
     * @return array{order_id: string, gross_amount: string, currency: string, merchant_id: string, transaction_id: string, transaction_status: string, status_code: string}
     */
    private function invoice(Purchase $purchase): array
    {
        return ['order_id' => $purchase->reference, 'gross_amount' => '100000.00', 'currency' => 'IDR', 'merchant_id' => 'test-merchant', 'transaction_id' => 'transaction-test', 'transaction_status' => 'settlement', 'status_code' => '200'];
    }

    /**
     * @return array{order_id: string, status_code: string, gross_amount: string, signature_key: string}
     */
    private function notification(Purchase $purchase): array
    {
        return ['order_id' => $purchase->reference, 'status_code' => '200', 'gross_amount' => '100000.00', 'signature_key' => hash('sha512', $purchase->reference.'200100000.00test-key')];
    }

    public function test_checkout_reuses_order_and_uses_server_price_and_basic_auth(): void
    {
        $purchase = $this->purchase();
        Http::fake([
            'app.sandbox.midtrans.com/*' => Http::response(['token' => 'snap-token', 'redirect_url' => 'https://app.sandbox.midtrans.com/snap/v2/vtweb/snap-token']),
            'api.sandbox.midtrans.com/*' => Http::response(['status_code' => '404']),
        ]);
        $this->actingAs($purchase->user)->post(route('purchases.store', $purchase->package_id), ['amount' => 1, 'credits' => 999])->assertSessionHasNoErrors()->assertRedirect(route('purchases.show', $purchase));
        $this->post(route('purchases.store', $purchase->package_id))->assertSessionHasNoErrors()->assertRedirect(route('purchases.show', $purchase));
        $this->assertSame(1, Purchase::count());
        $this->assertNull($purchase->fresh()->provider_id);
        $this->assertSame(0, $purchase->user->fresh()->credits);
        Http::assertSent(fn (Request $request): bool => $request->method() === 'POST' && $request->url() === 'https://app.sandbox.midtrans.com/snap/v1/transactions' && $request['transaction_details']['order_id'] === $purchase->reference && $request['transaction_details']['gross_amount'] === 100000 && $request['callbacks']['finish'] === route('purchases.show', $purchase) && $request->hasHeader('Authorization', 'Basic '.base64_encode('test-key:')));
        $this->assertCount(1, Http::recorded(fn (Request $request): bool => $request->method() === 'POST'));
        $this->get(route('purchases.show', $purchase).'?transaction_status=settlement')->assertOk()->assertSee('Midtrans')->assertSee('Sandbox')->assertDontSee('test-key');
        $this->assertSame(0, $purchase->user->fresh()->credits);
    }

    public function test_new_purchase_is_marked_as_midtrans_and_legacy_order_is_not_reused(): void
    {
        $legacy = $this->purchase();
        $legacy->update(['provider' => null, 'checkout_url' => 'https://legacy.example/checkout']);
        Http::fake(['app.sandbox.midtrans.com/*' => Http::response(['redirect_url' => 'https://app.sandbox.midtrans.com/snap/v2/vtweb/new-order'])]);
        $this->actingAs($legacy->user)->get(route('purchases.show', $legacy))->assertOk()->assertSee('sebelum perpindahan')->assertDontSee('https://legacy.example/checkout');
        $this->post(route('purchases.refresh', $legacy))->assertUnprocessable();
        Http::assertNothingSent();
        $this->post(route('purchases.store', $legacy->package_id))->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame(2, Purchase::count());
        $this->assertSame('midtrans', Purchase::latest('id')->first()->provider);
        $this->assertNull($legacy->fresh()->provider);
    }

    public function test_invalid_signature_is_rejected_before_provider_is_contacted(): void
    {
        $purchase = $this->purchase();
        $body = $this->notification($purchase);
        $body['gross_amount'] = '1.00';
        $this->postJson(route('midtrans.webhook'), $body)->assertUnauthorized();
        Http::assertNothingSent();
        $this->assertSame(0, CreditEntry::count());
    }

    public function test_webhook_accepts_midtrans_request_without_csrf_token(): void
    {
        $purchase = $this->purchase();
        Http::fake(['api.sandbox.midtrans.com/*' => Http::response($this->invoice($purchase))]);
        $this->app['env'] = 'local';
        $this->postJson(route('midtrans.webhook'), $this->notification($purchase))->assertOk();
        $this->assertSame('PAID', $purchase->fresh()->status);
    }

    public function test_checkout_lock_and_fresh_state_prevent_duplicate_requests(): void
    {
        $purchase = $this->purchase();
        $lock = Cache::lock('invoice:'.$purchase->id, 30);
        $this->assertTrue($lock->get());
        try {
            app(MidtransPayments::class)->invoice($purchase);
            Http::assertNothingSent();
        } finally {
            $lock->release();
        }
        $purchase->fresh()->update(['checkout_url' => 'https://app.sandbox.midtrans.com/snap/v2/vtweb/existing']);
        app(MidtransPayments::class)->invoice($purchase);
        Http::assertNothingSent();
    }

    public function test_non_success_status_code_never_grants_credit(): void
    {
        $purchase = $this->purchase();
        Http::fake(['api.sandbox.midtrans.com/*' => Http::response(array_replace($this->invoice($purchase), ['status_code' => '202']))]);
        $this->postJson(route('midtrans.webhook'), $this->notification($purchase))->assertOk();
        $this->assertSame(0, CreditEntry::count());
        $this->assertSame('PENDING', $purchase->fresh()->status);
    }

    public function test_notification_payload_cannot_override_provider_state(): void
    {
        $purchase = $this->purchase();
        Http::fake(['api.sandbox.midtrans.com/*' => Http::response(array_replace($this->invoice($purchase), ['transaction_status' => 'pending', 'status_code' => '201']))]);
        $this->postJson(route('midtrans.webhook'), $this->notification($purchase) + ['transaction_status' => 'settlement'])->assertOk();
        $this->assertSame('PENDING', $purchase->fresh()->status);
        $this->assertSame(0, CreditEntry::count());
    }

    /**
     * @return array<string, array{string, string, string}>
     */
    public static function statuses(): array
    {
        return [
            'settled' => ['settlement', 'accept', 'PAID'],
            'captured' => ['capture', 'accept', 'PAID'],
            'challenged card' => ['capture', 'challenge', 'PENDING'],
            'rejected fraud' => ['settlement', 'deny', 'PENDING'],
            'pending' => ['pending', 'accept', 'PENDING'],
            'expired' => ['expire', 'accept', 'EXPIRED'],
            'denied' => ['deny', 'deny', 'FAILED'],
            'cancelled' => ['cancel', 'accept', 'FAILED'],
            'failed' => ['failure', 'accept', 'FAILED'],
            'refunded before grant' => ['refund', 'accept', 'PENDING'],
        ];
    }

    #[DataProvider('statuses')]
    public function test_provider_status_mapping(string $status, string $fraud, string $expected): void
    {
        $purchase = $this->purchase();
        Http::fake(['api.sandbox.midtrans.com/*' => Http::response(array_replace($this->invoice($purchase), ['transaction_status' => $status, 'fraud_status' => $fraud]))]);
        $this->postJson(route('midtrans.webhook'), $this->notification($purchase))->assertOk();
        $this->assertSame($expected, $purchase->fresh()->status);
        $this->assertSame($expected === 'PAID' ? 3 : 0, $purchase->user->fresh()->credits);
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function mismatches(): array
    {
        return [
            'amount' => ['gross_amount', '100000.01'],
            'merchant' => ['merchant_id', 'another-merchant'],
            'currency' => ['currency', 'USD'],
            'order' => ['order_id', 'another-order'],
            'transaction' => ['transaction_id', 'another-transaction'],
        ];
    }

    #[DataProvider('mismatches')]
    public function test_mismatched_provider_data_never_grants_credit(string $field, string $value): void
    {
        $purchase = $this->purchase();
        $purchase->update(['provider_id' => 'transaction-test']);
        Http::fake(['api.sandbox.midtrans.com/*' => Http::response(array_replace($this->invoice($purchase), [$field => $value]))]);
        $this->postJson(route('midtrans.webhook'), $this->notification($purchase))->assertUnprocessable();
        $this->assertSame(0, CreditEntry::count());
        $this->assertSame(0, $purchase->user->fresh()->credits);
    }

    public function test_unselected_payment_method_is_pending_and_webhook_can_retry(): void
    {
        $purchase = $this->purchase();
        $purchase->update(['checkout_url' => 'https://app.sandbox.midtrans.com/snap/v2/vtweb/existing']);
        Http::fake(['api.sandbox.midtrans.com/*' => Http::response(['status_code' => '404'])]);
        $this->actingAs($purchase->user)->post(route('purchases.refresh', $purchase))->assertSessionHasNoErrors();
        $this->assertSame('PENDING', $purchase->fresh()->status);
        $this->postJson(route('midtrans.webhook'), $this->notification($purchase))->assertStatus(503);
        $this->assertSame(0, CreditEntry::count());
    }

    public function test_provider_outage_returns_retryable_webhook_response(): void
    {
        $purchase = $this->purchase();
        Http::fake(['api.sandbox.midtrans.com/*' => Http::failedConnection()]);
        $this->postJson(route('midtrans.webhook'), $this->notification($purchase))->assertStatus(503);
        $this->assertSame('PENDING', $purchase->fresh()->status);
        $this->assertSame(0, CreditEntry::count());
    }

    public function test_temporary_failure_preserves_order_and_refresh_can_retry_checkout(): void
    {
        $purchase = $this->purchase();
        Http::fake([
            'api.sandbox.midtrans.com/*' => Http::response(['status_code' => '404']),
            'app.sandbox.midtrans.com/*' => Http::sequence()->pushStatus(503)->push(['redirect_url' => 'https://app.sandbox.midtrans.com/snap/v2/vtweb/recovered']),
        ]);
        $this->actingAs($purchase->user)->post(route('purchases.store', $purchase->package_id))->assertSessionHasErrors('payment');
        $this->post(route('purchases.refresh', $purchase))->assertSessionHasNoErrors();
        $this->assertSame(1, Purchase::count());
        $this->assertSame('https://app.sandbox.midtrans.com/snap/v2/vtweb/recovered', $purchase->fresh()->checkout_url);
        Http::assertSent(fn (Request $request): bool => $request->method() === 'POST' && $request['transaction_details']['order_id'] === $purchase->reference);
    }

    public function test_checkout_rejects_untrusted_redirect_and_hides_provider_errors(): void
    {
        $purchase = $this->purchase();
        Http::fake([
            'api.sandbox.midtrans.com/*' => Http::response(['status_code' => '404']),
            'app.sandbox.midtrans.com/*' => Http::response(['redirect_url' => 'https://app.sandbox.midtrans.com.evil.example/checkout']),
        ]);
        $this->actingAs($purchase->user)->post(route('purchases.store', $purchase->package_id))->assertSessionHasErrors('payment');
        $this->assertNull($purchase->fresh()->checkout_url);
        $this->assertSame(0, CreditEntry::count());
    }

    public function test_missing_config_and_other_users_cannot_start_or_refresh_payment(): void
    {
        $purchase = $this->purchase();
        config(['services.midtrans.server_key' => null]);
        $this->actingAs($purchase->user)->post(route('purchases.store', $purchase->package_id))->assertSessionHasErrors('payment');
        $this->actingAs(User::factory()->create())->post(route('purchases.refresh', $purchase))->assertForbidden();
        $this->get(route('purchases.show', $purchase))->assertForbidden();
        $this->assertSame(1, Purchase::count());
        Http::assertNothingSent();
    }

    public function test_scheduled_reconciliation_skips_legacy_and_does_not_duplicate_grants(): void
    {
        $purchase = $this->purchase();
        $legacy = $this->purchase();
        $legacy->update(['provider' => null]);
        Http::fake(['api.sandbox.midtrans.com/*' => Http::response($this->invoice($purchase))]);
        $this->artisan('payments:reconcile')->assertSuccessful();
        $this->artisan('payments:reconcile')->assertSuccessful();
        $this->assertSame('PAID', $purchase->fresh()->status);
        $this->assertSame('PENDING', $legacy->fresh()->status);
        $this->assertSame(1, CreditEntry::count());
        Http::assertSentCount(1);
    }

    public function test_production_mode_uses_production_host(): void
    {
        $purchase = $this->purchase();
        config(['services.midtrans.is_production' => true]);
        Http::fake(['app.midtrans.com/*' => Http::response(['redirect_url' => 'https://app.midtrans.com/snap/v2/vtweb/production-token'])]);
        app(MidtransPayments::class)->invoice($purchase);
        $this->assertSame('https://app.midtrans.com/snap/v2/vtweb/production-token', $purchase->fresh()->checkout_url);
        Http::assertSentCount(1);
    }
}

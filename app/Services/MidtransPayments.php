<?php

namespace App\Services;

use App\Models\CreditEntry;
use App\Models\Purchase;
use App\Models\User;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class MidtransPayments
{
    private function client(): PendingRequest
    {
        abort_unless(config('services.midtrans.server_key') && config('services.midtrans.merchant_id'), 503, 'Pembayaran belum dikonfigurasi.');

        return Http::withBasicAuth(config('services.midtrans.server_key'), '')->acceptJson()->asJson()->timeout(10)->connectTimeout(5)->withOptions(['allow_redirects' => false]);
    }

    private function host(string $service): string
    {
        return $service.(config('services.midtrans.is_production') ? '.midtrans.com' : '.sandbox.midtrans.com');
    }

    public function invoice(Purchase $purchase): void
    {
        Cache::lock('invoice:'.$purchase->id, 30)->get(function () use ($purchase): void {
            $purchase->refresh();
            abort_unless($purchase->provider === 'midtrans', 422);
            if ($purchase->checkout_url || $purchase->provider_id || $purchase->status !== 'PENDING') {
                return;
            }
            // shortcut: A lost Snap response may need admin recovery; add checkout recovery if this occurs regularly.
            $response = $this->client()->post('https://'.$this->host('app').'/snap/v1/transactions', [
                'transaction_details' => ['order_id' => $purchase->reference, 'gross_amount' => $purchase->amount],
                'item_details' => [['id' => (string) $purchase->package_id, 'price' => $purchase->amount, 'quantity' => 1, 'name' => mb_substr($purchase->package_name, 0, 50)]],
                'customer_details' => ['first_name' => $purchase->user->name, 'email' => $purchase->user->email],
                'callbacks' => ['finish' => route('purchases.show', $purchase)],
            ])->throw()->json();
            $checkout = $response['redirect_url'] ?? null;
            abort_unless(is_string($checkout) && filter_var($checkout, FILTER_VALIDATE_URL) && parse_url($checkout, PHP_URL_SCHEME) === 'https' && parse_url($checkout, PHP_URL_HOST) === $this->host('app') && ! parse_url($checkout, PHP_URL_USER) && ! parse_url($checkout, PHP_URL_PASS), 502, 'Checkout Midtrans tidak valid.');
            $purchase->update(['checkout_url' => $checkout]);
        });
    }

    public function reconcile(Purchase $purchase): bool
    {
        abort_unless($purchase->provider === 'midtrans', 422, 'Transaksi ini menggunakan provider lama. Hubungi admin untuk pemeriksaan.');
        $response = $this->client()->get('https://'.$this->host('api').'/v2/'.rawurlencode($purchase->reference).'/status');
        // Snap has no Core API status until the customer selects a payment method.
        if ($response->status() === 404 || (string) $response->json('status_code') === '404') {
            return false;
        }
        $invoice = $response->throw()->json();
        abort_unless(is_array($invoice), 502);
        $this->apply($purchase, $invoice);

        return true;
    }

    /**
     * @param  array{order_id?: string, gross_amount?: string|int, currency?: string, merchant_id?: string, transaction_id?: string, transaction_status?: string, fraud_status?: string, status_code?: string}  $invoice
     */
    public function apply(Purchase $purchase, array $invoice): void
    {
        abort_unless($purchase->provider === 'midtrans' && ($invoice['order_id'] ?? null) === $purchase->reference && preg_match('/\A'.preg_quote((string) $purchase->amount, '/').'(?:\.0{1,2})?\z/', (string) ($invoice['gross_amount'] ?? '')) && ($invoice['currency'] ?? '') === 'IDR', 422, 'Nominal atau referensi pembayaran tidak sesuai.');
        abort_unless(config('services.midtrans.merchant_id') && ($invoice['merchant_id'] ?? null) === config('services.midtrans.merchant_id'), 422, 'Merchant pembayaran tidak sesuai.');
        abort_unless(is_string($invoice['transaction_id'] ?? null) && $invoice['transaction_id'] !== '' && strlen($invoice['transaction_id']) <= 100, 422);
        DB::transaction(function () use ($purchase, $invoice): void {
            $user = User::whereKey($purchase->user_id)->lockForUpdate()->firstOrFail();
            $purchase = Purchase::whereKey($purchase->id)->lockForUpdate()->firstOrFail();
            abort_unless($purchase->provider === 'midtrans' && (! $purchase->provider_id || $purchase->provider_id === $invoice['transaction_id']), 422);
            $purchase->provider_id = $invoice['transaction_id'];
            if (in_array($invoice['transaction_status'] ?? '', ['capture', 'settlement'], true) && (string) ($invoice['status_code'] ?? '') === '200' && ($invoice['fraud_status'] ?? 'accept') === 'accept' && $purchase->status !== 'PAID') {
                CreditEntry::create(['user_id' => $user->id, 'amount' => $purchase->credits, 'type' => 'PURCHASE', 'operation_key' => 'purchase:'.$purchase->id, 'reason' => 'Pembelian '.$purchase->package_name]);
                $user->credits += $purchase->credits;
                $user->save();
                $purchase->status = 'PAID';
                $purchase->paid_at = now();
            } elseif ($purchase->status !== 'PAID') {
                if (($invoice['transaction_status'] ?? '') === 'expire') {
                    $purchase->status = 'EXPIRED';
                } elseif (in_array($invoice['transaction_status'] ?? '', ['deny', 'cancel', 'failure'], true)) {
                    $purchase->status = 'FAILED';
                }
            }
            $purchase->save();
        }, 3);
    }
}

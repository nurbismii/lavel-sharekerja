<?php

namespace App\Services;

use App\Models\CreditEntry;
use App\Models\Purchase;
use App\Models\User;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class XenditPayments
{
    private function client(): PendingRequest
    {
        abort_unless(config('services.xendit.secret_key'), 503, 'Pembayaran belum dikonfigurasi.');

        return Http::withBasicAuth(config('services.xendit.secret_key'), '')->acceptJson()->timeout(15)->connectTimeout(5);
    }

    public function invoice(Purchase $purchase): void
    {
        if ($purchase->provider_id) {
            return;
        }
        $response = $this->client()->post('https://api.xendit.co/v2/invoices', ['external_id' => $purchase->reference, 'amount' => $purchase->amount, 'currency' => 'IDR', 'description' => 'ShareKerja — '.$purchase->package_name, 'payer_email' => $purchase->user->email, 'success_redirect_url' => route('purchases.show', $purchase), 'failure_redirect_url' => route('purchases.show', $purchase), 'invoice_duration' => 86400])->throw()->json();
        abort_unless(is_string($response['id'] ?? null) && filter_var($response['invoice_url'] ?? '', FILTER_VALIDATE_URL), 502, 'Invoice provider tidak valid.');
        $host = parse_url($response['invoice_url'], PHP_URL_HOST);
        abort_unless(parse_url($response['invoice_url'], PHP_URL_SCHEME) === 'https' && ($host === 'checkout.xendit.co' || $host === 'checkout-staging.xendit.co'), 502);
        $purchase->update(['provider_id' => $response['id'], 'checkout_url' => $response['invoice_url']]);
    }

    public function reconcile(Purchase $purchase): void
    {
        if ($purchase->provider_id) {
            $invoice = $this->client()->get('https://api.xendit.co/v2/invoices/'.rawurlencode($purchase->provider_id))->throw()->json();
        } else {
            $invoices = $this->client()->get('https://api.xendit.co/v2/invoices', ['external_id' => $purchase->reference, 'limit' => 10])->throw()->json();
            $invoice = collect($invoices)->first(fn ($item) => ($item['external_id'] ?? null) === $purchase->reference);
            if (! $invoice) {
                return;
            }
        }
        $this->apply($purchase, $invoice);
    }

    public function apply(Purchase $purchase, array $invoice): void
    {
        abort_unless(($invoice['external_id'] ?? null) === $purchase->reference && (string) ($invoice['amount'] ?? '') === (string) $purchase->amount && ($invoice['currency'] ?? '') === 'IDR', 422, 'Nominal atau referensi pembayaran tidak sesuai.');
        if (config('services.xendit.business_id')) {
            abort_unless(($invoice['user_id'] ?? null) === config('services.xendit.business_id'), 422);
        }
        abort_unless(! empty($invoice['id']) && (! $purchase->provider_id || $purchase->provider_id === $invoice['id']), 422);
        DB::transaction(function () use ($purchase, $invoice) {
            $user = User::whereKey($purchase->user_id)->lockForUpdate()->firstOrFail();
            $purchase = Purchase::whereKey($purchase->id)->lockForUpdate()->firstOrFail();
            abort_unless(! $purchase->provider_id || $purchase->provider_id === $invoice['id'], 422);
            $purchase->provider_id = $invoice['id'];
            $checkout = $invoice['invoice_url'] ?? null;
            if (is_string($checkout) && parse_url($checkout, PHP_URL_SCHEME) === 'https' && in_array(parse_url($checkout, PHP_URL_HOST), ['checkout.xendit.co', 'checkout-staging.xendit.co'], true)) {
                $purchase->checkout_url = $checkout;
            }
            if (in_array($invoice['status'] ?? '', ['PAID', 'SETTLED'], true) && $purchase->status !== 'PAID') {
                CreditEntry::create(['user_id' => $user->id, 'amount' => $purchase->credits, 'type' => 'PURCHASE', 'operation_key' => 'purchase:'.$purchase->id, 'reason' => 'Pembelian '.$purchase->package_name]);
                $user->credits += $purchase->credits;
                $user->save();
                $purchase->status = 'PAID';
                $purchase->paid_at = now();
            } elseif (($invoice['status'] ?? '') === 'EXPIRED' && $purchase->status !== 'PAID') {
                $purchase->status = 'EXPIRED';
            }
            $purchase->save();
        }, 3);
    }
}

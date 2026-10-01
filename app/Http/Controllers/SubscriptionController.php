<?php

namespace App\Http\Controllers;

use App\Models\Subscription;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SubscriptionController extends Controller
{
    public function edit(Request $request): View
    {
        return view('subscription', ['subscription' => $request->user()->subscription]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate(['active' => 'required|boolean', 'all_jobs' => 'nullable|accepted', 'category' => ['nullable', Rule::in(config('sharekerja.categories'))], 'location' => 'nullable|string|max:150', 'level' => ['nullable', Rule::in(config('sharekerja.levels'))], 'employment_type' => ['nullable', Rule::in(config('sharekerja.employment_types'))]]);
        $preferences = [];
        foreach (['category', 'location', 'level', 'employment_type'] as $field) {
            $preferences[$field] = empty($data[$field]) ? [] : [$data[$field]];
        }
        if ($request->boolean('active') && ! array_filter($preferences) && ! $request->boolean('all_jobs')) {
            return back()->withInput()->withErrors(['all_jobs' => 'Konfirmasi menerima semua lowongan atau pilih preferensi.']);
        }
        $subscription = $request->user()->subscription;
        Subscription::updateOrCreate(['user_id' => $request->user()->id], ['consent_version' => '4.2', 'unsubscribed_at' => $request->boolean('active') ? null : now(), 'active' => $request->boolean('active'), 'preferences' => $preferences, 'token' => $subscription?->token ?? Str::random(64), 'subscribed_at' => $request->boolean('active') ? ($subscription?->active ? $subscription->subscribed_at : now()) : null]);

        return back()->with('success', 'Preferensi email tersimpan.');
    }

    public function unsubscribe(string $token): View
    {
        $subscription = Subscription::where('token', $token)->firstOrFail();

        return view('unsubscribe', compact('subscription'));
    }

    public function stop(string $token): RedirectResponse
    {
        Subscription::where('token', $token)->firstOrFail()->update(['active' => false, 'subscribed_at' => null, 'unsubscribed_at' => now()]);

        return to_route('home')->with('success', 'Langganan email berhasil dihentikan.');
    }
}

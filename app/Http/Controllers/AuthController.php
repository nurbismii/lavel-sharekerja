<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Models\SocialAccount;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Laravel\Socialite\Facades\Socialite;

class AuthController extends Controller
{
    public function login(): View
    {
        return view('auth.login');
    }

    public function redirect(): RedirectResponse
    {
        if (! config('services.google.client_id') || ! config('services.google.client_secret') || ! config('services.google.redirect')) {
            return to_route('login')->withErrors(['login' => 'Login Google belum dikonfigurasi. Silakan hubungi pengelola.']);
        }

        return Socialite::driver('google')->redirect();
    }

    public function callback(Request $request): RedirectResponse
    {
        try {
            $identity = Socialite::driver('google')->user();
        } catch (\Throwable $exception) {
            return to_route('login')->withErrors(['login' => 'Login Google tidak selesai atau sesi kedaluwarsa. Silakan coba lagi.']);
        }
        if (! $identity->getEmail() || ! ($identity->user['email_verified'] ?? $identity->user['verified_email'] ?? false)) {
            return to_route('login')->withErrors(['login' => 'Gunakan akun Google dengan email terverifikasi.']);
        }
        try {
            $user = DB::transaction(function () use ($identity) {
                $account = SocialAccount::where('provider', 'google')->where('provider_user_id', $identity->getId())->first();
                if ($account) {
                    return User::findOrFail($account->user_id);
                }
                if (User::where('email', $identity->getEmail())->exists()) {
                    return null;
                }
                $user = User::create(['name' => $identity->getName() ?: $identity->getEmail(), 'email' => $identity->getEmail(), 'password' => Str::random(64)]);
                $user->forceFill(['email_verified_at' => now()])->save();
                SocialAccount::create(['user_id' => $user->id, 'provider' => 'google', 'provider_user_id' => $identity->getId(), 'provider_email' => $identity->getEmail()]);

                return $user;
            });
        } catch (UniqueConstraintViolationException) {
            $account = SocialAccount::where('provider', 'google')->where('provider_user_id', $identity->getId())->first();
            $user = $account ? User::find($account->user_id) : null;
        }
        if (! $user) {
            return to_route('login')->withErrors(['login' => 'Email sudah digunakan. Hubungi dukungan untuk memverifikasi kepemilikan akun.']);
        }
        abort_unless($user->status === 'ACTIVE', 403);
        Auth::login($user);
        $request->session()->regenerate();
        $user->forceFill(['last_login_at' => now()])->save();

        return to_route($user->roles()->exists() ? 'dashboard' : 'onboarding');
    }

    public function onboarding(): View
    {
        return view('auth.onboarding');
    }

    public function role(Request $request): RedirectResponse
    {
        $data = $request->validate(['role' => 'required|in:JOB_SEEKER,RECRUITER']);
        $role = Role::firstOrCreate(['name' => $data['role']]);
        DB::table('user_roles')->insertOrIgnore(['user_id' => $request->user()->id, 'role_id' => $role->id]);

        return to_route('dashboard')->with('success', 'Akun siap digunakan. Lengkapi profil Anda.');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return to_route('home');
    }
}

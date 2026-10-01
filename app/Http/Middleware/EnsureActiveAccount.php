<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureActiveAccount
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user() && $request->user()->status !== 'ACTIVE' && ! $request->routeIs('logout')) {
            abort(403, 'Akun Anda sedang ditangguhkan. Hubungi pengelola.');
        }

        $response = $next($request);
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        if ($request->user() || $request->routeIs('unsubscribe*', 'auth.*', 'login', 'xendit.webhook', 'jobs.external')) {
            $response->headers->set('Cache-Control', 'private, no-store');
        }

        return $response;
    }
}

<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Signing up and "forgot password" send an email from the site's mail account. Without a
 * limit a script could send thousands and get that account blocked for spam. These routes
 * come from Fortify, so they are limited here by route name.
 */
class LimitEmailSendingForms
{
    /**
     * Route name => [attempts, per seconds], per IP address.
     */
    private const LIMITS = [
        'register.store' => [5, 3600],
        'password.email' => [5, 3600],
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $name = $request->route()?->getName();

        if (! $request->isMethod('post') || ! isset(self::LIMITS[$name])) {
            return $next($request);
        }

        [$attempts, $seconds] = self::LIMITS[$name];
        $key = "email-form:{$name}:{$request->ip()}";

        if (RateLimiter::tooManyAttempts($key, $attempts)) {
            throw ValidationException::withMessages([
                'email' => __('Pārāk daudz mēģinājumu. Mēģini vēlreiz pēc :minutes min.', [
                    'minutes' => (int) ceil(RateLimiter::availableIn($key) / 60),
                ]),
            ]);
        }

        RateLimiter::hit($key, $seconds);

        return $next($request);
    }
}

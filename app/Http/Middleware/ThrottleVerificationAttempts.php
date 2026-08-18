<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Cache\RateLimiter;
use Illuminate\Http\Request;

class ThrottleVerificationAttempts
{
    protected $limiter;

    public function __construct(RateLimiter $limiter)
    {
        $this->limiter = $limiter;
    }

    public function handle(Request $request, Closure $next)
    {
        $email = $request->input('email');

        if (!$email) {
            return $next($request);
        }

        $key = 'verify-attempts:' . $email;
        $maxAttempts =  10;
        $decayMinutes = 20;

        if ($this->limiter->tooManyAttempts($key, $maxAttempts)) {
            $seconds = $this->limiter->availableIn($key);
            $minutes = ceil($seconds / 60);

            return response()->json([
                'success' => false,
                'message' => "Too many failed attempts. Please try again after {$minutes} minutes.",
            ], 429);
        }

        $response = $next($request);

        if ($response->getStatusCode() === 400) {
            $this->limiter->hit($key, $decayMinutes * 60);
        } elseif ($response->getStatusCode() === 200) {
            $this->limiter->clear($key);
        }

        return $response;
    }
}

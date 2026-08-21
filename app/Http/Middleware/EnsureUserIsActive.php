<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureUserIsActive
{
    /**
     * Routes that suspended users are allowed to access.
     * These are read-only operations and essential account management.
     */
    private const SUSPENDED_ALLOWED_ROUTES = [
        // Auth routes
        'me',                          // GET /me
        // Profile view routes
        'profiles.show',              // GET /profiles/{profile}
        'profiles.my-profile',        // GET /my-profile
        // Notification view route
        'notifications.index',        // GET /notifications
    ];

    /**
     * Route patterns (URI patterns) that suspended users are allowed to access.
     * Used when route names are not available.
     */
    private const SUSPENDED_ALLOWED_PATTERNS = [
        'api/me',                      // GET/DELETE /me
        'api/logout',                  // POST /logout
        'api/my-profile',              // GET /my-profile
        'api/profiles/*',              // GET /profiles/{profile} (view only)
        'api/notifications',           // GET /notifications (index only)
        'api/forgot-password',         // POST /forgot-password
        'api/reset-password',          // POST /reset-password
    ];

    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if (!$user) {
            return $next($request);
        }

        // Check if user is suspended (has suspended_at timestamp)
        if ($user->suspended_at !== null) {
            // Allow suspended users to access specific routes
            if ($this->isAllowedRouteForSuspendedUser($request)) {
                return $next($request);
            }

            return response()->json([
                'success' => false,
                'message' => 'Your account has been suspended. You have limited access to the platform.',
            ], 403);
        }

        // Check if user is deactivated (is_active = false but not suspended)
        if (!$user->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'Your account is deactivated. Please contact support.',
            ], 403);
        }

        return $next($request);
    }

    /**
     * Check if the current route is allowed for suspended users.
     */
    private function isAllowedRouteForSuspendedUser(Request $request): bool
    {
        $route = $request->route();
        $uri = $request->path();
        $method = $request->method();

        // Check by route name
        if ($route) {
            $routeName = $route->getName();
            if ($routeName && in_array($routeName, self::SUSPENDED_ALLOWED_ROUTES, true)) {
                return true;
            }
        }

        // Check by URI pattern and method
        // GET /me
        if ($uri === 'api/me' && $method === 'GET') {
            return true;
        }

        // DELETE /me (account deletion)
        if ($uri === 'api/me' && $method === 'DELETE') {
            return true;
        }

        // POST /logout
        if (($uri === 'api/logout' || $uri === 'api/logout-all' || str_starts_with($uri, 'api/devices')) && $method === 'POST') {
            return true;
        }

        // GET /my-profile
        if ($uri === 'api/my-profile' && $method === 'GET') {
            return true;
        }

        // GET /profiles/{profile} (view only, not PUT/PATCH)
        if (preg_match('#^api/profiles/[^/]+$#', $uri) && $method === 'GET') {
            return true;
        }

        // GET /notifications (view only)
        if ($uri === 'api/notifications' && $method === 'GET') {
            return true;
        }

        // POST /forgot-password, POST /reset-password (public routes, but just in case)
        if (in_array($uri, ['api/forgot-password', 'api/reset-password']) && $method === 'POST') {
            return true;
        }

        return false;
    }
}
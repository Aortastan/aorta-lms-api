<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class UpdateLastActivity
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        if (Auth::check()) {
            $user = Auth::user();
            // Optional: debounce update to prevent too many queries
            // if (!$user->last_activity_at || $user->last_activity_at->diffInMinutes(now()) >= 1)
            $user->last_activity_at = now();
            $user->save();
        }

        return $next($request);
    }
}

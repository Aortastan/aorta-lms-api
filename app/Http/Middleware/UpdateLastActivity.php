<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Carbon\Carbon;
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
            // Debounce update to prevent too many queries: at most once every 2 minutes
            if (!$user->last_activity_at || Carbon::parse($user->last_activity_at)->diffInMinutes(now()) >= 2) {
                $user->last_activity_at = now();
                $user->save();
            }
        }

        return $next($request);
    }
}

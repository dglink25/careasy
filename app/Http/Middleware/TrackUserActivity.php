<?php

namespace App\Http\Middleware;

use Carbon\Carbon;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;


class TrackUserActivity{
    public function handle(Request $request, Closure $next): Response
    {
        if ($user = $request->user()) {
            $cacheKey = "user_activity_{$user->id}";

            // Throttle : mise à jour max toutes les 5 minutes
            if (!cache()->has($cacheKey)) {
                $user->timestamps = false; // Ne pas modifier updated_at
                $user->last_activity_at = now();

                // Réactiver si inactif
                if ($user->activity_status === 'inactive') {
                    $user->activity_status             = 'active';
                    $user->inactivity_reminder_count   = 0;
                    $user->last_inactivity_reminder_at = null;
                }

                $user->save();

                cache()->put($cacheKey, true, Carbon::now()->addMinutes(5));
            }
        }

        return $next($request);
    }
}
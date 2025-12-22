<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class MaintenanceMode
{
    public function handle(Request $request, Closure $next)
    {
        if (env('APP_MAINTENANCE', false) === true) {
            return response()->view('maintenance', [], 503);
        }

        return $next($request);
    }
}

<?php

namespace App\Http\Middleware;

use Illuminate\Auth\Middleware\Authenticate as Middleware;
use Illuminate\Http\Request;

class Authenticate extends Middleware
{
    /**
     * Get the path the user should be redirected to when they are not authenticated.
     */
    protected function redirectTo(Request $request): ?string
    {
        // API请求不重定向，直接返回401
        if ($request->expectsJson() || $request->is('api/*')) {
            return null;
        }
        
        // Web请求也不重定向，因为这是一个API应用
        return null;
    }
}

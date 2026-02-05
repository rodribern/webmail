<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureImapAuthenticated
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!session()->has('imap_credentials') || !session()->has('user')) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Não autenticado.'], 401);
            }

            return redirect()->route('login');
        }

        return $next($request);
    }
}

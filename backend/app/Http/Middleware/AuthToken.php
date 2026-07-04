<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;

class AuthToken
{
    /**
     * Middleware d'authentification par token simple.
     * J'aurais pu utiliser Sanctum mais j'ai préféré garder le contrôle
     * sur la logique d'auth pour ce projet.
     */
    public function handle(Request $request, Closure $next, string $role = null)
    {
        // le token peut venir du header Authorization ou en query param (pour l'export CSV)
        $token = $request->bearerToken() ?? $request->query('token');
        $user  = $token ? User::where('token', $token)->where('actif', true)->first() : null;

        if (!$user) {
            return response()->json(['message' => 'Non authentifié.'], 401);
        }

        // l'admin a accès à tout, pas besoin de vérifier le rôle
        if ($role && $user->role !== $role && $user->role !== 'ADMIN') {
            return response()->json(['message' => 'Accès refusé.'], 403);
        }

        $request->merge(['_user' => $user]);
        return $next($request);
    }
}

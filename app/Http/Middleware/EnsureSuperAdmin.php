<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureSuperAdmin
{
    /**
     * Handle an incoming request.
     * Hanya memperbolehkan akses bagi pengguna dengan role Super Admin (role_id === 1).
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        $isSuperAdmin = $user && (
            (int)$user->role_id === 1 || 
            ($user->role && strtolower($user->role->nama_role) === 'super admin')
        );

        if (!$isSuperAdmin) {
            if ($request->expectsJson() || $request->ajax() || $request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Akses ditolak. Tindakan ini hanya diizinkan bagi Super Admin.',
                ], 403);
            }

            abort(403, 'Akses ditolak. Fitur pengelolaan data ini hanya diizinkan bagi Super Admin.');
        }

        return $next($request);
    }
}

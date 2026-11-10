<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckPermission
{
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        if (!auth()->check()) {
            return redirect()->route('login');
        }

        $user = auth()->user();

        if ($user->hasPermission($permission)) {
            return $next($request);
        }

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json(['status' => 'error', 'message' => "Bạn chưa được cấp quyền ({$permission})."], 403);
        }

        abort(403, "Bạn không có quyền truy cập chức năng ({$permission}).");
    }
}

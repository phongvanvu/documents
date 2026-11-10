<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckRole
{
    public function handle(Request $request, Closure $next, ...$roles): Response
    {
        if (!auth()->check()) {
            return redirect()->route('login');
        }

        $user = auth()->user();

        if ($user->isAdmin() || in_array($user->role, $roles, true)) {
            return $next($request);
        }

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json(['status' => 'error', 'message' => 'Bạn không có quyền thực hiện chức năng này.'], 403);
        }

        abort(403, 'Bạn không có quyền truy cập trang này.');
    }
}

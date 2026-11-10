<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\UserSession;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    public function index()
    {
        $users = User::withCount('logs')->orderBy('id', 'desc')->paginate(15);
        return view('users.index', compact('users'));
    }

    public function create()
    {
        return view('users.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'username' => 'required|string|max:50|unique:users,username',
            'full_name' => 'required|string|max:255',
            'email' => 'nullable|email|max:100',
            'password' => 'required|min:6',
            'role' => 'required|in:admin,editor,viewer',
        ], [
            'username.required' => 'Vui lòng nhập tên đăng nhập.',
            'username.unique' => 'Tên đăng nhập này đã được sử dụng.',
            'full_name.required' => 'Vui lòng nhập họ và tên.',
            'password.required' => 'Vui lòng nhập mật khẩu.',
            'password.min' => 'Mật khẩu phải có ít nhất 6 ký tự.',
        ]);

        $user = User::create([
            'username' => $validated['username'],
            'full_name' => $validated['full_name'],
            'email' => $validated['email'] ?? null,
            'password' => Hash::make($validated['password']),
            'role' => $validated['role'],
        ]);

        log_activity("Tạo tài khoản người dùng mới: {$user->username} ({$user->role})");

        return redirect()->route('users.index')->with('success', 'Đã tạo người dùng mới thành công!');
    }

    public function edit($id)
    {
        $user = User::findOrFail($id);
        return view('users.edit', compact('user'));
    }

    public function update(Request $request, $id)
    {
        $user = User::findOrFail($id);

        $validated = $request->validate([
            'full_name' => 'required|string|max:255',
            'email' => 'nullable|email|max:100',
            'password' => 'nullable|min:6',
            'role' => 'required|in:admin,editor,viewer',
        ]);

        $user->full_name = $validated['full_name'];
        $user->email = $validated['email'] ?? null;
        $user->role = $validated['role'];

        if (!empty($validated['password'])) {
            $user->password = Hash::make($validated['password']);
        }

        $user->save();
        log_activity("Cập nhật tài khoản: {$user->username}");

        return redirect()->route('users.index')->with('success', 'Cập nhật thông tin tài khoản thành công!');
    }

    public function destroy($id)
    {
        $user = User::findOrFail($id);

        if ($user->id === auth()->id()) {
            return back()->with('error', 'Bạn không thể tự xóa tài khoản của chính mình.');
        }

        $username = $user->username;
        $user->delete();

        log_activity("Xóa tài khoản người dùng: {$username}");

        return back()->with('success', 'Đã xóa người dùng thành công.');
    }

    // Quản lý quyền theo vai trò
    public function permissions()
    {
        $allPermissions = DB::table('permissions')->orderBy('module')->orderBy('id')->get()->groupBy('module');
        $rolePermissions = DB::table('role_permissions')->get();

        $matrix = [];
        foreach ($rolePermissions as $rp) {
            $matrix[$rp->role][$rp->perm_key] = true;
        }

        return view('users.permissions', compact('allPermissions', 'matrix'));
    }

    public function togglePermission(Request $request)
    {
        $role = $request->input('role');
        $permKey = $request->input('perm_key');
        $enable = $request->boolean('enable');

        if ($role === 'admin') {
            return response()->json(['status' => 'error', 'message' => 'Admin luôn có toàn quyền.'], 400);
        }

        if ($enable) {
            $exists = DB::table('role_permissions')->where([
                'role' => $role,
                'perm_key' => $permKey,
            ])->exists();

            if (!$exists) {
                DB::table('role_permissions')->insert([
                    'role' => $role,
                    'perm_key' => $permKey,
                ]);
            }
        } else {
            DB::table('role_permissions')->where([
                'role' => $role,
                'perm_key' => $permKey,
            ])->delete();
        }

        log_activity("Thay đổi quyền ({$permKey}) cho vai trò ({$role}): " . ($enable ? 'Bật' : 'Tắt'));

        return response()->json(['status' => 'success', 'message' => 'Cập nhật quyền thành công!']);
    }

    // Quản lý phiên đăng nhập
    public function sessions()
    {
        $sessions = UserSession::with('user')->orderBy('last_activity', 'desc')->paginate(20);
        return view('users.sessions', compact('sessions'));
    }

    public function revokeSession($id)
    {
        $session = UserSession::findOrFail($id);
        $session->delete();

        return back()->with('success', 'Đã thu hồi phiên đăng nhập.');
    }
}

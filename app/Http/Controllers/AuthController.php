<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function showLogin()
    {
        if (Auth::check()) {
            return redirect()->route('dashboard');
        }
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'username' => 'required|string',
            'password' => 'required|string',
        ], [
            'username.required' => 'Vui lòng nhập tên đăng nhập.',
            'password.required' => 'Vui lòng nhập mật khẩu.',
        ]);

        $remember = $request->boolean('remember');

        if (Auth::attempt(['username' => $credentials['username'], 'password' => $credentials['password']], $remember)) {
            $request->session()->regenerate();
            log_activity('Đăng nhập hệ thống thành công');
            return redirect()->intended(route('dashboard'));
        }

        return back()->withErrors([
            'username' => 'Tên đăng nhập hoặc mật khẩu không chính xác.',
        ])->onlyInput('username');
    }

    public function logout(Request $request)
    {
        if (Auth::check()) {
            log_activity('Đăng xuất khỏi hệ thống');
        }

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('success', 'Đã đăng xuất thành công.');
    }

    public function showProfile()
    {
        return view('auth.profile', [
            'user' => Auth::user(),
        ]);
    }

    public function updateProfile(Request $request)
    {
        $user = Auth::user();

        $validated = $request->validate([
            'full_name' => 'required|string|max:255',
            'email' => 'nullable|email|max:100',
            'avatar' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048',
        ]);

        $user->full_name = $validated['full_name'];
        $user->email = $validated['email'] ?? null;

        if ($request->hasFile('avatar') && $request->file('avatar')->isValid()) {
            $avatarFile = $request->file('avatar');
            $ext = $avatarFile->getClientOriginalExtension();
            $filename = 'avatar_' . time() . '_' . bin2hex(random_bytes(3)) . '.' . $ext;
            
            $targetDir = public_path('uploads/avatars');
            if (!is_dir($targetDir)) {
                mkdir($targetDir, 0755, true);
            }
            
            $avatarFile->move($targetDir, $filename);
            $user->avatar = 'uploads/avatars/' . $filename;
        }

        $user->save();
        log_activity('Cập nhật thông tin hồ sơ cá nhân');

        return back()->with('success', 'Cập nhật thông tin cá nhân thành công!');
    }

    public function showChangePassword()
    {
        return view('auth.change_password');
    }

    public function updatePassword(Request $request)
    {
        $request->validate([
            'current_password' => 'required',
            'new_password' => 'required|min:6|confirmed',
        ], [
            'current_password.required' => 'Vui lòng nhập mật khẩu hiện tại.',
            'new_password.required' => 'Vui lòng nhập mật khẩu mới.',
            'new_password.min' => 'Mật khẩu mới phải có ít nhất 6 ký tự.',
            'new_password.confirmed' => 'Xác nhận mật khẩu mới không khớp.',
        ]);

        $user = Auth::user();

        if (!Hash::check($request->current_password, $user->password)) {
            return back()->withErrors(['current_password' => 'Mật khẩu hiện tại không chính xác.']);
        }

        $user->password = Hash::make($request->new_password);
        $user->save();

        log_activity('Đổi mật khẩu tài khoản');

        return back()->with('success', 'Đổi mật khẩu thành công!');
    }
}

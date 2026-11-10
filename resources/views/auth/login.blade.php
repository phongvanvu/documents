@extends('layouts.auth')

@section('title', 'Đăng nhập - ' . get_setting('site_title'))

@section('content')
<div class="card login-card p-4 p-md-5 bg-white">
    <div class="text-center mb-4">
        <img src="{{ asset('logo.webp') }}" alt="Logo" class="mb-3" style="width: 80px; height: 80px; object-fit: contain;" onerror="this.style.display='none'">
        <h4 class="fw-bold text-danger mb-1">{{ get_setting('agency_name', 'PHÒNG TÀI CHÍNH KẾ TOÁN') }}</h4>
        <p class="text-muted small mb-0">{{ get_setting('site_title', 'HỆ THỐNG QUẢN LÝ VĂN BẢN') }}</p>
    </div>

    @if(session('error'))
        <div class="alert alert-danger py-2 small">{{ session('error') }}</div>
    @endif

    @if(session('success'))
        <div class="alert alert-success py-2 small">{{ session('success') }}</div>
    @endif

    <form action="{{ route('login.post') }}" method="POST">
        @csrf
        <div class="mb-3">
            <label class="form-label fw-semibold small text-muted">Tên đăng nhập</label>
            <div class="input-group">
                <span class="input-group-text bg-light border-end-0"><i class="fas fa-user text-muted"></i></span>
                <input type="text" name="username" class="form-control border-start-0 @error('username') is-invalid @enderror"
                       value="{{ old('username') }}" placeholder="Nhập tên đăng nhập..." required autofocus>
            </div>
            @error('username')
                <div class="text-danger small mt-1">{{ $message }}</div>
            @enderror
        </div>

        <div class="mb-3">
            <label class="form-label fw-semibold small text-muted">Mật khẩu</label>
            <div class="input-group">
                <span class="input-group-text bg-light border-end-0"><i class="fas fa-lock text-muted"></i></span>
                <input type="password" name="password" class="form-control border-start-0 @error('password') is-invalid @enderror"
                       placeholder="Nhập mật khẩu..." required>
            </div>
            @error('password')
                <div class="text-danger small mt-1">{{ $message }}</div>
            @enderror
        </div>

        <div class="d-flex align-items-center justify-content-between mb-4">
            <div class="form-check">
                <input class="form-check-input" type="checkbox" name="remember" id="remember" {{ old('remember') ? 'checked' : '' }}>
                <label class="form-check-label small text-muted" for="remember">Ghi nhớ đăng nhập</label>
            </div>
        </div>

        <button type="submit" class="btn btn-danger w-100 py-2 fw-bold shadow-sm">
            <i class="fas fa-sign-in-alt me-1"></i>Đăng nhập
        </button>
    </form>
</div>
@endsection

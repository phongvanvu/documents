@extends('layouts.app')

@section('title', 'Đổi mật khẩu - ' . get_setting('site_title'))

@section('content')
<div class="row justify-content-center">
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
            <div class="card-header bg-danger text-white py-3 px-4">
                <h5 class="card-title mb-0 fw-bold"><i class="fas fa-key me-2"></i>Đổi mật khẩu tài khoản</h5>
            </div>
            <div class="card-body p-4">
                <form action="{{ route('change-password.update') }}" method="POST">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Mật khẩu hiện tại <span class="text-danger">*</span></label>
                        <input type="password" name="current_password" class="form-control @error('current_password') is-invalid @enderror" required>
                        @error('current_password')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Mật khẩu mới <span class="text-danger">*</span></label>
                        <input type="password" name="new_password" class="form-control @error('new_password') is-invalid @enderror" required>
                        @error('new_password')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-semibold">Xác nhận mật khẩu mới <span class="text-danger">*</span></label>
                        <input type="password" name="new_password_confirmation" class="form-control" required>
                    </div>

                    <div class="d-flex justify-content-end gap-2">
                        <button type="submit" class="btn btn-danger px-4 fw-bold">
                            <i class="fas fa-check-circle me-1"></i>Cập nhật mật khẩu
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

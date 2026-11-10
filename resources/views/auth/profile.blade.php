@extends('layouts.app')

@section('title', 'Hồ sơ cá nhân - ' . get_setting('site_title'))

@section('content')
<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
            <div class="card-header bg-danger text-white py-3 px-4">
                <h5 class="card-title mb-0 fw-bold"><i class="fas fa-id-card me-2"></i>Hồ sơ cá nhân</h5>
            </div>
            <div class="card-body p-4">
                <form action="{{ route('profile.update') }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    <div class="d-flex align-items-center mb-4">
                        <img src="{{ $user->avatar_url }}" alt="Avatar"
                             class="rounded-circle border border-3 border-danger shadow-sm me-3"
                             style="width: 80px; height: 80px; object-fit: cover;">
                        <div>
                            <h5 class="fw-bold mb-1">{{ $user->full_name }}</h5>
                            <span class="badge bg-danger">{{ strtoupper($user->role) }}</span>
                            <div class="mt-2">
                                <label class="btn btn-outline-secondary btn-sm" for="avatar">
                                    <i class="fas fa-camera me-1"></i>Đổi ảnh đại diện
                                </label>
                                <input type="file" name="avatar" id="avatar" class="d-none" accept="image/*" onchange="this.form.submit()">
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Tên đăng nhập</label>
                        <input type="text" class="form-control bg-light" value="{{ $user->username }}" readonly disabled>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Họ và tên <span class="text-danger">*</span></label>
                        <input type="text" name="full_name" class="form-control @error('full_name') is-invalid @enderror"
                               value="{{ old('full_name', $user->full_name) }}" required>
                        @error('full_name')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-semibold">Địa chỉ Email</label>
                        <input type="email" name="email" class="form-control @error('email') is-invalid @enderror"
                               value="{{ old('email', $user->email) }}" placeholder="name@example.com">
                        @error('email')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="d-flex justify-content-end gap-2">
                        <button type="submit" class="btn btn-danger px-4 fw-bold">
                            <i class="fas fa-save me-1"></i>Lưu thay đổi
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

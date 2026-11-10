@extends('layouts.app')

@section('title', 'Thêm Người dùng mới')

@section('content')
<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm rounded-4 mb-4">
            <div class="card-header bg-white border-bottom py-3 px-4 d-flex justify-content-between align-items-center">
                <h5 class="fw-bold mb-0 text-dark"><i class="fas fa-user-plus text-danger me-2"></i>Thêm người dùng mới</h5>
                <a href="{{ route('users.index') }}" class="btn btn-outline-secondary btn-sm rounded-pill px-3">
                    <i class="fas fa-arrow-left me-1"></i>Quay lại
                </a>
            </div>
            <div class="card-body p-4">
                <form action="{{ route('users.store') }}" method="POST">
                    @csrf
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">Tên đăng nhập <span class="text-danger">*</span></label>
                            <input type="text" name="username" class="form-control" value="{{ old('username') }}" placeholder="VD: nguyenvanan" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">Họ và tên <span class="text-danger">*</span></label>
                            <input type="text" name="full_name" class="form-control" value="{{ old('full_name') }}" placeholder="VD: Nguyễn Văn An" required>
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">Email</label>
                            <input type="email" name="email" class="form-control" value="{{ old('email') }}" placeholder="email@example.com">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">Mật khẩu khởi tạo <span class="text-danger">*</span></label>
                            <input type="password" name="password" class="form-control" placeholder="Tối thiểu 6 ký tự..." required>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-semibold small">Vai trò truy cập <span class="text-danger">*</span></label>
                        <select name="role" class="form-select" required>
                            <option value="viewer" {{ old('role') === 'viewer' ? 'selected' : '' }}>Viewer (Chỉ xem và tải văn bản)</option>
                            <option value="editor" {{ old('role') === 'editor' ? 'selected' : '' }}>Editor (Xem, thêm mới, sửa văn bản và tài liệu đính kèm)</option>
                            <option value="admin" {{ old('role') === 'admin' ? 'selected' : '' }}>Admin (Toàn quyền quản trị hệ thống)</option>
                        </select>
                    </div>

                    <div class="d-flex justify-content-end gap-2 pt-3 border-top">
                        <a href="{{ route('users.index') }}" class="btn btn-outline-secondary px-4">Hủy bỏ</a>
                        <button type="submit" class="btn btn-danger px-4 fw-bold">
                            <i class="fas fa-save me-1"></i>Lưu người dùng
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

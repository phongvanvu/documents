@extends('layouts.app')

@section('title', 'Cấu hình hệ thống - ' . get_setting('site_title'))

@section('content')
<div class="row justify-content-center">
    <div class="col-lg-10">
        <div class="card border-0 shadow-sm rounded-4 mb-4">
            <div class="card-header bg-white border-bottom py-3 px-4">
                <h5 class="fw-bold mb-0 text-dark"><i class="fas fa-sliders-h text-danger me-2"></i>Cấu hình & Tham số hệ thống</h5>
            </div>
            <div class="card-body p-4">
                <form action="{{ route('system.settings.update') }}" method="POST">
                    @csrf

                    <!-- 1. THÔNG TIN CƠ QUAN & TIÊU ĐỀ -->
                    <h6 class="fw-bold text-danger mb-3 border-bottom pb-2">
                        <i class="fas fa-landmark me-2"></i>1. Thông tin đơn vị & Giao diện
                    </h6>
                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">Tiêu đề trang (Site Title)</label>
                            <input type="text" name="site_title" class="form-control" value="{{ $settings['site_title'] ?? '' }}" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">Tên phòng / ban trực tiếp quản lý</label>
                            <input type="text" name="agency_name" class="form-control" value="{{ $settings['agency_name'] ?? '' }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">Cơ quan chủ quản cấp trên</label>
                            <input type="text" name="parent_agency" class="form-control" value="{{ $settings['parent_agency'] ?? '' }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">Tiêu đề in sổ theo dõi văn bản</label>
                            <input type="text" name="register_header_title" class="form-control" value="{{ $settings['register_header_title'] ?? '' }}">
                        </div>
                        <div class="col-md-12">
                            <label class="form-label fw-semibold small">Nội dung chân trang (Footer Copyright)</label>
                            <input type="text" name="footer_copyright" class="form-control" value="{{ $settings['footer_copyright'] ?? '' }}">
                        </div>
                    </div>

                    <!-- 2. BẢO MẬT & ĐÓNG DẤU MỜ WATERMARK -->
                    <h6 class="fw-bold text-danger mb-3 border-bottom pb-2">
                        <i class="fas fa-shield-alt me-2"></i>2. Bảo mật tài liệu & Tải lên
                    </h6>
                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">Dòng chữ đóng dấu mờ PDF (Watermark text)</label>
                            <input type="text" name="watermark_text" class="form-control" value="{{ $settings['watermark_text'] ?? '' }}">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold small">Dung lượng tải lên tối đa (MB)</label>
                            <input type="number" name="max_upload_size_mb" class="form-control" value="{{ $settings['max_upload_size_mb'] ?? '120' }}">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold small">Các mốc cảnh báo hết hạn (ngày)</label>
                            <input type="text" name="alert_expiry_days" class="form-control" value="{{ $settings['alert_expiry_days'] ?? '30,60' }}">
                        </div>
                        <div class="col-md-12">
                            <label class="form-label fw-semibold small">Các định dạng tệp cho phép</label>
                            <input type="text" name="allowed_file_exts" class="form-control" value="{{ $settings['allowed_file_exts'] ?? 'pdf,doc,docx,xls,xlsx,png,jpg,jpeg,zip,rar' }}">
                        </div>
                    </div>

                    <div class="d-flex justify-content-end gap-2 pt-3 border-top">
                        <button type="submit" class="btn btn-danger px-4 fw-bold shadow-sm">
                            <i class="fas fa-save me-1"></i>Lưu cấu hình hệ thống
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

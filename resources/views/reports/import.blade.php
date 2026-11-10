@extends('layouts.app')

@section('title', 'Nhập danh mục từ CSV')

@section('content')
<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm rounded-4 mb-4">
            <div class="card-header bg-white border-bottom py-3 px-4">
                <h5 class="fw-bold mb-0 text-dark"><i class="fas fa-file-import text-danger me-2"></i>Nhập danh mục văn bản từ tệp CSV</h5>
            </div>
            <div class="card-body p-4">
                <div class="alert alert-info border-0 rounded-3 mb-4 small">
                    <h6 class="fw-bold"><i class="fas fa-info-circle me-1"></i>Quy chuẩn định dạng tệp CSV:</h6>
                    <ul class="mb-0 ps-3">
                        <li>Cột 1: <strong>Số / Ký hiệu văn bản</strong> (Bắt buộc)</li>
                        <li>Cột 2: <strong>Ngày ban hành</strong> (Định dạng: YYYY-MM-DD hoặc DD/MM/YYYY)</li>
                        <li>Cột 3: <strong>Tên loại văn bản</strong> (VD: Nghị định, Quyết định...)</li>
                        <li>Cột 4: <strong>Tên cơ quan ban hành</strong> (VD: Chính phủ, Bộ Tài chính...)</li>
                        <li>Cột 5: <strong>Trích yếu nội dung</strong> (Bắt buộc)</li>
                    </ul>
                </div>

                <form action="{{ route('reports.import.post') }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    <div class="mb-4">
                        <label class="form-label fw-semibold">Chọn tệp CSV từ máy tính (.csv, tối đa 10MB) <span class="text-danger">*</span></label>
                        <input type="file" name="csv_file" class="form-control" accept=".csv,text/csv,text/plain" required>
                    </div>

                    <div class="d-flex justify-content-end gap-2">
                        <button type="submit" class="btn btn-danger px-4 fw-bold">
                            <i class="fas fa-upload me-1"></i>Bắt đầu nhập dữ liệu
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

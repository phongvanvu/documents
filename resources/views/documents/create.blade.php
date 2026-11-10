@extends('layouts.app')

@section('title', 'Thêm văn bản mới - ' . get_setting('site_title'))

@section('content')
<div class="row justify-content-center">
    <div class="col-lg-10">
        <div class="card border-0 shadow-sm rounded-4 mb-4">
            <div class="card-header bg-white border-bottom py-3 px-4 d-flex justify-content-between align-items-center">
                <h5 class="fw-bold mb-0 text-dark"><i class="fas fa-plus-circle text-danger me-2"></i>Thêm văn bản pháp quy mới</h5>
                <a href="{{ route('documents.index') }}" class="btn btn-outline-secondary btn-sm rounded-pill px-3">
                    <i class="fas fa-arrow-left me-1"></i>Quay lại danh sách
                </a>
            </div>
            <div class="card-body p-4">
                <form action="{{ route('documents.store') }}" method="POST" enctype="multipart/form-data" id="documentForm">
                    @csrf

                    <div class="row g-3 mb-3">
                        <!-- Số / Ký hiệu -->
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Số / Ký hiệu văn bản <span class="text-danger">*</span></label>
                            <input type="text" name="document_number" id="document_number"
                                   class="form-control @error('document_number') is-invalid @enderror"
                                   value="{{ old('document_number') }}" placeholder="VD: 123/QĐ-BV, 45/TB-TCKT..." required>
                            <div id="duplicateAlert" class="small mt-1 text-danger d-none">
                                <i class="fas fa-exclamation-triangle me-1"></i>Số hiệu này đã tồn tại trong hệ thống!
                            </div>
                            @error('document_number')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <!-- Loại văn bản -->
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Loại văn bản <span class="text-danger">*</span></label>
                            <select name="document_type_id" class="form-select @error('document_type_id') is-invalid @enderror" required>
                                <option value="">-- Chọn loại VB --</option>
                                @foreach($documentTypes as $t)
                                    <option value="{{ $t->id }}" {{ old('document_type_id') == $t->id ? 'selected' : '' }}>{{ $t->type_name }}</option>
                                @endforeach
                            </select>
                            @error('document_type_id')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <!-- Cơ quan ban hành -->
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Cơ quan ban hành <span class="text-danger">*</span></label>
                            <select name="authority_id" class="form-select @error('authority_id') is-invalid @enderror" required>
                                <option value="">-- Chọn cơ quan --</option>
                                @foreach($authorities as $a)
                                    <option value="{{ $a->id }}" {{ old('authority_id') == $a->id ? 'selected' : '' }}>{{ $a->authority_name }}</option>
                                @endforeach
                            </select>
                            @error('authority_id')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <!-- Ngày ban hành -->
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Ngày ban hành <span class="text-danger">*</span></label>
                            <input type="date" name="issued_date" class="form-control @error('issued_date') is-invalid @enderror"
                                   value="{{ old('issued_date', date('Y-m-d')) }}" required>
                            @error('issued_date')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <!-- Ngày có hiệu lực -->
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Ngày có hiệu lực</label>
                            <input type="date" name="effective_date" class="form-control @error('effective_date') is-invalid @enderror"
                                   value="{{ old('effective_date') }}">
                            @error('effective_date')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <!-- Ngày hết hiệu lực -->
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Ngày hết hiệu lực</label>
                            <input type="date" name="expired_date" class="form-control @error('expired_date') is-invalid @enderror"
                                   value="{{ old('expired_date') }}">
                            @error('expired_date')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <!-- Trạng thái -->
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Trạng thái hiệu lực <span class="text-danger">*</span></label>
                            <select name="status" class="form-select @error('status') is-invalid @enderror" required>
                                <option value="Còn hiệu lực" {{ old('status', 'Còn hiệu lực') === 'Còn hiệu lực' ? 'selected' : '' }}>Còn hiệu lực</option>
                                <option value="Hết hiệu lực" {{ old('status') === 'Hết hiệu lực' ? 'selected' : '' }}>Hết hiệu lực</option>
                                <option value="Chưa có hiệu lực" {{ old('status') === 'Chưa có hiệu lực' ? 'selected' : '' }}>Chưa có hiệu lực</option>
                                <option value="Hết hiệu lực một phần" {{ old('status') === 'Hết hiệu lực một phần' ? 'selected' : '' }}>Hết hiệu lực một phần</option>
                                <option value="Không xác định" {{ old('status') === 'Không xác định' ? 'selected' : '' }}>Không xác định</option>
                            </select>
                            @error('status')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <!-- Tệp đính kèm -->
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Tệp văn bản chính (PDF, Word, Excel, ZIP... tối đa 120MB)</label>
                            <input type="file" name="document_file" class="form-control @error('document_file') is-invalid @enderror"
                                   accept=".pdf,.doc,.docx,.xls,.xlsx,.png,.jpg,.jpeg,.zip,.rar">
                            @error('document_file')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <!-- Trích yếu nội dung -->
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Trích yếu nội dung <span class="text-danger">*</span></label>
                        <textarea name="content" rows="4" class="form-control @error('content') is-invalid @enderror"
                                  placeholder="Nhập tóm tắt trích yếu nội dung của văn bản..." required>{{ old('content') }}</textarea>
                        @error('content')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <!-- Mật Checkbox -->
                    <div class="form-check form-switch mb-4">
                        <input class="form-check-input" type="checkbox" name="is_secret" id="is_secret" value="1" {{ old('is_secret') ? 'checked' : '' }}>
                        <label class="form-check-label fw-semibold text-danger" for="is_secret">
                            <i class="fas fa-user-secret me-1"></i>Đánh dấu văn bản MẬT (Hệ thống sẽ không lưu tệp tải lên)
                        </label>
                    </div>

                    <!-- SUBMIT BUTTONS -->
                    <div class="d-flex justify-content-end gap-2 pt-3 border-top">
                        <a href="{{ route('documents.index') }}" class="btn btn-outline-secondary px-4">Hủy bỏ</a>
                        <button type="submit" class="btn btn-danger px-4 fw-bold">
                            <i class="fas fa-save me-1"></i>Lưu văn bản
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    document.getElementById('document_number').addEventListener('blur', function() {
        var val = this.value.trim();
        var alertEl = document.getElementById('duplicateAlert');
        if (val.length > 2) {
            fetch('{{ route("documents.check-duplicate") }}?document_number=' + encodeURIComponent(val))
                .then(res => res.json())
                .then(data => {
                    if (data.exists) {
                        alertEl.classList.remove('d-none');
                    } else {
                        alertEl.classList.add('d-none');
                    }
                });
        } else {
            alertEl.classList.add('d-none');
        }
    });
</script>
@endpush

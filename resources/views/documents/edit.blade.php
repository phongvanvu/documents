@extends('layouts.app')

@section('title', 'Chỉnh sửa văn bản - ' . get_setting('site_title'))

@section('content')
<div class="row justify-content-center">
    <div class="col-lg-10">
        <div class="card border-0 shadow-sm rounded-4 mb-4">
            <div class="card-header bg-white border-bottom py-3 px-4 d-flex justify-content-between align-items-center">
                <h5 class="fw-bold mb-0 text-dark">
                    <i class="fas fa-edit text-danger me-2"></i>Chỉnh sửa văn bản: <span class="text-danger">{{ $document->document_number }}</span>
                </h5>
                <a href="{{ route('documents.show', $document->id) }}" class="btn btn-outline-secondary btn-sm rounded-pill px-3">
                    <i class="fas fa-arrow-left me-1"></i>Quay lại chi tiết
                </a>
            </div>
            <div class="card-body p-4">
                <form action="{{ route('documents.update', $document->id) }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    @method('PUT')

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Số / Ký hiệu văn bản <span class="text-danger">*</span></label>
                            <input type="text" name="document_number" class="form-control @error('document_number') is-invalid @enderror"
                                   value="{{ old('document_number', $document->document_number) }}" required>
                            @error('document_number')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Loại văn bản <span class="text-danger">*</span></label>
                            <select name="document_type_id" class="form-select @error('document_type_id') is-invalid @enderror" required>
                                @foreach($documentTypes as $t)
                                    <option value="{{ $t->id }}" {{ old('document_type_id', $document->document_type_id) == $t->id ? 'selected' : '' }}>
                                        {{ $t->type_name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Cơ quan ban hành <span class="text-danger">*</span></label>
                            <select name="authority_id" class="form-select @error('authority_id') is-invalid @enderror" required>
                                @foreach($authorities as $a)
                                    <option value="{{ $a->id }}" {{ old('authority_id', $document->authority_id) == $a->id ? 'selected' : '' }}>
                                        {{ $a->authority_name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Ngày ban hành <span class="text-danger">*</span></label>
                            <input type="date" name="issued_date" class="form-control"
                                   value="{{ old('issued_date', $document->issued_date ? $document->issued_date->format('Y-m-d') : '') }}" required>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Ngày có hiệu lực</label>
                            <input type="date" name="effective_date" class="form-control"
                                   value="{{ old('effective_date', $document->effective_date ? $document->effective_date->format('Y-m-d') : '') }}">
                        </div>

                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Ngày hết hiệu lực</label>
                            <input type="date" name="expired_date" class="form-control"
                                   value="{{ old('expired_date', $document->expired_date ? $document->expired_date->format('Y-m-d') : '') }}">
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Trạng thái hiệu lực <span class="text-danger">*</span></label>
                            <select name="status" class="form-select" required>
                                @foreach(['Còn hiệu lực', 'Hết hiệu lực', 'Chưa có hiệu lực', 'Hết hiệu lực một phần', 'Không xác định'] as $st)
                                    <option value="{{ $st }}" {{ old('status', $document->status) === $st ? 'selected' : '' }}>{{ $st }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Thay thế tệp văn bản (Để trống nếu giữ nguyên tệp cũ)</label>
                            <input type="file" name="document_file" class="form-control" accept=".pdf,.doc,.docx,.xls,.xlsx,.png,.jpg,.jpeg,.zip,.rar">
                            @if($document->file_exists)
                                <div class="small mt-1 text-success">
                                    <i class="fas fa-paperclip me-1"></i>Tệp hiện tại: 
                                    <a href="{{ route('documents.download', $document->id) }}" class="fw-bold">{{ basename($document->file_path) }}</a>
                                </div>
                            @endif
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Trích yếu nội dung <span class="text-danger">*</span></label>
                        <textarea name="content" rows="4" class="form-control" required>{{ old('content', $document->content) }}</textarea>
                    </div>

                    <div class="form-check form-switch mb-4">
                        <input class="form-check-input" type="checkbox" name="is_secret" id="is_secret" value="1" {{ old('is_secret', $document->is_secret) ? 'checked' : '' }}>
                        <label class="form-check-label fw-semibold text-danger" for="is_secret">
                            <i class="fas fa-user-secret me-1"></i>Văn bản MẬT (Xóa/Không lưu tệp đính kèm)
                        </label>
                    </div>

                    <div class="d-flex justify-content-end gap-2 pt-3 border-top">
                        <a href="{{ route('documents.show', $document->id) }}" class="btn btn-outline-secondary px-4">Hủy bỏ</a>
                        <button type="submit" class="btn btn-danger px-4 fw-bold">
                            <i class="fas fa-save me-1"></i>Cập nhật văn bản
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

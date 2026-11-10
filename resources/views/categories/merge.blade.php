@extends('layouts.app')

@section('title', 'Gộp danh mục trùng lặp')

@section('content')
<div class="row g-4">
    <!-- GỘP LOẠI VĂN BẢN -->
    <div class="col-md-6">
        <div class="card border-0 shadow-sm rounded-4 h-100">
            <div class="card-header bg-white border-bottom py-3 px-4">
                <h6 class="fw-bold mb-0"><i class="fas fa-object-group text-danger me-2"></i>Gộp Loại văn bản trùng lặp</h6>
            </div>
            <div class="card-body p-4">
                <form action="{{ route('categories.merge.post') }}" method="POST" onsubmit="return confirm('Xác nhận gộp các loại văn bản đã chọn? Thao tác không thể hoàn tác!');">
                    @csrf
                    <input type="hidden" name="target_type" value="type">

                    <div class="mb-3">
                        <label class="form-label fw-bold small text-success">1. Chọn loại văn bản CHÍNH (Giữ lại)</label>
                        <select name="primary_id" class="form-select" required>
                            <option value="">-- Chọn loại văn bản giữ lại --</option>
                            @foreach($types as $t)
                                <option value="{{ $t->id }}">{{ $t->type_name }} ({{ $t->documents_count }} VB)</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold small text-danger">2. Chọn các loại văn bản TRÙNG (Sẽ bị xóa sau khi chuyển VB)</label>
                        <select name="duplicate_ids[]" class="form-select" multiple size="8" required>
                            @foreach($types as $t)
                                <option value="{{ $t->id }}">{{ $t->type_name }} ({{ $t->documents_count }} VB)</option>
                            @endforeach
                        </select>
                        <div class="form-text small">Giữ phím Ctrl để chọn nhiều mục cùng lúc.</div>
                    </div>

                    <button type="submit" class="btn btn-danger w-100 fw-bold">
                        <i class="fas fa-compress-arrows-alt me-1"></i>Tiến hành gộp Loại văn bản
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- GỘP CƠ QUAN BAN HÀNH -->
    <div class="col-md-6">
        <div class="card border-0 shadow-sm rounded-4 h-100">
            <div class="card-header bg-white border-bottom py-3 px-4">
                <h6 class="fw-bold mb-0"><i class="fas fa-building text-primary me-2"></i>Gộp Cơ quan ban hành trùng lặp</h6>
            </div>
            <div class="card-body p-4">
                <form action="{{ route('categories.merge.post') }}" method="POST" onsubmit="return confirm('Xác nhận gộp các cơ quan ban hành đã chọn? Thao tác không thể hoàn tác!');">
                    @csrf
                    <input type="hidden" name="target_type" value="authority">

                    <div class="mb-3">
                        <label class="form-label fw-bold small text-success">1. Chọn cơ quan CHÍNH (Giữ lại)</label>
                        <select name="primary_id" class="form-select" required>
                            <option value="">-- Chọn cơ quan giữ lại --</option>
                            @foreach($authorities as $a)
                                <option value="{{ $a->id }}">{{ $a->authority_name }} ({{ $a->documents_count }} VB)</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold small text-danger">2. Chọn các cơ quan TRÙNG (Sẽ bị xóa sau khi chuyển VB)</label>
                        <select name="duplicate_ids[]" class="form-select" multiple size="8" required>
                            @foreach($authorities as $a)
                                <option value="{{ $a->id }}">{{ $a->authority_name }} ({{ $a->documents_count }} VB)</option>
                            @endforeach
                        </select>
                        <div class="form-text small">Giữ phím Ctrl để chọn nhiều mục cùng lúc.</div>
                    </div>

                    <button type="submit" class="btn btn-primary w-100 fw-bold">
                        <i class="fas fa-compress-arrows-alt me-1"></i>Tiến hành gộp Cơ quan
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

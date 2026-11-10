@extends('layouts.app')

@section('title', 'Mục lục ngân sách - ' . get_setting('site_title'))

@section('content')
<div class="card border-0 shadow-sm rounded-4 mb-4">
    <div class="card-header bg-white border-bottom py-3 px-4 d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div>
            <h5 class="fw-bold mb-1 text-dark"><i class="fas fa-coins text-danger me-2"></i>Tra cứu Mục lục ngân sách nhà nước</h5>
            <p class="text-muted small mb-0">Tìm kiếm nhanh mã chương, loại, khoản, mục và tiểu mục</p>
        </div>
    </div>

    <div class="card-body p-4 border-bottom bg-light">
        <form action="{{ route('reports.mlns') }}" method="GET" class="row g-2">
            <div class="col-md-10">
                <div class="input-group">
                    <span class="input-group-text bg-white"><i class="fas fa-search text-muted"></i></span>
                    <input type="text" name="q" class="form-control" placeholder="Nhập tên mục lục hoặc mô tả tra cứu..." value="{{ $term }}">
                </div>
            </div>
            <div class="col-md-2 d-flex gap-1">
                <button type="submit" class="btn btn-danger w-100 fw-bold">Tìm kiếm</button>
                @if($term)
                    <a href="{{ route('reports.mlns') }}" class="btn btn-outline-secondary"><i class="fas fa-undo"></i></a>
                @endif
            </div>
        </form>
    </div>

    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 small">
                <thead class="table-light">
                    <tr>
                        <th style="width: 60px;" class="ps-4">ID</th>
                        <th style="min-width: 250px;">Tên mục lục / Nội dung</th>
                        <th>Mô tả chi tiết</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($items as $item)
                        <tr>
                            <td class="ps-4 fw-bold text-muted">{{ $item->id }}</td>
                            <td class="fw-bold text-dark">{{ $item->ten }}</td>
                            <td class="text-muted">{{ $item->mota ?: '-' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="text-center py-4 text-muted">Không tìm thấy mục lục ngân sách phù hợp.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="card-footer bg-white py-3">
        {{ $items->links('pagination::bootstrap-5') }}
    </div>
</div>
@endsection

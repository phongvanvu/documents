@extends('layouts.app')

@section('title', 'Báo cáo & Thống kê văn bản')

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
    <div>
        <h4 class="fw-bold mb-1 text-dark"><i class="fas fa-chart-pie text-danger me-2"></i>Báo cáo & Thống kê chi tiết</h4>
        <p class="text-muted small mb-0">Phân tích số liệu văn bản theo năm {{ $year }}</p>
    </div>

    <div class="d-flex gap-2">
        <form action="{{ route('reports.index') }}" method="GET" class="d-flex gap-2">
            <select name="year" class="form-select form-select-sm" onchange="this.form.submit()">
                @foreach($availableYears as $yr)
                    <option value="{{ $yr }}" {{ $year == $yr ? 'selected' : '' }}>Năm {{ $yr }}</option>
                @endforeach
            </select>
        </form>
        <a href="{{ route('reports.export', ['year' => $year, 'format' => 'xlsx']) }}" class="btn btn-success btn-sm rounded-pill px-3">
            <i class="fas fa-file-excel me-1"></i>Xuất Excel Năm {{ $year }}
        </a>
    </div>
</div>

<div class="row g-4 mb-4">
    <!-- BY TYPE -->
    <div class="col-md-6">
        <div class="card border-0 shadow-sm rounded-4 h-100">
            <div class="card-header bg-white border-bottom py-3 px-4">
                <h6 class="fw-bold mb-0"><i class="fas fa-tags text-danger me-2"></i>Thống kê theo Loại văn bản ({{ $year }})</h6>
            </div>
            <div class="card-body p-0">
                <table class="table table-hover align-middle mb-0 small">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4">Loại văn bản</th>
                            <th class="text-end pe-4">Số lượng</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($byType as $item)
                            <tr>
                                <td class="ps-4 fw-semibold">{{ $item->documentType?->type_name ?? 'Khác' }}</td>
                                <td class="text-end pe-4 fw-bold text-danger">{{ number_format($item->total) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="2" class="text-center py-4 text-muted">Không có dữ liệu năm {{ $year }}.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- BY AUTHORITY -->
    <div class="col-md-6">
        <div class="card border-0 shadow-sm rounded-4 h-100">
            <div class="card-header bg-white border-bottom py-3 px-4">
                <h6 class="fw-bold mb-0"><i class="fas fa-building text-primary me-2"></i>Top 10 Cơ quan ban hành ({{ $year }})</h6>
            </div>
            <div class="card-body p-0">
                <table class="table table-hover align-middle mb-0 small">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4">Cơ quan ban hành</th>
                            <th class="text-end pe-4">Số lượng</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($byAuthority as $item)
                            <tr>
                                <td class="ps-4 fw-semibold">{{ $item->issuingAuthority?->authority_name ?? 'Khác' }}</td>
                                <td class="text-end pe-4 fw-bold text-primary">{{ number_format($item->total) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="2" class="text-center py-4 text-muted">Không có dữ liệu năm {{ $year }}.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection

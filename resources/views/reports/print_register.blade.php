@extends('layouts.app')

@section('title', 'Sổ theo dõi văn bản pháp quy')

@section('content')
<div class="card border-0 shadow-sm rounded-4 mb-4">
    <div class="card-header bg-white border-bottom py-3 px-4 d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div>
            <h5 class="fw-bold mb-1 text-dark"><i class="fas fa-book text-danger me-2"></i>{{ get_setting('register_header_title', 'SỔ ĐĂNG KÝ THEO DÕI VĂN BẢN PHÁP QUY') }}</h5>
            <p class="text-muted small mb-0">{{ get_setting('agency_name') }} - Tổng số: <strong>{{ number_format($documents->count()) }}</strong> văn bản</p>
        </div>
        <div class="d-flex gap-2">
            <button onclick="window.print()" class="btn btn-outline-dark btn-sm rounded-pill px-3">
                <i class="fas fa-print me-1"></i>In sổ theo dõi
            </button>
            <a href="{{ route('reports.export', array_merge(request()->all(), ['format' => 'xlsx'])) }}" class="btn btn-success btn-sm rounded-pill px-3">
                <i class="fas fa-file-excel me-1"></i>Xuất Excel
            </a>
        </div>
    </div>

    <!-- FILTER FORM -->
    <div class="card-body p-4 border-bottom bg-light d-print-none">
        <form action="{{ route('reports.print-register') }}" method="GET" class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label small fw-semibold">Từ ngày ban hành</label>
                <input type="date" name="from_date" class="form-control form-control-sm" value="{{ request('from_date') }}">
            </div>
            <div class="col-md-3">
                <label class="form-label small fw-semibold">Đến ngày ban hành</label>
                <input type="date" name="to_date" class="form-control form-control-sm" value="{{ request('to_date') }}">
            </div>
            <div class="col-md-3">
                <label class="form-label small fw-semibold">Loại văn bản</label>
                <select name="type_id" class="form-select form-select-sm">
                    <option value="">-- Tất cả --</option>
                    @foreach($documentTypes as $t)
                        <option value="{{ $t->id }}" {{ request('type_id') == $t->id ? 'selected' : '' }}>{{ $t->type_name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3 d-flex gap-1">
                <button type="submit" class="btn btn-danger btn-sm w-100 fw-bold">Lọc danh sách</button>
                <a href="{{ route('reports.print-register') }}" class="btn btn-outline-secondary btn-sm"><i class="fas fa-undo"></i></a>
            </div>
        </form>
    </div>

    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-bordered align-middle mb-0 small" style="font-size: 0.82rem;">
                <thead class="table-light text-center">
                    <tr>
                        <th style="width: 40px;">STT</th>
                        <th style="width: 140px;">Số / Ký hiệu</th>
                        <th style="width: 90px;">Ngày BH</th>
                        <th style="width: 110px;">Loại VB</th>
                        <th style="width: 130px;">Cơ quan BH</th>
                        <th>Trích yếu nội dung</th>
                        <th style="width: 90px;">Ngày HL</th>
                        <th style="width: 100px;">Trạng thái</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($documents as $idx => $doc)
                        <tr>
                            <td class="text-center">{{ $idx + 1 }}</td>
                            <td class="fw-bold">{{ $doc->document_number }}</td>
                            <td class="text-center">{{ $doc->issued_date ? $doc->issued_date->format('d/m/Y') : '' }}</td>
                            <td>{{ $doc->documentType?->type_name ?? '' }}</td>
                            <td>{{ $doc->issuingAuthority?->authority_name ?? '' }}</td>
                            <td>{{ $doc->content }}</td>
                            <td class="text-center">{{ $doc->effective_date ? $doc->effective_date->format('d/m/Y') : '' }}</td>
                            <td class="text-center"><span class="{{ $doc->status_badge_class }}">{{ $doc->status }}</span></td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="text-center py-4 text-muted">Không có văn bản trong khoảng thời gian này.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection

@extends('layouts.app')

@section('title', 'Tổng quan hệ thống - ' . get_setting('site_title'))

@section('content')
<div class="mb-4">
    <h4 class="fw-bold text-dark mb-1"><i class="fas fa-tachometer-alt text-danger me-2"></i>Bảng điều khiển & Tổng quan</h4>
    <p class="text-muted small mb-0">{{ get_setting('agency_name') }} - {{ get_setting('site_title') }}</p>
</div>

<!-- STAT CARDS -->
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-3">
        <div class="card border-0 shadow-sm rounded-4 p-3 bg-white h-100">
            <div class="d-flex align-items-center">
                <div class="rounded-4 bg-danger bg-opacity-10 p-3 text-danger me-3 fs-3">
                    <i class="fas fa-file-invoice"></i>
                </div>
                <div>
                    <span class="text-muted small fw-semibold">TỔNG SỐ VĂN BẢN</span>
                    <h3 class="fw-bold mb-0 text-dark">{{ number_format($totalDocs) }}</h3>
                </div>
            </div>
            <div class="mt-3 pt-2 border-top text-muted small d-flex justify-content-between">
                <span>Văn bản trong CSDL</span>
                <a href="{{ route('documents.index') }}" class="text-danger fw-bold text-decoration-none">Xem tất cả &rarr;</a>
            </div>
        </div>
    </div>

    <div class="col-sm-6 col-xl-3">
        <div class="card border-0 shadow-sm rounded-4 p-3 bg-white h-100">
            <div class="d-flex align-items-center">
                <div class="rounded-4 bg-success bg-opacity-10 p-3 text-success me-3 fs-3">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div>
                    <span class="text-muted small fw-semibold">CÒN HIỆU LỰC</span>
                    <h3 class="fw-bold mb-0 text-success">{{ number_format($activeDocs) }}</h3>
                </div>
            </div>
            <div class="mt-3 pt-2 border-top text-muted small d-flex justify-content-between">
                <span>Đang áp dụng</span>
                <a href="{{ route('documents.index', ['status' => 'Còn hiệu lực']) }}" class="text-success fw-bold text-decoration-none">Lọc danh sách &rarr;</a>
            </div>
        </div>
    </div>

    <div class="col-sm-6 col-xl-3">
        <div class="card border-0 shadow-sm rounded-4 p-3 bg-white h-100">
            <div class="d-flex align-items-center">
                <div class="rounded-4 bg-warning bg-opacity-10 p-3 text-warning me-3 fs-3">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
                <div>
                    <span class="text-muted small fw-semibold">SẮP HẾT HẠN (30 NGÀY)</span>
                    <h3 class="fw-bold mb-0 text-warning text-dark">{{ number_format($expiringSoonDocs) }}</h3>
                </div>
            </div>
            <div class="mt-3 pt-2 border-top text-muted small d-flex justify-content-between">
                <span>Cần xử lý / rà soát</span>
                <a href="{{ route('documents.index', ['expiring_soon' => 30]) }}" class="text-warning text-dark fw-bold text-decoration-none">Xem chi tiết &rarr;</a>
            </div>
        </div>
    </div>

    <div class="col-sm-6 col-xl-3">
        <div class="card border-0 shadow-sm rounded-4 p-3 bg-white h-100">
            <div class="d-flex align-items-center">
                <div class="rounded-4 bg-danger bg-opacity-10 p-3 text-danger me-3 fs-3">
                    <i class="fas fa-calendar-times"></i>
                </div>
                <div>
                    <span class="text-muted small fw-semibold">HẾT HIỆU LỰC</span>
                    <h3 class="fw-bold mb-0 text-danger">{{ number_format($expiredDocs) }}</h3>
                </div>
            </div>
            <div class="mt-3 pt-2 border-top text-muted small d-flex justify-content-between">
                <span>Văn bản đã hết hạn</span>
                <a href="{{ route('documents.index', ['status' => 'Hết hiệu lực']) }}" class="text-danger fw-bold text-decoration-none">Lọc danh sách &rarr;</a>
            </div>
        </div>
    </div>
</div>

<!-- CHARTS ROW -->
<div class="row g-4 mb-4">
    <!-- Monthly Chart -->
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm rounded-4 p-4 bg-white h-100">
            <div class="d-flex align-items-center justify-content-between mb-3">
                <h6 class="fw-bold mb-0 text-dark"><i class="fas fa-chart-area text-danger me-2"></i>Số lượng văn bản ban hành (12 tháng gần nhất)</h6>
                <span class="badge bg-light text-muted border">Biểu đồ tháng</span>
            </div>
            <div style="height: 280px;">
                <canvas id="monthlyChart"></canvas>
            </div>
        </div>
    </div>

    <!-- Status Distribution Doughnut Chart -->
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm rounded-4 p-4 bg-white h-100">
            <div class="d-flex align-items-center justify-content-between mb-3">
                <h6 class="fw-bold mb-0 text-dark"><i class="fas fa-chart-pie text-danger me-2"></i>Tỷ lệ trạng thái</h6>
            </div>
            <div style="height: 230px;" class="d-flex align-items-center justify-content-center">
                <canvas id="statusChart"></canvas>
            </div>
            <div class="mt-3 pt-2 border-top d-flex justify-content-around text-center small">
                <div>
                    <div class="text-muted">Loại VB</div>
                    <div class="fw-bold text-dark">{{ $totalTypes }}</div>
                </div>
                <div>
                    <div class="text-muted">Cơ quan</div>
                    <div class="fw-bold text-dark">{{ $totalAuthorities }}</div>
                </div>
                <div>
                    <div class="text-muted">Phụ lục</div>
                    <div class="fw-bold text-dark">{{ $totalAttachments }}</div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- TABLES ROW -->
<div class="row g-4">
    <!-- Recent Documents -->
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm rounded-4 bg-white overflow-hidden h-100">
            <div class="card-header bg-white border-bottom py-3 px-4 d-flex align-items-center justify-content-between">
                <h6 class="fw-bold mb-0 text-dark"><i class="fas fa-history text-danger me-2"></i>Văn bản mới cập nhật</h6>
                <a href="{{ route('documents.index') }}" class="btn btn-outline-danger btn-sm rounded-pill px-3">Xem tất cả</a>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4">Số / Ký hiệu</th>
                            <th>Ngày ban hành</th>
                            <th>Cơ quan ban hành</th>
                            <th>Trạng thái</th>
                            <th class="text-end pe-4">Thao tác</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($recentDocs as $doc)
                            <tr>
                                <td class="ps-4">
                                    <a href="{{ route('documents.show', $doc->id) }}" class="fw-bold text-dark text-decoration-none">
                                        {{ $doc->document_number }}
                                    </a>
                                    <div class="text-muted text-truncate small" style="max-width: 250px;">{{ $doc->content }}</div>
                                </td>
                                <td>{{ $doc->issued_date ? \Carbon\Carbon::parse($doc->issued_date)->format('d/m/Y') : '-' }}</td>
                                <td><span class="badge bg-light text-dark border">{{ $doc->issuingAuthority?->authority_name ?? '-' }}</span></td>
                                <td><span class="{{ $doc->status_badge_class }}">{{ $doc->status }}</span></td>
                                <td class="text-end pe-4">
                                    <a href="{{ route('documents.show', $doc->id) }}" class="btn btn-sm btn-outline-primary" title="Xem chi tiết">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    @if($doc->file_exists)
                                        <a href="{{ route('documents.download', $doc->id) }}" class="btn btn-sm btn-outline-danger" title="Tải tệp chính">
                                            <i class="fas fa-download"></i>
                                        </a>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center py-4 text-muted">Chưa có văn bản nào trong hệ thống.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Recent Logs -->
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm rounded-4 bg-white overflow-hidden h-100">
            <div class="card-header bg-white border-bottom py-3 px-4 d-flex align-items-center justify-content-between">
                <h6 class="fw-bold mb-0 text-dark"><i class="fas fa-clipboard-list text-danger me-2"></i>Nhật ký hoạt động</h6>
                @if(auth()->user()->isAdmin())
                    <a href="{{ route('system.logs') }}" class="text-danger small text-decoration-none fw-semibold">Xem tất cả</a>
                @endif
            </div>
            <div class="card-body p-0">
                <ul class="list-group list-group-flush">
                    @forelse($recentLogs as $log)
                        <li class="list-group-item px-4 py-3 border-bottom">
                            <div class="d-flex justify-content-between align-items-start">
                                <div class="fw-semibold small text-dark">{{ $log->user?->full_name ?? ($log->user?->username ?? 'Hệ thống') }}</div>
                                <span class="text-muted" style="font-size: 0.75rem;">
                                    {{ \Carbon\Carbon::parse($log->created_at)->diffForHumans() }}
                                </span>
                            </div>
                            <div class="text-muted small mt-1">{{ $log->action }}</div>
                        </li>
                    @empty
                        <li class="list-group-item text-center py-4 text-muted">Chưa có nhật ký hoạt động.</li>
                    @endforelse
                </ul>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    // Monthly Chart
    var ctxMonthly = document.getElementById('monthlyChart').getContext('2d');
    new Chart(ctxMonthly, {
        type: 'bar',
        data: {
            labels: @json($monthlyLabels),
            datasets: [{
                label: 'Số văn bản',
                data: @json($monthlyData),
                backgroundColor: 'rgba(220, 53, 69, 0.75)',
                borderColor: '#dc3545',
                borderWidth: 1,
                borderRadius: 6
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                y: { beginAtZero: true, grid: { color: '#f1f1f1' } },
                x: { grid: { display: false } }
            }
        }
    });

    // Status Doughnut Chart
    var ctxStatus = document.getElementById('statusChart').getContext('2d');
    new Chart(ctxStatus, {
        type: 'doughnut',
        data: {
            labels: @json(array_keys($statusCounts)),
            datasets: [{
                data: @json(array_values($statusCounts)),
                backgroundColor: ['#198754', '#dc3545', '#ffc107', '#6c757d', '#adb5bd']
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { position: 'bottom', labels: { boxWidth: 12, font: { size: 11 } } } }
        }
    });
</script>
@endpush

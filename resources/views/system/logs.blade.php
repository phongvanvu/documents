@extends('layouts.app')

@section('title', 'Nhật ký hoạt động hệ thống')

@section('content')
<div class="card border-0 shadow-sm rounded-4 mb-4">
    <div class="card-header bg-white border-bottom py-3 px-4 d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div>
            <h5 class="fw-bold mb-1 text-dark"><i class="fas fa-clipboard-list text-danger me-2"></i>Nhật ký hoạt động hệ thống</h5>
            <p class="text-muted small mb-0">Theo dõi lịch sử thao tác của tất cả người dùng</p>
        </div>
        <form action="{{ route('system.logs.clear') }}" method="POST" onsubmit="return confirm('Xóa toàn bộ nhật ký hoạt động?');">
            @csrf
            <button type="submit" class="btn btn-outline-danger btn-sm rounded-pill px-3">
                <i class="fas fa-broom me-1"></i>Làm sạch nhật ký
            </button>
        </form>
    </div>

    <div class="card-body p-4 border-bottom bg-light">
        <form action="{{ route('system.logs') }}" method="GET" class="row g-2">
            <div class="col-md-5">
                <input type="text" name="q" class="form-control form-control-sm" placeholder="Tìm nội dung thao tác..." value="{{ request('q') }}">
            </div>
            <div class="col-md-4">
                <select name="user_id" class="form-select form-select-sm">
                    <option value="">-- Tất cả người dùng --</option>
                    @foreach($users as $u)
                        <option value="{{ $u->id }}" {{ request('user_id') == $u->id ? 'selected' : '' }}>{{ $u->username }} ({{ $u->full_name }})</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3 d-flex gap-1">
                <button type="submit" class="btn btn-danger btn-sm w-100 fw-bold">Lọc</button>
                <a href="{{ route('system.logs') }}" class="btn btn-outline-secondary btn-sm"><i class="fas fa-undo"></i></a>
            </div>
        </form>
    </div>

    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 small">
                <thead class="table-light">
                    <tr>
                        <th class="ps-4" style="width: 180px;">Thời gian</th>
                        <th style="width: 200px;">Người thực hiện</th>
                        <th>Hành động / Nội dung</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($logs as $log)
                        <tr>
                            <td class="ps-4 text-muted">{{ \Carbon\Carbon::parse($log->created_at)->format('d/m/Y H:i:s') }}</td>
                            <td>
                                <span class="fw-bold text-dark">{{ $log->user?->full_name ?? ($log->user?->username ?? 'Hệ thống') }}</span>
                            </td>
                            <td>{{ $log->action }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="text-center py-4 text-muted">Không có bản ghi nhật ký nào.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="card-footer bg-white py-3">
        {{ $logs->links('pagination::bootstrap-5') }}
    </div>
</div>
@endsection

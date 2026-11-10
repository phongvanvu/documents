@extends('layouts.app')

@section('title', 'Quản lý Phiên đăng nhập')

@section('content')
<div class="card border-0 shadow-sm rounded-4 mb-4">
    <div class="card-header bg-white border-bottom py-3 px-4">
        <h5 class="fw-bold mb-1 text-dark"><i class="fas fa-user-clock text-danger me-2"></i>Các phiên đăng nhập đang hoạt động</h5>
        <p class="text-muted small mb-0">Theo dõi thiết bị, địa chỉ IP và cưỡng chế đăng xuất nếu phát hiện bất thường</p>
    </div>

    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 small">
                <thead class="table-light">
                    <tr>
                        <th class="ps-4">Người dùng</th>
                        <th>Địa chỉ IP</th>
                        <th>Trình duyệt / Thiết bị</th>
                        <th>Hoạt động gần nhất</th>
                        <th class="text-end pe-4">Thao tác</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($sessions as $s)
                        <tr>
                            <td class="ps-4 fw-bold text-dark">{{ $s->user?->full_name ?? ($s->user?->username ?? 'Khách') }}</td>
                            <td><code>{{ $s->ip_address }}</code></td>
                            <td><div class="text-muted text-truncate" style="max-width: 300px;">{{ $s->user_agent }}</div></td>
                            <td>{{ \Carbon\Carbon::parse($s->last_activity)->diffForHumans() }}</td>
                            <td class="text-end pe-4">
                                <form action="{{ route('users.sessions.revoke', $s->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Thu hồi phiên đăng nhập này?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger">
                                        <i class="fas fa-ban me-1"></i>Thu hồi
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center py-4 text-muted">Không có phiên đăng nhập nào.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="card-footer bg-white py-3">
        {{ $sessions->links('pagination::bootstrap-5') }}
    </div>
</div>
@endsection

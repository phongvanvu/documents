@extends('layouts.app')

@section('title', 'Sao lưu Cơ sở dữ liệu')

@section('content')
<div class="card border-0 shadow-sm rounded-4 mb-4">
    <div class="card-header bg-white border-bottom py-3 px-4 d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div>
            <h5 class="fw-bold mb-1 text-dark"><i class="fas fa-database text-danger me-2"></i>Quản lý Sao lưu Cơ sở dữ liệu</h5>
            <p class="text-muted small mb-0">Tạo bản sao lưu và tải về lưu trữ an toàn</p>
        </div>
        <div class="d-flex gap-2">
            <form action="{{ route('system.optimize-db') }}" method="POST">
                @csrf
                <button type="submit" class="btn btn-outline-primary btn-sm rounded-pill px-3">
                    <i class="fas fa-tachometer-alt me-1"></i>Tối ưu hóa bảng
                </button>
            </form>

            <form action="{{ route('system.backups.create') }}" method="POST">
                @csrf
                <button type="submit" class="btn btn-danger btn-sm rounded-pill px-3 fw-bold">
                    <i class="fas fa-plus me-1"></i>Tạo bản sao lưu ngay
                </button>
            </form>
        </div>
    </div>

    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 small">
                <thead class="table-light">
                    <tr>
                        <th class="ps-4">Tên tệp sao lưu (.SQL)</th>
                        <th>Dung lượng</th>
                        <th>Thời gian tạo</th>
                        <th class="text-end pe-4">Thao tác</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($backups as $b)
                        <tr>
                            <td class="ps-4 fw-bold text-dark">
                                <i class="fas fa-file-code text-danger me-2"></i>{{ $b['name'] }}
                            </td>
                            <td>{{ $b['size'] }}</td>
                            <td>{{ $b['modified_at'] }}</td>
                            <td class="text-end pe-4 text-nowrap">
                                <a href="{{ route('system.backups.download', $b['name']) }}" class="btn btn-sm btn-outline-success">
                                    <i class="fas fa-download me-1"></i>Tải về
                                </a>

                                <form action="{{ route('system.backups.delete', $b['name']) }}" method="POST" class="d-inline" onsubmit="return confirm('Xóa tệp sao lưu này khỏi máy chủ?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger"><i class="fas fa-trash-alt"></i></button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-center py-4 text-muted">Chưa có bản sao lưu nào được tạo.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection

@extends('layouts.app')

@section('title', 'Quản lý Người dùng')

@section('content')
<div class="card border-0 shadow-sm rounded-4 mb-4">
    <div class="card-header bg-white border-bottom py-3 px-4 d-flex justify-content-between align-items-center">
        <div>
            <h5 class="fw-bold mb-1 text-dark"><i class="fas fa-users text-danger me-2"></i>Quản lý người dùng hệ thống</h5>
            <p class="text-muted small mb-0">Danh sách tài khoản và phân quyền truy cập</p>
        </div>
        <a href="{{ route('users.create') }}" class="btn btn-danger btn-sm rounded-pill px-3 fw-bold">
            <i class="fas fa-user-plus me-1"></i>Thêm người dùng
        </a>
    </div>

    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-4">Tài khoản</th>
                        <th>Họ và tên</th>
                        <th>Email</th>
                        <th>Vai trò</th>
                        <th>Số hoạt động</th>
                        <th>Ngày tạo</th>
                        <th class="text-end pe-4">Thao tác</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($users as $user)
                        <tr>
                            <td class="ps-4">
                                <div class="d-flex align-items-center">
                                    <img src="{{ $user->avatar_url }}" alt="Avatar"
                                         class="rounded-circle border me-2" style="width: 36px; height: 36px; object-fit: cover;">
                                    <span class="fw-bold text-dark">{{ $user->username }}</span>
                                </div>
                            </td>
                            <td>{{ $user->full_name }}</td>
                            <td>{{ $user->email ?: '-' }}</td>
                            <td>
                                @if($user->isAdmin())
                                    <span class="badge bg-danger">ADMIN</span>
                                @elseif($user->isEditor())
                                    <span class="badge bg-primary">EDITOR</span>
                                @else
                                    <span class="badge bg-secondary">VIEWER</span>
                                @endif
                            </td>
                            <td><span class="badge bg-light text-muted border">{{ $user->logs_count }}</span></td>
                            <td class="small text-muted">{{ $user->created_at ? $user->created_at->format('d/m/Y') : '-' }}</td>
                            <td class="text-end pe-4 text-nowrap">
                                <a href="{{ route('users.edit', $user->id) }}" class="btn btn-sm btn-outline-primary" title="Sửa">
                                    <i class="fas fa-edit"></i>
                                </a>

                                @if($user->id !== auth()->id())
                                    <form action="{{ route('users.destroy', $user->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Bạn có chắc chắn muốn xóa tài khoản này?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger" title="Xóa">
                                            <i class="fas fa-trash-alt"></i>
                                        </button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    <div class="card-footer bg-white py-3">
        {{ $users->links('pagination::bootstrap-5') }}
    </div>
</div>
@endsection

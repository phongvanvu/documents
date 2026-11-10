@extends('layouts.app')

@section('title', 'Quản lý Cơ quan ban hành')

@section('content')
<div class="row g-4">
    <div class="col-md-4">
        <div class="card border-0 shadow-sm rounded-4">
            <div class="card-header bg-white border-bottom py-3 px-4">
                <h6 class="fw-bold mb-0"><i class="fas fa-plus-circle text-danger me-2"></i>Thêm cơ quan ban hành</h6>
            </div>
            <div class="card-body p-4">
                <form action="{{ route('categories.authorities.store') }}" method="POST">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Tên cơ quan ban hành <span class="text-danger">*</span></label>
                        <input type="text" name="authority_name" class="form-control" placeholder="VD: Bộ Công an, Bệnh viện 19-8..." required>
                    </div>
                    <button type="submit" class="btn btn-danger w-100 fw-bold">
                        <i class="fas fa-save me-1"></i>Thêm mới
                    </button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-md-8">
        <div class="card border-0 shadow-sm rounded-4">
            <div class="card-header bg-white border-bottom py-3 px-4">
                <h6 class="fw-bold mb-0"><i class="fas fa-building text-danger me-2"></i>Danh sách Cơ quan ban hành ({{ $authorities->total() }})</h6>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-4">Tên cơ quan</th>
                                <th>Số văn bản</th>
                                <th class="text-end pe-4">Thao tác</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($authorities as $auth)
                                <tr>
                                    <td class="ps-4 fw-bold text-dark">{{ $auth->authority_name }}</td>
                                    <td><span class="badge bg-light text-dark border">{{ $auth->documents_count }} VB</span></td>
                                    <td class="text-end pe-4 text-nowrap">
                                        <button type="button" class="btn btn-sm btn-outline-primary"
                                                onclick="editAuth({{ $auth->id }}, '{{ addslashes($auth->authority_name) }}')">
                                            <i class="fas fa-edit"></i>
                                        </button>

                                        <form action="{{ route('categories.authorities.destroy', $auth->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Xóa cơ quan này?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-outline-danger"><i class="fas fa-trash-alt"></i></button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="card-footer bg-white py-3">
                {{ $authorities->links('pagination::bootstrap-5') }}
            </div>
        </div>
    </div>
</div>

<!-- MODAL EDIT -->
<div class="modal fade" id="editAuthModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content rounded-4 border-0">
            <form id="editAuthForm" method="POST">
                @csrf
                @method('PUT')
                <div class="modal-header border-bottom">
                    <h6 class="modal-title fw-bold">Chỉnh sửa Cơ quan ban hành</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Tên cơ quan <span class="text-danger">*</span></label>
                        <input type="text" name="authority_name" id="editAuthName" class="form-control" required>
                    </div>
                </div>
                <div class="modal-footer border-top">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Đóng</button>
                    <button type="submit" class="btn btn-danger btn-sm fw-bold">Lưu thay đổi</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    function editAuth(id, name) {
        document.getElementById('editAuthForm').action = '/categories/authorities/' + id;
        document.getElementById('editAuthName').value = name;
        new bootstrap.Modal(document.getElementById('editAuthModal')).show();
    }
</script>
@endpush
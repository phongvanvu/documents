@extends('layouts.app')

@section('title', 'Phân quyền nâng cao')

@section('content')
<div class="card border-0 shadow-sm rounded-4 mb-4">
    <div class="card-header bg-white border-bottom py-3 px-4">
        <h5 class="fw-bold mb-1 text-dark"><i class="fas fa-user-shield text-danger me-2"></i>Ma trận Phân quyền theo Vai trò</h5>
        <p class="text-muted small mb-0">Bật/tắt quyền truy cập chi tiết cho Editor và Viewer (Admin mặc định có toàn quyền)</p>
    </div>

    <div class="card-body p-4">
        @foreach($allPermissions as $module => $perms)
            <h6 class="fw-bold text-danger text-uppercase small mt-3 mb-2">{{ $module }}</h6>
            <div class="table-responsive mb-4">
                <table class="table table-bordered align-middle mb-0 small">
                    <thead class="table-light">
                        <tr>
                            <th>Tên quyền</th>
                            <th style="width: 150px;" class="text-center">Editor</th>
                            <th style="width: 150px;" class="text-center">Viewer</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($perms as $p)
                            <tr>
                                <td>
                                    <div class="fw-bold">{{ $p->perm_name }}</div>
                                    <div class="text-muted" style="font-size: 0.75rem;"><code>{{ $p->perm_key }}</code></div>
                                </td>
                                <td class="text-center">
                                    <div class="form-check form-switch d-flex justify-content-center">
                                        <input class="form-check-input" type="checkbox"
                                               {{ isset($matrix['editor'][$p->perm_key]) ? 'checked' : '' }}
                                               onchange="togglePerm('editor', '{{ $p->perm_key }}', this.checked)">
                                    </div>
                                </td>
                                <td class="text-center">
                                    <div class="form-check form-switch d-flex justify-content-center">
                                        <input class="form-check-input" type="checkbox"
                                               {{ isset($matrix['viewer'][$p->perm_key]) ? 'checked' : '' }}
                                               onchange="togglePerm('viewer', '{{ $p->perm_key }}', this.checked)">
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endforeach
    </div>
</div>
@endsection

@push('scripts')
<script>
    function togglePerm(role, permKey, enable) {
        fetch('{{ route("users.permissions.toggle") }}', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': csrfToken,
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            },
            body: JSON.stringify({ role: role, perm_key: permKey, enable: enable })
        })
        .then(res => res.json())
        .then(data => {
            if (data.status === 'success') {
                Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: data.message, showConfirmButton: false, timer: 1500 });
            }
        });
    }
</script>
@endpush

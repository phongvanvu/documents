@extends('layouts.app')

@section('title', 'Thùng rác văn bản - ' . get_setting('site_title'))

@section('content')
<div class="card border-0 shadow-sm rounded-4 mb-4">
    <div class="card-header bg-white border-bottom py-3 px-4 d-flex justify-content-between align-items-center">
        <div>
            <h5 class="fw-bold mb-1 text-danger"><i class="fas fa-trash-alt me-2"></i>Thùng rác văn bản</h5>
            <p class="text-muted small mb-0">Danh sách các văn bản đã bị xóa tạm thời (Có thể khôi phục lại)</p>
        </div>
        <a href="{{ route('documents.index') }}" class="btn btn-outline-secondary btn-sm rounded-pill px-3">
            <i class="fas fa-arrow-left me-1"></i>Về danh sách
        </a>
    </div>

    <div class="card-body p-4">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-4">Số / Ký hiệu</th>
                        <th>Trích yếu nội dung</th>
                        <th>Ngày ban hành</th>
                        <th>Thời gian xóa</th>
                        <th class="text-end pe-4">Thao tác</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($documents as $doc)
                        <tr>
                            <td class="ps-4 fw-bold text-dark">{{ $doc->document_number }}</td>
                            <td><div class="small text-truncate" style="max-width: 350px;">{{ $doc->content }}</div></td>
                            <td>{{ $doc->issued_date ? \Carbon\Carbon::parse($doc->issued_date)->format('d/m/Y') : '-' }}</td>
                            <td class="text-muted small">{{ $doc->deleted_at ? \Carbon\Carbon::parse($doc->deleted_at)->format('d/m/Y H:i') : '-' }}</td>
                            <td class="text-end pe-4 text-nowrap">
                                <form action="{{ route('documents.restore', $doc->id) }}" method="POST" class="d-inline">
                                    @csrf
                                    <button type="submit" class="btn btn-sm btn-outline-success" title="Khôi phục">
                                        <i class="fas fa-undo me-1"></i>Khôi phục
                                    </button>
                                </form>

                                <form action="{{ route('documents.force-delete', $doc->id) }}" method="POST" class="d-inline" onsubmit="return confirm('CẢNH BÁO: Thao tác này sẽ xóa vĩnh viễn văn bản và tệp đính kèm. Bạn có chắc không?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger" title="Xóa vĩnh viễn">
                                        <i class="fas fa-times me-1"></i>Xóa vĩnh viễn
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center py-5 text-muted">
                                <i class="fas fa-trash-restore fa-3x mb-3 opacity-50"></i>
                                <div>Thùng rác hiện đang trống.</div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">
            {{ $documents->links('pagination::bootstrap-5') }}
        </div>
    </div>
</div>
@endsection

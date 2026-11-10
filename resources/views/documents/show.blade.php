@extends('layouts.app')

@section('title', $document->document_number . ' - Chi tiết văn bản')

@section('content')
<div class="mb-3 d-flex flex-wrap justify-content-between align-items-center gap-2">
    <div>
        <a href="{{ route('documents.index') }}" class="btn btn-outline-secondary btn-sm rounded-pill px-3">
            <i class="fas fa-arrow-left me-1"></i>Danh sách văn bản
        </a>
    </div>
    <div class="d-flex gap-2">
        @if($document->file_exists || $document->attachments->isNotEmpty())
            <a href="{{ route('attachments.zip', $document->id) }}" class="btn btn-outline-success btn-sm rounded-pill px-3 fw-bold">
                <i class="fas fa-file-archive me-1"></i>Tải trọn bộ (.ZIP)
            </a>
        @endif

        @if(auth()->user()->isEditor())
            <a href="{{ route('documents.edit', $document->id) }}" class="btn btn-outline-primary btn-sm rounded-pill px-3">
                <i class="fas fa-edit me-1"></i>Chỉnh sửa
            </a>
            <form action="{{ route('documents.destroy', $document->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Chuyển văn bản này vào thùng rác?');">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn btn-outline-danger btn-sm rounded-pill px-3">
                    <i class="fas fa-trash-alt me-1"></i>Xóa
                </button>
            </form>
        @endif
    </div>
</div>

<!-- METADATA CARD -->
<div class="card border-0 shadow-sm rounded-4 mb-4">
    <div class="card-header bg-white border-bottom py-3 px-4 d-flex flex-wrap justify-content-between align-items-center gap-2">
        <div class="d-flex align-items-center gap-2">
            <h4 class="fw-bold mb-0 text-danger">{{ $document->document_number }}</h4>
            <span class="{{ $document->status_badge_class }} fs-6">{{ $document->status }}</span>
            @if($document->is_secret)
                <span class="badge bg-dark">VĂN BẢN MẬT</span>
            @endif
        </div>
        <button type="button" class="btn btn-sm btn-outline-warning rounded-pill px-3" onclick="toggleFavoriteShow({{ $document->id }}, this)">
            <i class="{{ $document->is_favorite ? 'fas' : 'far' }} fa-star me-1"></i>
            <span>{{ $document->is_favorite ? 'Đã đánh dấu quan trọng' : 'Đánh dấu quan trọng' }}</span>
        </button>
    </div>

    <div class="card-body p-4">
        <div class="row g-4">
            <div class="col-md-8">
                <h6 class="text-muted text-uppercase small fw-bold mb-2">Trích yếu nội dung</h6>
                <p class="fs-6 text-dark lh-base mb-4">{{ $document->content }}</p>

                <!-- Ghi chú cá nhân -->
                <div class="bg-light p-3 rounded-3 border">
                    <label class="form-label small fw-bold text-muted mb-1"><i class="fas fa-sticky-note text-warning me-1"></i>Ghi chú cá nhân / Theo dõi thực hiện</label>
                    <div class="input-group input-group-sm">
                        <input type="text" id="personalNoteInput" class="form-control" value="{{ $document->personal_note }}" placeholder="Nhập ghi chú nhanh cho văn bản này...">
                        <button type="button" class="btn btn-warning fw-bold" onclick="saveNote({{ $document->id }})">
                            <i class="fas fa-save me-1"></i>Lưu ghi chú
                        </button>
                    </div>
                </div>
            </div>

            <div class="col-md-4 border-start-md">
                <ul class="list-group list-group-flush small">
                    <li class="list-group-item d-flex justify-content-between px-0">
                        <span class="text-muted">Loại văn bản:</span>
                        <span class="fw-bold text-dark">{{ $document->documentType?->type_name ?? '-' }}</span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between px-0">
                        <span class="text-muted">Cơ quan ban hành:</span>
                        <span class="fw-bold text-dark text-end">{{ $document->issuingAuthority?->authority_name ?? '-' }}</span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between px-0">
                        <span class="text-muted">Ngày ban hành:</span>
                        <span class="fw-bold">{{ $document->issued_date ? $document->issued_date->format('d/m/Y') : '-' }}</span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between px-0">
                        <span class="text-muted">Ngày có hiệu lực:</span>
                        <span class="fw-bold">{{ $document->effective_date ? $document->effective_date->format('d/m/Y') : '-' }}</span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between px-0">
                        <span class="text-muted">Ngày hết hạn:</span>
                        <span class="fw-bold text-danger">{{ $document->expired_date ? $document->expired_date->format('d/m/Y') : 'Không thời hạn' }}</span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between px-0">
                        <span class="text-muted">Tệp văn bản chính:</span>
                        @if($document->file_exists)
                            <a href="{{ route('documents.download', $document->id) }}" class="btn btn-sm btn-danger py-0 px-2 rounded-pill">
                                <i class="fas fa-download me-1"></i>Tải về
                            </a>
                        @else
                            <span class="text-muted fst-italic">Không có tệp</span>
                        @endif
                    </li>
                </ul>
            </div>
        </div>
    </div>
</div>

<!-- INLINE PREVIEW CARD -->
@if($document->file_exists)
    @php
        $ext = strtolower(pathinfo($document->file_path, PATHINFO_EXTENSION));
    @endphp
    <div class="card border-0 shadow-sm rounded-4 mb-4 overflow-hidden">
        <div class="card-header bg-white border-bottom py-3 px-4 d-flex justify-content-between align-items-center">
            <h6 class="fw-bold mb-0"><i class="fas fa-file-pdf text-danger me-2"></i>Xem trước tài liệu chính ({{ strtoupper($ext) }})</h6>
            <a href="{{ $document->file_url }}" target="_blank" class="btn btn-sm btn-outline-primary rounded-pill px-3">
                <i class="fas fa-external-link-alt me-1"></i>Mở trong tab mới
            </a>
        </div>
        <div class="card-body p-0 bg-dark">
            @if($ext === 'pdf')
                <iframe src="{{ $document->file_url }}" style="width: 100%; height: 700px; border: 0;"></iframe>
            @elseif(in_array($ext, ['png', 'jpg', 'jpeg', 'webp']))
                <div class="text-center p-4 bg-light">
                    <img src="{{ $document->file_url }}" alt="Preview" class="img-fluid rounded shadow-sm" style="max-height: 700px;">
                </div>
            @else
                <div class="text-center py-5 bg-light text-muted">
                    <i class="fas fa-file-word fa-4x mb-3 text-primary"></i>
                    <p class="mb-2">Tệp định dạng <strong>.{{ strtoupper($ext) }}</strong> không hỗ trợ xem trực tiếp trên trình duyệt.</p>
                    <a href="{{ route('documents.download', $document->id) }}" class="btn btn-danger rounded-pill px-4">
                        <i class="fas fa-download me-1"></i>Tải tệp xuống để xem
                    </a>
                </div>
            @endif
        </div>
    </div>
@endif

<!-- TABS FOR ATTACHMENTS, RELATIONS, VERSIONS -->
<div class="card border-0 shadow-sm rounded-4 mb-4">
    <div class="card-header bg-white border-bottom pt-3 px-4">
        <ul class="nav nav-tabs card-header-tabs" role="tablist">
            <li class="nav-item">
                <button class="nav-link active fw-bold" data-bs-toggle="tab" data-bs-target="#tab-attachments" type="button">
                    <i class="fas fa-paperclip me-1 text-danger"></i>Tài liệu đính kèm ({{ $document->attachments->count() }})
                </button>
            </li>
            <li class="nav-item">
                <button class="nav-link fw-bold" data-bs-toggle="tab" data-bs-target="#tab-relations" type="button">
                    <i class="fas fa-project-diagram me-1 text-primary"></i>Văn bản liên quan ({{ $relatedDocuments->count() }})
                </button>
            </li>
            <li class="nav-item">
                <button class="nav-link fw-bold" data-bs-toggle="tab" data-bs-target="#tab-versions" type="button">
                    <i class="fas fa-history me-1 text-success"></i>Lịch sử phiên bản ({{ $document->versions->count() }})
                </button>
            </li>
        </ul>
    </div>

    <div class="card-body p-4 tab-content">
        <!-- TAB 1: ATTACHMENTS -->
        <div class="tab-pane fade show active" id="tab-attachments">
            @if(auth()->user()->isEditor())
                <form action="{{ route('attachments.store', $document->id) }}" method="POST" enctype="multipart/form-data" class="bg-light p-3 rounded-3 mb-4 border">
                    @csrf
                    <h6 class="fw-bold small mb-3 text-dark"><i class="fas fa-plus me-1 text-danger"></i>Thêm tài liệu làm việc / Phụ lục kèm theo</h6>
                    <div class="row g-2">
                        <div class="col-md-4">
                            <input type="text" name="attachment_title" class="form-control form-control-sm" placeholder="Tên tài liệu / Biểu mẫu..." required>
                        </div>
                        <div class="col-md-3">
                            <select name="attachment_type" class="form-select form-select-sm">
                                <option value="Biểu mẫu">Biểu mẫu</option>
                                <option value="Excel tính toán">Excel tính toán</option>
                                <option value="Công văn tham khảo">Công văn tham khảo</option>
                                <option value="Bản trích điều khoản">Bản trích điều khoản</option>
                                <option value="Ghi chú">Ghi chú</option>
                                <option value="Khác">Khác</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <input type="file" name="attachment_file" class="form-control form-control-sm" required>
                        </div>
                        <div class="col-md-2">
                            <button type="submit" class="btn btn-danger btn-sm w-100 fw-bold">Tải lên</button>
                        </div>
                    </div>
                </form>
            @endif

            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 small">
                    <thead class="table-light">
                        <tr>
                            <th>Tên tài liệu</th>
                            <th>Phân loại</th>
                            <th>Dung lượng</th>
                            <th>Người tải</th>
                            <th class="text-end">Thao tác</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($document->attachments as $att)
                            <tr>
                                <td class="fw-semibold">{{ $att->attachment_title }}
                                    <div class="text-muted" style="font-size: 0.75rem;">{{ $att->original_name }}</div>
                                </td>
                                <td><span class="badge bg-light text-dark border">{{ $att->attachment_type }}</span></td>
                                <td>{{ $att->formatted_size }}</td>
                                <td>{{ $att->uploader?->full_name ?? 'Hệ thống' }}</td>
                                <td class="text-end">
                                    <a href="{{ route('attachments.download', $att->id) }}" class="btn btn-sm btn-outline-primary">
                                        <i class="fas fa-download"></i>
                                    </a>
                                    @if(auth()->user()->isEditor())
                                        <form action="{{ route('attachments.destroy', $att->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Xóa tài liệu đính kèm này?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-outline-danger"><i class="fas fa-trash-alt"></i></button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-center py-3 text-muted">Chưa có tài liệu đính kèm.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <!-- TAB 2: RELATIONS -->
        <div class="tab-pane fade" id="tab-relations">
            @if(auth()->user()->isEditor())
                <form action="{{ route('relations.store', $document->id) }}" method="POST" class="bg-light p-3 rounded-3 mb-4 border">
                    @csrf
                    <h6 class="fw-bold small mb-3"><i class="fas fa-link me-1 text-primary"></i>Liên kết văn bản liên quan</h6>
                    <div class="row g-2 align-items-center">
                        <div class="col-md-5 position-relative">
                            <input type="text" id="relationSearchInput" class="form-control form-control-sm" placeholder="Nhập số hiệu / trích yếu văn bản cần liên kết..." autocomplete="off">
                            <input type="hidden" name="related_document_id" id="relatedDocumentId" required>
                            <div id="relationSearchResults" class="list-group position-absolute w-100 shadow-sm" style="z-index: 1000; display: none;"></div>
                        </div>
                        <div class="col-md-4">
                            <select name="relation_type" class="form-select form-select-sm" required>
                                <option value="Thay thế">Thay thế cho văn bản</option>
                                <option value="Sửa đổi, bổ sung">Sửa đổi, bổ sung cho</option>
                                <option value="Hướng dẫn thực hiện">Hướng dẫn thực hiện</option>
                                <option value="Bãi bỏ">Bãi bỏ văn bản</option>
                                <option value="Tham khảo / Căn cứ">Căn cứ / Tham khảo</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <button type="submit" class="btn btn-primary btn-sm w-100 fw-bold">Thêm liên kết</button>
                        </div>
                    </div>
                </form>
            @endif

            <div class="list-group list-group-flush">
                @forelse($relatedDocuments as $relDoc)
                    <div class="list-group-item d-flex justify-content-between align-items-center px-0">
                        <div>
                            <span class="badge bg-primary bg-opacity-10 text-primary border border-primary me-2">{{ $relDoc->relation_type_label }}</span>
                            <a href="{{ route('documents.show', $relDoc->id) }}" class="fw-bold text-dark text-decoration-none">
                                {{ $relDoc->document_number }}
                            </a>
                            <div class="text-muted small mt-1">{{ Str::limit($relDoc->content, 120) }}</div>
                        </div>
                        <a href="{{ route('documents.show', $relDoc->id) }}" class="btn btn-sm btn-outline-secondary">Xem</a>
                    </div>
                @empty
                    <div class="text-center py-3 text-muted small">Chưa có văn bản liên quan nào được gắn kết.</div>
                @endforelse
            </div>
        </div>

        <!-- TAB 3: VERSIONS -->
        <div class="tab-pane fade" id="tab-versions">
            @if(auth()->user()->isEditor())
                <form action="{{ route('versions.store', $document->id) }}" method="POST" enctype="multipart/form-data" class="bg-light p-3 rounded-3 mb-4 border">
                    @csrf
                    <h6 class="fw-bold small mb-3"><i class="fas fa-upload me-1 text-success"></i>Lưu phiên bản mới</h6>
                    <div class="row g-2">
                        <div class="col-md-2">
                            <input type="text" name="version_number" class="form-control form-control-sm" placeholder="VD: v2.0" required>
                        </div>
                        <div class="col-md-4">
                            <input type="text" name="change_log" class="form-control form-control-sm" placeholder="Tóm tắt nội dung thay đổi...">
                        </div>
                        <div class="col-md-4">
                            <input type="file" name="version_file" class="form-control form-control-sm" required>
                        </div>
                        <div class="col-md-2">
                            <button type="submit" class="btn btn-success btn-sm w-100 fw-bold">Lưu bản mới</button>
                        </div>
                    </div>
                </form>
            @endif

            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 small">
                    <thead class="table-light">
                        <tr>
                            <th>Phiên bản</th>
                            <th>Mô tả thay đổi</th>
                            <th>Người lưu</th>
                            <th>Ngày lưu</th>
                            <th class="text-end">Thao tác</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($document->versions as $ver)
                            <tr>
                                <td><span class="badge bg-success">{{ $ver->version_number }}</span></td>
                                <td>{{ $ver->change_log ?: ($ver->content_summary ?: '-') }}</td>
                                <td>{{ $ver->uploader?->full_name ?? 'Hệ thống' }}</td>
                                <td>{{ $ver->created_at ? \Carbon\Carbon::parse($ver->created_at)->format('d/m/Y H:i') : '-' }}</td>
                                <td class="text-end">
                                    @if($ver->file_url)
                                        <a href="{{ $ver->file_url }}" target="_blank" class="btn btn-sm btn-outline-primary"><i class="fas fa-download"></i></a>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-center py-3 text-muted">Chưa có lịch sử phiên bản.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    function toggleFavoriteShow(id, btn) {
        fetch('/documents/' + id + '/favorite', {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' }
        })
        .then(res => res.json())
        .then(data => {
            if (data.status === 'success') {
                var icon = btn.querySelector('i');
                var span = btn.querySelector('span');
                if (data.is_favorite) {
                    icon.className = 'fas fa-star me-1';
                    span.textContent = 'Đã đánh dấu quan trọng';
                } else {
                    icon.className = 'far fa-star me-1';
                    span.textContent = 'Đánh dấu quan trọng';
                }
            }
        });
    }

    function saveNote(id) {
        var note = document.getElementById('personalNoteInput').value;
        fetch('/documents/' + id + '/note', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': csrfToken,
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            },
            body: JSON.stringify({ note: note })
        })
        .then(res => res.json())
        .then(data => {
            if (data.status === 'success') {
                Swal.fire({ icon: 'success', title: 'Đã lưu ghi chú!', timer: 1500, showConfirmButton: false });
            }
        });
    }

    // Tìm kiếm văn bản để liên kết
    var searchInput = document.getElementById('relationSearchInput');
    if (searchInput) {
        searchInput.addEventListener('input', function() {
            var q = this.value.trim();
            var resultsEl = document.getElementById('relationSearchResults');
            if (q.length < 2) {
                resultsEl.style.display = 'none';
                return;
            }
            fetch('{{ route("relations.search") }}?current_id={{ $document->id }}&q=' + encodeURIComponent(q))
                .then(res => res.json())
                .then(items => {
                    resultsEl.innerHTML = '';
                    if (items.length === 0) {
                        resultsEl.style.display = 'none';
                        return;
                    }
                    items.forEach(item => {
                        var a = document.createElement('a');
                        a.href = '#';
                        a.className = 'list-group-item list-group-item-action small';
                        a.innerHTML = '<strong>' + item.document_number + '</strong> - ' + (item.content || '').substring(0, 60) + '...';
                        a.onclick = function(e) {
                            e.preventDefault();
                            document.getElementById('relatedDocumentId').value = item.id;
                            searchInput.value = item.document_number;
                            resultsEl.style.display = 'none';
                        };
                        resultsEl.appendChild(a);
                    });
                    resultsEl.style.display = 'block';
                });
        });
    }
</script>
@endpush

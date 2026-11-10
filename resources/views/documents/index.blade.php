@extends('layouts.app')

@section('title', 'Danh sách văn bản - ' . get_setting('site_title'))

@section('content')
<div class="card border-0 shadow-sm rounded-4 mb-4">
    <div class="card-body p-4">
        <!-- HEADER & ACTIONS -->
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
            <div>
                <h4 class="fw-bold mb-1 text-dark"><i class="fas fa-file-contract text-danger me-2"></i>Danh mục Văn bản Pháp quy</h4>
                <p class="text-muted small mb-0">Tìm thấy <span class="fw-bold text-danger">{{ number_format($documents->total()) }}</span> văn bản phù hợp</p>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <a href="{{ route('reports.export', request()->all()) }}" class="btn btn-outline-success btn-sm rounded-pill px-3">
                    <i class="fas fa-file-excel me-1"></i>Xuất Excel
                </a>
                @if(auth()->user()->isEditor())
                    <a href="{{ route('documents.create') }}" class="btn btn-danger btn-sm rounded-pill px-3 fw-bold">
                        <i class="fas fa-plus me-1"></i>Thêm văn bản mới
                    </a>
                @endif
            </div>
        </div>

        <!-- FILTER FORM -->
        <form action="{{ route('documents.index') }}" method="GET" class="bg-light p-3 rounded-4 mb-4 border">
            <div class="row g-2 align-items-center">
                <div class="col-md-4">
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-white"><i class="fas fa-search text-muted"></i></span>
                        <input type="text" name="q" class="form-control" placeholder="Tìm theo số hiệu hoặc trích yếu..." value="{{ request('q') }}">
                    </div>
                </div>

                <div class="col-sm-6 col-md-2">
                    <select name="type_id" class="form-select form-select-sm">
                        <option value="">-- Tất cả loại VB --</option>
                        @foreach($documentTypes as $t)
                            <option value="{{ $t->id }}" {{ request('type_id') == $t->id ? 'selected' : '' }}>{{ $t->type_name }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="col-sm-6 col-md-2">
                    <select name="authority_id" class="form-select form-select-sm">
                        <option value="">-- Tất cả cơ quan --</option>
                        @foreach($authorities as $a)
                            <option value="{{ $a->id }}" {{ request('authority_id') == $a->id ? 'selected' : '' }}>{{ $a->authority_name }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="col-sm-6 col-md-2">
                    <select name="status" class="form-select form-select-sm">
                        <option value="">-- Trạng thái --</option>
                        <option value="Còn hiệu lực" {{ request('status') === 'Còn hiệu lực' ? 'selected' : '' }}>Còn hiệu lực</option>
                        <option value="Hết hiệu lực" {{ request('status') === 'Hết hiệu lực' ? 'selected' : '' }}>Hết hiệu lực</option>
                        <option value="Chưa có hiệu lực" {{ request('status') === 'Chưa có hiệu lực' ? 'selected' : '' }}>Chưa có hiệu lực</option>
                    </select>
                </div>

                <div class="col-sm-6 col-md-2 d-flex gap-1">
                    <button type="submit" class="btn btn-danger btn-sm w-100 fw-bold">
                        <i class="fas fa-filter me-1"></i>Lọc
                    </button>
                    <a href="{{ route('documents.index') }}" class="btn btn-outline-secondary btn-sm" title="Đặt lại bộ lọc">
                        <i class="fas fa-undo"></i>
                    </a>
                </div>
            </div>

            <!-- Extended Filters (Năm ban hành, Yêu thích) -->
            <div class="d-flex flex-wrap align-items-center gap-2 mt-2 pt-2 border-top">
                <span class="text-muted small fw-semibold me-1">Lọc nhanh:</span>
                <a href="{{ route('documents.index', ['favorite' => 1]) }}"
                   class="btn btn-xs rounded-pill {{ request('favorite') ? 'btn-warning text-dark fw-bold' : 'btn-outline-secondary' }} py-0 px-2" style="font-size: 0.8rem;">
                    <i class="fas fa-star me-1 text-warning"></i>Quan trọng
                </a>
                <a href="{{ route('documents.index', ['expiring_soon' => 30]) }}"
                   class="btn btn-xs rounded-pill {{ request('expiring_soon') ? 'btn-danger text-white fw-bold' : 'btn-outline-secondary' }} py-0 px-2" style="font-size: 0.8rem;">
                    <i class="fas fa-bell me-1"></i>Sắp hết hạn (30 ngày)
                </a>

                @if($availableYears->isNotEmpty())
                    <span class="text-muted small ms-auto">Năm:</span>
                    @foreach($availableYears->take(6) as $yr)
                        <a href="{{ route('documents.index', array_merge(request()->except('page'), ['year' => $yr])) }}"
                           class="btn btn-xs rounded-pill {{ request('year') == $yr ? 'btn-danger fw-bold' : 'btn-outline-secondary' }} py-0 px-2" style="font-size: 0.8rem;">
                            {{ $yr }}
                        </a>
                    @endforeach
                @endif
            </div>
        </form>

        <!-- DOCUMENTS TABLE -->
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th style="width: 40px;" class="text-center">⭐</th>
                        <th style="min-width: 180px;">Số / Ký hiệu</th>
                        <th style="min-width: 250px;">Trích yếu nội dung</th>
                        <th style="min-width: 120px;">Ngày BH</th>
                        <th style="min-width: 140px;">Cơ quan</th>
                        <th style="min-width: 120px;">Trạng thái</th>
                        <th style="min-width: 140px;" class="text-end pe-3">Thao tác</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($documents as $doc)
                        <tr>
                            <!-- Favorite Star -->
                            <td class="text-center">
                                <button type="button" class="btn btn-sm btn-link p-0 text-decoration-none" onclick="toggleFavorite({{ $doc->id }}, this)">
                                    <i class="{{ $doc->is_favorite ? 'fas fa-star text-warning' : 'far fa-star text-muted' }} fs-5"></i>
                                </button>
                            </td>

                            <!-- Document Number & Type -->
                            <td>
                                <a href="{{ route('documents.show', $doc->id) }}" class="fw-bold text-danger text-decoration-none">
                                    {{ $doc->document_number }}
                                </a>
                                @if($doc->is_secret)
                                    <span class="badge bg-danger ms-1 small">MẬT</span>
                                @endif
                                <div class="text-muted small mt-1">
                                    <span class="badge bg-light text-secondary border">{{ $doc->documentType?->type_name ?? 'Chưa phân loại' }}</span>
                                </div>
                            </td>

                            <!-- Excerpt Content -->
                            <td>
                                <div class="text-dark small text-break" style="line-height: 1.4;">
                                    {{ Str::limit($doc->content, 180) }}
                                </div>
                                @if(!empty($doc->personal_note))
                                    <div class="mt-1 small text-info fst-italic">
                                        <i class="fas fa-sticky-note me-1"></i>{{ Str::limit($doc->personal_note, 80) }}
                                    </div>
                                @endif
                            </td>

                            <!-- Issued Date -->
                            <td class="small">
                                <div class="fw-semibold">{{ $doc->issued_date ? \Carbon\Carbon::parse($doc->issued_date)->format('d/m/Y') : '-' }}</div>
                                @if($doc->expired_date)
                                    <div class="text-muted" style="font-size: 0.75rem;">Hết hạn: {{ \Carbon\Carbon::parse($doc->expired_date)->format('d/m/Y') }}</div>
                                @endif
                            </td>

                            <!-- Authority -->
                            <td>
                                <span class="badge bg-light text-dark border text-wrap text-start" style="max-width: 160px;">
                                    {{ $doc->issuingAuthority?->authority_name ?? '-' }}
                                </span>
                            </td>

                            <!-- Status -->
                            <td>
                                <span class="{{ $doc->status_badge_class }}">{{ $doc->status }}</span>
                            </td>

                            <!-- Action Buttons -->
                            <td class="text-end pe-3 text-nowrap">
                                <a href="{{ route('documents.show', $doc->id) }}" class="btn btn-sm btn-outline-primary" title="Xem chi tiết">
                                    <i class="fas fa-eye"></i>
                                </a>

                                @if($doc->file_exists)
                                    <a href="{{ route('documents.download', $doc->id) }}" class="btn btn-sm btn-outline-danger" title="Tải tệp chính">
                                        <i class="fas fa-download"></i>
                                    </a>
                                @endif

                                @if(auth()->user()->isEditor())
                                    <a href="{{ route('documents.edit', $doc->id) }}" class="btn btn-sm btn-outline-secondary" title="Sửa">
                                        <i class="fas fa-edit"></i>
                                    </a>

                                    <form action="{{ route('documents.destroy', $doc->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Bạn có chắc chắn muốn chuyển văn bản này vào thùng rác?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger" title="Xóa">
                                            <i class="fas fa-trash-alt"></i>
                                        </button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center py-5 text-muted">
                                <i class="fas fa-search fa-3x mb-3 text-muted opacity-50"></i>
                                <div class="fw-semibold">Không tìm thấy văn bản nào phù hợp.</div>
                                <div class="small">Hãy thử tìm kiếm với từ khóa khác hoặc xóa bớt bộ lọc.</div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- PAGINATION -->
        <div class="mt-4 d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div class="text-muted small">
                Hiển thị từ {{ $documents->firstItem() ?? 0 }} đến {{ $documents->lastItem() ?? 0 }} trên tổng số {{ number_format($documents->total()) }} văn bản
            </div>
            <div>
                {{ $documents->links('pagination::bootstrap-5') }}
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    function toggleFavorite(id, btn) {
        fetch('/documents/' + id + '/favorite', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': csrfToken,
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            }
        })
        .then(res => res.json())
        .then(data => {
            if (data.status === 'success') {
                var icon = btn.querySelector('i');
                if (data.is_favorite) {
                    icon.className = 'fas fa-star text-warning fs-5';
                } else {
                    icon.className = 'far fa-star text-muted fs-5';
                }
            }
        });
    }
</script>
@endpush

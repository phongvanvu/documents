@extends('layouts.app')

@section('title', 'Thêm văn bản mới - ' . get_setting('site_title'))

@push('styles')
<style>
    .searchable-select-native {
        position: absolute;
        bottom: 0;
        left: 50%;
        width: 1px;
        height: 1px;
        opacity: 0;
        pointer-events: none;
        margin: 0;
        padding: 0;
        border: 0;
    }
    .searchable-select-trigger {
        cursor: pointer;
        background-color: #fff;
    }
    .searchable-select-trigger:focus {
        border-color: #dc3545;
        box-shadow: 0 0 0 0.25rem rgba(220, 53, 69, 0.25);
    }
    .searchable-select-menu {
        z-index: 1060;
        border-radius: 0.5rem;
        border: 1px solid rgba(0, 0, 0, 0.15);
    }
    .searchable-select-list {
        max-height: 240px;
        overflow-y: auto;
    }
    .searchable-select-option {
        display: flex;
        align-items: center;
        justify-content: space-between;
        width: 100%;
        text-align: left;
        padding: 0.45rem 0.65rem;
        border: 0;
        background: transparent;
        border-radius: 0.35rem;
        font-size: 0.9rem;
        color: #212529;
        cursor: pointer;
    }
    .searchable-select-option:hover,
    .searchable-select-option.is-highlighted {
        background-color: #f8d7da;
        color: #842029;
    }
    .searchable-select-option.is-selected {
        background-color: #dc3545;
        color: #fff;
        font-weight: 600;
    }
</style>
@endpush

@section('content')
<div class="row justify-content-center">
    <div class="col-lg-10">
        <div class="card border-0 shadow-sm rounded-4 mb-4">
            <div class="card-header bg-white border-bottom py-3 px-4 d-flex justify-content-between align-items-center">
                <h5 class="fw-bold mb-0 text-dark"><i class="fas fa-plus-circle text-danger me-2"></i>Thêm văn bản pháp quy mới</h5>
                <a href="{{ route('documents.index') }}" class="btn btn-outline-secondary btn-sm rounded-pill px-3">
                    <i class="fas fa-arrow-left me-1"></i>Quay lại danh sách
                </a>
            </div>
            <div class="card-body p-4">
                <form action="{{ route('documents.store') }}" method="POST" enctype="multipart/form-data" id="documentForm">
                    @csrf

                    <div class="row g-3 mb-3">
                        <!-- Số / Ký hiệu -->
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Số / Ký hiệu văn bản <span class="text-danger">*</span></label>
                            <div class="input-group has-validation">
                                <input type="text" name="document_number" id="document_number"
                                       class="form-control @error('document_number') is-invalid @enderror"
                                       value="{{ old('document_number') }}" placeholder="VD: 123/QĐ-BV, 45/TB-TCKT..."
                                       autocomplete="off" required>
                                <button type="button" class="btn btn-outline-secondary" id="btnManualCheckDuplicate" title="Tìm & kiểm tra trùng số hiệu ngay">
                                    <i class="fas fa-search me-1"></i>Kiểm tra
                                </button>
                                @error('document_number')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div id="checkDocLoading" class="small text-muted mt-1 d-none">
                                <i class="fas fa-spinner fa-spin me-1 text-danger"></i>Đang tìm kiếm văn bản trùng...
                            </div>
                            <div id="duplicateAlert" class="small mt-1 text-danger fw-semibold d-none">
                                <i class="fas fa-exclamation-triangle me-1"></i><span id="duplicateAlertText">Số hiệu này đã tồn tại trong hệ thống!</span>
                            </div>
                        </div>

                        <!-- Loại văn bản -->
                        <div class="col-md-3">
                            <label class="form-label fw-semibold" for="document_type_id">Loại văn bản <span class="text-danger">*</span></label>
                            <div class="d-flex gap-1 align-items-start">
                                <div class="flex-grow-1 min-w-0">
                                    <select name="document_type_id" id="document_type_id"
                                            class="form-select searchable-select @error('document_type_id') is-invalid @enderror"
                                            data-placeholder="-- Chọn hoặc gõ tìm loại VB --"
                                            data-search-placeholder="Gõ để tìm loại văn bản..."
                                            required>
                                        <option value="">-- Chọn loại VB --</option>
                                        @foreach($documentTypes as $t)
                                            <option value="{{ $t->id }}" {{ old('document_type_id') == $t->id ? 'selected' : '' }}>{{ $t->type_name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <button type="button" class="btn btn-outline-danger flex-shrink-0" id="btnOpenAddTypeModal"
                                        title="Thêm loại văn bản mới" aria-label="Thêm loại văn bản mới">
                                    <i class="fas fa-plus"></i>
                                </button>
                            </div>
                            @error('document_type_id')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                        </div>

                        <!-- Cơ quan ban hành -->
                        <div class="col-md-3">
                            <label class="form-label fw-semibold" for="authority_id">Cơ quan ban hành <span class="text-danger">*</span></label>
                            <div class="d-flex gap-1 align-items-start">
                                <div class="flex-grow-1 min-w-0">
                                    <select name="authority_id" id="authority_id"
                                            class="form-select searchable-select @error('authority_id') is-invalid @enderror"
                                            data-placeholder="-- Chọn hoặc gõ tìm cơ quan --"
                                            data-search-placeholder="Gõ để tìm cơ quan..."
                                            required>
                                        <option value="">-- Chọn cơ quan --</option>
                                        @foreach($authorities as $a)
                                            <option value="{{ $a->id }}" {{ old('authority_id') == $a->id ? 'selected' : '' }}>{{ $a->authority_name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <button type="button" class="btn btn-outline-danger flex-shrink-0" id="btnOpenAddAuthorityModal"
                                        title="Thêm cơ quan ban hành mới" aria-label="Thêm cơ quan ban hành mới">
                                    <i class="fas fa-plus"></i>
                                </button>
                            </div>
                            @error('authority_id')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <!-- KHUNG TÌM KIẾM & CẢNH BÁO TRÙNG SỐ HIỆU -->
                    <div id="duplicateResultsContainer" class="mb-3 d-none">
                        <div class="p-3 rounded-3 shadow-sm" style="background-color: #fff8e1; border: 1.5px solid #ffe082; border-left: 5px solid #dc3545;">
                            <div class="d-flex justify-content-between align-items-center mb-2 gap-2">
                                <div class="fw-bold text-danger small fs-6" id="duplicateBoxTitle">
                                    <i class="fas fa-exclamation-triangle me-2"></i>Phát hiện <span id="duplicateCountText" class="badge bg-danger rounded-pill">0</span> văn bản có số ký hiệu tương tự:
                                </div>
                                <button type="button" class="btn btn-sm btn-outline-secondary py-0 px-2" id="btnCloseDuplicateBox" title="Đóng cảnh báo này">
                                    <i class="fas fa-times me-1"></i>Đóng
                                </button>
                            </div>
                            <div class="table-responsive bg-white rounded border">
                                <table class="table table-sm table-hover align-middle mb-0 small">
                                    <thead class="table-light">
                                        <tr>
                                            <th style="width: 170px;">Số / Ký hiệu</th>
                                            <th>Trích yếu nội dung</th>
                                            <th style="width: 160px;">Loại / Cơ quan BH</th>
                                            <th style="width: 100px;" class="text-center">Ngày BH</th>
                                            <th style="width: 115px;" class="text-center">Hiệu lực</th>
                                            <th style="width: 65px;" class="text-center">Xem</th>
                                        </tr>
                                    </thead>
                                    <tbody id="duplicateTableBody"></tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <!-- Ngày ban hành -->
                        <div class="col-md-4">
                            <label class="form-label fw-semibold" for="issued_date">Ngày ban hành <span class="text-danger">*</span></label>
                            <input type="date" name="issued_date" id="issued_date" class="form-control @error('issued_date') is-invalid @enderror"
                                   value="{{ old('issued_date') }}" required>
                            @error('issued_date')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <!-- Ngày có hiệu lực -->
                        <div class="col-md-4">
                            <label class="form-label fw-semibold" for="effective_date">Ngày có hiệu lực</label>
                            <input type="date" name="effective_date" id="effective_date" class="form-control @error('effective_date') is-invalid @enderror"
                                   value="{{ old('effective_date') }}">
                            @error('effective_date')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <!-- Ngày hết hiệu lực -->
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Ngày hết hiệu lực</label>
                            <input type="date" name="expired_date" class="form-control @error('expired_date') is-invalid @enderror"
                                   value="{{ old('expired_date') }}">
                            @error('expired_date')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <!-- Trạng thái -->
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Trạng thái hiệu lực <span class="text-danger">*</span></label>
                            <select name="status" class="form-select @error('status') is-invalid @enderror" required>
                                <option value="Còn hiệu lực" {{ old('status', 'Còn hiệu lực') === 'Còn hiệu lực' ? 'selected' : '' }}>Còn hiệu lực</option>
                                <option value="Hết hiệu lực" {{ old('status') === 'Hết hiệu lực' ? 'selected' : '' }}>Hết hiệu lực</option>
                                <option value="Chưa có hiệu lực" {{ old('status') === 'Chưa có hiệu lực' ? 'selected' : '' }}>Chưa có hiệu lực</option>
                                <option value="Hết hiệu lực một phần" {{ old('status') === 'Hết hiệu lực một phần' ? 'selected' : '' }}>Hết hiệu lực một phần</option>
                                <option value="Không xác định" {{ old('status') === 'Không xác định' ? 'selected' : '' }}>Không xác định</option>
                            </select>
                            @error('status')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <!-- Tệp đính kèm -->
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Tệp văn bản chính (PDF, Word, Excel, ZIP... tối đa 120MB)</label>
                            <input type="file" name="document_file" class="form-control @error('document_file') is-invalid @enderror"
                                   accept=".pdf,.doc,.docx,.xls,.xlsx,.png,.jpg,.jpeg,.zip,.rar">
                            @error('document_file')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <!-- Trích yếu nội dung -->
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Trích yếu nội dung <span class="text-danger">*</span></label>
                        <textarea name="content" rows="4" class="form-control @error('content') is-invalid @enderror"
                                  placeholder="Nhập tóm tắt trích yếu nội dung của văn bản..." required>{{ old('content') }}</textarea>
                        @error('content')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <!-- Mật Checkbox -->
                    <div class="form-check form-switch mb-4">
                        <input class="form-check-input" type="checkbox" name="is_secret" id="is_secret" value="1" {{ old('is_secret') ? 'checked' : '' }}>
                        <label class="form-check-label fw-semibold text-danger" for="is_secret">
                            <i class="fas fa-user-secret me-1"></i>Đánh dấu văn bản MẬT (Hệ thống sẽ không lưu tệp tải lên)
                        </label>
                    </div>

                    <!-- SUBMIT BUTTONS -->
                    <div class="d-flex justify-content-end gap-2 pt-3 border-top">
                        <a href="{{ route('documents.index') }}" class="btn btn-outline-secondary px-4">Hủy bỏ</a>
                        <button type="submit" class="btn btn-danger px-4 fw-bold">
                            <i class="fas fa-save me-1"></i>Lưu văn bản
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- MODAL THÊM LOẠI VĂN BẢN MỚI -->
<div class="modal fade" id="modalAddType" tabindex="-1" aria-labelledby="modalAddTypeLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-4 border-0 shadow">
            <div class="modal-header bg-danger text-white">
                <h6 class="modal-title fw-bold" id="modalAddTypeLabel">
                    <i class="fas fa-plus-circle me-2"></i>Thêm loại văn bản mới
                </h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Đóng"></button>
            </div>
            <form id="formAddType">
                <div class="modal-body p-4">
                    <div class="mb-2">
                        <label class="form-label fw-semibold small" for="newTypeNameInput">
                            Tên loại văn bản <span class="text-danger">*</span>
                        </label>
                        <input type="text" name="type_name" id="newTypeNameInput" class="form-control"
                               placeholder="VD: Nghị định, Thông tư, Hướng dẫn..." maxlength="255" autocomplete="off" required>
                        <div class="invalid-feedback" id="newTypeNameError"></div>
                    </div>
                </div>
                <div class="modal-footer border-top">
                    <button type="button" class="btn btn-outline-secondary btn-sm px-3" data-bs-dismiss="modal">Hủy</button>
                    <button type="submit" class="btn btn-danger btn-sm px-3 fw-bold" id="btnSubmitAddType">
                        <i class="fas fa-save me-1"></i>Lưu loại văn bản
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- MODAL THÊM CƠ QUAN BAN HÀNH MỚI -->
<div class="modal fade" id="modalAddAuthority" tabindex="-1" aria-labelledby="modalAddAuthorityLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-4 border-0 shadow">
            <div class="modal-header bg-danger text-white">
                <h6 class="modal-title fw-bold" id="modalAddAuthorityLabel">
                    <i class="fas fa-building me-2"></i>Thêm cơ quan ban hành mới
                </h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Đóng"></button>
            </div>
            <form id="formAddAuthority">
                <div class="modal-body p-4">
                    <div class="mb-2">
                        <label class="form-label fw-semibold small" for="newAuthorityNameInput">
                            Tên cơ quan ban hành <span class="text-danger">*</span>
                        </label>
                        <input type="text" name="authority_name" id="newAuthorityNameInput" class="form-control"
                               placeholder="VD: Bộ Công an, Bộ Y tế, Chính phủ..." maxlength="255" autocomplete="off" required>
                        <div class="invalid-feedback" id="newAuthorityNameError"></div>
                    </div>
                </div>
                <div class="modal-footer border-top">
                    <button type="button" class="btn btn-outline-secondary btn-sm px-3" data-bs-dismiss="modal">Hủy</button>
                    <button type="submit" class="btn btn-danger btn-sm px-3 fw-bold" id="btnSubmitAddAuthority">
                        <i class="fas fa-save me-1"></i>Lưu cơ quan ban hành
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    function normalizeVietnamese(str) {
        return String(str || '')
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '')
            .replace(/đ/g, 'd')
            .replace(/Đ/g, 'D')
            .toLowerCase()
            .trim();
    }

    function escapeHtml(str) {
        return String(str ?? '').replace(/[&<>"']/g, function(ch) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[ch];
        });
    }

    // =========================================================================
    // 1. SEARCHABLE SELECT (GÕ TÌM LOẠI VĂN BẢN & CƠ QUAN BAN HÀNH)
    // =========================================================================
    function makeSearchableSelect(selectEl) {
        if (!selectEl || selectEl.dataset.ssInitialized) return;
        selectEl.dataset.ssInitialized = '1';

        var placeholderText = selectEl.dataset.placeholder || '-- Chọn --';
        var searchPlaceholder = selectEl.dataset.searchPlaceholder || 'Gõ để tìm...';

        var wrapper = document.createElement('div');
        wrapper.className = 'searchable-select-wrapper position-relative';
        selectEl.parentNode.insertBefore(wrapper, selectEl);
        wrapper.appendChild(selectEl);

        selectEl.classList.add('searchable-select-native');
        selectEl.setAttribute('tabindex', '-1');

        var trigger = document.createElement('button');
        trigger.type = 'button';
        trigger.className = 'form-select text-start d-flex align-items-center justify-content-between searchable-select-trigger';
        if (selectEl.classList.contains('is-invalid')) {
            trigger.classList.add('is-invalid');
        }

        var labelSpan = document.createElement('span');
        labelSpan.className = 'text-truncate pe-2';
        trigger.appendChild(labelSpan);
        wrapper.appendChild(trigger);

        var menu = document.createElement('div');
        menu.className = 'dropdown-menu w-100 p-2 shadow-sm searchable-select-menu';

        var searchWrap = document.createElement('div');
        searchWrap.className = 'input-group input-group-sm mb-2';
        searchWrap.innerHTML = '<span class="input-group-text bg-white"><i class="fas fa-search text-muted"></i></span>';
        var searchInput = document.createElement('input');
        searchInput.type = 'search';
        searchInput.className = 'form-control searchable-select-input';
        searchInput.placeholder = searchPlaceholder;
        searchInput.setAttribute('autocomplete', 'off');
        searchWrap.appendChild(searchInput);
        menu.appendChild(searchWrap);

        var list = document.createElement('div');
        list.className = 'searchable-select-list';
        menu.appendChild(list);
        wrapper.appendChild(menu);

        var isOpen = false;
        var highlightedIndex = -1;
        var visibleOptionButtons = [];

        function getOptionsData() {
            return Array.from(selectEl.options)
                .filter(function(opt) { return opt.value !== ''; })
                .map(function(opt) {
                    return {
                        value: opt.value,
                        text: opt.textContent.trim(),
                        normalized: normalizeVietnamese(opt.textContent)
                    };
                });
        }

        function syncLabel() {
            var selected = selectEl.options[selectEl.selectedIndex];
            if (selected && selected.value !== '') {
                labelSpan.textContent = selected.textContent.trim();
                labelSpan.classList.remove('text-muted');
                trigger.classList.remove('is-invalid');
            } else {
                labelSpan.textContent = placeholderText;
                labelSpan.classList.add('text-muted');
            }
        }

        function setHighlight(idx) {
            if (visibleOptionButtons.length === 0) {
                highlightedIndex = -1;
                return;
            }
            if (idx < 0) idx = visibleOptionButtons.length - 1;
            if (idx >= visibleOptionButtons.length) idx = 0;

            visibleOptionButtons.forEach(function(btn) { btn.classList.remove('is-highlighted'); });
            highlightedIndex = idx;
            var activeBtn = visibleOptionButtons[highlightedIndex];
            if (activeBtn) {
                activeBtn.classList.add('is-highlighted');
                activeBtn.scrollIntoView({ block: 'nearest' });
            }
        }

        function selectValue(val) {
            selectEl.value = val;
            selectEl.dispatchEvent(new Event('change', { bubbles: true }));
            syncLabel();
            closeMenu(true);
        }

        function renderList(filterTerm) {
            var q = normalizeVietnamese(filterTerm);
            var items = getOptionsData().filter(function(item) {
                return !q || item.normalized.includes(q);
            });

            list.innerHTML = '';
            visibleOptionButtons = [];
            highlightedIndex = -1;

            if (items.length === 0) {
                var emptyDiv = document.createElement('div');
                emptyDiv.className = 'text-muted small text-center py-2 fst-italic';
                emptyDiv.textContent = 'Không tìm thấy kết quả phù hợp.';
                list.appendChild(emptyDiv);

                if (quickAddHandlers[selectEl.id] && filterTerm.trim() !== '') {
                    var quickAddBtn = document.createElement('button');
                    quickAddBtn.type = 'button';
                    quickAddBtn.className = 'btn btn-sm btn-outline-danger w-100 mt-1 fw-semibold';
                    quickAddBtn.innerHTML = '<i class="fas fa-plus me-1"></i>Thêm "' + escapeHtml(filterTerm.trim()) + '"';
                    quickAddBtn.addEventListener('click', function(e) {
                        e.preventDefault();
                        e.stopPropagation();
                        closeMenu(false);
                        quickAddHandlers[selectEl.id](filterTerm.trim());
                    });
                    list.appendChild(quickAddBtn);
                }
                return;
            }

            items.forEach(function(item) {
                var btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'searchable-select-option';
                if (item.value === selectEl.value) {
                    btn.classList.add('is-selected');
                }
                btn.textContent = item.text;
                btn.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    selectValue(item.value);
                });
                list.appendChild(btn);
                visibleOptionButtons.push(btn);
            });

            if (q) {
                setHighlight(0);
            } else {
                var selectedIdx = items.findIndex(function(item) { return item.value === selectEl.value; });
                if (selectedIdx >= 0) {
                    setHighlight(selectedIdx);
                }
            }
        }

        function openMenu(initialChar) {
            document.querySelectorAll('.searchable-select-menu.show').forEach(function(otherMenu) {
                if (otherMenu !== menu) {
                    otherMenu.classList.remove('show');
                }
            });
            isOpen = true;
            menu.classList.add('show');
            searchInput.value = initialChar || '';
            renderList(searchInput.value);
            setTimeout(function() {
                searchInput.focus();
                if (initialChar) {
                    searchInput.setSelectionRange(searchInput.value.length, searchInput.value.length);
                }
            }, 0);
        }

        function closeMenu(returnFocus) {
            if (!isOpen) return;
            isOpen = false;
            menu.classList.remove('show');
            if (returnFocus) {
                trigger.focus();
            }
        }

        trigger.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            if (isOpen) {
                closeMenu(false);
            } else {
                openMenu('');
            }
        });

        trigger.addEventListener('keydown', function(e) {
            if (e.key === 'ArrowDown' || e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                openMenu('');
            } else if (e.key.length === 1 && !e.ctrlKey && !e.metaKey && !e.altKey) {
                e.preventDefault();
                openMenu(e.key);
            }
        });

        searchInput.addEventListener('input', function() {
            renderList(this.value);
        });

        searchInput.addEventListener('keydown', function(e) {
            if (e.key === 'ArrowDown') {
                e.preventDefault();
                setHighlight(highlightedIndex + 1);
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                setHighlight(highlightedIndex - 1);
            } else if (e.key === 'Enter') {
                e.preventDefault();
                if (highlightedIndex >= 0 && visibleOptionButtons[highlightedIndex]) {
                    visibleOptionButtons[highlightedIndex].click();
                }
            } else if (e.key === 'Escape') {
                e.preventDefault();
                closeMenu(true);
            } else if (e.key === 'Tab') {
                closeMenu(false);
            }
        });

        selectEl.addEventListener('invalid', function() {
            trigger.classList.add('is-invalid');
        });

        document.addEventListener('click', function(e) {
            if (isOpen && !wrapper.contains(e.target)) {
                closeMenu(false);
            }
        });

        selectEl._searchableSelect = {
            addAndSelect: function(value, text) {
                var strVal = String(value);
                var existing = Array.from(selectEl.options).find(function(opt) {
                    return opt.value === strVal;
                });
                if (!existing) {
                    var newOpt = document.createElement('option');
                    newOpt.value = strVal;
                    newOpt.textContent = text;
                    selectEl.appendChild(newOpt);
                }
                selectEl.value = strVal;
                selectEl.dispatchEvent(new Event('change', { bubbles: true }));
                syncLabel();
            }
        };

        syncLabel();
    }

    var quickAddHandlers = {};

    function initQuickAddCategory(cfg) {
        var modalEl = document.getElementById(cfg.modalId);
        var formEl = document.getElementById(cfg.formId);
        var inputEl = document.getElementById(cfg.inputId);
        var errorEl = document.getElementById(cfg.errorId);
        var submitBtn = document.getElementById(cfg.submitBtnId);
        var openBtn = document.getElementById(cfg.openBtnId);
        var targetSelect = document.getElementById(cfg.selectId);

        function resetError() {
            if (inputEl) inputEl.classList.remove('is-invalid');
            if (errorEl) errorEl.textContent = '';
        }

        function openModal(prefillName) {
            if (!modalEl) return;
            resetError();
            if (inputEl) {
                inputEl.value = prefillName || '';
            }
            bootstrap.Modal.getOrCreateInstance(modalEl).show();
        }

        quickAddHandlers[cfg.selectId] = openModal;

        if (modalEl) {
            modalEl.addEventListener('shown.bs.modal', function() {
                if (inputEl) {
                    inputEl.focus();
                    inputEl.select();
                }
            });
        }

        if (openBtn) {
            openBtn.addEventListener('click', function() {
                openModal('');
            });
        }

        if (formEl) {
            formEl.addEventListener('submit', function(e) {
                e.preventDefault();
                resetError();

                var value = inputEl ? inputEl.value.trim() : '';
                if (!value) {
                    inputEl.classList.add('is-invalid');
                    errorEl.textContent = cfg.emptyMessage;
                    return;
                }

                var origBtnHtml = submitBtn.innerHTML;
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Đang lưu...';

                var payload = {};
                payload[cfg.fieldName] = value;

                fetch(cfg.url, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': csrfToken,
                        'Content-Type': 'application/json',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify(payload)
                })
                .then(function(res) {
                    return res.json().then(function(data) {
                        return { ok: res.ok, status: res.status, data: data };
                    });
                })
                .then(function(result) {
                    var addedItem = result.data && result.data.item;
                    if (result.ok && result.data.status === 'success' && addedItem) {
                        if (targetSelect && targetSelect._searchableSelect) {
                            targetSelect._searchableSelect.addAndSelect(addedItem.id, addedItem.name);
                        }
                        formEl.reset();
                        bootstrap.Modal.getOrCreateInstance(modalEl).hide();

                        if (typeof Swal !== 'undefined') {
                            Swal.fire({
                                toast: true,
                                position: 'top-end',
                                icon: 'success',
                                title: result.data.message || cfg.successFallback,
                                showConfirmButton: false,
                                timer: 2000
                            });
                        }
                    } else {
                        var fieldErrors = result.data && result.data.errors && result.data.errors[cfg.fieldName];
                        var errMsg = (fieldErrors && fieldErrors[0])
                            || (result.data && result.data.message)
                            || cfg.errorFallback;
                        inputEl.classList.add('is-invalid');
                        errorEl.textContent = errMsg;
                    }
                })
                .catch(function() {
                    inputEl.classList.add('is-invalid');
                    errorEl.textContent = 'Lỗi kết nối máy chủ. Vui lòng thử lại.';
                })
                .finally(function() {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = origBtnHtml;
                });
            });
        }
    }

    initQuickAddCategory({
        modalId: 'modalAddType',
        formId: 'formAddType',
        inputId: 'newTypeNameInput',
        errorId: 'newTypeNameError',
        submitBtnId: 'btnSubmitAddType',
        openBtnId: 'btnOpenAddTypeModal',
        selectId: 'document_type_id',
        fieldName: 'type_name',
        url: '{{ route("categories.types.store") }}',
        emptyMessage: 'Vui lòng nhập tên loại văn bản.',
        successFallback: 'Đã thêm loại văn bản mới!',
        errorFallback: 'Không thể thêm loại văn bản mới.'
    });

    initQuickAddCategory({
        modalId: 'modalAddAuthority',
        formId: 'formAddAuthority',
        inputId: 'newAuthorityNameInput',
        errorId: 'newAuthorityNameError',
        submitBtnId: 'btnSubmitAddAuthority',
        openBtnId: 'btnOpenAddAuthorityModal',
        selectId: 'authority_id',
        fieldName: 'authority_name',
        url: '{{ route("categories.authorities.store") }}',
        emptyMessage: 'Vui lòng nhập tên cơ quan ban hành.',
        successFallback: 'Đã thêm cơ quan ban hành mới!',
        errorFallback: 'Không thể thêm cơ quan ban hành mới.'
    });

    document.querySelectorAll('select.searchable-select').forEach(makeSearchableSelect);

    // =========================================================================
    // 2. TÌM KIẾM & CẢNH BÁO TRÙNG SỐ HIỆU VĂN BẢN KHI GÕ
    // =========================================================================
    var docInput = document.getElementById('document_number');
    var checkLoading = document.getElementById('checkDocLoading');
    var duplicateAlert = document.getElementById('duplicateAlert');
    var duplicateAlertText = document.getElementById('duplicateAlertText');
    var duplicateBox = document.getElementById('duplicateResultsContainer');
    var duplicateBoxTitle = document.getElementById('duplicateBoxTitle');
    var duplicateTableBody = document.getElementById('duplicateTableBody');
    var btnManualCheck = document.getElementById('btnManualCheckDuplicate');
    var btnCloseBox = document.getElementById('btnCloseDuplicateBox');

    var checkTimeout = null;
    var activeRequestId = 0;
    var lastCheckedQuery = null;

    function statusBadgeClass(status) {
        if (status === 'Còn hiệu lực') return 'bg-success';
        if (status === 'Hết hiệu lực') return 'bg-danger';
        if (status === 'Chưa có hiệu lực') return 'bg-warning text-dark';
        return 'bg-secondary';
    }

    function runVerifyDuplicate(forceAlert) {
        if (!docInput) return;
        var query = docInput.value.trim();
        lastCheckedQuery = query;

        if (query.length < 2) {
            duplicateAlert.classList.add('d-none');
            duplicateBox.classList.add('d-none');
            checkLoading.classList.add('d-none');
            duplicateTableBody.innerHTML = '';
            return;
        }

        checkLoading.classList.remove('d-none');
        var requestId = ++activeRequestId;

        fetch('{{ route("documents.check-duplicate") }}?document_number=' + encodeURIComponent(query), {
            headers: { 'Accept': 'application/json' }
        })
        .then(function(res) { return res.json(); })
        .then(function(data) {
            if (requestId !== activeRequestId) return;
            checkLoading.classList.add('d-none');

            var docs = Array.isArray(data.documents) ? data.documents : [];
            if (data.exists && docs.length > 0) {
                if (data.exact_exists) {
                    duplicateAlertText.textContent = 'Cảnh báo: Số hiệu này đã tồn tại chính xác trong hệ thống!';
                    duplicateAlert.classList.remove('d-none');
                    duplicateBoxTitle.innerHTML = '<i class="fas fa-exclamation-triangle me-2"></i>Cảnh báo: Phát hiện văn bản <span class="text-decoration-underline">TRÙNG CHÍNH XÁC</span> và các số hiệu tương tự (' + docs.length + '):';
                } else {
                    duplicateAlertText.textContent = 'Tìm thấy ' + docs.length + ' văn bản có số ký hiệu gần giống bên dưới.';
                    duplicateAlert.classList.remove('d-none');
                    duplicateBoxTitle.innerHTML = '<i class="fas fa-search me-2"></i>Tìm thấy <span class="badge bg-danger rounded-pill">' + docs.length + '</span> văn bản có số ký hiệu tương tự:';
                }

                duplicateTableBody.innerHTML = docs.map(function(d) {
                    var exactBadge = d.is_exact ? '<span class="badge bg-danger ms-1">Trùng khớp</span>' : '';
                    return '<tr>' +
                        '<td class="fw-bold text-danger">' + escapeHtml(d.document_number) + exactBadge + '</td>' +
                        '<td>' + escapeHtml(d.content) + '</td>' +
                        '<td><div class="fw-semibold text-dark">' + escapeHtml(d.type_name) + '</div><div class="text-muted" style="font-size: 0.75rem;">' + escapeHtml(d.authority_name) + '</div></td>' +
                        '<td class="text-center text-nowrap">' + escapeHtml(d.issued_date_formatted) + '</td>' +
                        '<td class="text-center text-nowrap"><span class="badge ' + statusBadgeClass(d.status) + '">' + escapeHtml(d.status) + '</span></td>' +
                        '<td class="text-center"><a href="' + escapeHtml(d.url) + '" target="_blank" class="btn btn-sm btn-outline-primary py-0 px-2" title="Mở xem trong tab mới"><i class="fas fa-external-link-alt"></i></a></td>' +
                    '</tr>';
                }).join('');

                duplicateBox.classList.remove('d-none');
            } else {
                duplicateAlert.classList.add('d-none');
                duplicateBox.classList.add('d-none');
                duplicateTableBody.innerHTML = '';

                if (forceAlert && typeof Swal !== 'undefined') {
                    Swal.fire({
                        toast: true,
                        position: 'top-end',
                        icon: 'success',
                        title: 'Không tìm thấy văn bản trùng số hiệu',
                        showConfirmButton: false,
                        timer: 2000
                    });
                }
            }
        })
        .catch(function() {
            if (requestId === activeRequestId) {
                checkLoading.classList.add('d-none');
            }
        });
    }

    if (docInput) {
        docInput.addEventListener('input', function() {
            clearTimeout(checkTimeout);
            checkTimeout = setTimeout(function() { runVerifyDuplicate(false); }, 400);
        });

        docInput.addEventListener('blur', function() {
            clearTimeout(checkTimeout);
            if (docInput.value.trim() !== lastCheckedQuery) {
                runVerifyDuplicate(false);
            }
        });

        if (docInput.value.trim().length >= 2) {
            runVerifyDuplicate(false);
        }
    }

    if (btnManualCheck) {
        btnManualCheck.addEventListener('click', function(e) {
            e.preventDefault();
            clearTimeout(checkTimeout);
            runVerifyDuplicate(true);
        });
    }

    if (btnCloseBox) {
        btnCloseBox.addEventListener('click', function(e) {
            e.preventDefault();
            duplicateBox.classList.add('d-none');
        });
    }

    // =========================================================================
    // 3. TỰ ĐỘNG ĐIỀN NGÀY CÓ HIỆU LỰC MẶC ĐỊNH = NGÀY BAN HÀNH
    // =========================================================================
    var issuedDateInput = document.getElementById('issued_date');
    var effectiveDateInput = document.getElementById('effective_date');

    if (issuedDateInput && effectiveDateInput) {
        var effectiveManuallyEdited = Boolean(
            effectiveDateInput.value && effectiveDateInput.value !== issuedDateInput.value
        );

        effectiveDateInput.addEventListener('change', function() {
            effectiveManuallyEdited = Boolean(this.value && this.value !== issuedDateInput.value);
        });

        function syncEffectiveFromIssued(requireFourDigitYear) {
            var val = issuedDateInput.value;
            if (!val || !/^\d{4}-\d{2}-\d{2}$/.test(val)) return;
            if (requireFourDigitYear && parseInt(val.slice(0, 4), 10) < 1000) return;

            if (!effectiveDateInput.value || !effectiveManuallyEdited) {
                effectiveDateInput.value = val;
            }
        }

        issuedDateInput.addEventListener('input', function() { syncEffectiveFromIssued(true); });
        issuedDateInput.addEventListener('change', function() { syncEffectiveFromIssued(false); });
        issuedDateInput.addEventListener('blur', function() { syncEffectiveFromIssued(false); });
    }
});
</script>
@endpush

<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\DocumentRelation;
use App\Models\DocumentType;
use App\Models\DocumentVersion;
use App\Models\IssuingAuthority;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DocumentController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->only([
            'q', 'type_id', 'authority_id', 'status', 'year',
            'from_date', 'to_date', 'favorite', 'expiring_soon'
        ]);

        $query = Document::with(['documentType', 'issuingAuthority'])
            ->filter($filters);

        // Sắp xếp
        $sortBy = $request->get('sort', 'issued_date');
        $sortDir = $request->get('dir', 'desc');
        $allowedSorts = ['issued_date', 'document_number', 'effective_date', 'expired_date', 'id'];

        if (in_array($sortBy, $allowedSorts, true)) {
            $query->orderBy($sortBy, $sortDir === 'asc' ? 'asc' : 'desc');
        } else {
            $query->latest('id');
        }

        $perPage = (int) $request->get('per_page', 20);
        $documents = $query->paginate($perPage)->withQueryString();

        $documentTypes = DocumentType::orderBy('type_name')->get();
        $authorities = IssuingAuthority::orderBy('authority_name')->get();

        // Lấy danh sách các năm ban hành có trong CSDL để lọc
        $availableYears = Document::selectRaw('YEAR(issued_date) as yr')
            ->whereNotNull('issued_date')
            ->distinct()
            ->orderBy('yr', 'desc')
            ->pluck('yr')
            ->filter();

        return view('documents.index', compact(
            'documents',
            'documentTypes',
            'authorities',
            'availableYears',
            'filters'
        ));
    }

    public function create()
    {
        $documentTypes = DocumentType::orderBy('type_name')->get();
        $authorities = IssuingAuthority::orderBy('authority_name')->get();

        return view('documents.create', compact('documentTypes', 'authorities'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'document_number' => 'required|string|max:255',
            'document_type_id' => 'required|exists:document_types,id',
            'authority_id' => 'required|exists:issuing_authorities,id',
            'issued_date' => 'required|date',
            'effective_date' => 'nullable|date',
            'expired_date' => 'nullable|date',
            'status' => 'required|string|max:50',
            'content' => 'required|string',
            'document_file' => 'nullable|file|mimes:pdf,doc,docx,xls,xlsx,png,jpg,jpeg,zip,rar|max:122880', // 120MB
            'is_secret' => 'nullable|boolean',
            'related_document_id' => 'nullable|exists:documents,id',
            'relation_type' => 'nullable|string|max:50',
        ], [
            'document_number.required' => 'Vui lòng nhập số / ký hiệu văn bản.',
            'document_type_id.required' => 'Vui lòng chọn loại văn bản.',
            'authority_id.required' => 'Vui lòng chọn cơ quan ban hành.',
            'issued_date.required' => 'Vui lòng chọn ngày ban hành.',
            'content.required' => 'Vui lòng nhập trích yếu nội dung văn bản.',
            'document_file.max' => 'Dung lượng tệp không được vượt quá 120MB.',
            'document_file.mimes' => 'Định dạng tệp không được hỗ trợ.',
        ]);

        // Tự động kiểm tra trạng thái hết hạn nếu ngày hết hạn đã qua
        if (!empty($validated['expired_date']) && Carbon::parse($validated['expired_date'])->isPast()) {
            $validated['status'] = 'Hết hiệu lực';
        }

        // Xử lý tệp đính kèm
        $filePath = null;
        $isSecret = $request->boolean('is_secret');

        // Kiểm tra xem số hiệu có ký hiệu mật không
        $checkDoc = new Document(['document_number' => $validated['document_number']]);
        if ($checkDoc->is_secret) {
            $isSecret = true;
        }

        // Nếu không phải văn bản mật và có tệp tải lên
        if (!$isSecret && $request->hasFile('document_file') && $request->file('document_file')->isValid()) {
            $file = $request->file('document_file');
            $uploadSubdir = 'uploads/documents/' . date('Y/m');
            $targetDir = public_path($uploadSubdir);

            if (!is_dir($targetDir)) {
                mkdir($targetDir, 0755, true);
            }

            $safeName = time() . '_' . bin2hex(random_bytes(4)) . '_' . sanitize_upload_filename($file->getClientOriginalName());
            $file->move($targetDir, $safeName);
            $filePath = $uploadSubdir . '/' . $safeName;
        }

        $document = Document::create([
            'document_number' => $validated['document_number'],
            'document_type_id' => $validated['document_type_id'],
            'authority_id' => $validated['authority_id'],
            'issued_date' => $validated['issued_date'],
            'effective_date' => $validated['effective_date'] ?? null,
            'expired_date' => $validated['expired_date'] ?? null,
            'status' => $validated['status'],
            'content' => $validated['content'],
            'file_path' => $filePath,
        ]);

        // Nếu có chọn văn bản liên quan
        if (!empty($validated['related_document_id']) && !empty($validated['relation_type'])) {
            DocumentRelation::create([
                'document_id' => $document->id,
                'related_document_id' => $validated['related_document_id'],
                'relation_type' => $validated['relation_type'],
            ]);
        }

        log_activity("Thêm văn bản mới: {$document->document_number} (ID: {$document->id})");

        return redirect()->route('documents.show', $document->id)
            ->with('success', 'Thêm mới văn bản thành công!');
    }

    public function show($id)
    {
        $document = Document::with([
            'documentType',
            'issuingAuthority',
            'attachments.uploader',
            'versions.uploader',
        ])->findOrFail($id);

        $relatedDocuments = $document->allRelatedDocuments();

        return view('documents.show', compact('document', 'relatedDocuments'));
    }

    public function edit($id)
    {
        $document = Document::findOrFail($id);
        $documentTypes = DocumentType::orderBy('type_name')->get();
        $authorities = IssuingAuthority::orderBy('authority_name')->get();

        return view('documents.edit', compact('document', 'documentTypes', 'authorities'));
    }

    public function update(Request $request, $id)
    {
        $document = Document::findOrFail($id);

        $validated = $request->validate([
            'document_number' => 'required|string|max:255',
            'document_type_id' => 'required|exists:document_types,id',
            'authority_id' => 'required|exists:issuing_authorities,id',
            'issued_date' => 'required|date',
            'effective_date' => 'nullable|date',
            'expired_date' => 'nullable|date',
            'status' => 'required|string|max:50',
            'content' => 'required|string',
            'document_file' => 'nullable|file|mimes:pdf,doc,docx,xls,xlsx,png,jpg,jpeg,zip,rar|max:122880',
            'is_secret' => 'nullable|boolean',
        ]);

        if (!empty($validated['expired_date']) && Carbon::parse($validated['expired_date'])->isPast()) {
            $validated['status'] = 'Hết hiệu lực';
        }

        $oldContent = $document->content;
        $oldFile = $document->file_path;
        $newFilePath = $document->file_path;

        $isSecret = $request->boolean('is_secret');
        if ($isSecret) {
            $newFilePath = null;
        } elseif ($request->hasFile('document_file') && $request->file('document_file')->isValid()) {
            $file = $request->file('document_file');
            $uploadSubdir = 'uploads/documents/' . date('Y/m');
            $targetDir = public_path($uploadSubdir);

            if (!is_dir($targetDir)) {
                mkdir($targetDir, 0755, true);
            }

            $safeName = time() . '_' . bin2hex(random_bytes(4)) . '_' . sanitize_upload_filename($file->getClientOriginalName());
            $file->move($targetDir, $safeName);
            $newFilePath = $uploadSubdir . '/' . $safeName;
        }

        // Tự động lưu snapshot phiên bản nếu tệp hoặc nội dung thay đổi
        if (($oldContent !== $validated['content'] || $oldFile !== $newFilePath) && !empty($oldFile)) {
            $nextVerNum = 'v' . ($document->versions()->count() + 1);
            $userId = auth()->id() ?? 1;
            DocumentVersion::create([
                'document_id' => $document->id,
                'version_number' => $nextVerNum,
                'file_path' => $oldFile,
                'content_summary' => $oldContent,
                'change_log' => 'Tự động sao lưu phiên bản cũ trước khi cập nhật.',
                'created_by' => $userId,
                'uploaded_by' => $userId,
                'created_at' => now(),
            ]);
        }

        $document->update([
            'document_number' => $validated['document_number'],
            'document_type_id' => $validated['document_type_id'],
            'authority_id' => $validated['authority_id'],
            'issued_date' => $validated['issued_date'],
            'effective_date' => $validated['effective_date'] ?? null,
            'expired_date' => $validated['expired_date'] ?? null,
            'status' => $validated['status'],
            'content' => $validated['content'],
            'file_path' => $newFilePath,
        ]);

        log_activity("Cập nhật văn bản: {$document->document_number} (ID: {$document->id})");

        return redirect()->route('documents.show', $document->id)
            ->with('success', 'Cập nhật văn bản thành công!');
    }

    public function destroy($id)
    {
        $document = Document::findOrFail($id);
        $document->delete(); // Soft delete

        log_activity("Chuyển văn bản vào thùng rác: {$document->document_number} (ID: {$document->id})");

        return redirect()->route('documents.index')
            ->with('success', 'Đã chuyển văn bản vào thùng rác.');
    }

    // Danh sách thùng rác
    public function trash()
    {
        $documents = Document::onlyTrashed()
            ->with(['documentType', 'issuingAuthority'])
            ->latest('deleted_at')
            ->paginate(20);

        return view('documents.trash', compact('documents'));
    }

    // Khôi phục văn bản từ thùng rác
    public function restore($id)
    {
        $document = Document::onlyTrashed()->findOrFail($id);
        $document->restore();

        log_activity("Khôi phục văn bản từ thùng rác: {$document->document_number} (ID: {$document->id})");

        return back()->with('success', 'Đã khôi phục văn bản thành công!');
    }

    // Xóa vĩnh viễn
    public function forceDelete($id)
    {
        $document = Document::onlyTrashed()->findOrFail($id);

        // Xóa tệp vật lý nếu có
        if (!empty($document->file_path) && file_exists(public_path($document->file_path))) {
            @unlink(public_path($document->file_path));
        }

        $docNum = $document->document_number;
        $document->forceDelete();

        log_activity("Xóa vĩnh viễn văn bản: {$docNum} (ID: {$id})");

        return back()->with('success', 'Đã xóa vĩnh viễn văn bản khỏi hệ thống.');
    }

    // AJAX Toggle Yêu thích
    public function toggleFavorite($id)
    {
        $document = Document::findOrFail($id);
        $document->is_favorite = !$document->is_favorite;
        $document->save();

        return response()->json([
            'status' => 'success',
            'is_favorite' => $document->is_favorite,
            'message' => $document->is_favorite ? 'Đã thêm vào danh sách yêu thích.' : 'Đã bỏ yêu thích.',
        ]);
    }

    // AJAX Cập nhật ghi chú cá nhân
    public function updateNote(Request $request, $id)
    {
        $document = Document::findOrFail($id);
        $document->personal_note = $request->input('note');
        $document->save();

        return response()->json([
            'status' => 'success',
            'message' => 'Đã lưu ghi chú thành công.',
        ]);
    }

    // AJAX Kiểm tra trùng số hiệu
    public function checkDuplicate(Request $request)
    {
        $docNum = trim($request->get('document_number', ''));
        $excludeId = (int) $request->get('exclude_id', 0);

        if (empty($docNum)) {
            return response()->json(['exists' => false]);
        }

        $exists = Document::where('document_number', $docNum)
            ->when($excludeId > 0, fn($q) => $q->where('id', '!=', $excludeId))
            ->exists();

        return response()->json(['exists' => $exists]);
    }

    // Tải tệp văn bản chính
    public function download($id)
    {
        $document = Document::findOrFail($id);

        if (!$document->file_exists) {
            return back()->with('error', 'Tệp tin không tồn tại trên máy chủ.');
        }

        $fullPath = public_path($document->file_path);
        if (!file_exists($fullPath)) {
            $fullPath = base_path($document->file_path);
        }

        return response()->download($fullPath, basename($document->file_path));
    }
}

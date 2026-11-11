<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\DocumentType;
use App\Models\IssuingAuthority;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CategoryController extends Controller
{
    // === LOẠI VĂN BẢN ===
    public function indexTypes()
    {
        $types = DocumentType::withCount('documents')->orderBy('type_name')->paginate(20);
        return view('categories.types', compact('types'));
    }

    public function storeType(Request $request)
    {
        if (!auth()->user()?->isEditor()) {
            if ($request->expectsJson()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Bạn không có quyền thêm loại văn bản.',
                ], 403);
            }
            abort(403, 'Bạn không có quyền thêm loại văn bản.');
        }

        $validated = $request->validate([
            'type_name' => 'required|string|max:255|unique:document_types,type_name',
        ], [
            'type_name.required' => 'Vui lòng nhập tên loại văn bản.',
            'type_name.max' => 'Tên loại văn bản tối đa 255 ký tự.',
            'type_name.unique' => 'Loại văn bản này đã tồn tại trong hệ thống.',
        ]);

        $type = DocumentType::create($validated);
        log_activity("Thêm loại văn bản: {$type->type_name}");

        if ($request->expectsJson()) {
            return response()->json([
                'status' => 'success',
                'message' => 'Đã thêm loại văn bản mới.',
                'type' => [
                    'id' => $type->id,
                    'type_name' => $type->type_name,
                ],
            ], 201);
        }

        return back()->with('success', 'Đã thêm loại văn bản mới.');
    }

    public function updateType(Request $request, $id)
    {
        $type = DocumentType::findOrFail($id);

        $validated = $request->validate([
            'type_name' => "required|string|max:255|unique:document_types,type_name,{$id}",
        ]);

        $type->update($validated);
        log_activity("Cập nhật loại văn bản: {$type->type_name}");

        return back()->with('success', 'Đã cập nhật loại văn bản.');
    }

    public function destroyType($id)
    {
        $type = DocumentType::withCount('documents')->findOrFail($id);

        if ($type->documents_count > 0) {
            return back()->with('error', "Không thể xóa vì còn {$type->documents_count} văn bản thuộc loại này. Hãy gộp sang loại khác trước!");
        }

        $typeName = $type->type_name;
        $type->delete();
        log_activity("Xóa loại văn bản: {$typeName}");

        return back()->with('success', 'Đã xóa loại văn bản.');
    }

    // === CƠ QUAN BAN HÀNH ===
    public function indexAuthorities()
    {
        $authorities = IssuingAuthority::withCount('documents')->orderBy('authority_name')->paginate(20);
        return view('categories.authorities', compact('authorities'));
    }

    public function storeAuthority(Request $request)
    {
        $validated = $request->validate([
            'authority_name' => 'required|string|max:255|unique:issuing_authorities,authority_name',
        ]);

        IssuingAuthority::create($validated);
        log_activity("Thêm cơ quan ban hành: {$validated['authority_name']}");

        return back()->with('success', 'Đã thêm cơ quan ban hành mới.');
    }

    public function updateAuthority(Request $request, $id)
    {
        $authority = IssuingAuthority::findOrFail($id);

        $validated = $request->validate([
            'authority_name' => "required|string|max:255|unique:issuing_authorities,authority_name,{$id}",
        ]);

        $authority->update($validated);
        log_activity("Cập nhật cơ quan ban hành: {$authority->authority_name}");

        return back()->with('success', 'Đã cập nhật cơ quan ban hành.');
    }

    public function destroyAuthority($id)
    {
        $authority = IssuingAuthority::withCount('documents')->findOrFail($id);

        if ($authority->documents_count > 0) {
            return back()->with('error', "Không thể xóa vì còn {$authority->documents_count} văn bản thuộc cơ quan này. Hãy gộp trước!");
        }

        $name = $authority->authority_name;
        $authority->delete();
        log_activity("Xóa cơ quan ban hành: {$name}");

        return back()->with('success', 'Đã xóa cơ quan ban hành.');
    }

    // === GỘP DANH MỤC TRÙNG LẶP ===
    public function showMerge()
    {
        $types = DocumentType::withCount('documents')->orderBy('type_name')->get();
        $authorities = IssuingAuthority::withCount('documents')->orderBy('authority_name')->get();

        return view('categories.merge', compact('types', 'authorities'));
    }

    public function processMerge(Request $request)
    {
        $target = $request->get('target_type'); // 'type' hoặc 'authority'
        $primaryId = (int) $request->get('primary_id');
        $duplicateIds = (array) $request->get('duplicate_ids', []);

        if ($primaryId <= 0 || empty($duplicateIds)) {
            return back()->with('error', 'Vui lòng chọn danh mục chính và ít nhất một danh mục cần gộp.');
        }

        // Bỏ primaryId nếu lỡ chọn trùng
        $duplicateIds = array_diff($duplicateIds, [$primaryId]);
        if (empty($duplicateIds)) {
            return back()->with('error', 'Danh mục cần gộp không được trùng với danh mục chính.');
        }

        DB::beginTransaction();
        try {
            if ($target === 'type') {
                $primary = DocumentType::findOrFail($primaryId);
                // Cập nhật tất cả văn bản sang primary_id
                $updatedCount = Document::whereIn('document_type_id', $duplicateIds)
                    ->update(['document_type_id' => $primaryId]);
                // Xóa các danh mục trùng
                DocumentType::whereIn('id', $duplicateIds)->delete();
                log_activity("Gộp " . count($duplicateIds) . " loại văn bản vào '{$primary->type_name}' ({$updatedCount} VB được chuyển)");
            } else {
                $primary = IssuingAuthority::findOrFail($primaryId);
                $updatedCount = Document::whereIn('authority_id', $duplicateIds)
                    ->update(['authority_id' => $primaryId]);
                IssuingAuthority::whereIn('id', $duplicateIds)->delete();
                log_activity("Gộp " . count($duplicateIds) . " cơ quan ban hành vào '{$primary->authority_name}' ({$updatedCount} VB được chuyển)");
            }

            DB::commit();
            return back()->with('success', "Gộp danh mục thành công! Đã cập nhật {$updatedCount} văn bản liên quan.");
        } catch (\Throwable $e) {
            DB::rollBack();
            return back()->with('error', 'Lỗi khi gộp danh mục: ' . $e->getMessage());
        }
    }
}

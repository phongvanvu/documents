<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\DocumentRelation;
use Illuminate\Http\Request;

class DocumentRelationController extends Controller
{
    public function store(Request $request, $documentId)
    {
        $validated = $request->validate([
            'related_document_id' => 'required|different:document_id|exists:documents,id',
            'relation_type' => 'required|string|max:50',
        ], [
            'related_document_id.required' => 'Vui lòng chọn văn bản liên quan.',
            'related_document_id.different' => 'Không thể liên kết văn bản với chính nó.',
            'relation_type.required' => 'Vui lòng chọn loại quan hệ.',
        ]);

        $exists = DocumentRelation::where('document_id', $documentId)
            ->where('related_document_id', $validated['related_document_id'])
            ->exists();

        if ($exists) {
            return back()->with('error', 'Mối liên kết giữa 2 văn bản này đã tồn tại.');
        }

        DocumentRelation::create([
            'document_id' => $documentId,
            'related_document_id' => $validated['related_document_id'],
            'relation_type' => $validated['relation_type'],
        ]);

        log_activity("Thêm liên kết văn bản ID: {$documentId} -> {$validated['related_document_id']}");

        return back()->with('success', 'Đã thêm mối liên kết văn bản thành công!');
    }

    public function destroy($id)
    {
        $relation = DocumentRelation::findOrFail($id);
        $relation->delete();

        return back()->with('success', 'Đã xóa mối liên kết văn bản.');
    }

    // AJAX Tìm kiếm văn bản để liên kết
    public function search(Request $request)
    {
        $term = trim($request->get('q', ''));
        $currentId = (int) $request->get('current_id', 0);

        if (mb_strlen($term, 'UTF-8') < 2) {
            return response()->json([]);
        }

        $docs = Document::select('id', 'document_number', 'content', 'issued_date')
            ->where('id', '!=', $currentId)
            ->where(function ($q) use ($term) {
                $q->where('document_number', 'like', "%{$term}%")
                  ->orWhere('content', 'like', "%{$term}%");
            })
            ->take(10)
            ->get();

        return response()->json($docs);
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\DocumentVersion;
use Illuminate\Http\Request;

class DocumentVersionController extends Controller
{
    public function store(Request $request, $documentId)
    {
        $document = Document::findOrFail($documentId);

        $validated = $request->validate([
            'version_number' => 'required|string|max:50',
            'change_log' => 'nullable|string',
            'version_file' => 'required|file|mimes:pdf,doc,docx,xls,xlsx,png,jpg,jpeg|max:122880',
        ]);

        $file = $request->file('version_file');
        $uploadSubdir = 'uploads/versions/' . date('Y/m');
        $targetDir = public_path($uploadSubdir);

        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0755, true);
        }

        $safeName = time() . '_' . bin2hex(random_bytes(4)) . '_' . sanitize_upload_filename($file->getClientOriginalName());
        $file->move($targetDir, $safeName);
        $filePath = $uploadSubdir . '/' . $safeName;

        $userId = auth()->id() ?? 1;

        $version = DocumentVersion::create([
            'document_id' => $document->id,
            'version_number' => $validated['version_number'],
            'file_path' => $filePath,
            'content_summary' => $document->content,
            'change_log' => $validated['change_log'] ?? $request->input('change_summary') ?? '',
            'created_by' => $userId,
            'uploaded_by' => $userId,
            'created_at' => now(),
        ]);

        log_activity("Tải lên phiên bản {$version->version_number} cho VB ID: {$document->id}");

        return back()->with('success', 'Đã lưu phiên bản mới thành công!');
    }

    public function destroy($id)
    {
        $version = DocumentVersion::findOrFail($id);
        $docId = $version->document_id;
        $verNum = $version->version_number;

        if (!empty($version->file_path) && file_exists(public_path($version->file_path))) {
            @unlink(public_path($version->file_path));
        }

        $version->delete();
        log_activity("Xóa phiên bản {$verNum} của VB ID: {$docId}");

        return back()->with('success', 'Đã xóa bản ghi phiên bản.');
    }
}

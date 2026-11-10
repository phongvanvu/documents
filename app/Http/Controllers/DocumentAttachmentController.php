<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\DocumentAttachment;
use Illuminate\Http\Request;
use ZipArchive;

class DocumentAttachmentController extends Controller
{
    public function store(Request $request, $documentId)
    {
        $document = Document::findOrFail($documentId);

        $validated = $request->validate([
            'attachment_title' => 'required|string|max:255',
            'attachment_type' => 'required|string|max:100',
            'attachment_file' => 'required|file|max:122880',
            'note' => 'nullable|string',
        ], [
            'attachment_title.required' => 'Vui lòng nhập tên tài liệu kèm theo.',
            'attachment_file.required' => 'Vui lòng chọn tệp tải lên.',
            'attachment_file.max' => 'Dung lượng tệp tối đa là 120MB.',
        ]);

        $file = $request->file('attachment_file');
        $originalName = $file->getClientOriginalName();
        $fileExt = strtolower($file->getClientOriginalExtension());
        $fileSize = $file->getSize();

        $uploadSubdir = 'uploads/document_attachments/' . date('Y/m');
        $targetDir = public_path($uploadSubdir);

        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0755, true);
        }

        $safeName = time() . '_' . bin2hex(random_bytes(4)) . '_' . sanitize_upload_filename($originalName);
        $file->move($targetDir, $safeName);
        $filePath = $uploadSubdir . '/' . $safeName;

        $attachment = DocumentAttachment::create([
            'document_id' => $document->id,
            'attachment_name' => $originalName,
            'attachment_title' => $validated['attachment_title'],
            'attachment_type' => $validated['attachment_type'],
            'attachment_path' => $filePath,
            'original_name' => $originalName,
            'file_ext' => $fileExt,
            'file_size' => $fileSize,
            'note' => $validated['note'] ?? null,
            'uploaded_by' => auth()->id() ?? 1,
            'created_at' => now(),
        ]);

        log_activity("Thêm tài liệu kèm theo '{$attachment->attachment_title}' cho VB ID: {$document->id}");

        return back()->with('success', 'Đã thêm tài liệu kèm theo thành công!');
    }

    public function destroy($id)
    {
        $attachment = DocumentAttachment::findOrFail($id);
        $docId = $attachment->document_id;
        $title = $attachment->attachment_title;

        if (!empty($attachment->attachment_path) && file_exists(public_path($attachment->attachment_path))) {
            @unlink(public_path($attachment->attachment_path));
        }

        $attachment->delete();
        log_activity("Xóa tài liệu kèm theo '{$title}' của VB ID: {$docId}");

        return back()->with('success', 'Đã xóa tài liệu đính kèm.');
    }

    public function download($id)
    {
        $attachment = DocumentAttachment::findOrFail($id);

        if (!$attachment->file_exists) {
            return back()->with('error', 'Tệp tin không tồn tại trên máy chủ.');
        }

        $fullPath = public_path($attachment->attachment_path);
        if (!file_exists($fullPath)) {
            $fullPath = base_path($attachment->attachment_path);
        }

        return response()->download($fullPath, $attachment->original_name ?? basename($fullPath));
    }

    public function downloadAllZip($documentId)
    {
        $document = Document::with('attachments')->findOrFail($documentId);

        if ($document->attachments->isEmpty() && empty($document->file_path)) {
            return back()->with('error', 'Văn bản không có tệp đính kèm nào.');
        }

        $zipFileName = 'van_ban_' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $document->document_number) . '_dinh_kem.zip';
        $tempZipPath = storage_path('app/' . $zipFileName);

        $zip = new ZipArchive();
        if ($zip->open($tempZipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            return back()->with('error', 'Không thể khởi tạo tệp ZIP.');
        }

        // Tệp chính
        if ($document->file_exists) {
            $mainPath = public_path($document->file_path);
            if (!file_exists($mainPath)) $mainPath = base_path($document->file_path);
            if (file_exists($mainPath)) {
                $zip->addFile($mainPath, 'Tep_chinh_' . basename($mainPath));
            }
        }

        // Các tài liệu kèm theo
        foreach ($document->attachments as $idx => $att) {
            if ($att->file_exists) {
                $attPath = public_path($att->attachment_path);
                if (!file_exists($attPath)) $attPath = base_path($att->attachment_path);
                if (file_exists($attPath)) {
                    $entryName = ($idx + 1) . '_' . ($att->original_name ?: basename($attPath));
                    $zip->addFile($attPath, $entryName);
                }
            }
        }

        $zip->close();

        log_activity("Tải toàn bộ tệp ZIP đính kèm của VB ID: {$document->id}");

        return response()->download($tempZipPath, $zipFileName)->deleteFileAfterSend(true);
    }
}

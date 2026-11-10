<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\DocumentType;
use App\Models\IssuingAuthority;
use App\Models\Mlns;
use Carbon\Carbon;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Writer\Csv;

class ReportController extends Controller
{
    // Báo cáo & Thống kê tổng hợp
    public function index(Request $request)
    {
        $year = (int) $request->get('year', Carbon::now()->year);

        // Thống kê theo loại văn bản
        $byType = Document::select('document_type_id', \DB::raw('count(*) as total'))
            ->with('documentType')
            ->whereYear('issued_date', $year)
            ->groupBy('document_type_id')
            ->orderByDesc('total')
            ->get();

        // Thống kê theo cơ quan ban hành (Top 10)
        $byAuthority = Document::select('authority_id', \DB::raw('count(*) as total'))
            ->with('issuingAuthority')
            ->whereYear('issued_date', $year)
            ->groupBy('authority_id')
            ->orderByDesc('total')
            ->take(10)
            ->get();

        // Thống kê theo trạng thái
        $byStatus = Document::select('status', \DB::raw('count(*) as total'))
            ->whereYear('issued_date', $year)
            ->groupBy('status')
            ->get();

        $availableYears = Document::selectRaw('YEAR(issued_date) as yr')
            ->whereNotNull('issued_date')
            ->distinct()
            ->orderBy('yr', 'desc')
            ->pluck('yr')
            ->filter();

        return view('reports.index', compact('byType', 'byAuthority', 'byStatus', 'year', 'availableYears'));
    }

    // Sổ đăng ký theo dõi văn bản pháp quy (Xem & In)
    public function printRegister(Request $request)
    {
        $filters = $request->only(['from_date', 'to_date', 'type_id', 'authority_id', 'status']);

        $query = Document::with(['documentType', 'issuingAuthority'])
            ->filter($filters)
            ->orderBy('issued_date', 'asc');

        $documents = $query->get();

        $documentTypes = DocumentType::orderBy('type_name')->get();
        $authorities = IssuingAuthority::orderBy('authority_name')->get();

        return view('reports.print_register', compact('documents', 'documentTypes', 'authorities', 'filters'));
    }

    // Xuất danh mục văn bản ra Excel / CSV
    public function export(Request $request)
    {
        $format = $request->get('format', 'xlsx'); // 'xlsx' hoặc 'csv'
        $filters = $request->only(['q', 'type_id', 'authority_id', 'status', 'year', 'from_date', 'to_date']);

        $documents = Document::with(['documentType', 'issuingAuthority'])
            ->filter($filters)
            ->orderBy('issued_date', 'desc')
            ->get();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Danh muc van ban');

        // Header
        $headers = [
            'STT', 'Số / Ký hiệu', 'Ngày ban hành', 'Loại văn bản',
            'Cơ quan ban hành', 'Trích yếu nội dung', 'Ngày có hiệu lực',
            'Ngày hết hạn', 'Trạng thái', 'Đường dẫn tệp'
        ];
        $sheet->fromArray($headers, null, 'A1');

        $rows = [];
        foreach ($documents as $idx => $doc) {
            $rows[] = [
                $idx + 1,
                $doc->document_number,
                $doc->issued_date ? Carbon::parse($doc->issued_date)->format('d/m/Y') : '',
                $doc->documentType?->type_name ?? '',
                $doc->issuingAuthority?->authority_name ?? '',
                $doc->content,
                $doc->effective_date ? Carbon::parse($doc->effective_date)->format('d/m/Y') : '',
                $doc->expired_date ? Carbon::parse($doc->expired_date)->format('d/m/Y') : '',
                $doc->status,
                $doc->file_path ? url($doc->file_path) : '',
            ];
        }

        if (!empty($rows)) {
            $sheet->fromArray($rows, null, 'A2');
        }

        // Tự động căn độ rộng cột
        foreach (range('A', 'J') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $filename = 'danh_muc_van_ban_' . date('Ymd_His') . '.' . $format;
        $tempPath = storage_path('app/' . $filename);

        if ($format === 'csv') {
            $writer = new Csv($spreadsheet);
            $writer->setUseBOM(true); // Để Excel mở CSV tiếng Việt không lỗi font
        } else {
            $writer = new Xlsx($spreadsheet);
        }

        $writer->save($tempPath);
        log_activity("Xuất danh mục văn bản ({$documents->count()} bản ghi) định dạng {$format}");

        return response()->download($tempPath, $filename)->deleteFileAfterSend(true);
    }

    // Nhập văn bản từ CSV
    public function showImport()
    {
        return view('reports.import');
    }

    public function processImport(Request $request)
    {
        $request->validate([
            'csv_file' => 'required|file|mimes:csv,txt|max:10240',
        ]);

        $file = $request->file('csv_file');
        $handle = fopen($file->getRealPath(), 'r');

        if (!$handle) {
            return back()->with('error', 'Không thể đọc tệp CSV.');
        }

        // Bỏ dòng tiêu đề
        $header = fgetcsv($handle);
        $imported = 0;
        $skipped = 0;

        while (($row = fgetcsv($handle)) !== false) {
            if (empty($row[0]) || empty($row[1])) {
                $skipped++;
                continue;
            }

            $docNumber = trim($row[0]);
            $issuedDate = !empty($row[1]) ? date('Y-m-d', strtotime(str_replace('/', '-', trim($row[1])))) : null;
            $typeName = trim($row[2] ?? 'Công văn');
            $authName = trim($row[3] ?? 'Khác');
            $content = trim($row[4] ?? '');

            if (empty($content) || !$issuedDate) {
                $skipped++;
                continue;
            }

            // Tìm hoặc tạo loại văn bản
            $type = DocumentType::firstOrCreate(['type_name' => $typeName]);
            // Tìm hoặc tạo cơ quan
            $auth = IssuingAuthority::firstOrCreate(['authority_name' => $authName]);

            Document::create([
                'document_number' => $docNumber,
                'issued_date' => $issuedDate,
                'document_type_id' => $type->id,
                'authority_id' => $auth->id,
                'content' => $content,
                'status' => 'Còn hiệu lực',
            ]);

            $imported++;
        }

        fclose($handle);
        log_activity("Nhập danh mục từ CSV: {$imported} thành công, {$skipped} bỏ qua");

        return back()->with('success', "Nhập dữ liệu thành công! Đã thêm {$imported} văn bản, bỏ qua {$skipped} dòng không hợp lệ.");
    }

    // Mục lục ngân sách (MLNS)
    public function mlns(Request $request)
    {
        $term = trim($request->get('q', ''));
        $query = Mlns::query();

        if (!empty($term)) {
            $query->where(function ($q) use ($term) {
                $q->where('ten', 'like', "%{$term}%")
                  ->orWhere('mota', 'like', "%{$term}%");
            });
        }

        $items = $query->paginate(30)->withQueryString();

        return view('reports.mlns', compact('items', 'term'));
    }
}

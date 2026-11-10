<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\SystemSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class SystemController extends Controller
{
    // Cài đặt hệ thống
    public function settings()
    {
        $settings = SystemSetting::allSettings();
        return view('system.settings', compact('settings'));
    }

    public function updateSettings(Request $request)
    {
        $data = $request->except(['_token']);

        foreach ($data as $key => $val) {
            SystemSetting::set($key, is_array($val) ? implode(',', $val) : $val);
        }

        log_activity('Cập nhật cài đặt hệ thống');

        return back()->with('success', 'Đã lưu các cài đặt hệ thống thành công!');
    }

    // Nhật ký hoạt động
    public function logs(Request $request)
    {
        $query = ActivityLog::with('user');

        if ($request->filled('q')) {
            $query->where('action', 'like', '%' . trim($request->q) . '%');
        }

        if ($request->filled('user_id')) {
            $query->where('user_id', (int) $request->user_id);
        }

        $logs = $query->latest('created_at')->paginate(30)->withQueryString();
        $users = \App\Models\User::orderBy('username')->get();

        return view('system.logs', compact('logs', 'users'));
    }

    public function clearLogs()
    {
        DB::table('logs')->truncate();
        log_activity('Xóa toàn bộ nhật ký hệ thống');

        return back()->with('success', 'Đã làm sạch toàn bộ nhật ký hệ thống.');
    }

    // Quản lý sao lưu cơ sở dữ liệu
    public function backups()
    {
        $backupDir = base_path('backups');
        if (!is_dir($backupDir)) {
            mkdir($backupDir, 0755, true);
        }

        $files = File::files($backupDir);
        $backups = [];

        foreach ($files as $file) {
            if (in_array($file->getExtension(), ['sql', 'gz', 'zip'], true)) {
                $backups[] = [
                    'name' => $file->getFilename(),
                    'path' => $file->getPathname(),
                    'size' => format_bytes($file->getSize()),
                    'modified_at' => date('d/m/Y H:i:s', $file->getMTime()),
                ];
            }
        }

        // Sắp xếp mới nhất lên đầu
        usort($backups, fn($a, $b) => strcmp($b['name'], $a['name']));

        return view('system.backups', compact('backups'));
    }

    public function createBackup()
    {
        $backupDir = base_path('backups');
        if (!is_dir($backupDir)) {
            mkdir($backupDir, 0755, true);
        }

        $filename = 'backup_documents_db_' . date('Y_m_d_His') . '.sql';
        $targetFile = $backupDir . '/' . $filename;

        // Dump MySQL tables via PHP
        $tables = DB::select('SHOW TABLES');
        $dbName = config('database.connections.mysql.database');
        $key = 'Tables_in_' . $dbName;

        $handle = fopen($targetFile, 'w');
        fwrite($handle, "-- DUMP DATABASE: {$dbName}\n-- CREATED AT: " . date('Y-m-d H:i:s') . "\n\nSET FOREIGN_KEY_CHECKS=0;\n\n");

        foreach ($tables as $t) {
            $tableName = $t->$key;
            $createTable = DB::select("SHOW CREATE TABLE `{$tableName}`");
            fwrite($handle, "DROP TABLE IF EXISTS `{$tableName}`;\n" . $createTable[0]->{'Create Table'} . ";\n\n");

            $rows = DB::table($tableName)->get();
            if ($rows->isNotEmpty()) {
                foreach ($rows as $row) {
                    $values = array_map(function ($v) {
                        return is_null($v) ? 'NULL' : "'" . addslashes($v) . "'";
                    }, (array) $row);

                    fwrite($handle, "INSERT INTO `{$tableName}` VALUES (" . implode(',', $values) . ");\n");
                }
                fwrite($handle, "\n");
            }
        }

        fwrite($handle, "SET FOREIGN_KEY_CHECKS=1;\n");
        fclose($handle);

        log_activity("Tạo bản sao lưu CSDL: {$filename}");

        return back()->with('success', "Đã tạo bản sao lưu CSDL thành công ({$filename})!");
    }

    public function downloadBackup($filename)
    {
        $file = base_path('backups/' . basename($filename));
        if (!file_exists($file)) {
            return back()->with('error', 'Tệp sao lưu không tồn tại.');
        }

        return response()->download($file);
    }

    public function deleteBackup($filename)
    {
        $file = base_path('backups/' . basename($filename));
        if (file_exists($file)) {
            @unlink($file);
            log_activity("Xóa bản sao lưu: {$filename}");
            return back()->with('success', 'Đã xóa bản sao lưu thành công.');
        }

        return back()->with('error', 'Tệp sao lưu không tồn tại.');
    }

    // Tối ưu hóa cơ sở dữ liệu
    public function optimizeDb()
    {
        $tables = DB::select('SHOW TABLES');
        $dbName = config('database.connections.mysql.database');
        $key = 'Tables_in_' . $dbName;

        $results = [];
        foreach ($tables as $t) {
            $tableName = $t->$key;
            $res = DB::select("OPTIMIZE TABLE `{$tableName}`");
            $results[] = [
                'table' => $tableName,
                'status' => $res[0]->Msg_text ?? 'OK',
            ];
        }

        log_activity('Thực hiện tối ưu hóa toàn bộ các bảng trong CSDL');

        return back()->with('success', 'Đã tối ưu hóa thành công ' . count($results) . ' bảng trong cơ sở dữ liệu!');
    }

    // Thông tin hệ thống
    public function systemInfo()
    {
        $info = [
            'php_version' => PHP_VERSION,
            'laravel_version' => app()->version(),
            'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'PHP CLI Server / Web Server',
            'mysql_version' => DB::select('SELECT VERSION() as v')[0]->v ?? 'Unknown',
            'database_name' => config('database.connections.mysql.database'),
            'upload_max_filesize' => ini_get('upload_max_filesize'),
            'post_max_size' => ini_get('post_max_size'),
            'max_execution_time' => ini_get('max_execution_time') . 's',
            'memory_limit' => ini_get('memory_limit'),
            'total_documents' => \App\Models\Document::count(),
            'total_attachments' => \App\Models\DocumentAttachment::count(),
            'total_users' => \App\Models\User::count(),
        ];

        return view('system.info', compact('info'));
    }
}

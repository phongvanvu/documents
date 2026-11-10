<?php

use App\Models\ActivityLog;
use App\Models\SystemSetting;

if (!function_exists('get_setting')) {
    function get_setting(string $key, $default = ''): string
    {
        return SystemSetting::get($key, $default) ?? $default;
    }
}

if (!function_exists('log_activity')) {
    function log_activity(string $action, ?int $userId = null): void
    {
        ActivityLog::log($action, $userId);
    }
}

if (!function_exists('remove_vietnamese_accents')) {
    function remove_vietnamese_accents(string $str): string
    {
        $chars = [
            'a' => ['á','à','ả','ã','ạ','ă','ắ','ằ','ẳ','ẵ','ặ','â','ấ','ầ','ẩ','ẫ','ậ'],
            'A' => ['Á','À','Ả','Ã','Ạ','Ă','Ắ','Ằ','Ẳ','Ẵ','Ặ','Â','Ấ','Ầ','Ẩ','Ẫ','Ậ'],
            'd' => ['đ'],
            'D' => ['Đ'],
            'e' => ['é','è','ẻ','ẽ','ẹ','ê','ế','ề','ể','ễ','ệ'],
            'E' => ['É','È','Ẻ','Ẽ','Ẹ','Ê','Ế','Ề','Ể','Ễ','Ệ'],
            'i' => ['í','ì','ỉ','ĩ','ị'],
            'I' => ['Í','Ì','Ỉ','Ĩ','Ị'],
            'o' => ['ó','ò','ỏ','õ','ọ','ô','ố','ồ','ổ','ỗ','ộ','ơ','ớ','ờ','ở','ỡ','ợ'],
            'O' => ['Ó','Ò','Ỏ','Õ','Ọ','Ô','Ố','Ồ','Ổ','Ỗ','Ộ','Ơ','Ớ','Ờ','Ở','Ỡ','Ợ'],
            'u' => ['ú','ù','ủ','ũ','ụ','ư','ứ','ừ','ử','ữ','ự'],
            'U' => ['Ú','Ù','Ủ','Ũ','Ụ','Ư','Ứ','Ừ','Ử','Ữ','Ự'],
            'y' => ['ý','ỳ','ỷ','ỹ','ỵ'],
            'Y' => ['Ý','Ỳ','Ỷ','Ỹ','Ỵ'],
        ];
        foreach ($chars as $replacement => $originals) {
            $str = str_replace($originals, $replacement, $str);
        }
        return $str;
    }
}

if (!function_exists('sanitize_upload_filename')) {
    function sanitize_upload_filename(string $filename, int $maxBaseLen = 80): string
    {
        $filename = str_replace('\\', '/', $filename);
        $cleanBase = basename($filename);

        $ext = pathinfo($cleanBase, PATHINFO_EXTENSION);
        $extPart = $ext !== '' ? '.' . strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $ext)) : '';
        $nameWithoutExt = pathinfo($cleanBase, PATHINFO_FILENAME);
        $nameWithoutExt = remove_vietnamese_accents($nameWithoutExt);
        $nameWithoutExt = preg_replace('/[^a-zA-Z0-9_-]/', '_', $nameWithoutExt);
        $nameWithoutExt = preg_replace('/_+/', '_', $nameWithoutExt);
        $nameWithoutExt = trim($nameWithoutExt, '_-');

        if ($nameWithoutExt === '') {
            $nameWithoutExt = 'document';
        }

        if (mb_strlen($nameWithoutExt, 'UTF-8') > $maxBaseLen) {
            $nameWithoutExt = mb_substr($nameWithoutExt, 0, $maxBaseLen, 'UTF-8');
            $nameWithoutExt = rtrim($nameWithoutExt, '_-');
        }

        return $nameWithoutExt . $extPart;
    }
}

if (!function_exists('format_bytes')) {
    function format_bytes(int $bytes, int $precision = 2): string
    {
        if ($bytes <= 0) return '0 B';
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $pow = floor(log($bytes, 1024));
        return round($bytes / pow(1024, $pow), $precision) . ' ' . ($units[$pow] ?? 'B');
    }
}

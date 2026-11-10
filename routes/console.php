<?php

use App\Models\Document;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Lệnh quét tự động cập nhật văn bản hết hạn
Artisan::command('documents:check-expiry', function () {
    $count = Document::whereNotNull('expired_date')
        ->whereDate('expired_date', '<', now())
        ->where('status', '!=', 'Hết hiệu lực')
        ->update(['status' => 'Hết hiệu lực']);

    $this->info("Đã quét và cập nhật {$count} văn bản hết hạn sang trạng thái 'Hết hiệu lực'.");
})->purpose('Quét và tự động chuyển trạng thái văn bản quá hạn');

// Lên lịch chạy hàng ngày vào lúc 00:00
Schedule::command('documents:check-expiry')->daily();

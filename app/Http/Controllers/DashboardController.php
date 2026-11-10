<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Document;
use App\Models\DocumentAttachment;
use App\Models\DocumentType;
use App\Models\IssuingAuthority;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index()
    {
        $today = Carbon::today();
        $soonThreshold = Carbon::today()->addDays(30);

        // Thống kê số lượng tổng quan
        $totalDocs = Document::count();
        $activeDocs = Document::where('status', 'Còn hiệu lực')->count();
        $expiredDocs = Document::where('status', 'Hết hiệu lực')
            ->orWhere(function ($q) use ($today) {
                $q->whereNotNull('expired_date')->whereDate('expired_date', '<', $today);
            })->count();
        $expiringSoonDocs = Document::whereNotNull('expired_date')
            ->whereDate('expired_date', '>=', $today)
            ->whereDate('expired_date', '<=', $soonThreshold)
            ->count();

        $totalTypes = DocumentType::count();
        $totalAuthorities = IssuingAuthority::count();
        $totalAttachments = DocumentAttachment::count();

        // 10 văn bản mới nhất
        $recentDocs = Document::with(['documentType', 'issuingAuthority'])
            ->latest('id')
            ->take(10)
            ->get();

        // 8 hoạt động hệ thống gần nhất
        $recentLogs = ActivityLog::with('user')
            ->latest('created_at')
            ->take(8)
            ->get();

        // Dữ liệu biểu đồ theo 12 tháng gần nhất
        $monthlyLabels = [];
        $monthlyData = [];
        for ($i = 11; $i >= 0; $i--) {
            $month = Carbon::now()->subMonths($i);
            $monthlyLabels[] = $month->format('m/Y');
            $monthlyData[] = Document::whereYear('issued_date', $month->year)
                ->whereMonth('issued_date', $month->month)
                ->count();
        }

        // Dữ liệu phân bổ theo trạng thái
        $statusCounts = [
            'Còn hiệu lực' => Document::where('status', 'Còn hiệu lực')->count(),
            'Hết hiệu lực' => Document::where('status', 'Hết hiệu lực')->count(),
            'Chưa có hiệu lực' => Document::where('status', 'Chưa có hiệu lực')->count(),
            'Hết hiệu lực một phần' => Document::where('status', 'Hết hiệu lực một phần')->count(),
            'Không xác định' => Document::where('status', 'Không xác định')->count(),
        ];

        return view('dashboard', compact(
            'totalDocs',
            'activeDocs',
            'expiredDocs',
            'expiringSoonDocs',
            'totalTypes',
            'totalAuthorities',
            'totalAttachments',
            'recentDocs',
            'recentLogs',
            'monthlyLabels',
            'monthlyData',
            'statusCounts'
        ));
    }
}

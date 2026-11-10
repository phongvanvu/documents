@extends('layouts.app')

@section('title', 'Thông tin máy chủ & Hệ thống')

@section('content')
<div class="row g-4">
    <div class="col-md-6">
        <div class="card border-0 shadow-sm rounded-4 h-100">
            <div class="card-header bg-white border-bottom py-3 px-4">
                <h6 class="fw-bold mb-0"><i class="fas fa-server text-danger me-2"></i>Thông số môi trường máy chủ</h6>
            </div>
            <div class="card-body p-4">
                <ul class="list-group list-group-flush small">
                    <li class="list-group-item d-flex justify-content-between px-0">
                        <span class="text-muted">Phiên bản Laravel Framework:</span>
                        <span class="badge bg-danger">{{ $info['laravel_version'] }}</span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between px-0">
                        <span class="text-muted">Phiên bản PHP:</span>
                        <span class="fw-bold text-dark">{{ $info['php_version'] }}</span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between px-0">
                        <span class="text-muted">Cơ sở dữ liệu (MariaDB/MySQL):</span>
                        <span class="fw-bold text-dark">{{ $info['mysql_version'] }}</span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between px-0">
                        <span class="text-muted">Tên CSDL đang kết nối:</span>
                        <span class="fw-bold text-primary">{{ $info['database_name'] }}</span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between px-0">
                        <span class="text-muted">Dung lượng upload tối đa (PHP):</span>
                        <span class="fw-bold">{{ $info['upload_max_filesize'] }} (POST: {{ $info['post_max_size'] }})</span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between px-0">
                        <span class="text-muted">Giới hạn bộ nhớ PHP:</span>
                        <span class="fw-bold">{{ $info['memory_limit'] }}</span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between px-0">
                        <span class="text-muted">Thời gian thực thi tối đa:</span>
                        <span class="fw-bold">{{ $info['max_execution_time'] }}</span>
                    </li>
                </ul>
            </div>
        </div>
    </div>

    <div class="col-md-6">
        <div class="card border-0 shadow-sm rounded-4 h-100">
            <div class="card-header bg-white border-bottom py-3 px-4">
                <h6 class="fw-bold mb-0"><i class="fas fa-database text-primary me-2"></i>Tổng hợp quy mô dữ liệu</h6>
            </div>
            <div class="card-body p-4">
                <ul class="list-group list-group-flush small">
                    <li class="list-group-item d-flex justify-content-between px-0">
                        <span class="text-muted">Tổng số văn bản đang lưu:</span>
                        <span class="fw-bold text-danger fs-6">{{ number_format($info['total_documents']) }}</span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between px-0">
                        <span class="text-muted">Tổng số tệp phụ lục đính kèm:</span>
                        <span class="fw-bold text-dark fs-6">{{ number_format($info['total_attachments']) }}</span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between px-0">
                        <span class="text-muted">Số lượng tài khoản hệ thống:</span>
                        <span class="fw-bold text-dark fs-6">{{ number_format($info['total_users']) }}</span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between px-0">
                        <span class="text-muted">Web Server Software:</span>
                        <span class="text-muted text-end">{{ $info['server_software'] }}</span>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</div>
@endsection

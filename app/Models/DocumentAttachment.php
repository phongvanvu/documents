<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DocumentAttachment extends Model
{
    use HasFactory;

    protected $table = 'document_attachments';

    const UPDATED_AT = null;

    protected $fillable = [
        'document_id',
        'attachment_title',
        'attachment_type',
        'attachment_path',
        'original_name',
        'file_ext',
        'file_size',
        'note',
        'uploaded_by',
        'created_at',
    ];

    public function document()
    {
        return $this->belongsTo(Document::class, 'document_id');
    }

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function getFileUrlAttribute(): ?string
    {
        if (empty($this->attachment_path)) return null;
        return asset($this->attachment_path);
    }

    public function getFileExistsAttribute(): bool
    {
        if (empty($this->attachment_path)) return false;
        return file_exists(public_path($this->attachment_path)) || file_exists(base_path($this->attachment_path));
    }

    public function getFormattedSizeAttribute(): string
    {
        $bytes = (int) $this->file_size;
        if ($bytes <= 0) return '0 B';
        $units = ['B', 'KB', 'MB', 'GB'];
        $power = floor(log($bytes, 1024));
        return round($bytes / pow(1024, $power), 2) . ' ' . ($units[$power] ?? 'B');
    }
}

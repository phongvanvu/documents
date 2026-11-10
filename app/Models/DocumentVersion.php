<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DocumentVersion extends Model
{
    use HasFactory;

    protected $table = 'document_versions';

    const UPDATED_AT = null;

    protected $fillable = [
        'document_id',
        'version_number',
        'file_path',
        'content_summary',
        'change_log',
        'created_by',
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
        if (empty($this->file_path)) return null;
        return asset($this->file_path);
    }
}

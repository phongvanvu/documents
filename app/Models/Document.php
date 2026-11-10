<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Document extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'documents';

    const UPDATED_AT = null; // Bảng documents hiện tại chỉ có created_at và deleted_at

    protected $fillable = [
        'document_number',
        'issued_date',
        'content',
        'file_path',
        'document_type_id',
        'effective_date',
        'expired_date',
        'status',
        'authority_id',
        'is_favorite',
        'personal_note',
        'tags',
        'deleted_at',
    ];

    protected function casts(): array
    {
        return [
            'issued_date' => 'date:Y-m-d',
            'effective_date' => 'date:Y-m-d',
            'expired_date' => 'date:Y-m-d',
            'is_favorite' => 'boolean',
            'created_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    // Quan hệ loại văn bản
    public function documentType()
    {
        return $this->belongsTo(DocumentType::class, 'document_type_id');
    }

    // Quan hệ cơ quan ban hành
    public function issuingAuthority()
    {
        return $this->belongsTo(IssuingAuthority::class, 'authority_id');
    }

    // Quan hệ tài liệu đính kèm
    public function attachments()
    {
        return $this->hasMany(DocumentAttachment::class, 'document_id');
    }

    // Quan hệ các phiên bản
    public function versions()
    {
        return $this->hasMany(DocumentVersion::class, 'document_id')->orderBy('id', 'desc');
    }

    // Các văn bản liên quan (xuất phát từ văn bản này)
    public function relations()
    {
        return $this->hasMany(DocumentRelation::class, 'document_id');
    }

    // Các văn bản được liên kết tới văn bản này
    public function inverseRelations()
    {
        return $this->hasMany(DocumentRelation::class, 'related_document_id');
    }

    // Lấy tất cả văn bản liên quan cả 2 chiều
    public function allRelatedDocuments()
    {
        $direct = $this->relations()->with('relatedDocument')->get()->map(function ($rel) {
            $doc = $rel->relatedDocument;
            if ($doc) $doc->relation_type_label = $rel->relation_type;
            return $doc;
        })->filter();

        $inverse = $this->inverseRelations()->with('document')->get()->map(function ($rel) {
            $doc = $rel->document;
            if ($doc) $doc->relation_type_label = 'Được ' . strtolower($rel->relation_type);
            return $doc;
        })->filter();

        return $direct->merge($inverse)->unique('id');
    }

    // Kiểm tra văn bản Mật
    public function getIsSecretAttribute(): bool
    {
        $num = mb_strtoupper($this->document_number ?? '', 'UTF-8');
        return strpos($num, '-M') !== false 
            || strpos($num, '/M') !== false 
            || strpos($num, ' M ') !== false 
            || strpos($num, 'MẬT') !== false;
    }

    // Kiểm tra tệp đính kèm có tồn tại trên đĩa không
    public function getFileExistsAttribute(): bool
    {
        if (empty($this->file_path)) return false;
        return file_exists(public_path($this->file_path)) || file_exists(base_path($this->file_path));
    }

    // Đường dẫn truy cập URL của tệp
    public function getFileUrlAttribute(): ?string
    {
        if (empty($this->file_path)) return null;
        return asset($this->file_path);
    }

    // Kiểm tra văn bản đã hết hạn chưa
    public function getIsExpiredAttribute(): bool
    {
        if (empty($this->expired_date)) return false;
        return Carbon::parse($this->expired_date)->isPast();
    }

    // Lấy class badge hiển thị trạng thái
    public function getStatusBadgeClassAttribute(): string
    {
        return match ($this->status) {
            'Còn hiệu lực' => 'badge bg-success',
            'Hết hiệu lực' => 'badge bg-danger',
            'Chưa có hiệu lực' => 'badge bg-warning text-dark',
            'Hết hiệu lực một phần' => 'badge bg-secondary',
            default => 'badge bg-light text-dark border',
        };
    }

    // Scope lọc dữ liệu đa năng
    public function scopeFilter(Builder $query, array $filters): Builder
    {
        // Tìm kiếm theo từ khóa (Số ký hiệu hoặc trích yếu nội dung)
        if (!empty($filters['q'])) {
            $term = trim($filters['q']);
            $query->where(function ($q) use ($term) {
                $q->where('document_number', 'like', "%{$term}%")
                  ->orWhere('content', 'like', "%{$term}%");
            });
        }

        // Lọc theo Loại văn bản
        if (!empty($filters['type_id'])) {
            $query->where('document_type_id', (int) $filters['type_id']);
        }

        // Lọc theo Cơ quan ban hành
        if (!empty($filters['authority_id'])) {
            $query->where('authority_id', (int) $filters['authority_id']);
        }

        // Lọc theo Trạng thái hiệu lực
        if (!empty($filters['status']) && $filters['status'] !== 'all') {
            $query->where('status', $filters['status']);
        }

        // Lọc theo Năm ban hành
        if (!empty($filters['year'])) {
            $query->whereYear('issued_date', (int) $filters['year']);
        }

        // Lọc theo khoảng ngày ban hành
        if (!empty($filters['from_date'])) {
            $query->whereDate('issued_date', '>=', $filters['from_date']);
        }
        if (!empty($filters['to_date'])) {
            $query->whereDate('issued_date', '<=', $filters['to_date']);
        }

        // Lọc văn bản yêu thích
        if (isset($filters['favorite']) && $filters['favorite'] == 1) {
            $query->where('is_favorite', 1);
        }

        // Lọc văn bản sắp hết hạn
        if (!empty($filters['expiring_soon'])) {
            $days = (int) $filters['expiring_soon'];
            $query->whereNotNull('expired_date')
                  ->whereDate('expired_date', '>=', now())
                  ->whereDate('expired_date', '<=', now()->addDays($days));
        }

        return $query;
    }
}

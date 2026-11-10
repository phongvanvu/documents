<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DocumentRelation extends Model
{
    use HasFactory;

    protected $table = 'document_relations';

    const UPDATED_AT = null;

    protected $fillable = [
        'document_id',
        'related_document_id',
        'relation_type',
        'created_at',
    ];

    public function document()
    {
        return $this->belongsTo(Document::class, 'document_id');
    }

    public function relatedDocument()
    {
        return $this->belongsTo(Document::class, 'related_document_id');
    }
}

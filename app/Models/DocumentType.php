<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DocumentType extends Model
{
    use HasFactory;

    protected $table = 'document_types';

    public $timestamps = false;

    protected $fillable = [
        'type_name',
    ];

    public function documents()
    {
        return $this->hasMany(Document::class, 'document_type_id');
    }
}

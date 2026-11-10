<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class IssuingAuthority extends Model
{
    use HasFactory;

    protected $table = 'issuing_authorities';

    public $timestamps = false;

    protected $fillable = [
        'authority_name',
    ];

    public function documents()
    {
        return $this->hasMany(Document::class, 'authority_id');
    }
}

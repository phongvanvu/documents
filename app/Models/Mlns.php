<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Mlns extends Model
{
    use HasFactory;

    protected $table = 'mlns';

    public $timestamps = false;

    protected $fillable = [
        'ten',
        'mota',
    ];
}

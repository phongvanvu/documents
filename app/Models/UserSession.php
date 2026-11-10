<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserSession extends Model
{
    protected $table = 'user_sessions';

    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'session_token',
        'ip_address',
        'user_agent',
        'last_activity',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}

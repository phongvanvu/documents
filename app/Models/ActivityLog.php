<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ActivityLog extends Model
{
    use HasFactory;

    protected $table = 'logs';

    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'action',
        'created_at',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public static function log(string $action, ?int $userId = null): void
    {
        try {
            self::create([
                'user_id' => $userId ?? auth()->id() ?? 0,
                'action' => $action,
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            \Log::error('ActivityLog error: ' . $e->getMessage());
        }
    }
}

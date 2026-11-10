<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $table = 'users';

    protected $fillable = [
        'username',
        'full_name',
        'email',
        'password',
        'role',
        'avatar',
        'remember_token',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    // Quan hệ với Activity Logs
    public function logs()
    {
        return $this->hasMany(ActivityLog::class, 'user_id');
    }

    // Quan hệ với Document Attachments
    public function attachments()
    {
        return $this->hasMany(DocumentAttachment::class, 'uploaded_by');
    }

    // Quan hệ với User Sessions
    public function activeSessions()
    {
        return $this->hasMany(UserSession::class, 'user_id');
    }

    // Role helpers
    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isEditor(): bool
    {
        return in_array($this->role, ['admin', 'editor'], true);
    }

    public function isViewer(): bool
    {
        return in_array($this->role, ['admin', 'editor', 'viewer'], true);
    }

    public function hasRole(string|array $roles): bool
    {
        if ($this->isAdmin()) return true;
        $roles = (array) $roles;
        return in_array($this->role, $roles, true);
    }

    // Kiểm tra quyền theo bảng role_permissions
    public function hasPermission(string $permKey): bool
    {
        if ($this->isAdmin()) return true;

        static $cachedPermissions = [];

        if (!isset($cachedPermissions[$this->role])) {
            $perms = DB::table('role_permissions')
                ->where('role', $this->role)
                ->pluck('perm_key')
                ->toArray();
            $cachedPermissions[$this->role] = $perms;
        }

        return in_array($permKey, $cachedPermissions[$this->role], true);
    }

    // Accessor cho ảnh đại diện
    public function getAvatarUrlAttribute(): string
    {
        if (!empty($this->avatar) && file_exists(public_path($this->avatar))) {
            return asset($this->avatar);
        }
        return asset('uploads/avatars/default.jpg');
    }
}

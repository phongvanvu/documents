<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class SystemSetting extends Model
{
    protected $table = 'system_settings';

    protected $primaryKey = 'setting_key';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $fillable = [
        'setting_key',
        'setting_value',
    ];

    public static function get(string $key, $default = null): ?string
    {
        try {
            return Cache::rememberForever("sys_setting_{$key}", function () use ($key, $default) {
                $record = self::find($key);
                return $record ? $record->setting_value : $default;
            });
        } catch (\Throwable $e) {
            return $default;
        }
    }

    public static function set(string $key, ?string $value): void
    {
        self::updateOrCreate(
            ['setting_key' => $key],
            ['setting_value' => $value]
        );
        Cache::forget("sys_setting_{$key}");
        Cache::forget("all_sys_settings");
    }

    public static function allSettings(): array
    {
        try {
            return Cache::rememberForever('all_sys_settings', function () {
                return self::pluck('setting_value', 'setting_key')->toArray();
            });
        } catch (\Throwable $e) {
            return [];
        }
    }
}

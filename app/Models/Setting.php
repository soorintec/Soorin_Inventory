<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Setting extends Model
{
    use HasFactory;

    protected $fillable = ['key', 'value', 'group', 'type'];

    public static function get(string $key, mixed $default = null): mixed
    {
        return Cache::rememberForever("setting.$key", function () use ($key, $default) {
            $setting = self::where('key', $key)->first();

            if (! $setting) {
                return $default;
            }

            return match ($setting->type) {
                'bool'  => filter_var($setting->value, FILTER_VALIDATE_BOOLEAN),
                'int'   => (int) $setting->value,
                default => $setting->value,
            };
        });
    }

    public static function set(string $key, mixed $value, string $group = 'general', string $type = 'string'): void
    {
        self::updateOrCreate(
            ['key' => $key],
            ['value' => is_bool($value) ? ($value ? '1' : '0') : (string) $value, 'group' => $group, 'type' => $type],
        );

        Cache::forget("setting.$key");
    }

    protected static function booted(): void
    {
        static::saved(fn (Setting $s) => Cache::forget("setting.{$s->key}"));
        static::deleted(fn (Setting $s) => Cache::forget("setting.{$s->key}"));
    }
}

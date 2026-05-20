<?php

namespace App\Services\System;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;

class SettingService
{
    protected const CACHE_KEY = 'settings_cache';

    public static function get($key, $default = null)
    {
        $settings = Cache::remember(self::CACHE_KEY, 3600, function () {
            return Setting::all()->pluck('value', 'key')->toArray();
        });

        return $settings[$key] ?? $default;
    }

    public static function set($key, $value)
    {
        Setting::updateOrCreate(['key' => $key], ['value' => $value]);
        Cache::forget(self::CACHE_KEY);
    }

    public static function getNumber($key, $default = 0)
    {
        return (float) self::get($key, $default);
    }

    public static function getBool($key, $default = false)
    {
        return (bool) self::get($key, $default);
    }
}

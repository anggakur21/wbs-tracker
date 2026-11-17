<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

/** App-wide key/value settings. Values are encrypted at rest (they include OAuth tokens). */
class Setting extends Model
{
    protected $primaryKey = 'key';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['key', 'value'];

    public static function get(string $key, mixed $default = null): mixed
    {
        $raw = static::query()->whereKey($key)->value('value');

        return $raw === null ? $default : json_decode(Crypt::decryptString($raw), true);
    }

    public static function put(string $key, mixed $value): void
    {
        static::query()->updateOrCreate(['key' => $key], ['value' => Crypt::encryptString(json_encode($value))]);
    }

    public static function forget(string ...$keys): void
    {
        static::query()->whereIn('key', $keys)->delete();
    }
}

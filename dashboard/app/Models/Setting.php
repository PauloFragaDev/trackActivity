<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Key-value store sencillo para preferencias single-user (duración de ciclos
 * de pomodoro, daily focus goal, etc.). El valor se guarda como JSON para
 * admitir números, strings o arrays sin tocar el esquema.
 */
class Setting extends Model
{
    protected $fillable = ['key', 'value'];

    /** Lee una clave; si no existe devuelve $default. */
    public static function get(string $key, mixed $default = null): mixed
    {
        $row = static::query()->where('key', $key)->first();
        if (! $row) {
            return $default;
        }
        $decoded = json_decode((string) $row->value, true);
        return $decoded === null && $row->value !== 'null' ? $default : $decoded;
    }

    /** Inserta o actualiza una clave. */
    public static function set(string $key, mixed $value): void
    {
        static::query()->updateOrCreate(
            ['key' => $key],
            ['value' => json_encode($value, JSON_UNESCAPED_UNICODE)],
        );
    }

    /** Lee varias claves de golpe, con defaults. */
    public static function many(array $defaults): array
    {
        $rows = static::query()
            ->whereIn('key', array_keys($defaults))
            ->pluck('value', 'key')
            ->toArray();

        $out = $defaults;
        foreach ($rows as $key => $raw) {
            $decoded = json_decode((string) $raw, true);
            if ($decoded !== null || $raw === 'null') {
                $out[$key] = $decoded;
            }
        }
        return $out;
    }
}

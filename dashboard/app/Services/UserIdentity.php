<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Str;

/**
 * Identidad del usuario de esta instalación (single-user).
 *
 * - `name`: visible y editable en Configuración. Es lo único que pone el usuario.
 * - `token`: UUID estable por instalación, generado automáticamente la primera
 *   vez que se lee. Invisible para el usuario; identifica al autor de forma
 *   estable (útil para cuando los comentarios se sincronicen entre sistemas).
 *
 * Ambos viven en el singleton `Setting` (claves `user.name` / `user.token`).
 */
class UserIdentity
{
    public static function name(): string
    {
        return trim((string) (Setting::get('user.name') ?? ''));
    }

    public static function setName(string $name): void
    {
        Setting::set('user.name', trim($name));
    }

    /** UUID estable de esta instalación; se genera y persiste si no existe. */
    public static function token(): string
    {
        $token = Setting::get('user.token');
        if (! is_string($token) || $token === '') {
            $token = (string) Str::uuid();
            Setting::set('user.token', $token);
        }

        return $token;
    }
}

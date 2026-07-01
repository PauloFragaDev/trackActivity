<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Crea/actualiza las cuentas de login del Kanban público desde variables de
 * entorno — nunca hardcodeadas — para poder recrearlas en cada deploy sin
 * guardar contraseñas reales en el repo.
 */
class TeamUsersSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([1, 2, 3] as $n) {
            $email = env("TEAM_USER_{$n}_EMAIL");
            if (! $email) {
                continue;
            }

            $attributes = [
                'name'           => env("TEAM_USER_{$n}_NAME", $email),
                'team_member_id' => env("TEAM_USER_{$n}_MEMBER_ID") ?: null,
            ];

            // El cast `hashed` de User::password genera un salt nuevo en cada
            // hash, así que asignar siempre el password en texto plano marca
            // el atributo como "dirty" en cada ejecución aunque no haya
            // cambiado, forzando un UPDATE real en cada boot del contenedor.
            // Comparamos contra el hash ya guardado y solo incluimos la clave
            // `password` cuando de verdad hace falta reescribirlo.
            $plainPassword = env("TEAM_USER_{$n}_PASSWORD");
            $existingUser  = User::where('email', $email)->first();

            $passwordUnchanged = $existingUser
                && is_string($plainPassword)
                && Hash::check($plainPassword, $existingUser->password);

            if (! $passwordUnchanged) {
                $attributes['password'] = $plainPassword;
            }

            User::updateOrCreate(['email' => $email], $attributes);
        }
    }
}

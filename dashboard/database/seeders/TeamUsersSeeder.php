<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

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

            User::updateOrCreate(
                ['email' => $email],
                [
                    'name'           => env("TEAM_USER_{$n}_NAME", $email),
                    'password'       => env("TEAM_USER_{$n}_PASSWORD"),
                    'team_member_id' => env("TEAM_USER_{$n}_MEMBER_ID") ?: null,
                ]
            );
        }
    }
}

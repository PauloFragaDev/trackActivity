<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Modelo base para entidades del equipo. Usa la conexión 'supabase'.
 */
abstract class TeamModel extends Model
{
    protected $connection = 'supabase';
}

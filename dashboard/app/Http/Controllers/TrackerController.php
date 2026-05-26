<?php

namespace App\Http\Controllers;

use App\Services\TrackerManager;
use Illuminate\Http\RedirectResponse;

/**
 * Botón "Iniciar / Detener tracker" del sidebar.
 */
class TrackerController extends Controller
{
    public function toggle(TrackerManager $tracker): RedirectResponse
    {
        try {
            if ($tracker->status()['running']) {
                $tracker->stop();
                return back()->with('status', 'Tracker detenido.');
            }

            $tracker->start();

            return back()->with('status', 'Tracker iniciado.');
        } catch (\Throwable $e) {
            return back()->with('status', 'No se pudo controlar el tracker: ' . $e->getMessage());
        }
    }
}

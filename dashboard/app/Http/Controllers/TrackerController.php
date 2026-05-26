<?php

namespace App\Http\Controllers;

use App\Services\SchedulerManager;
use App\Services\TrackerManager;
use Illuminate\Http\RedirectResponse;

/**
 * Botón "Iniciar / Detener tracker" del sidebar. Orquesta dos procesos:
 * el daemon Python (captura eventos) y el scheduler de Laravel (los agrega
 * a bloques y dispara los demás jobs).
 */
class TrackerController extends Controller
{
    public function toggle(TrackerManager $tracker, SchedulerManager $scheduler): RedirectResponse
    {
        $running = $tracker->status()['running'] || $scheduler->status()['running'];

        try {
            if ($running) {
                $tracker->stop();
                $scheduler->stop();

                return back()->with('status', 'Tracking detenido.');
            }

            $tracker->start();
            try {
                $scheduler->start();
            } catch (\Throwable $e) {
                // Si el scheduler falla, paramos el daemon para no dejar medio sistema en marcha.
                $tracker->stop();
                throw $e;
            }

            return back()->with('status', 'Tracking iniciado (daemon + scheduler).');
        } catch (\Throwable $e) {
            return back()->with('status', 'No se pudo controlar el tracking: ' . $e->getMessage());
        }
    }
}

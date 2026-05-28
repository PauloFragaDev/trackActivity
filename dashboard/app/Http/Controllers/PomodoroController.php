<?php

namespace App\Http\Controllers;

use App\Services\PomodoroService;
use Illuminate\View\View;

/**
 * Página única del Pomodoro. El estado del timer vive en el navegador
 * (localStorage); este controller solo entrega la config inicial para que
 * resources/js/pomodoro.js sepa cuánto dura cada fase.
 */
class PomodoroController extends Controller
{
    public function __construct(private readonly PomodoroService $pomodoro) {}

    public function index(): View
    {
        return view('pomodoro.index', [
            'config' => $this->pomodoro->currentConfig(),
        ]);
    }
}

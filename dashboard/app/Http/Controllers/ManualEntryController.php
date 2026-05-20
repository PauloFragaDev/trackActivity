<?php

namespace App\Http\Controllers;

use App\Enums\EntryKind;
use App\Models\ManualEntry;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * CRUD de entradas manuales: tramos de tiempo (reuniones, correcciones de
 * horas) que el usuario añade a mano desde la vista de día o el calendario.
 *
 * El formulario trabaja en hora local (`tracker.display_timezone`); aquí se
 * convierte a UTC, que es como se persiste todo en la BBDD.
 */
class ManualEntryController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatedData($request);
        [$startUtc, $endUtc] = $this->resolveRange($data);

        ManualEntry::create([
            'starts_at'  => $startUtc,
            'ends_at'    => $endUtc,
            'project_id' => $data['project_id'] ?? null,
            'kind'       => $data['kind'],
            'title'      => $data['title'],
            'notes'      => $data['notes'] ?? null,
        ]);

        return $this->backTo($data, 'Entrada manual añadida.');
    }

    public function update(Request $request, ManualEntry $manualEntry): RedirectResponse
    {
        $data = $this->validatedData($request);
        [$startUtc, $endUtc] = $this->resolveRange($data);

        $manualEntry->update([
            'starts_at'  => $startUtc,
            'ends_at'    => $endUtc,
            'project_id' => $data['project_id'] ?? null,
            'kind'       => $data['kind'],
            'title'      => $data['title'],
            'notes'      => $data['notes'] ?? null,
        ]);

        return $this->backTo($data, 'Entrada manual actualizada.');
    }

    public function destroy(Request $request, ManualEntry $manualEntry): RedirectResponse
    {
        $data = $request->validate([
            'date'   => ['required', 'date_format:Y-m-d'],
            'return' => ['nullable', 'in:day,calendar'],
        ]);

        $manualEntry->delete();

        return $this->backTo($data, 'Entrada manual eliminada.');
    }

    // ──────────────────────────────────────────────

    /** @return array<string,mixed> */
    private function validatedData(Request $request): array
    {
        $data = $request->validate([
            'date'       => ['required', 'date_format:Y-m-d'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time'   => ['required', 'date_format:H:i'],
            'project_id' => ['nullable', 'integer', 'exists:projects,id'],
            'kind'       => ['required', Rule::enum(EntryKind::class)],
            'title'      => ['required', 'string', 'max:200'],
            'notes'      => ['nullable', 'string', 'max:1000'],
            'return'     => ['nullable', 'in:day,calendar'],
        ]);

        // El fin debe ser posterior al inicio (mismo día).
        if ($data['end_time'] <= $data['start_time']) {
            throw ValidationException::withMessages([
                'end_time' => 'La hora de fin debe ser posterior a la de inicio.',
            ]);
        }

        return $data;
    }

    /**
     * Combina date + start_time/end_time (hora local) y los pasa a UTC.
     *
     * @param  array<string,mixed>  $data
     * @return array{0:CarbonImmutable,1:CarbonImmutable}
     */
    private function resolveRange(array $data): array
    {
        $tz = config('tracker.display_timezone', 'UTC');

        $start = CarbonImmutable::createFromFormat('Y-m-d H:i', "{$data['date']} {$data['start_time']}", $tz);
        $end   = CarbonImmutable::createFromFormat('Y-m-d H:i', "{$data['date']} {$data['end_time']}", $tz);

        return [$start->setTimezone('UTC'), $end->setTimezone('UTC')];
    }

    /**
     * Redirige a la vista de origen (día o calendario) con un flash.
     *
     * @param  array<string,mixed>  $data
     */
    private function backTo(array $data, string $message): RedirectResponse
    {
        if (($data['return'] ?? 'day') === 'calendar') {
            $route = redirect()->route('calendar.month', ['ym' => substr($data['date'], 0, 7)]);
        } else {
            $route = redirect()->route('timeline.day', ['date' => $data['date']]);
        }

        return $route->with('status', $message);
    }
}

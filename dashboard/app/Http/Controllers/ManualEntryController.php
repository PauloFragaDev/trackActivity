<?php

namespace App\Http\Controllers;

use App\Enums\BlockStatus;
use App\Enums\EntryKind;
use App\Models\ManualEntry;
use App\Models\TimeBlock;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * CRUD de entradas manuales: tramos de tiempo (reuniones, correcciones de
 * horas) que el usuario añade a mano desde la vista de día o el calendario.
 *
 * El formulario trabaja en hora local (`tracker.display_timezone`); aquí se
 * convierte a UTC, que es como se persiste todo en la BBDD.
 *
 * Solapamientos: si la entrada pisa otra entrada manual o una sesión
 * automática, no se guarda directamente — se devuelve un aviso para que el
 * usuario confirme reemplazar (lo solapado se borra) o cancele.
 */
class ManualEntryController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatedData($request);
        [$startUtc, $endUtc] = $this->resolveRange($data);

        $overlaps = $this->findOverlaps($startUtc, $endUtc, null);
        if ($this->mustConfirm($request, $overlaps)) {
            return $this->overlapBack($request, $overlaps, route('manual-entries.store'), 'POST');
        }

        DB::transaction(function () use ($overlaps, $startUtc, $endUtc, $data) {
            $this->removeOverlapping($overlaps);
            ManualEntry::create([
                'starts_at'  => $startUtc,
                'ends_at'    => $endUtc,
                'project_id' => $data['project_id'] ?? null,
                'kind'       => $data['kind'],
                'title'      => $data['title'],
                'notes'      => $data['notes'] ?? null,
            ]);
        });

        return $this->backTo($data, 'Entrada manual añadida.');
    }

    public function update(Request $request, ManualEntry $manualEntry): RedirectResponse
    {
        $data = $this->validatedData($request);
        [$startUtc, $endUtc] = $this->resolveRange($data);

        $overlaps = $this->findOverlaps($startUtc, $endUtc, $manualEntry->id);
        if ($this->mustConfirm($request, $overlaps)) {
            return $this->overlapBack($request, $overlaps, route('manual-entries.update', $manualEntry), 'PATCH');
        }

        DB::transaction(function () use ($overlaps, $manualEntry, $startUtc, $endUtc, $data) {
            $this->removeOverlapping($overlaps);
            $manualEntry->update([
                'starts_at'  => $startUtc,
                'ends_at'    => $endUtc,
                'project_id' => $data['project_id'] ?? null,
                'kind'       => $data['kind'],
                'title'      => $data['title'],
                'notes'      => $data['notes'] ?? null,
            ]);
        });

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
    // Solapamientos
    // ──────────────────────────────────────────────

    /**
     * Entradas manuales y sesiones automáticas (time_blocks no-idle) que
     * solapan en horario con [$startUtc, $endUtc).
     *
     * @return array{manual: \Illuminate\Support\Collection, blocks: \Illuminate\Support\Collection}
     */
    private function findOverlaps(CarbonImmutable $startUtc, CarbonImmutable $endUtc, ?int $excludeId): array
    {
        $start = $startUtc->format('Y-m-d H:i:s');
        $end   = $endUtc->format('Y-m-d H:i:s');

        // Dos intervalos solapan si  a.inicio < b.fin  &&  a.fin > b.inicio.
        $manual = ManualEntry::query()
            ->when($excludeId, fn ($q) => $q->where('id', '!=', $excludeId))
            ->where('starts_at', '<', $end)
            ->where('ends_at', '>', $start)
            ->orderBy('starts_at')
            ->get();

        $blocks = TimeBlock::query()
            ->where('status', '!=', BlockStatus::Idle->value)
            ->where('starts_at', '<', $end)
            ->where('ends_at', '>', $start)
            ->get();

        return ['manual' => $manual, 'blocks' => $blocks];
    }

    /** @param array{manual:\Illuminate\Support\Collection,blocks:\Illuminate\Support\Collection} $overlaps */
    private function mustConfirm(Request $request, array $overlaps): bool
    {
        $hasOverlap = $overlaps['manual']->isNotEmpty() || $overlaps['blocks']->isNotEmpty();

        return $hasOverlap && ! $request->boolean('confirm_replace');
    }

    /**
     * Borra lo solapado. Solo se invoca cuando ya se va a guardar (sin
     * solape, o con confirm_replace): si las colecciones vienen vacías es
     * un no-op.
     *
     * @param array{manual:\Illuminate\Support\Collection,blocks:\Illuminate\Support\Collection} $overlaps
     */
    private function removeOverlapping(array $overlaps): void
    {
        $overlaps['manual']->each(fn (ManualEntry $m) => $m->delete());
        $overlaps['blocks']->each(fn (TimeBlock $b) => $b->delete());
    }

    /**
     * Vuelve atrás con el aviso de solape. El front (app.js) lo muestra con
     * SweetAlert y, si el usuario confirma, reenvía el formulario con
     * confirm_replace=1.
     *
     * @param array{manual:\Illuminate\Support\Collection,blocks:\Illuminate\Support\Collection} $overlaps
     */
    private function overlapBack(Request $request, array $overlaps, string $action, string $method): RedirectResponse
    {
        return back()->withInput()->with('overlap', [
            'message' => $this->overlapMessage($overlaps),
            'action'  => $action,
            'method'  => $method,
            'fields'  => $request->except(['_method', 'confirm_replace']),
        ]);
    }

    /** @param array{manual:\Illuminate\Support\Collection,blocks:\Illuminate\Support\Collection} $overlaps */
    private function overlapMessage(array $overlaps): string
    {
        $tz = config('tracker.display_timezone', 'UTC');
        $parts = [];

        foreach ($overlaps['manual'] as $m) {
            $parts[] = sprintf(
                '«%s» (%s–%s)',
                $m->title,
                $m->starts_at->copy()->setTimezone($tz)->format('H:i'),
                $m->ends_at->copy()->setTimezone($tz)->format('H:i'),
            );
        }

        $blocks = $overlaps['blocks']->count();
        if ($blocks > 0) {
            $parts[] = $blocks . ' ' . ($blocks === 1 ? 'bloque' : 'bloques') . ' de actividad automática';
        }

        return 'El horario indicado se solapa con: ' . implode('; ', $parts)
            . '. Reemplazar borrará lo solapado.';
    }

    // ──────────────────────────────────────────────
    // Helpers de validación / redirección
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

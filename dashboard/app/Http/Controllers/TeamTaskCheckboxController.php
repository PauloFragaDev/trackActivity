<?php

namespace App\Http\Controllers;

use App\Models\TeamTask;
use App\Models\TeamTaskCheckbox;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class TeamTaskCheckboxController extends Controller
{
    public function store(Request $request, TeamTask $teamTask): JsonResponse
    {
        $data = $request->validate(['title' => ['required', 'string', 'max:500']]);

        $checkbox = $teamTask->checkboxes()->create([
            'title'    => $data['title'],
            'checked'  => false,
            'position' => ($teamTask->checkboxes()->max('position') ?? -1) + 1,
        ]);

        return response()->json([
            'id'      => $checkbox->id,
            'title'   => $checkbox->title,
            'checked' => $checkbox->checked,
        ]);
    }

    public function update(Request $request, TeamTask $teamTask, TeamTaskCheckbox $checkbox): JsonResponse
    {
        abort_unless($checkbox->task_id === $teamTask->id, 404);
        $data = $request->validate(['checked' => ['required', 'boolean']]);
        $checkbox->update(['checked' => $data['checked']]);

        return response()->json(['ok' => true]);
    }

    public function destroy(TeamTask $teamTask, TeamTaskCheckbox $checkbox): Response
    {
        abort_unless($checkbox->task_id === $teamTask->id, 404);
        $checkbox->delete();

        return response()->noContent();
    }
}

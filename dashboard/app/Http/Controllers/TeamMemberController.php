<?php

namespace App\Http\Controllers;

use App\Models\TeamMember;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TeamMemberController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(TeamMember::orderBy('position')->get(['id', 'name', 'color']));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name'  => ['required', 'string', 'max:80'],
            'color' => ['required', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
        ]);
        $data['position'] = (TeamMember::max('position') ?? -1) + 1;
        TeamMember::create($data);

        return redirect()->route('settings.integrations')->with('status', 'Miembro añadido.');
    }

    public function update(Request $request, TeamMember $teamMember): RedirectResponse
    {
        $data = $request->validate([
            'name'  => ['required', 'string', 'max:80'],
            'color' => ['required', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
        ]);
        $teamMember->update($data);

        return redirect()->route('settings.integrations')->with('status', 'Miembro actualizado.');
    }

    public function destroy(TeamMember $teamMember): RedirectResponse
    {
        $teamMember->delete();

        return redirect()->route('settings.integrations')->with('status', 'Miembro eliminado.');
    }
}

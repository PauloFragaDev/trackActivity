<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Models\TeamMember;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TeamIdentityController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'member_id' => ['required', 'integer', 'exists:supabase.team_members,id'],
        ]);

        $member = TeamMember::findOrFail($data['member_id']);
        session([
            'team_member_id'   => $member->id,
            'team_member_name' => $member->name,
        ]);
        Setting::set('team.member_id', $member->id);

        return response()->json(['ok' => true]);
    }

    public function destroy(Request $request): JsonResponse|RedirectResponse
    {
        session()->forget(['team_member_id', 'team_member_name']);
        Setting::set('team.member_id', null);

        if ($request->expectsJson()) {
            return response()->json(['ok' => true]);
        }

        return redirect()->back()->with('status', 'Te has desvinculado del equipo en este dispositivo.');
    }
}

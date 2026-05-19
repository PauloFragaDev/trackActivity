<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

class HelpController extends Controller
{
    public function index(): View
    {
        return view('help.index', [
            'dbPath'   => config('database.connections.sqlite.database'),
            'tz'       => config('tracker.display_timezone'),
            'blockMin' => config('tracker.block_minutes'),
        ]);
    }
}

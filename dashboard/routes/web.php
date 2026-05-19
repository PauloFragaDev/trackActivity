<?php

use App\Http\Controllers\TimelineController;
use Illuminate\Support\Facades\Route;

Route::get('/',           [TimelineController::class, 'today'])->name('timeline.today');
Route::get('/day/{date}', [TimelineController::class, 'day'])
    ->where('date', '\d{4}-\d{2}-\d{2}')
    ->name('timeline.day');

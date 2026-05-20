<?php

namespace App\Models;

use App\Enums\SummaryEngine;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GeneratedSummary extends Model
{
    const CREATED_AT = 'generated_at';
    const UPDATED_AT = 'updated_at';

    protected $fillable = ['time_block_id', 'text', 'engine', 'edited_by_user', 'generated_at'];

    protected $casts = [
        'generated_at'   => 'datetime',
        'edited_by_user' => 'boolean',
        'engine'         => SummaryEngine::class,
    ];

    public function timeBlock(): BelongsTo
    {
        return $this->belongsTo(TimeBlock::class);
    }
}

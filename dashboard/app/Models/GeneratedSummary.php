<?php

namespace App\Models;

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
    ];

    public const ENGINE_TEMPLATE = 'template';
    public const ENGINE_LLM      = 'llm';

    public function timeBlock(): BelongsTo
    {
        return $this->belongsTo(TimeBlock::class);
    }
}

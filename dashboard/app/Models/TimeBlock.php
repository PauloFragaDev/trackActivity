<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class TimeBlock extends Model
{
    protected $fillable = [
        'starts_at', 'ends_at', 'dominant_project_id', 'confidence',
        'status', 'scoring_snapshot', 'generated_at',
    ];

    protected $casts = [
        'starts_at'        => 'datetime',
        'ends_at'          => 'datetime',
        'generated_at'     => 'datetime',
        'confidence'       => 'float',
        'scoring_snapshot' => 'array',
    ];

    public const STATUS_AUTO   = 'auto';
    public const STATUS_EDITED = 'edited';
    public const STATUS_MERGED = 'merged';
    public const STATUS_SPLIT  = 'split';
    public const STATUS_IDLE   = 'idle';

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'dominant_project_id');
    }

    public function summary(): HasOne
    {
        return $this->hasOne(GeneratedSummary::class);
    }

    public function evidence(): HasMany
    {
        return $this->hasMany(TimeBlockEvidence::class);
    }

    public function confidenceLabel(): string
    {
        $cfg = config('tracker.confidence');
        return match (true) {
            $this->confidence === null      => 'n/a',
            $this->confidence >= $cfg['high']   => 'Alta',
            $this->confidence >= $cfg['medium'] => 'Media',
            default                              => 'Baja',
        };
    }
}

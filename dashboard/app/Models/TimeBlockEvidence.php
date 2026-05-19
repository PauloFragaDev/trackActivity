<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TimeBlockEvidence extends Model
{
    public $timestamps = false;

    protected $table = 'time_block_evidence';

    protected $fillable = ['time_block_id', 'activity_event_id', 'weight_contributed', 'note'];

    protected $casts = ['weight_contributed' => 'integer'];

    public function timeBlock(): BelongsTo
    {
        return $this->belongsTo(TimeBlock::class);
    }

    public function activityEvent(): BelongsTo
    {
        return $this->belongsTo(ActivityEvent::class);
    }
}

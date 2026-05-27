<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ActivityEvent extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'occurred_at', 'source', 'app', 'title', 'repo_name', 'branch',
        'modified_files', 'url', 'subject', 'metadata', 'project_id',
    ];

    protected $casts = [
        'occurred_at'    => 'datetime',
        'metadata'       => 'array',
        'modified_files' => 'integer',
        'project_id'     => 'integer',
    ];

    public const SOURCE_WINDOW      = 'window';
    public const SOURCE_GIT         = 'git';
    public const SOURCE_BROWSER     = 'browser';
    public const SOURCE_THUNDERBIRD = 'thunderbird';
    public const SOURCE_IDLE        = 'idle';

    public function evidence(): HasMany
    {
        return $this->hasMany(TimeBlockEvidence::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function scopeBetween($query, \DateTimeInterface $start, \DateTimeInterface $end)
    {
        return $query->where('occurred_at', '>=', $start)
                     ->where('occurred_at', '<',  $end);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectMapping extends Model
{
    protected $fillable = [
        'project_id', 'type', 'pattern', 'is_regex', 'weight_bonus', 'enabled',
    ];

    protected $casts = [
        'is_regex'     => 'boolean',
        'enabled'      => 'boolean',
        'weight_bonus' => 'integer',
    ];

    public const TYPES = [
        'repository',
        'folder',
        'url_pattern',
        'email_subject',
        'window_title',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function scopeEnabled($query)
    {
        return $query->where('enabled', true);
    }

    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }
}

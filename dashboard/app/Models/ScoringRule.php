<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ScoringRule extends Model
{
    public $timestamps = false;

    protected $fillable = ['signal_kind', 'weight', 'enabled', 'description'];

    protected $casts = [
        'weight'  => 'integer',
        'enabled' => 'boolean',
    ];

    public const KIND_VSCODE_IN_REPO     = 'vscode_in_repo';
    public const KIND_TERMINAL_IN_REPO   = 'terminal_in_repo';
    public const KIND_GIT_MODIFIED       = 'git_modified';
    public const KIND_GIT_COMMIT_RECENT  = 'git_commit_recent';
    public const KIND_URL_MATCH          = 'url_match';
    public const KIND_EMAIL_MATCH        = 'email_match';
    public const KIND_WINDOW_TITLE_MATCH = 'window_title_match';
}

<?php

namespace App\Services\Export\Renderers;

use App\Services\Export\Report;

interface Renderer
{
    public function render(Report $report): string;

    public function contentType(): string;
}

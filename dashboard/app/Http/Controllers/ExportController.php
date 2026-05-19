<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Services\Export\ExportQuery;
use App\Services\Export\Exporter;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class ExportController extends Controller
{
    public function __construct(private readonly Exporter $exporter) {}

    public function form(): View
    {
        $tz = config('tracker.display_timezone', 'UTC');
        $today = CarbonImmutable::now($tz);
        return view('export.form', [
            'projects' => Project::orderBy('code')->get(),
            'today'    => $today->toDateString(),
            'weekAgo'  => $today->subDays(7)->toDateString(),
        ]);
    }

    public function download(Request $request): Response
    {
        $tz = config('tracker.display_timezone', 'UTC');

        $data = $request->validate([
            'from'           => ['required', 'date'],
            'to'             => ['required', 'date', 'after_or_equal:from'],
            'projects'       => ['array'],
            'projects.*'     => ['string'],
            'min_confidence' => ['in:low,medium,high'],
            'include_idle'   => ['boolean'],
            'group_by'       => ['in:session,project-day'],
            'format'         => ['required', 'in:txt,md,csv'],
        ]);

        $from = CarbonImmutable::parse($data['from'], $tz)->startOfDay();
        $to   = CarbonImmutable::parse($data['to'],   $tz)->startOfDay()->addDay();   // exclusivo

        $query = new ExportQuery(
            fromLocal:     $from,
            toLocal:       $to,
            projectCodes:  array_values(array_filter($data['projects'] ?? [])),
            minConfidence: $data['min_confidence'] ?? ExportQuery::CONF_LOW,
            includeIdle:   (bool) ($data['include_idle'] ?? false),
            groupBy:       $data['group_by']      ?? ExportQuery::GROUP_SESSION,
            format:        $data['format'],
        );

        $report = $this->exporter->buildReport($query);
        $body   = $this->exporter->render($report);

        return response($body, 200, [
            'Content-Type'        => $this->exporter->contentTypeFor($query->format),
            'Content-Disposition' => 'attachment; filename="' . $query->filename() . '"',
            'Cache-Control'       => 'no-store',
        ]);
    }
}

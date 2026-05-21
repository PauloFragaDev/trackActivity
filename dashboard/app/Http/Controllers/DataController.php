<?php

namespace App\Http\Controllers;

use App\Models\ManualEntry;
use App\Models\Note;
use App\Models\NoteFolder;
use App\Models\Project;
use App\Services\BackupService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use ZipArchive;

/**
 * Página "Datos": copias de seguridad de la base de datos, restauración y
 * exportación de notas y datos.
 */
class DataController extends Controller
{
    public function index(BackupService $backups): View
    {
        return view('data.index', [
            'snapshots' => $backups->snapshots(),
        ]);
    }

    /** Crea una copia de seguridad bajo demanda. */
    public function backupNow(BackupService $backups): RedirectResponse
    {
        try {
            $backups->create();
            $backups->prune();
        } catch (\Throwable $e) {
            return back()->with('status', 'No se pudo crear la copia: ' . $e->getMessage());
        }

        return redirect()->route('data.index')->with('status', 'Copia de seguridad creada.');
    }

    /** Descarga una copia de seguridad. */
    public function downloadBackup(string $name, BackupService $backups): BinaryFileResponse
    {
        $path = $backups->snapshotPath($name);
        abort_unless($path !== null, 404);

        return response()->download($path);
    }

    /** Restaura la BBDD desde un fichero subido o desde una copia existente. */
    public function restore(Request $request, BackupService $backups): RedirectResponse
    {
        $source = null;

        if ($request->hasFile('file')) {
            $request->validate(['file' => ['file', 'max:512000']]);
            $source = $request->file('file')->getRealPath();
        } elseif ($request->filled('snapshot')) {
            $source = $backups->snapshotPath((string) $request->input('snapshot'));
        }

        if ($source === null || ! is_file($source)) {
            return back()->with('status', 'No se indicó una copia válida para restaurar.');
        }

        try {
            $backups->restore($source);
        } catch (\Throwable $e) {
            return back()->with('status', 'No se pudo restaurar: ' . $e->getMessage());
        }

        return redirect()->route('data.index')
            ->with('status', 'Base de datos restaurada. Reinicia el tracker para que use la copia restaurada.');
    }

    /** Exporta todas las notas como un ZIP de ficheros .md, por carpetas. */
    public function exportNotes(): BinaryFileResponse
    {
        $folders = NoteFolder::all()->keyBy('id');
        $tmp     = tempnam(sys_get_temp_dir(), 'notes-export-');

        $zip = new ZipArchive();
        $zip->open($tmp, ZipArchive::OVERWRITE);

        $used = [];
        foreach (Note::orderBy('id')->get() as $note) {
            // Ruta de carpetas de la nota (de raíz a hoja).
            $segments = [];
            $fid      = $note->folder_id;
            $guard    = 0;
            while ($fid && $guard++ < 20 && ($f = $folders->get($fid))) {
                array_unshift($segments, $this->safeName($f->name));
                $fid = $f->parent_id;
            }

            $base = ($segments ? implode('/', $segments) . '/' : '') . $this->safeName($note->title);
            $path = $base . '.md';
            for ($i = 2; isset($used[$path]); $i++) {
                $path = $base . '-' . $i . '.md';
            }
            $used[$path] = true;

            $zip->addFromString($path, '# ' . $note->title . "\n\n" . (string) $note->body);
        }

        $zip->close();

        return response()
            ->download($tmp, 'notas-' . now()->format('Y-m-d') . '.zip')
            ->deleteFileAfterSend();
    }

    /** Exporta los datos de la aplicación como un JSON. */
    public function exportData(): JsonResponse
    {
        $payload = [
            'exported_at'    => now()->toIso8601String(),
            'note_folders'   => NoteFolder::all(),
            'notes'          => Note::all(),
            'projects'       => Project::all(),
            'manual_entries' => ManualEntry::all(),
        ];

        return response()->json($payload, 200, [
            'Content-Disposition' => 'attachment; filename="datos-' . now()->format('Y-m-d') . '.json"',
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /** Normaliza un texto para usarlo como nombre de fichero/carpeta. */
    private function safeName(string $name): string
    {
        $clean = preg_replace('#[/\\\\:*?"<>|]+#', '-', trim($name));

        return trim((string) $clean, '-. ') ?: 'sin-titulo';
    }
}

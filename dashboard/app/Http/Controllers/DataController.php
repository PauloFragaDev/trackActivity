<?php

namespace App\Http\Controllers;

use App\Services\BackupService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

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
}

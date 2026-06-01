<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Note;
use App\Models\Task;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * CRM de clientes. Un cliente agrupa proyectos; sus tareas/notas/tiempo se
 * obtienen a través de esos proyectos. La analítica de tiempo vive en
 * ClientService (PR2).
 */
class ClientController extends Controller
{
    public function index(): View
    {
        return view('clients.index', [
            'clients' => Client::withCount('projects')->orderBy('name')->get(),
        ]);
    }

    public function create(): View
    {
        return view('clients.form', ['client' => new Client(), 'isNew' => true]);
    }

    public function store(Request $request): RedirectResponse
    {
        Client::create($this->validateClient($request));

        return redirect()->route('clients.index')->with('status', 'Cliente creado.');
    }

    public function show(Client $client): View
    {
        $projectIds = $client->projectIds();

        return view('clients.show', [
            'client' => $client->load('projects'),
            'tasks'  => Task::with('project')
                ->whereIn('project_id', $projectIds)->orderBy('status')->get(),
            'notes'  => Note::whereIn('project_id', $projectIds)
                ->orderByDesc('updated_at')->get(),
        ]);
    }

    public function edit(Client $client): View
    {
        return view('clients.form', ['client' => $client, 'isNew' => false]);
    }

    public function update(Request $request, Client $client): RedirectResponse
    {
        $client->update($this->validateClient($request));

        return redirect()->route('clients.index')->with('status', 'Cliente actualizado.');
    }

    public function destroy(Client $client): RedirectResponse
    {
        // Desvincula sus proyectos antes de archivar (SoftDeletes no dispara
        // la FK nullOnDelete, que solo actúa en borrado físico).
        $client->projects()->update(['client_id' => null]);
        $client->delete();

        return redirect()->route('clients.index')->with('status', 'Cliente archivado.');
    }

    /** @return array<string,mixed> */
    private function validateClient(Request $request): array
    {
        return $request->validate([
            'name'    => ['required', 'string', 'max:128'],
            'company' => ['nullable', 'string', 'max:128'],
            'email'   => ['nullable', 'email', 'max:190'],
            'phone'   => ['nullable', 'string', 'max:64'],
            'website' => ['nullable', 'string', 'max:190'],
            'notes'   => ['nullable', 'string', 'max:2000'],
            'color'   => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
        ], [
            'color.regex' => 'El color debe ser hex tipo #RRGGBB.',
        ]);
    }
}

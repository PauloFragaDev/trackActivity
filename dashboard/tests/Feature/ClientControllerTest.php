<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_client_has_many_projects(): void
    {
        $client = Client::create(['name' => 'Acme']);
        Project::create(['code' => 'ACME1', 'name' => 'Web', 'client_id' => $client->id]);
        Project::create(['code' => 'ACME2', 'name' => 'API',  'client_id' => $client->id]);

        $this->assertCount(2, $client->projects);
        $this->assertSame($client->id, Project::where('code', 'ACME1')->first()->client->id);
    }

    public function test_index_lists_clients(): void
    {
        Client::create(['name' => 'Acme']);
        $this->get('/clients')->assertOk()->assertSee('Acme');
    }

    public function test_store_creates_client(): void
    {
        $this->post('/clients', ['name' => 'Globex', 'email' => 'a@b.com'])
            ->assertRedirect();
        $this->assertDatabaseHas('clients', ['name' => 'Globex', 'email' => 'a@b.com']);
    }

    public function test_store_requires_name(): void
    {
        $this->post('/clients', ['name' => ''])->assertSessionHasErrors('name');
    }

    public function test_show_renders_client_with_projects(): void
    {
        $c = Client::create(['name' => 'Acme']);
        Project::create(['code' => 'ACME1', 'name' => 'Web', 'client_id' => $c->id]);
        $this->get("/clients/{$c->id}")->assertOk()->assertSee('Acme')->assertSee('ACME1');
    }

    public function test_update_edits_client(): void
    {
        $c = Client::create(['name' => 'Old']);
        $this->patch("/clients/{$c->id}", ['name' => 'New'])->assertRedirect();
        $this->assertSame('New', $c->fresh()->name);
    }

    public function test_destroy_soft_deletes_and_keeps_projects(): void
    {
        $c = Client::create(['name' => 'Acme']);
        $p = Project::create(['code' => 'ACME1', 'name' => 'Web', 'client_id' => $c->id]);
        $this->delete("/clients/{$c->id}")->assertRedirect();
        $this->assertSoftDeleted('clients', ['id' => $c->id]);
        $this->assertNull($p->fresh()->client_id);
    }
}

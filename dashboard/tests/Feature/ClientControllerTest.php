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
}

<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DataControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_data_page_loads(): void
    {
        $this->get('/data')->assertOk()->assertSee('Copias de seguridad');
    }
}

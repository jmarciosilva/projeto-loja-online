<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    // A partir da F2.3-B o layout público lê as cores do tema no banco, então
    // a home passou a exigir o schema migrado. O ThemeService não engole um
    // erro de tabela ausente de propósito: isso mascararia um deploy quebrado.
    use RefreshDatabase;

    /**
     * A basic test example.
     */
    public function test_the_application_returns_a_successful_response(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
    }
}

<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HomePageTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_page_loads_successfully(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertSee('SpaceMatch')
            ->assertSee('Welcome to SpaceMatch!')
            ->assertSee('Watch Tutorial')
            ->assertSee('Skip for Now')
            ->assertSee("Don't Show Again", false)
            ->assertSee('Watch tutorial')
            ->assertSee('Find Your Space, Anywhere in the Philippines.')
            ->assertSee('name="property_type"', false)
            ->assertSee('Rental categories')
            ->assertSee('Find a rental type that fits.')
            ->assertSee('List your property')
            ->assertSee('About SpaceMatch')
            ->assertSee('How do owners publish a property?')
            ->assertSee('SpaceMatch does not guarantee every listing');
    }
}

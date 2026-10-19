<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class HomePageTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_page_loads_successfully(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertSee('SpaceMatch')
            ->assertDontSee('data-tutorial', false)
            ->assertSee('Register as a Student Renter')
            ->assertSee('Find Your Space, Anywhere in the Philippines.')
            ->assertSee('name="property_type"', false)
            ->assertSee('Rental categories')
            ->assertSee('Find a rental type that fits.')
            ->assertSee('List your property')
            ->assertSee('About SpaceMatch')
            ->assertSee('How do owners publish a property?')
            ->assertSee('SpaceMatch does not guarantee every listing')
            ->assertSee('data-home-carousel', false)
            ->assertSee('data-rental-map', false)
            ->assertSee('data-map-canvas', false)
            ->assertDontSee('data-load-map', false);
    }

    public function test_home_page_loads_before_synthetic_listing_migration_is_applied(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            $table->dropIndex(['is_synthetic']);
            $table->dropColumn('is_synthetic');
        });

        $this->get('/')->assertOk();
    }

    public function test_home_page_loads_before_review_moderation_migration_is_applied(): void
    {
        Schema::table('reviews', function (Blueprint $table) {
            $table->dropColumn('hidden_at');
        });

        $this->get('/')->assertOk();
    }
}

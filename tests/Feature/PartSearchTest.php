<?php

namespace Tests\Feature;

use App\Models\Part;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PartSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_matching_names_for_type(): void
    {
        Part::create(['type' => 'ratchet', 'name' => '3-60']);
        Part::create(['type' => 'ratchet', 'name' => '3-80']);
        Part::create(['type' => 'bit', 'name' => '3-60-bit-noise']);

        $this->actingAs(User::factory()->create())
            ->getJson('/parts/search?type=ratchet&q=3-6')
            ->assertOk()
            ->assertExactJson(['3-60']);
    }

    public function test_invalid_type_returns_empty(): void
    {
        $this->actingAs(User::factory()->create())
            ->getJson('/parts/search?type=nope&q=x')->assertOk()->assertExactJson([]);
    }

    public function test_requires_login(): void
    {
        $this->getJson('/parts/search?type=ratchet&q=3')->assertUnauthorized();
    }
}

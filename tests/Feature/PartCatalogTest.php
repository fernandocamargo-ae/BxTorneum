<?php

namespace Tests\Feature;

use App\Models\Part;
use App\Models\User;
use App\Support\BeybladeLines;
use App\Support\PartCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PartCatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_migrating_leaves_every_catalog_part_available(): void
    {
        foreach (PartCatalog::all() as $type => $names) {
            $this->assertNotEmpty($names, $type);
            $stored = Part::where('type', $type)->pluck('name')->all();
            $this->assertSame([], array_values(array_diff($names, $stored)), $type);
        }
    }

    public function test_it_covers_every_slot_the_app_uses(): void
    {
        $slots = collect(BeybladeLines::SLOTS)->flatten()->unique()->sort()->values()->all();

        $this->assertSame($slots, collect(PartCatalog::all())->keys()->sort()->values()->all());
    }

    public function test_the_catalog_has_no_repeated_names_within_a_slot(): void
    {
        foreach (PartCatalog::all() as $type => $names) {
            $keys = array_map(fn ($name) => preg_replace('/[^a-z0-9]/', '', strtolower($name)), $names);
            $this->assertSame(count($keys), count(array_unique($keys)), $type);
        }
    }

    public function test_adding_the_catalog_again_changes_nothing_and_respects_existing_names(): void
    {
        Part::where('type', 'blade')->where('name', 'Wizard Rod')->delete();
        $typed = Part::create(['type' => 'blade', 'name' => 'Wizzard Rod']);
        $sameButLower = Part::create(['type' => 'blade', 'name' => 'wizard rod']);
        $before = Part::count();

        PartCatalog::addMissing();

        $this->assertSame($before, Part::count()); // "wizard rod" ya cubre a "Wizard Rod"
        $this->assertSame('Wizzard Rod', $typed->fresh()->name);
        $this->assertSame('wizard rod', $sameButLower->fresh()->name);
    }

    public function test_players_find_new_parts_in_the_autocomplete(): void
    {
        $this->actingAs(User::factory()->create());

        $this->getJson('/parts/search?type=blade&q=meteor')->assertJsonFragment(['Meteor Dragoon']);
        $this->getJson('/parts/search?type=assist_blade&q=slash')->assertJsonFragment(['Slash']);
        $this->getJson('/parts/search?type=metal_blade&q=blitz')->assertJsonFragment(['Blitz']);
        $this->getJson('/parts/search?type=bit&q=vortex')->assertJsonFragment(['Vortex']);
    }
}

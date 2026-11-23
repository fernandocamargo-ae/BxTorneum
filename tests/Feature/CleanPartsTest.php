<?php

namespace Tests\Feature;

use App\Models\Deck;
use App\Models\Part;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CleanPartsTest extends TestCase
{
    use RefreshDatabase;

    /** Un deck con piezas tal como quedaron escritas antes de la limpieza. */
    private function deckWith(array $parts, string $line = 'bx'): Deck
    {
        $deck = User::factory()->create()->decks()->create(['name' => 'Deck', 'visibility' => 'private']);
        $beyblade = $deck->deckBeyblades()->create(['line' => $line, 'position' => 1]);
        foreach ($parts as $slot => $name) {
            $beyblade->parts()->attach(Part::firstOrCreate(['type' => $slot, 'name' => $name])->id, ['slot' => $slot]);
        }

        return $deck;
    }

    private function partsOf(Deck $deck): array
    {
        return $deck->fresh()->deckBeyblades()->with('parts')->first()->partsBySlot();
    }

    public function test_it_joins_misspelled_and_repeated_parts_and_keeps_the_decks_complete(): void
    {
        $one = $this->deckWith(['blade' => 'Aeropegasus', 'ratchet' => '1-70', 'bit' => 'Fribol']);
        $two = $this->deckWith(['blade' => 'Wizzard Rod', 'ratchet' => '5-70', 'bit' => 'NIDDLE']);
        $three = $this->deckWith(['blade' => 'Soar phoenyx', 'ratchet' => '9-60', 'bit' => 'Gear needel']);

        $this->artisan('parts:clean')->assertSuccessful();

        $this->assertSame(['blade' => 'Aero Pegasus', 'ratchet' => '1-70', 'bit' => 'Free Ball'], $this->partsOf($one));
        $this->assertSame(['blade' => 'Wizard Rod', 'ratchet' => '5-70', 'bit' => 'Needle'], $this->partsOf($two));
        $this->assertSame(['blade' => 'Phoenix Wing', 'ratchet' => '9-60', 'bit' => 'Gear Needle'], $this->partsOf($three));
        $this->assertSame(1, Part::where('type', 'blade')->where('name', 'like', 'aero%peg%')->count());
        $this->assertFalse(Part::whereIn('name', ['Aeropegasus', 'Wizzard Rod', 'NIDDLE', 'Fribol'])->exists());
    }

    public function test_it_fixes_names_that_only_differ_in_capital_letters(): void
    {
        Part::where('name', 'Silver Wolf')->delete();
        $deck = $this->deckWith(['blade' => 'Silver wolf', 'ratchet' => '3-60', 'bit' => 'Flat']);

        $this->artisan('parts:clean')->assertSuccessful();

        $this->assertSame('Silver Wolf', $this->partsOf($deck)['blade']);
        $this->assertSame(1, Part::where('type', 'blade')->whereRaw('lower(name) = ?', ['silver wolf'])->count());
    }

    public function test_it_removes_made_up_parts_nobody_uses_but_never_one_in_a_deck(): void
    {
        Part::create(['type' => 'metal_blade', 'name' => 'Cobalt']);
        $deck = $this->deckWith(['blade' => 'FENIX', 'ratchet' => '9-90', 'bit' => 'Flat']);

        $this->artisan('parts:clean')->assertSuccessful();

        $this->assertFalse(Part::where('type', 'metal_blade')->where('name', 'Cobalt')->exists());
        $this->assertSame(['blade' => 'FENIX', 'ratchet' => '9-90', 'bit' => 'Flat'], $this->partsOf($deck));
    }

    public function test_the_name_of_the_whole_bey_becomes_the_right_part_in_each_slot(): void
    {
        $deck = $this->deckWith(['lock_chip' => 'FOX', 'main_blade' => 'FOX', 'assist_blade' => 'Jaggy', 'ratchet' => '3-60', 'bit' => 'Flat'], 'cx');

        $this->artisan('parts:clean')->assertSuccessful();

        $this->assertSame('Fox', $this->partsOf($deck)['lock_chip']);
        $this->assertSame('Brush', $this->partsOf($deck)['main_blade']); // Fox Brush
    }

    public function test_dry_run_changes_nothing(): void
    {
        $deck = $this->deckWith(['blade' => 'Wizzard Rod', 'ratchet' => '5-70', 'bit' => 'Flat']);
        $before = Part::orderBy('id')->pluck('name')->all();

        $this->artisan('parts:clean --dry-run')->assertSuccessful();

        $this->assertSame($before, Part::orderBy('id')->pluck('name')->all());
        $this->assertSame('Wizzard Rod', $this->partsOf($deck)['blade']);
    }

    public function test_running_it_twice_is_harmless(): void
    {
        $this->deckWith(['blade' => 'Wizzard Rod', 'ratchet' => '5-70', 'bit' => 'Fribol']);
        $this->artisan('parts:clean')->assertSuccessful();
        $after = Part::orderBy('id')->pluck('name')->all();

        $this->artisan('parts:clean')->assertSuccessful();

        $this->assertSame($after, Part::orderBy('id')->pluck('name')->all());
    }

    private function save(array $combo, ?User $user = null)
    {
        return $this->actingAs($user ?? User::factory()->create())->post('/decks', [
            'name' => 'Nuevo', 'visibility' => 'private', 'beyblades' => [$combo],
        ]);
    }

    public function test_saving_a_deck_stores_the_official_name_so_duplicates_do_not_come_back(): void
    {
        $this->save(['line' => 'bx', 'parts' => ['blade' => ' aeropegasus ', 'ratchet' => '1-70', 'bit' => 'lf']])->assertRedirect('/decks');

        $this->assertSame(['blade' => 'Aero Pegasus', 'ratchet' => '1-70', 'bit' => 'Low Flat'], $this->partsOf(Deck::first()));
        $this->assertSame(1, Part::where('type', 'blade')->where('name', 'like', 'aero%peg%')->count());
    }

    public function test_a_part_that_does_not_exist_is_rejected_with_a_clear_message(): void
    {
        $this->save(['line' => 'bx', 'parts' => ['blade' => 'fenix', 'ratchet' => '3-60', 'bit' => 'Xx']])
            ->assertSessionHasErrors([
                'beyblades.0.parts.blade' => 'No existe la pieza "fenix". Elígela de la lista.',
                'beyblades.0.parts.bit' => 'No existe la pieza "Xx". Elígela de la lista.',
            ])
            ->assertSessionDoesntHaveErrors('beyblades.0.parts.ratchet');

        $this->assertSame(0, Deck::count());
        $this->assertFalse(Part::where('name', 'fenix')->exists());
    }

    public function test_a_made_up_ratchet_explains_the_lines_without_ratchet(): void
    {
        $this->save(['line' => 'bx', 'parts' => ['blade' => 'Valor Bison', 'ratchet' => 'Na', 'bit' => 'Hexa']])
            ->assertSessionHasErrors(['beyblades.0.parts.ratchet' => 'No existe la pieza "Na". Elígela de la lista. Si tu bey no lleva ratchet, elige la línea BX Infinity o UX Infinity.']);
    }

    public function test_a_part_from_another_slot_tells_which_line_to_choose(): void
    {
        $this->save(['line' => 'cx', 'parts' => ['lock_chip' => 'Bahamut', 'main_blade' => 'Blitz', 'assist_blade' => 'Heavy', 'ratchet' => '5-60', 'bit' => 'Zap']])
            ->assertSessionHasErrors(['beyblades.0.parts.main_blade' => '"Blitz" es Metal Blade: elige la línea CX Infinity.']);

        $this->save(['line' => 'bx', 'parts' => ['blade' => 'Brave', 'ratchet' => '5-60', 'bit' => 'Zap']])
            ->assertSessionHasErrors(['beyblades.0.parts.blade' => '"Brave" es Main Blade: elige la línea CX.']);
    }

    public function test_an_admin_can_register_a_release_that_is_not_in_the_catalog_yet(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $combo = ['line' => 'bx', 'parts' => ['blade' => 'Bey  Nuevo 2027', 'ratchet' => '3-60', 'bit' => 'Flat']];

        $this->save($combo, $admin)->assertRedirect('/decks');
        $this->assertSame('Bey Nuevo 2027', $this->partsOf(Deck::first())['blade']);

        // A partir de ahí cualquiera puede usarla, y sin duplicarla.
        $this->save(['line' => 'bx', 'parts' => ['blade' => 'bey-nuevo 2027', 'ratchet' => '3-60', 'bit' => 'Flat']])->assertRedirect('/decks');
        $this->assertSame(1, Part::where('type', 'blade')->where('name', 'like', 'bey%nuevo%')->count());
    }

    public function test_the_search_finds_parts_by_hasbro_name_abbreviation_or_typo_and_hides_made_up_ones(): void
    {
        Part::create(['type' => 'ratchet', 'name' => 'Xx']);
        $this->actingAs(User::factory()->create());

        $this->getJson('/parts/search?type=blade&q=aero')->assertExactJson(['Aero Pegasus']);
        $this->getJson('/parts/search?type=blade&q=soar')->assertJsonFragment(['Phoenix Wing']);
        $this->getJson('/parts/search?type=blade&q=wizardrod')->assertExactJson(['Wizard Rod']);
        $this->getJson('/parts/search?type=ratchet&q=xx')->assertExactJson([]);
        $this->assertSame('Flat', $this->getJson('/parts/search?type=bit&q=flat')->json(0)); // lo que empieza igual, primero
    }
}

# BxTorneum — Decks personales, jugadores y reporte Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Let logged-in users create/manage personal decks (up to 3 combos each, public/private, one marked as their tournament deck), browse other players' public decks, and let an admin export a password-protected PDF of all players with a marked tournament deck.

**Architecture:** Mirrors the existing team-combo data model (`Beyblade`/`Part`/`BeybladeLines`) but scoped to a user instead of a team member: new `Deck` → `DeckBeyblade` → `Part` (shared table) chain. Auth (Laravel Breeze) and the `User.nickname` column already exist (see `docs/superpowers/specs/2026-07-10-bxtorneum-perfiles-decks-design.md`) — this plan only adds Decks, Players browsing, and the players PDF report. The existing `Team`/`Member`/`Beyblade` team-tournament code is not touched.

**Tech Stack:** Laravel 12, Inertia.js + Vue 3, Tailwind v4, PHPUnit, Vitest, barryvdh/laravel-dompdf.

## Global Constraints

- Reuse `App\Support\BeybladeLines` for line/slot definitions and ordering — do not redefine slot lists.
- Reuse the existing `parts` table/`Part` model — do not create a second parts vocabulary.
- A deck holds **up to 3** combos, exactly like a team member does today.
- Only one deck per user can have `is_tournament_deck = true` at a time; setting one unsets any other.
- "Public" visibility means visible to any **logged-in** user, not anonymous visitors. `/decks`, `/players`, `/players/{user}` all require `auth` middleware.
- Reuse `config('report.password')` (already used by the team report) for the players report — do not add a second password.
- Do not touch `Team`, `Member`, `Beyblade`, `TeamController`, `TeamComboController`, or their tests.

---

### Task 1: Deck & DeckBeyblade models + migrations

**Files:**
- Create: `database/migrations/<timestamp>_create_decks_table.php`
- Create: `database/migrations/<timestamp>_create_deck_beyblades_table.php`
- Create: `database/migrations/<timestamp>_create_deck_beyblade_part_table.php`
- Create: `app/Models/Deck.php`
- Create: `app/Models/DeckBeyblade.php`
- Modify: `app/Models/User.php`
- Test: `tests/Feature/DeckModelTest.php`

**Interfaces:**
- Consumes: `App\Models\Part` (existing, `type`+`name`, unique per type), `App\Support\BeybladeLines::slotsFor(string $line): array` (existing).
- Produces: `App\Models\Deck` (`user()`, `deckBeyblades()`, attributes `name`, `visibility` ('public'|'private'), `is_tournament_deck` bool), `App\Models\DeckBeyblade` (`deck()`, `parts()`, `partsBySlot(): array` ordered per `BeybladeLines::slotsFor($this->line)`), `User::decks()` hasMany relation. Later tasks (2+) build on `Deck::create()`, `$deck->deckBeyblades()->create()`, `$beyblade->parts()->attach($partId, ['slot' => $slot])`.

- [ ] **Step 1: Generate the three migrations**

Run:
```
php artisan make:migration create_decks_table
php artisan make:migration create_deck_beyblades_table
php artisan make:migration create_deck_beyblade_part_table
```

- [ ] **Step 2: Fill in `create_decks_table`**

```php
public function up(): void
{
    Schema::create('decks', function (Blueprint $table) {
        $table->id();
        $table->foreignId('user_id')->constrained()->cascadeOnDelete();
        $table->string('name');
        $table->enum('visibility', ['public', 'private'])->default('private');
        $table->boolean('is_tournament_deck')->default(false);
        $table->timestamps();
    });
}

public function down(): void
{
    Schema::dropIfExists('decks');
}
```

- [ ] **Step 3: Fill in `create_deck_beyblades_table`**

```php
public function up(): void
{
    Schema::create('deck_beyblades', function (Blueprint $table) {
        $table->id();
        $table->foreignId('deck_id')->constrained()->cascadeOnDelete();
        $table->string('line');
        $table->unsignedTinyInteger('position');
        $table->timestamps();
    });
}

public function down(): void
{
    Schema::dropIfExists('deck_beyblades');
}
```

- [ ] **Step 4: Fill in `create_deck_beyblade_part_table`**

```php
public function up(): void
{
    Schema::create('deck_beyblade_part', function (Blueprint $table) {
        $table->id();
        $table->foreignId('deck_beyblade_id')->constrained()->cascadeOnDelete();
        $table->foreignId('part_id')->constrained();
        $table->enum('slot', [
            'blade', 'ratchet', 'bit', 'lock_chip',
            'main_blade', 'assist_blade', 'over_blade', 'metal_blade',
        ]);
        $table->timestamps();
        $table->unique(['deck_beyblade_id', 'slot']);
    });
}

public function down(): void
{
    Schema::dropIfExists('deck_beyblade_part');
}
```

- [ ] **Step 5: Write the failing model test**

Create `tests/Feature/DeckModelTest.php`:
```php
<?php

namespace Tests\Feature;

use App\Models\Part;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeckModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_deck_belongs_to_user_and_aggregates_beyblades(): void
    {
        $user = User::factory()->create();
        $deck = $user->decks()->create(['name' => 'Ofensivo', 'visibility' => 'private']);
        $beyblade = $deck->deckBeyblades()->create(['line' => 'bx', 'position' => 1]);

        $blade = Part::firstOrCreate(['type' => 'blade', 'name' => 'Dran Sword']);
        $beyblade->parts()->attach($blade->id, ['slot' => 'blade']);

        $this->assertCount(1, $user->decks);
        $this->assertSame('Dran Sword', $beyblade->partsBySlot()['blade']);
    }

    public function test_parts_by_slot_orders_cx_fields_regardless_of_attach_order(): void
    {
        $user = User::factory()->create();
        $deck = $user->decks()->create(['name' => 'CX', 'visibility' => 'private']);
        $beyblade = $deck->deckBeyblades()->create(['line' => 'cx', 'position' => 1]);

        $beyblade->parts()->attach(Part::firstOrCreate(['type' => 'bit', 'name' => 'Kick'])->id, ['slot' => 'bit']);
        $beyblade->parts()->attach(Part::firstOrCreate(['type' => 'ratchet', 'name' => '4-50'])->id, ['slot' => 'ratchet']);
        $beyblade->parts()->attach(Part::firstOrCreate(['type' => 'lock_chip', 'name' => 'Emperor'])->id, ['slot' => 'lock_chip']);
        $beyblade->parts()->attach(Part::firstOrCreate(['type' => 'assist_blade', 'name' => 'Heavy'])->id, ['slot' => 'assist_blade']);
        $beyblade->parts()->attach(Part::firstOrCreate(['type' => 'main_blade', 'name' => 'Hunt'])->id, ['slot' => 'main_blade']);

        $this->assertSame(
            ['lock_chip', 'main_blade', 'assist_blade', 'ratchet', 'bit'],
            array_keys($beyblade->partsBySlot())
        );
    }
}
```

- [ ] **Step 6: Run it to confirm it fails**

Run: `php artisan test --filter=DeckModelTest`
Expected: FAIL — `Deck`/`DeckBeyblade` classes and `User::decks()` don't exist yet.

- [ ] **Step 7: Create `app/Models/Deck.php`**

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Deck extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'is_tournament_deck' => 'boolean',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function deckBeyblades()
    {
        return $this->hasMany(DeckBeyblade::class);
    }
}
```

- [ ] **Step 8: Create `app/Models/DeckBeyblade.php`**

```php
<?php

namespace App\Models;

use App\Support\BeybladeLines;
use Illuminate\Database\Eloquent\Model;

class DeckBeyblade extends Model
{
    protected $guarded = [];

    public function deck()
    {
        return $this->belongsTo(Deck::class);
    }

    public function parts()
    {
        return $this->belongsToMany(Part::class)->withPivot('slot');
    }

    /** @return array<string,string> slot => part name, ordered per BeybladeLines::slotsFor($this->line) */
    public function partsBySlot(): array
    {
        $bySlot = $this->parts
            ->mapWithKeys(fn (Part $part) => [$part->pivot->slot => $part->name]);

        return collect(BeybladeLines::slotsFor($this->line))
            ->filter(fn (string $slot) => $bySlot->has($slot))
            ->mapWithKeys(fn (string $slot) => [$slot => $bySlot->get($slot)])
            ->all();
    }
}
```

- [ ] **Step 9: Add the `decks()` relation to `app/Models/User.php`**

Add this method inside the `User` class (after the existing properties/casts):
```php
    public function decks()
    {
        return $this->hasMany(Deck::class);
    }
```

- [ ] **Step 10: Run migrations and the test**

Run: `php artisan migrate`
Run: `php artisan test --filter=DeckModelTest`
Expected: PASS (2 tests)

- [ ] **Step 11: Commit**

```bash
git add database/migrations app/Models/Deck.php app/Models/DeckBeyblade.php app/Models/User.php tests/Feature/DeckModelTest.php
git commit -m "feat: add Deck/DeckBeyblade models mirroring the team combo schema"
```

---

### Task 2: DeckRequest validation + DeckController + routes

**Files:**
- Create: `app/Http/Requests/DeckRequest.php`
- Create: `app/Http/Controllers/DeckController.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/DeckControllerTest.php`

**Interfaces:**
- Consumes: `App\Models\Deck`, `App\Models\DeckBeyblade`, `App\Models\Part`, `App\Support\BeybladeLines::{lines,slotsFor,requiredSlotsFor,SLOTS}` (all from Task 1 / existing).
- Produces: routes `decks.index` (GET `/decks`), `decks.create` (GET `/decks/create`), `decks.store` (POST `/decks`), `decks.edit` (GET `/decks/{deck}/edit`), `decks.update` (PUT `/decks/{deck}`), `decks.destroy` (DELETE `/decks/{deck}`), `decks.tournament` (PATCH `/decks/{deck}/tournament`, body `{ value: boolean }`). `DeckRequest::isEmptyCombo(array $beyblade): bool` (static, same contract as `TeamComboRequest::isEmptyCombo` — a combo with only blank part values is empty). Task 3 (frontend) posts to these routes with payload shape `{ name, visibility, beyblades: [{ line, parts: {slot: value} }, ...] }`.

- [ ] **Step 1: Write the failing controller test**

Create `tests/Feature/DeckControllerTest.php`:
```php
<?php

namespace Tests\Feature;

use App\Models\Deck;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeckControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_create_a_deck_with_up_to_three_combos(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/decks', [
            'name' => 'Ofensivo',
            'visibility' => 'private',
            'beyblades' => [
                ['line' => 'bx', 'parts' => ['blade' => 'Dran Sword', 'ratchet' => '3-60', 'bit' => 'Flat']],
                ['line' => '', 'parts' => []],
                ['line' => '', 'parts' => []],
            ],
        ]);

        $response->assertRedirect('/decks');
        $deck = Deck::first();
        $this->assertSame('Ofensivo', $deck->name);
        $this->assertCount(1, $deck->deckBeyblades);
    }

    public function test_user_cannot_touch_another_users_deck(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $deck = $owner->decks()->create(['name' => 'Ofensivo', 'visibility' => 'private']);

        $this->actingAs($other)->get("/decks/{$deck->id}/edit")->assertForbidden();
        $this->actingAs($other)->put("/decks/{$deck->id}", [
            'name' => 'Hackeado',
            'visibility' => 'private',
            'beyblades' => [],
        ])->assertForbidden();
        $this->actingAs($other)->delete("/decks/{$deck->id}")->assertForbidden();
    }

    public function test_marking_a_deck_as_tournament_unmarks_the_previous_one(): void
    {
        $user = User::factory()->create();
        $deckA = $user->decks()->create(['name' => 'A', 'visibility' => 'private', 'is_tournament_deck' => true]);
        $deckB = $user->decks()->create(['name' => 'B', 'visibility' => 'private']);

        $this->actingAs($user)->patch("/decks/{$deckB->id}/tournament", ['value' => true]);

        $this->assertFalse($deckA->fresh()->is_tournament_deck);
        $this->assertTrue($deckB->fresh()->is_tournament_deck);
    }

    public function test_editing_a_deck_replaces_its_combos(): void
    {
        $user = User::factory()->create();
        $deck = $user->decks()->create(['name' => 'Ofensivo', 'visibility' => 'private']);
        $deck->deckBeyblades()->create(['line' => 'bx', 'position' => 1]);

        $response = $this->actingAs($user)->put("/decks/{$deck->id}", [
            'name' => 'Ofensivo',
            'visibility' => 'public',
            'beyblades' => [
                ['line' => 'ux', 'parts' => ['blade' => 'Cobalt Drake', 'ratchet' => '3-60', 'bit' => 'Flat']],
            ],
        ]);

        $response->assertRedirect('/decks');
        $this->assertCount(1, $deck->fresh()->deckBeyblades);
        $this->assertSame('ux', $deck->fresh()->deckBeyblades->first()->line);
    }
}
```

- [ ] **Step 2: Run it to confirm it fails**

Run: `php artisan test --filter=DeckControllerTest`
Expected: FAIL — routes don't exist (404s).

- [ ] **Step 3: Create `app/Http/Requests/DeckRequest.php`**

```php
<?php

namespace App\Http\Requests;

use App\Support\BeybladeLines;
use Illuminate\Foundation\Http\FormRequest;

class DeckRequest extends FormRequest
{
    public function authorize(): bool
    {
        $deck = $this->route('deck');

        return ! $deck || $deck->user_id === auth()->id();
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'visibility' => ['required', 'in:public,private'],
            'beyblades' => ['present', 'array', 'max:3'],
            'beyblades.*.line' => ['nullable', 'string'],
            'beyblades.*.parts' => ['present', 'array'],
        ];
    }

    /** A combo is empty when none of its parts has a non-blank value. */
    public static function isEmptyCombo(array $beyblade): bool
    {
        foreach (($beyblade['parts'] ?? []) as $value) {
            if (is_string($value) && trim($value) !== '') {
                return false;
            }
        }

        return true;
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            foreach ($this->input('beyblades', []) as $bi => $beyblade) {
                if (self::isEmptyCombo($beyblade)) {
                    continue;
                }

                $line = $beyblade['line'] ?? null;
                if (! $line || ! in_array($line, BeybladeLines::lines(), true)) {
                    $validator->errors()->add("beyblades.$bi.line", 'Selecciona una línea válida para el combo.');
                    continue;
                }

                foreach (BeybladeLines::requiredSlotsFor($line) as $slot) {
                    $value = $beyblade['parts'][$slot] ?? null;
                    if (! is_string($value) || trim($value) === '') {
                        $validator->errors()->add(
                            "beyblades.$bi.parts.$slot",
                            "La pieza '$slot' es obligatoria para la línea $line."
                        );
                    }
                }
            }
        });
    }
}
```

- [ ] **Step 4: Create `app/Http/Controllers/DeckController.php`**

```php
<?php

namespace App\Http\Controllers;

use App\Http\Requests\DeckRequest;
use App\Models\Deck;
use App\Models\Part;
use App\Support\BeybladeLines;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class DeckController extends Controller
{
    public function index()
    {
        $decks = auth()->user()->decks()
            ->withCount('deckBeyblades')
            ->orderByDesc('is_tournament_deck')
            ->orderBy('name')
            ->get()
            ->map(fn (Deck $deck) => [
                'id' => $deck->id,
                'name' => $deck->name,
                'visibility' => $deck->visibility,
                'is_tournament_deck' => $deck->is_tournament_deck,
                'combos_count' => $deck->deck_beyblades_count,
            ]);

        return Inertia::render('Decks/Index', ['decks' => $decks]);
    }

    public function create()
    {
        return Inertia::render('Decks/Create', [
            'lines' => BeybladeLines::lines(),
            'slots' => BeybladeLines::SLOTS,
        ]);
    }

    public function store(DeckRequest $request): RedirectResponse
    {
        $deck = DB::transaction(function () use ($request) {
            $deck = auth()->user()->decks()->create([
                'name' => $request->validated('name'),
                'visibility' => $request->validated('visibility'),
            ]);

            $this->syncCombos($deck, $request->validated('beyblades'));

            return $deck;
        });

        return redirect()->route('decks.index')->with('success', "Deck \"{$deck->name}\" creado.");
    }

    public function edit(Deck $deck)
    {
        abort_unless($deck->user_id === auth()->id(), 403);

        $deck->load('deckBeyblades.parts');

        return Inertia::render('Decks/Edit', [
            'deck' => [
                'id' => $deck->id,
                'name' => $deck->name,
                'visibility' => $deck->visibility,
                'is_tournament_deck' => $deck->is_tournament_deck,
                'beyblades' => $deck->deckBeyblades->sortBy('position')->values()->map(fn ($b) => [
                    'line' => $b->line,
                    'parts' => $b->partsBySlot(),
                ]),
            ],
            'lines' => BeybladeLines::lines(),
            'slots' => BeybladeLines::SLOTS,
        ]);
    }

    public function update(DeckRequest $request, Deck $deck): RedirectResponse
    {
        DB::transaction(function () use ($request, $deck) {
            $deck->update([
                'name' => $request->validated('name'),
                'visibility' => $request->validated('visibility'),
            ]);

            $deck->deckBeyblades()->delete();
            $this->syncCombos($deck, $request->validated('beyblades'));
        });

        return redirect()->route('decks.index')->with('success', "Deck \"{$deck->name}\" actualizado.");
    }

    public function destroy(Deck $deck): RedirectResponse
    {
        abort_unless($deck->user_id === auth()->id(), 403);

        $deck->delete();

        return redirect()->route('decks.index')->with('success', 'Deck eliminado.');
    }

    public function markTournament(Request $request, Deck $deck): RedirectResponse
    {
        abort_unless($deck->user_id === auth()->id(), 403);

        $value = $request->boolean('value', true);

        DB::transaction(function () use ($deck, $value) {
            if ($value) {
                Deck::where('user_id', $deck->user_id)
                    ->where('id', '!=', $deck->id)
                    ->update(['is_tournament_deck' => false]);
            }

            $deck->update(['is_tournament_deck' => $value]);
        });

        return back()->with('success', $value ? "\"{$deck->name}\" es tu deck de torneo." : 'Deck de torneo desmarcado.');
    }

    private function syncCombos(Deck $deck, array $beyblades): void
    {
        $position = 0;

        foreach ($beyblades as $beyData) {
            if (DeckRequest::isEmptyCombo($beyData)) {
                continue;
            }

            $position++;
            $beyblade = $deck->deckBeyblades()->create([
                'line' => $beyData['line'],
                'position' => $position,
            ]);

            foreach (BeybladeLines::slotsFor($beyData['line']) as $slot) {
                $name = trim((string) ($beyData['parts'][$slot] ?? ''));
                if ($name === '') {
                    continue;
                }
                $part = Part::firstOrCreate(['type' => $slot, 'name' => $name]);
                $beyblade->parts()->attach($part->id, ['slot' => $slot]);
            }
        }
    }
}
```

- [ ] **Step 5: Add the routes**

In `routes/web.php`, add `use App\Http\Controllers\DeckController;` to the imports, and change the existing `auth` middleware group to:
```php
Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::resource('decks', DeckController::class)->except(['show']);
    Route::patch('/decks/{deck}/tournament', [DeckController::class, 'markTournament'])->name('decks.tournament');
});
```

- [ ] **Step 6: Run the test**

Run: `php artisan test --filter=DeckControllerTest`
Expected: PASS (4 tests)

- [ ] **Step 7: Run the full backend suite to check nothing else broke**

Run: `php artisan test`
Expected: all passing

- [ ] **Step 8: Commit**

```bash
git add app/Http/Requests/DeckRequest.php app/Http/Controllers/DeckController.php routes/web.php tests/Feature/DeckControllerTest.php
git commit -m "feat: add deck CRUD backend (create/edit/delete, mark tournament deck)"
```

---

### Task 3: Decks frontend pages + post-login redirect

**Files:**
- Create: `resources/js/Pages/Decks/Index.vue`
- Create: `resources/js/Pages/Decks/Create.vue`
- Create: `resources/js/Pages/Decks/Edit.vue`
- Test: `resources/js/Pages/__tests__/DecksIndex.test.js`
- Modify: `app/Http/Controllers/Auth/AuthenticatedSessionController.php`
- Modify: `app/Http/Controllers/Auth/RegisteredUserController.php`
- Modify: `app/Http/Controllers/Auth/ConfirmablePasswordController.php`
- Modify: `app/Http/Controllers/Auth/EmailVerificationNotificationController.php`
- Modify: `app/Http/Controllers/Auth/EmailVerificationPromptController.php`
- Modify: `app/Http/Controllers/Auth/VerifyEmailController.php`
- Modify: `tests/Feature/Auth/AuthenticationTest.php`
- Modify: `tests/Feature/Auth/EmailVerificationTest.php`
- Modify: `tests/Feature/Auth/RegistrationTest.php`

**Interfaces:**
- Consumes: `decks.index`/`decks.create`/`decks.store`/`decks.edit`/`decks.update`/`decks.destroy`/`decks.tournament` routes (Task 2), `resources/js/Components/BeybladeForm.vue` (existing, props `modelValue: {line, parts}`, `errors`, `index`, emits `update:modelValue`), `resources/js/lib/beybladeLines.js` exports (existing).
- Produces: nothing consumed by later tasks (this is the terminal UI for decks).

- [ ] **Step 1: Write the failing frontend test**

Create `resources/js/Pages/__tests__/DecksIndex.test.js`:
```js
import { describe, it, expect, vi } from 'vitest';
import { mount } from '@vue/test-utils';
import Index from '../Decks/Index.vue';

vi.mock('@inertiajs/vue3', () => ({
    Link: { props: ['href'], template: '<a :href="href"><slot/></a>' },
    Head: { template: '<div><slot/></div>' },
    router: { patch: vi.fn(), delete: vi.fn() },
}));

describe('Decks/Index', () => {
    const decks = [
        { id: 1, name: 'Ofensivo', visibility: 'private', is_tournament_deck: true, combos_count: 3 },
        { id: 2, name: 'Defensivo', visibility: 'public', is_tournament_deck: false, combos_count: 1 },
    ];

    it('shows deck badges and counts', () => {
        const wrapper = mount(Index, { props: { decks } });
        expect(wrapper.text()).toContain('⭐ Torneo');
        expect(wrapper.text()).toContain('Público · 1 combo(s)');
    });

    it('marks a deck as tournament via router.patch', async () => {
        const { router } = await import('@inertiajs/vue3');
        const wrapper = mount(Index, { props: { decks } });
        await wrapper.findAll('button')[2].trigger('click'); // deck 2's "Marcar como torneo" button
        expect(router.patch).toHaveBeenCalledWith('/decks/2/tournament', { value: true }, { preserveScroll: true });
    });
});
```

- [ ] **Step 2: Run it to confirm it fails**

Run: `npm test -- DecksIndex`
Expected: FAIL — `Decks/Index.vue` doesn't exist.

- [ ] **Step 3: Create `resources/js/Pages/Decks/Index.vue`**

```vue
<script setup>
import { Head, Link, router } from '@inertiajs/vue3';

defineProps({ decks: { type: Array, default: () => [] } });

function toggleTournament(deck) {
    router.patch(`/decks/${deck.id}/tournament`, { value: !deck.is_tournament_deck }, { preserveScroll: true });
}

function destroy(deck) {
    if (confirm(`¿Eliminar el deck "${deck.name}"? Esta acción no se puede deshacer.`)) {
        router.delete(`/decks/${deck.id}`);
    }
}
</script>

<template>
    <Head title="Mis decks" />

    <div class="mb-6 flex items-center justify-between gap-4">
        <h1 class="text-3xl font-black"><span class="bx-gradient-text">Mis decks</span></h1>
        <Link
            href="/decks/create"
            class="rounded-lg bg-gradient-to-r from-bx-cyan to-bx-magenta px-4 py-2 text-sm font-bold text-zinc-950 transition hover:opacity-90"
        >
            + Crear deck
        </Link>
    </div>

    <div v-if="decks.length" class="grid gap-4 sm:grid-cols-2">
        <div
            v-for="deck in decks"
            :key="deck.id"
            class="rounded-xl border border-white/10 bg-zinc-900/50 p-5"
        >
            <div class="flex items-start justify-between gap-2">
                <h2 class="text-lg font-bold">{{ deck.name }}</h2>
                <span
                    v-if="deck.is_tournament_deck"
                    class="shrink-0 rounded-full bg-bx-cyan/15 px-2 py-0.5 text-xs font-bold text-bx-cyan"
                >
                    ⭐ Torneo
                </span>
            </div>
            <p class="mt-1 text-sm text-zinc-400">
                {{ deck.visibility === 'public' ? 'Público' : 'Privado' }} · {{ deck.combos_count }} combo(s)
            </p>
            <div class="mt-4 flex flex-wrap gap-2">
                <Link
                    :href="`/decks/${deck.id}/edit`"
                    class="rounded-lg border border-white/15 px-3 py-1.5 text-sm font-semibold hover:border-bx-cyan hover:text-bx-cyan"
                >
                    Editar
                </Link>
                <button
                    type="button"
                    class="rounded-lg border border-white/15 px-3 py-1.5 text-sm font-semibold hover:border-bx-cyan hover:text-bx-cyan"
                    @click="toggleTournament(deck)"
                >
                    {{ deck.is_tournament_deck ? 'Desmarcar de torneo' : 'Marcar como torneo' }}
                </button>
                <button
                    type="button"
                    class="rounded-lg border border-bx-magenta/50 px-3 py-1.5 text-sm font-semibold text-bx-magenta hover:bg-bx-magenta/10"
                    @click="destroy(deck)"
                >
                    Eliminar
                </button>
            </div>
        </div>
    </div>

    <div v-else class="rounded-xl border border-dashed border-white/15 p-12 text-center text-zinc-400">
        <p class="mb-4">No tienes decks todavía.</p>
        <Link href="/decks/create" class="font-bold text-bx-cyan hover:underline">Crear el primero →</Link>
    </div>
</template>
```

- [ ] **Step 4: Run the test**

Run: `npm test -- DecksIndex`
Expected: PASS (2 tests)

- [ ] **Step 5: Create `resources/js/Pages/Decks/Create.vue`**

```vue
<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import BeybladeForm from '../../Components/BeybladeForm.vue';

const form = useForm({
    name: '',
    visibility: 'private',
    beyblades: [
        { line: 'bx', parts: {} },
        { line: 'bx', parts: {} },
        { line: 'bx', parts: {} },
    ],
});

function updateCombo(index, value) {
    form.beyblades[index] = value;
}

function errorsFor(index) {
    const out = {};
    const prefix = `beyblades.${index}.parts.`;
    for (const [key, msg] of Object.entries(form.errors)) {
        if (key.startsWith(prefix)) out[key.slice(prefix.length)] = msg;
    }
    return out;
}

function submit() {
    form.post('/decks');
}
</script>

<template>
    <Head title="Crear deck" />

    <form class="max-w-2xl space-y-6" @submit.prevent="submit">
        <h1 class="text-3xl font-black"><span class="bx-gradient-text">Crear deck</span></h1>

        <div>
            <label class="mb-1 block text-sm font-medium text-zinc-300">Nombre del deck</label>
            <input
                v-model="form.name"
                type="text"
                class="w-full rounded-md border border-white/10 bg-zinc-900 px-3 py-2 text-sm outline-none focus:border-bx-cyan"
            />
            <p v-if="form.errors.name" class="mt-1 text-xs text-bx-magenta">{{ form.errors.name }}</p>
        </div>

        <div>
            <label class="mb-1 block text-sm font-medium text-zinc-300">Visibilidad</label>
            <select
                v-model="form.visibility"
                class="w-full rounded-md border border-white/10 bg-zinc-900 px-3 py-2 text-sm outline-none focus:border-bx-cyan"
            >
                <option value="private">Privado</option>
                <option value="public">Público</option>
            </select>
        </div>

        <BeybladeForm
            v-for="(combo, i) in form.beyblades"
            :key="i"
            :model-value="combo"
            :index="i"
            :errors="errorsFor(i)"
            @update:model-value="(v) => updateCombo(i, v)"
        />

        <button
            type="submit"
            :disabled="form.processing"
            class="rounded-lg bg-gradient-to-r from-bx-cyan to-bx-magenta px-6 py-3 font-bold text-zinc-950 transition hover:opacity-90 disabled:opacity-50"
        >
            Guardar deck
        </button>
    </form>
</template>
```

- [ ] **Step 6: Create `resources/js/Pages/Decks/Edit.vue`**

```vue
<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import BeybladeForm from '../../Components/BeybladeForm.vue';

const props = defineProps({ deck: Object });

function emptySlots(n) {
    return Array.from({ length: n }, () => ({ line: 'bx', parts: {} }));
}

const form = useForm({
    name: props.deck.name,
    visibility: props.deck.visibility,
    beyblades: [
        ...props.deck.beyblades.map((b) => ({ line: b.line, parts: b.parts })),
        ...emptySlots(3 - props.deck.beyblades.length),
    ],
});

function updateCombo(index, value) {
    form.beyblades[index] = value;
}

function errorsFor(index) {
    const out = {};
    const prefix = `beyblades.${index}.parts.`;
    for (const [key, msg] of Object.entries(form.errors)) {
        if (key.startsWith(prefix)) out[key.slice(prefix.length)] = msg;
    }
    return out;
}

function submit() {
    form.put(`/decks/${props.deck.id}`);
}
</script>

<template>
    <Head :title="`Editar ${deck.name}`" />

    <form class="max-w-2xl space-y-6" @submit.prevent="submit">
        <h1 class="text-3xl font-black"><span class="bx-gradient-text">Editar deck</span></h1>

        <div>
            <label class="mb-1 block text-sm font-medium text-zinc-300">Nombre del deck</label>
            <input
                v-model="form.name"
                type="text"
                class="w-full rounded-md border border-white/10 bg-zinc-900 px-3 py-2 text-sm outline-none focus:border-bx-cyan"
            />
            <p v-if="form.errors.name" class="mt-1 text-xs text-bx-magenta">{{ form.errors.name }}</p>
        </div>

        <div>
            <label class="mb-1 block text-sm font-medium text-zinc-300">Visibilidad</label>
            <select
                v-model="form.visibility"
                class="w-full rounded-md border border-white/10 bg-zinc-900 px-3 py-2 text-sm outline-none focus:border-bx-cyan"
            >
                <option value="private">Privado</option>
                <option value="public">Público</option>
            </select>
        </div>

        <BeybladeForm
            v-for="(combo, i) in form.beyblades"
            :key="i"
            :model-value="combo"
            :index="i"
            :errors="errorsFor(i)"
            @update:model-value="(v) => updateCombo(i, v)"
        />

        <button
            type="submit"
            :disabled="form.processing"
            class="rounded-lg bg-gradient-to-r from-bx-cyan to-bx-magenta px-6 py-3 font-bold text-zinc-950 transition hover:opacity-90 disabled:opacity-50"
        >
            Guardar cambios
        </button>
    </form>
</template>
```

- [ ] **Step 7: Point post-login/register redirects at `decks.index`**

In each of these 6 files, replace `route('teams.index', absolute: false)` with `route('decks.index', absolute: false)` (in `VerifyEmailController.php` there are two occurrences of the same string):
- `app/Http/Controllers/Auth/AuthenticatedSessionController.php`
- `app/Http/Controllers/Auth/RegisteredUserController.php`
- `app/Http/Controllers/Auth/ConfirmablePasswordController.php`
- `app/Http/Controllers/Auth/EmailVerificationNotificationController.php`
- `app/Http/Controllers/Auth/EmailVerificationPromptController.php`
- `app/Http/Controllers/Auth/VerifyEmailController.php`

- [ ] **Step 8: Update the auth tests that assert the redirect target**

In `tests/Feature/Auth/AuthenticationTest.php`, `tests/Feature/Auth/EmailVerificationTest.php`, and `tests/Feature/Auth/RegistrationTest.php`, replace `route('teams.index', absolute: false)` with `route('decks.index', absolute: false)` (same two-occurrence note for the email-verification test).

- [ ] **Step 9: Run the full test suites**

Run: `php artisan test`
Run: `npm test`
Expected: all passing

- [ ] **Step 10: Commit**

```bash
git add resources/js/Pages/Decks resources/js/Pages/__tests__/DecksIndex.test.js app/Http/Controllers/Auth tests/Feature/Auth
git commit -m "feat: add decks UI (create/edit/mark tournament deck); land in decks after login"
```

---

### Task 4: Players browsing backend

**Files:**
- Create: `app/Http/Controllers/PlayerController.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/PlayerControllerTest.php`

**Interfaces:**
- Consumes: `App\Models\User`, `App\Models\Deck` (Task 1), `visibility` column.
- Produces: routes `players.index` (GET `/players`), `players.show` (GET `/players/{user}`). Inertia props for `Players/Index`: `players: [{id, name, nickname, public_decks_count}]`. Inertia props for `Players/Show`: `player: {id, name, nickname}`, `decks: [{id, name, is_tournament_deck, beyblades: [{line, parts}]}]` (only `visibility=public` decks). Task 5 (frontend) consumes exactly these prop shapes.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/PlayerControllerTest.php`:
```php
<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlayerControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get('/players')->assertRedirect('/login');
    }

    public function test_index_lists_only_users_with_public_decks(): void
    {
        $withPublic = User::factory()->create();
        $withPublic->decks()->create(['name' => 'Público', 'visibility' => 'public']);

        $withPrivateOnly = User::factory()->create();
        $withPrivateOnly->decks()->create(['name' => 'Privado', 'visibility' => 'private']);

        $viewer = User::factory()->create();

        $response = $this->actingAs($viewer)->get('/players');

        $response->assertInertia(fn ($page) => $page
            ->component('Players/Index')
            ->has('players', 1)
            ->where('players.0.nickname', $withPublic->nickname)
        );
    }

    public function test_show_only_exposes_public_decks(): void
    {
        $player = User::factory()->create();
        $player->decks()->create(['name' => 'Público', 'visibility' => 'public']);
        $player->decks()->create(['name' => 'Privado', 'visibility' => 'private']);

        $viewer = User::factory()->create();

        $response = $this->actingAs($viewer)->get("/players/{$player->id}");

        $response->assertInertia(fn ($page) => $page
            ->component('Players/Show')
            ->has('decks', 1)
            ->where('decks.0.name', 'Público')
        );
    }
}
```

- [ ] **Step 2: Run it to confirm it fails**

Run: `php artisan test --filter=PlayerControllerTest`
Expected: FAIL — routes don't exist.

- [ ] **Step 3: Create `app/Http/Controllers/PlayerController.php`**

```php
<?php

namespace App\Http\Controllers;

use App\Models\User;
use Inertia\Inertia;

class PlayerController extends Controller
{
    public function index()
    {
        $players = User::query()
            ->whereHas('decks', fn ($q) => $q->where('visibility', 'public'))
            ->withCount(['decks as public_decks_count' => fn ($q) => $q->where('visibility', 'public')])
            ->orderBy('nickname')
            ->get(['id', 'name', 'nickname'])
            ->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'nickname' => $user->nickname,
                'public_decks_count' => $user->public_decks_count,
            ]);

        return Inertia::render('Players/Index', ['players' => $players]);
    }

    public function show(User $user)
    {
        $decks = $user->decks()
            ->where('visibility', 'public')
            ->with('deckBeyblades.parts')
            ->orderBy('name')
            ->get()
            ->map(fn ($deck) => [
                'id' => $deck->id,
                'name' => $deck->name,
                'is_tournament_deck' => $deck->is_tournament_deck,
                'beyblades' => $deck->deckBeyblades->sortBy('position')->values()->map(fn ($b) => [
                    'line' => $b->line,
                    'parts' => $b->partsBySlot(),
                ]),
            ]);

        return Inertia::render('Players/Show', [
            'player' => ['id' => $user->id, 'name' => $user->name, 'nickname' => $user->nickname],
            'decks' => $decks,
        ]);
    }
}
```

- [ ] **Step 4: Add the routes**

In `routes/web.php`, add `use App\Http\Controllers\PlayerController;` to the imports, and inside the existing `auth` middleware group (after the `decks.tournament` line) add:
```php
    Route::get('/players', [PlayerController::class, 'index'])->name('players.index');
    Route::get('/players/{user}', [PlayerController::class, 'show'])->name('players.show');
```

- [ ] **Step 5: Run the test**

Run: `php artisan test --filter=PlayerControllerTest`
Expected: PASS (3 tests)

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/PlayerController.php routes/web.php tests/Feature/PlayerControllerTest.php
git commit -m "feat: add players browsing backend (public decks only)"
```

---

### Task 5: Players frontend pages

**Files:**
- Create: `resources/js/Pages/Players/Index.vue`
- Create: `resources/js/Pages/Players/Show.vue`
- Test: `resources/js/Pages/__tests__/PlayersIndex.test.js`

**Interfaces:**
- Consumes: `players.index`/`players.show` routes and prop shapes from Task 4, `resources/js/lib/beybladeLines.js` (`SLOT_LABELS`, `LINES`, existing).
- Produces: nothing consumed by later tasks besides Task 6 adding an export button to `Players/Index.vue`.

- [ ] **Step 1: Write the failing frontend test**

Create `resources/js/Pages/__tests__/PlayersIndex.test.js`:
```js
import { describe, it, expect, vi } from 'vitest';
import { mount } from '@vue/test-utils';
import Index from '../Players/Index.vue';

vi.mock('@inertiajs/vue3', () => ({
    Link: { props: ['href'], template: '<a :href="href"><slot/></a>' },
    Head: { template: '<div><slot/></div>' },
}));

describe('Players/Index', () => {
    const players = [
        { id: 1, name: 'Ricardo Perez', nickname: 'ricardox', public_decks_count: 2 },
    ];

    it('lists players with their nickname and public deck count', () => {
        const wrapper = mount(Index, { props: { players } });
        expect(wrapper.text()).toContain('ricardox');
        expect(wrapper.text()).toContain('2 deck(s) público(s)');
        expect(wrapper.find('a[href="/players/1"]').exists()).toBe(true);
    });

    it('shows an empty state with no players', () => {
        const wrapper = mount(Index, { props: { players: [] } });
        expect(wrapper.text()).toContain('Todavía no hay jugadores');
    });
});
```

- [ ] **Step 2: Run it to confirm it fails**

Run: `npm test -- PlayersIndex`
Expected: FAIL — `Players/Index.vue` doesn't exist.

- [ ] **Step 3: Create `resources/js/Pages/Players/Index.vue`**

```vue
<script setup>
import { Head, Link } from '@inertiajs/vue3';

defineProps({ players: { type: Array, default: () => [] } });
</script>

<template>
    <Head title="Jugadores" />

    <h1 class="mb-6 text-3xl font-black"><span class="bx-gradient-text">Jugadores</span></h1>

    <div v-if="players.length" class="grid gap-4 sm:grid-cols-2">
        <Link
            v-for="player in players"
            :key="player.id"
            :href="`/players/${player.id}`"
            class="group rounded-xl border border-white/10 bg-zinc-900/50 p-5 transition hover:border-bx-cyan hover:bx-glow"
        >
            <h2 class="text-lg font-bold group-hover:text-bx-cyan">{{ player.nickname }}</h2>
            <p class="mt-1 text-sm text-zinc-400">{{ player.name }} · {{ player.public_decks_count }} deck(s) público(s)</p>
        </Link>
    </div>

    <div v-else class="rounded-xl border border-dashed border-white/15 p-12 text-center text-zinc-400">
        Todavía no hay jugadores con decks públicos.
    </div>
</template>
```

- [ ] **Step 4: Run the test**

Run: `npm test -- PlayersIndex`
Expected: PASS (2 tests)

- [ ] **Step 5: Create `resources/js/Pages/Players/Show.vue`**

```vue
<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { SLOT_LABELS, LINES } from '../../lib/beybladeLines';

defineProps({ player: Object, decks: { type: Array, default: () => [] } });

function lineLabel(value) {
    return LINES.find((l) => l.value === value)?.label ?? value;
}
</script>

<template>
    <Head :title="player.nickname" />

    <Link href="/players" class="text-sm text-zinc-400 hover:text-bx-cyan">← Jugadores</Link>
    <h1 class="mb-6 mt-1 text-3xl font-black">
        {{ player.nickname }} <span class="text-lg font-medium text-zinc-400">({{ player.name }})</span>
    </h1>

    <div v-if="decks.length" class="space-y-6">
        <section
            v-for="deck in decks"
            :key="deck.id"
            class="rounded-xl border border-white/10 bg-zinc-900/40 p-5"
        >
            <div class="mb-4 flex items-center gap-3">
                <span class="text-lg font-bold">{{ deck.name }}</span>
                <span
                    v-if="deck.is_tournament_deck"
                    class="rounded-full bg-bx-cyan/15 px-2 py-0.5 text-xs font-bold text-bx-cyan"
                >
                    ⭐ Torneo
                </span>
            </div>

            <div v-if="deck.beyblades.length" class="grid gap-4 sm:grid-cols-3">
                <div
                    v-for="(combo, i) in deck.beyblades"
                    :key="i"
                    class="rounded-lg border border-white/10 bg-zinc-950/60 p-4"
                >
                    <p class="mb-2 text-xs font-bold uppercase tracking-widest text-bx-cyan">{{ lineLabel(combo.line) }}</p>
                    <dl class="space-y-1 text-sm">
                        <div v-for="(name, slot) in combo.parts" :key="slot" class="flex justify-between gap-2">
                            <dt class="text-zinc-500">{{ SLOT_LABELS[slot] ?? slot }}</dt>
                            <dd class="font-medium text-zinc-200">{{ name }}</dd>
                        </div>
                    </dl>
                </div>
            </div>
            <p v-else class="text-sm text-zinc-500">Este deck no tiene combos.</p>
        </section>
    </div>

    <p v-else class="text-zinc-400">Este jugador no tiene decks públicos.</p>
</template>
```

- [ ] **Step 6: Run the full frontend suite**

Run: `npm test`
Expected: all passing

- [ ] **Step 7: Commit**

```bash
git add resources/js/Pages/Players resources/js/Pages/__tests__/PlayersIndex.test.js
git commit -m "feat: add players browsing UI (public decks only)"
```

---

### Task 6: Players PDF report

**Files:**
- Modify: `resources/js/Components/ReportExport.vue`
- Modify: `resources/js/Components/__tests__/ReportExport.test.js`
- Modify: `resources/js/Pages/Players/Index.vue`
- Create: `app/Http/Controllers/PlayerReportController.php`
- Create: `resources/views/reports/players.blade.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/PlayerReportPdfTest.php`

**Interfaces:**
- Consumes: `config('report.password')` (existing, same one `ReportController` uses), `App\Models\User`, `App\Support\BeybladeLines::{lineLabel,slotLabel}` (existing), `DeckBeyblade::partsBySlot()` (Task 1).
- Produces: route `report.players.pdf` (POST `/report/players/pdf`, body `{password}`, downloads `reporte-jugadores.pdf`). `ReportExport.vue` now accepts optional props `endpoint` (default `/report/pdf`) and `filename` (default `reporte-bxtorneum.pdf`) — existing usage in `Teams/Index.vue` is unaffected.

- [ ] **Step 1: Write the failing backend test**

Create `tests/Feature/PlayerReportPdfTest.php`:
```php
<?php

namespace Tests\Feature;

use App\Models\Part;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlayerReportPdfTest extends TestCase
{
    use RefreshDatabase;

    private function seedPlayerWithTournamentDeck(): void
    {
        $user = User::factory()->create(['name' => 'Ricardo', 'nickname' => 'ricardox']);
        $deck = $user->decks()->create(['name' => 'Ofensivo', 'visibility' => 'private', 'is_tournament_deck' => true]);
        $beyblade = $deck->deckBeyblades()->create(['line' => 'bx', 'position' => 1]);
        $blade = Part::firstOrCreate(['type' => 'blade', 'name' => 'Dran Sword']);
        $beyblade->parts()->attach($blade->id, ['slot' => 'blade']);
    }

    public function test_downloads_pdf_with_correct_password(): void
    {
        config(['report.password' => 'secret123']);
        $this->seedPlayerWithTournamentDeck();

        $response = $this->postJson('/report/players/pdf', ['password' => 'secret123']);

        $response->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('content-type'));
    }

    public function test_rejects_wrong_password(): void
    {
        config(['report.password' => 'secret123']);

        $this->postJson('/report/players/pdf', ['password' => 'nope'])->assertForbidden();
    }

    public function test_requires_password(): void
    {
        config(['report.password' => 'secret123']);

        $this->postJson('/report/players/pdf', [])->assertStatus(422);
    }

    public function test_rejects_when_no_password_configured(): void
    {
        config(['report.password' => null]);

        $this->postJson('/report/players/pdf', ['password' => 'anything'])->assertForbidden();
    }
}
```

- [ ] **Step 2: Run it to confirm it fails**

Run: `php artisan test --filter=PlayerReportPdfTest`
Expected: FAIL — route doesn't exist.

- [ ] **Step 3: Create `app/Http/Controllers/PlayerReportController.php`**

```php
<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\BeybladeLines;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

class PlayerReportController extends Controller
{
    public function download(Request $request)
    {
        $request->validate(['password' => ['required', 'string']]);

        $expected = config('report.password');
        if (blank($expected) || ! hash_equals((string) $expected, (string) $request->input('password'))) {
            abort(403, 'Contraseña incorrecta.');
        }

        $players = User::query()
            ->whereHas('decks', fn ($q) => $q->where('is_tournament_deck', true))
            ->with(['decks' => fn ($q) => $q->where('is_tournament_deck', true)->with('deckBeyblades.parts')])
            ->orderBy('nickname')
            ->get()
            ->map(function (User $user) {
                $deck = $user->decks->first();

                return [
                    'name' => $user->name,
                    'nickname' => $user->nickname,
                    'deck_name' => $deck->name,
                    'beyblades' => $deck->deckBeyblades
                        ->sortBy('position')
                        ->values()
                        ->map(fn ($b) => [
                            'line_label' => BeybladeLines::lineLabel($b->line),
                            'parts' => collect($b->partsBySlot())
                                ->map(fn ($name, $slot) => [
                                    'slot_label' => BeybladeLines::slotLabel($slot),
                                    'name' => $name,
                                ])
                                ->values()
                                ->all(),
                        ])
                        ->all(),
                ];
            })
            ->all();

        $pdf = Pdf::loadView('reports.players', [
            'players' => $players,
            'generatedAt' => now()->format('d/m/Y H:i'),
        ]);

        return $pdf->download('reporte-jugadores.pdf');
    }
}
```

- [ ] **Step 4: Create `resources/views/reports/players.blade.php`**

```blade
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <style>
        * { font-family: DejaVu Sans, sans-serif; }
        body { color: #18181b; font-size: 12px; margin: 0; }
        .header { padding: 18px 24px; background: #0e7490; color: #ffffff; }
        .header h1 { margin: 0; font-size: 22px; letter-spacing: 1px; }
        .header .x { background: #db2777; padding: 0 6px; border-radius: 3px; }
        .header .sub { margin-top: 2px; font-size: 11px; color: #cffafe; }
        .meta { padding: 6px 24px; font-size: 10px; color: #52525b; }
        .player { padding: 6px 24px 14px; page-break-inside: avoid; }
        .player h2 { font-size: 15px; margin: 12px 0 4px; border-bottom: 2px solid #0e7490; padding-bottom: 2px; }
        .nickname { color: #0e7490; }
        table { width: 100%; border-collapse: collapse; margin-top: 6px; }
        th, td { border: 1px solid #e4e4e7; padding: 4px 6px; text-align: left; vertical-align: top; }
        th { background: #f4f4f5; font-size: 10px; text-transform: uppercase; letter-spacing: .5px; }
        .combo { margin-bottom: 4px; }
        .combo-line { font-weight: bold; color: #0e7490; }
    </style>
</head>
<body>
    <div class="header">
        <h1>BEYBLADE <span class="x">X</span> &middot; Torneum</h1>
        <div class="sub">Reporte de jugadores</div>
    </div>
    <div class="meta">Generado el {{ $generatedAt }} &middot; {{ count($players) }} jugadores</div>

    @foreach ($players as $player)
        <div class="player">
            <h2>{{ $player['name'] }} &middot; <span class="nickname">{{ $player['nickname'] }}</span></h2>
            <table>
                <thead>
                    <tr>
                        <th style="width: 20%">Deck de torneo</th>
                        <th>Combos</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>{{ $player['deck_name'] }}</td>
                        <td>
                            @foreach ($player['beyblades'] as $combo)
                                <div class="combo">
                                    <span class="combo-line">{{ $combo['line_label'] }}:</span>
                                    @foreach ($combo['parts'] as $part)
                                        {{ $part['slot_label'] }} {{ $part['name'] }}@if (! $loop->last) &middot; @endif
                                    @endforeach
                                </div>
                            @endforeach
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    @endforeach
</body>
</html>
```

- [ ] **Step 5: Add the route**

In `routes/web.php`, add `use App\Http\Controllers\PlayerReportController;` to the imports, and add this line next to the existing `report.pdf` route (outside the `auth` group — the password is the gate, same as the team report):
```php
Route::post('/report/players/pdf', [PlayerReportController::class, 'download'])->name('report.players.pdf');
```

- [ ] **Step 6: Run the backend test**

Run: `php artisan test --filter=PlayerReportPdfTest`
Expected: PASS (4 tests)

- [ ] **Step 7: Generalize `ReportExport.vue`**

Read the current file, then apply:
```vue
<script setup>
import axios from 'axios';
import { ref } from 'vue';

const props = defineProps({
    endpoint: { type: String, default: '/report/pdf' },
    filename: { type: String, default: 'reporte-bxtorneum.pdf' },
});

const open = ref(false);
const password = ref('');
const error = ref('');
const processing = ref(false);

function show() {
    password.value = '';
    error.value = '';
    open.value = true;
}

function close() {
    open.value = false;
}

async function download() {
    processing.value = true;
    error.value = '';
    try {
        const res = await axios.post(props.endpoint, { password: password.value }, { responseType: 'blob' });
        const url = URL.createObjectURL(res.data);
        const link = document.createElement('a');
        link.href = url;
        link.download = props.filename;
        document.body.appendChild(link);
        link.click();
        link.remove();
        URL.revokeObjectURL(url);
        open.value = false;
    } catch (e) {
        error.value = 'Contraseña incorrecta.';
    } finally {
        processing.value = false;
    }
}
</script>
```
(The `<template>` block is unchanged — keep it exactly as-is.)

- [ ] **Step 8: Add a test for the new props to `ReportExport.test.js`**

Append this test inside the existing `describe('ReportExport', ...)` block:
```js
    it('posts to a custom endpoint and filename when provided', async () => {
        axios.post.mockResolvedValue({ data: new Blob(['pdf']) });
        const wrapper = mount(ReportExport, {
            props: { endpoint: '/report/players/pdf', filename: 'reporte-jugadores.pdf' },
        });

        await wrapper.find('button').trigger('click');
        await wrapper.find('input[type="password"]').setValue('secret');
        await wrapper.findAll('button').at(-1).trigger('click');
        await flushPromises();

        expect(axios.post).toHaveBeenCalledWith(
            '/report/players/pdf',
            { password: 'secret' },
            { responseType: 'blob' },
        );
    });
```

- [ ] **Step 9: Wire the export button into `Players/Index.vue`**

Modify `resources/js/Pages/Players/Index.vue`: add `import ReportExport from '../../Components/ReportExport.vue';` to the script, and change the header `<div>` to:
```vue
    <div class="mb-6 flex items-center justify-between gap-4">
        <h1 class="text-3xl font-black"><span class="bx-gradient-text">Jugadores</span></h1>
        <ReportExport endpoint="/report/players/pdf" filename="reporte-jugadores.pdf" />
    </div>
```
(replacing the standalone `<h1>` line from Task 5).

- [ ] **Step 10: Run the full test suites**

Run: `php artisan test`
Run: `npm test`
Expected: all passing

- [ ] **Step 11: Commit**

```bash
git add resources/js/Components/ReportExport.vue resources/js/Components/__tests__/ReportExport.test.js resources/js/Pages/Players/Index.vue app/Http/Controllers/PlayerReportController.php resources/views/reports/players.blade.php routes/web.php tests/Feature/PlayerReportPdfTest.php
git commit -m "feat: add password-protected players PDF report"
```

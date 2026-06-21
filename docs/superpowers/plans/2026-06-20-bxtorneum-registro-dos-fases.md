# BxTorneum — Registro en dos fases + seeder — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Separar el registro de equipos en dos fases (nombres primero, combos después con guardado parcial), añadir un badge de completitud y un seeder con los equipos iniciales del torneo.

**Architecture:** Se reutiliza el modelo existente sin cambios de esquema (`beyblades` pasa a admitir 0–3 por miembro). `TeamRequest` se reduce a validar nombres; un nuevo `TeamComboController` + `TeamComboRequest` gestiona los combos parciales en `GET|PUT /teams/{team}/combos`. La completitud se deriva del conteo de beyblades. El frontend reaprovecha `MemberDeck`/`BeybladeForm`/`PartAutocomplete`.

**Tech Stack:** Laravel 12, PHP 8.2, MySQL, Inertia.js + Vue 3, Tailwind 4, PHPUnit, Vitest.

## Global Constraints

- No hay cambios de esquema de base de datos.
- Fase 1 valida solo: `name` del equipo + 3 miembros con `role` único y `name`.
- Fase 2 (combos) admite **guardado parcial**: un combo vacío se ignora; un combo con
  alguna pieza debe tener todas las obligatorias de su línea (`ratchet` opcional en Infinity).
- Completitud derivada: `Team::isComplete() === (beybladesCount() === 9)`.
- Catálogo híbrido: piezas vía `Part::firstOrCreate(['type'=>$slot,'name'=>$name])`.
- Mapa línea→slots: fuente de verdad en `App\Support\BeybladeLines` (PHP) y
  `resources/js/lib/beybladeLines.js` (JS); no duplicar.
- Roles: `captain`, `subcaptain`, `official`. Labels UI: Capitán / Subcapitán / Oficial.

## File Structure

- `app/Models/Team.php` — añadir `beybladesCount()` e `isComplete()`.
- `app/Http/Requests/TeamRequest.php` — reducir a validación de nombres.
- `app/Http/Controllers/TeamController.php` — `store`/`update` solo nombres; `index`/`show`
  exponen `is_complete` y `beyblades_count`; `create`/`edit` sin combos.
- `app/Http/Requests/TeamComboRequest.php` — **nuevo**, validación parcial de combos.
- `app/Http/Controllers/TeamComboController.php` — **nuevo**, `edit`/`update`.
- `routes/web.php` — añadir rutas de combos.
- `database/seeders/TeamSeeder.php` — **nuevo**, 13 equipos.
- `database/seeders/DatabaseSeeder.php` — registrar `TeamSeeder`.
- `resources/js/Pages/Teams/Create.vue` — reescribir a formulario de nombres.
- `resources/js/Pages/Teams/Edit.vue` — reescribir a edición de nombres.
- `resources/js/Pages/Teams/Combos.vue` — **nueva**, registro/edición de combos.
- `resources/js/Pages/Teams/Show.vue` — badge + "Pendiente" + botones a nombres/combos.
- `resources/js/Pages/Teams/Index.vue` — badge de completitud.
- Tests: `tests/Feature/{TeamNamesTest,TeamComboTest,TeamSeederTest}.php`,
  `tests/Unit/TeamCompletenessTest.php`, y actualizar
  `resources/js/Pages/__tests__/{TeamsCreate,TeamsIndex}.test.js` + nuevo `TeamsCombos.test.js`.

---

## Task 1: Team completeness helpers

**Files:**
- Modify: `app/Models/Team.php`
- Test: `tests/Unit/TeamCompletenessTest.php`

**Interfaces:**
- Produces: `Team::beybladesCount(): int` (suma de beyblades de todos sus miembros),
  `Team::isComplete(): bool` (`=== 9`).

- [ ] **Step 1: Write the failing test**

Create `tests/Unit/TeamCompletenessTest.php`:
```php
<?php

namespace Tests\Unit;

use App\Models\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeamCompletenessTest extends TestCase
{
    use RefreshDatabase;

    public function test_incomplete_until_nine_beyblades(): void
    {
        $team = Team::create(['name' => 'T']);
        $captain = $team->members()->create(['role' => 'captain', 'name' => 'A']);
        $team->members()->create(['role' => 'subcaptain', 'name' => 'B']);
        $team->members()->create(['role' => 'official', 'name' => 'C']);

        $this->assertSame(0, $team->beybladesCount());
        $this->assertFalse($team->isComplete());

        foreach ($team->members as $member) {
            for ($i = 1; $i <= 3; $i++) {
                $member->beyblades()->create(['line' => 'bx', 'position' => $i]);
            }
        }

        $this->assertSame(9, $team->fresh()->beybladesCount());
        $this->assertTrue($team->fresh()->isComplete());
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=TeamCompletenessTest`
Expected: FAIL (`beybladesCount` not defined).

- [ ] **Step 3: Implement the helpers**

In `app/Models/Team.php`, add inside the class (after the `members()` method):
```php
    public function beybladesCount(): int
    {
        return $this->members()->withCount('beyblades')->get()->sum('beyblades_count');
    }

    public function isComplete(): bool
    {
        return $this->beybladesCount() === 9;
    }
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=TeamCompletenessTest`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Models/Team.php tests/Unit/TeamCompletenessTest.php
git commit -m "feat: team completeness helpers"
```

---

## Task 2: Reduce TeamRequest to names-only + update TeamController

**Files:**
- Modify: `app/Http/Requests/TeamRequest.php`
- Modify: `app/Http/Controllers/TeamController.php`
- Test: `tests/Feature/TeamNamesTest.php`

**Interfaces:**
- Consumes: `Team` model.
- Produces:
  - `TeamRequest::rules()` validating `name` + `members` (size 3, role in/distinct, name).
  - `TeamController::store`/`update` persist team + 3 member names only (no beyblades).
  - `TeamController::index` returns teams with `is_complete` and `beyblades_count`.
  - `TeamController::show` returns the transform shape plus `is_complete` and `beyblades_count`.
  - `TeamController::create`/`edit` no longer pass `lines`/`slots`.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/TeamNamesTest.php`:
```php
<?php

namespace Tests\Feature;

use App\Models\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeamNamesTest extends TestCase
{
    use RefreshDatabase;

    private function payload(): array
    {
        return [
            'name' => 'Xplosivos',
            'members' => [
                ['role' => 'captain', 'name' => 'Ricardo'],
                ['role' => 'subcaptain', 'name' => 'Cielo azul'],
                ['role' => 'official', 'name' => 'Kristen'],
            ],
        ];
    }

    public function test_registers_team_with_names_only(): void
    {
        $this->post('/teams', $this->payload())->assertRedirect();

        $this->assertDatabaseHas('teams', ['name' => 'Xplosivos']);
        $this->assertDatabaseCount('members', 3);
        $this->assertDatabaseCount('beyblades', 0);
    }

    public function test_rejects_missing_member_name(): void
    {
        $payload = $this->payload();
        $payload['members'][0]['name'] = '';

        $this->post('/teams', $payload)->assertSessionHasErrors('members.0.name');
    }

    public function test_updates_member_names(): void
    {
        $this->post('/teams', $this->payload());
        $team = Team::first();

        $payload = $this->payload();
        $payload['name'] = 'Xplosivos 2';
        $payload['members'][0]['name'] = 'Ricardo R.';

        $this->put("/teams/{$team->id}", $payload)->assertRedirect();
        $this->assertDatabaseHas('teams', ['name' => 'Xplosivos 2']);
        $this->assertDatabaseHas('members', ['name' => 'Ricardo R.', 'role' => 'captain']);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=TeamNamesTest`
Expected: FAIL (`store` still expects beyblades / validation differs; `beyblades` count not 0).

- [ ] **Step 3: Rewrite TeamRequest to names-only**

Replace `app/Http/Requests/TeamRequest.php`:
```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TeamRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'members' => ['required', 'array', 'size:3'],
            'members.*.role' => ['required', Rule::in(['captain', 'subcaptain', 'official']), 'distinct'],
            'members.*.name' => ['required', 'string', 'max:255'],
        ];
    }
}
```

- [ ] **Step 4: Rewrite TeamController (names-only persistence + completeness in payload)**

Replace `app/Http/Controllers/TeamController.php`:
```php
<?php

namespace App\Http\Controllers;

use App\Http\Requests\TeamRequest;
use App\Models\Team;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class TeamController extends Controller
{
    public function index()
    {
        $teams = Team::orderBy('name')->get(['id', 'name'])->map(fn (Team $team) => [
            'id' => $team->id,
            'name' => $team->name,
            'beyblades_count' => $team->beybladesCount(),
            'is_complete' => $team->isComplete(),
        ]);

        return Inertia::render('Teams/Index', ['teams' => $teams]);
    }

    public function create()
    {
        return Inertia::render('Teams/Create');
    }

    public function store(TeamRequest $request)
    {
        $team = DB::transaction(fn () => $this->persistNames(new Team(), $request->validated()));

        return redirect()->route('teams.show', $team)->with('success', 'Equipo registrado.');
    }

    public function show(Team $team)
    {
        return Inertia::render('Teams/Show', ['team' => $this->transform($team)]);
    }

    public function edit(Team $team)
    {
        return Inertia::render('Teams/Edit', ['team' => $this->transform($team)]);
    }

    public function update(TeamRequest $request, Team $team)
    {
        DB::transaction(fn () => $this->persistNames($team, $request->validated()));

        return redirect()->route('teams.show', $team)->with('success', 'Equipo actualizado.');
    }

    public function destroy(Team $team)
    {
        $team->delete();

        return redirect()->route('teams.index')->with('success', 'Equipo eliminado.');
    }

    private function persistNames(Team $team, array $data): Team
    {
        $team->fill(['name' => $data['name']])->save();

        foreach ($data['members'] as $memberData) {
            $team->members()->updateOrCreate(
                ['role' => $memberData['role']],
                ['name' => $memberData['name']],
            );
        }

        return $team;
    }

    private function transform(Team $team): array
    {
        $team->load('members.beyblades.parts');

        return [
            'id' => $team->id,
            'name' => $team->name,
            'beyblades_count' => $team->beybladesCount(),
            'is_complete' => $team->isComplete(),
            'members' => $team->members->sortBy('id')->values()->map(fn ($member) => [
                'id' => $member->id,
                'role' => $member->role,
                'name' => $member->name,
                'beyblades' => $member->beyblades
                    ->sortBy('position')
                    ->values()
                    ->map(fn ($b) => [
                        'id' => $b->id,
                        'line' => $b->line,
                        'position' => $b->position,
                        'parts' => $b->partsBySlot(),
                    ]),
            ]),
        ];
    }
}
```

- [ ] **Step 5: Run test to verify it passes**

Run: `php artisan test --filter=TeamNamesTest`
Expected: PASS (3 tests).

Note: the old `tests/Feature/TeamRegistrationTest.php` (which posted 9 combos) now conflicts
with the names-only `store`. Delete it — its behavior is replaced by `TeamNamesTest` and
`TeamComboTest` (Task 4):
```bash
git rm tests/Feature/TeamRegistrationTest.php
```

- [ ] **Step 6: Run full backend suite**

Run: `php artisan test`
Expected: PASS (no references to removed test).

- [ ] **Step 7: Commit**

```bash
git add app/Http/Requests/TeamRequest.php app/Http/Controllers/TeamController.php tests/Feature/TeamNamesTest.php
git commit -m "feat: team registration becomes names-only (phase 1)"
```

---

## Task 3: TeamComboRequest (partial combo validation)

**Files:**
- Create: `app/Http/Requests/TeamComboRequest.php`
- Test: covered via Task 4 feature test (`TeamComboTest`)

**Interfaces:**
- Consumes: `App\Support\BeybladeLines`.
- Produces: `TeamComboRequest` with `authorize(): true`, base `rules()` for the nested shape,
  and `withValidator()` enforcing: empty combos ignored; non-empty combos require all
  required slots for their line. Helper `public static function isEmptyCombo(array $beyblade): bool`.

- [ ] **Step 1: Create the request class**

Create `app/Http/Requests/TeamComboRequest.php`:
```php
<?php

namespace App\Http\Requests;

use App\Support\BeybladeLines;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TeamComboRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'members' => ['required', 'array', 'size:3'],
            'members.*.id' => ['required', 'integer'],
            'members.*.beyblades' => ['present', 'array', 'max:3'],
            'members.*.beyblades.*.line' => ['required', Rule::in(BeybladeLines::lines())],
            'members.*.beyblades.*.parts' => ['present', 'array'],
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
            foreach ($this->input('members', []) as $mi => $member) {
                foreach ($member['beyblades'] ?? [] as $bi => $beyblade) {
                    if (self::isEmptyCombo($beyblade)) {
                        continue; // partial save: skip empty combos
                    }
                    $line = $beyblade['line'] ?? null;
                    if (! $line || ! in_array($line, BeybladeLines::lines(), true)) {
                        continue;
                    }
                    foreach (BeybladeLines::requiredSlotsFor($line) as $slot) {
                        $value = $beyblade['parts'][$slot] ?? null;
                        if (! is_string($value) || trim($value) === '') {
                            $validator->errors()->add(
                                "members.$mi.beyblades.$bi.parts.$slot",
                                "La pieza '$slot' es obligatoria para la línea $line."
                            );
                        }
                    }
                }
            }
        });
    }
}
```

- [ ] **Step 2: Commit**

```bash
git add app/Http/Requests/TeamComboRequest.php
git commit -m "feat: partial combo validation request"
```

---

## Task 4: TeamComboController + routes

**Files:**
- Create: `app/Http/Controllers/TeamComboController.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/TeamComboTest.php`

**Interfaces:**
- Consumes: `TeamComboRequest`, `Team`, `Part`, `BeybladeLines`.
- Produces:
  - `GET  /teams/{team}/combos` → `combos.edit` → `Inertia::render('Teams/Combos', ['team' => transform, 'lines' => BeybladeLines::lines(), 'slots' => BeybladeLines::SLOTS])`.
  - `PUT  /teams/{team}/combos` → `combos.update`: wipes the team's beyblades, reinserts only
    non-empty combos with `position` 1..n per member, parts via `firstOrCreate`.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/TeamComboTest.php`:
```php
<?php

namespace Tests\Feature;

use App\Models\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeamComboTest extends TestCase
{
    use RefreshDatabase;

    private function team(): Team
    {
        $team = Team::create(['name' => 'Xplosivos']);
        foreach ([['captain', 'Ricardo'], ['subcaptain', 'Cielo'], ['official', 'Kristen']] as [$role, $name]) {
            $team->members()->create(['role' => $role, 'name' => $name]);
        }

        return $team->fresh('members');
    }

    private function comboPayload(Team $team, array $captainDecks): array
    {
        $members = $team->members->sortBy('id')->values();
        $blank = ['line' => 'bx', 'parts' => []];

        return [
            'members' => [
                ['id' => $members[0]->id, 'beyblades' => $captainDecks],
                ['id' => $members[1]->id, 'beyblades' => [$blank, $blank, $blank]],
                ['id' => $members[2]->id, 'beyblades' => [$blank, $blank, $blank]],
            ],
        ];
    }

    public function test_saves_partial_combos_and_keeps_team_incomplete(): void
    {
        $team = $this->team();
        $payload = $this->comboPayload($team, [
            ['line' => 'bx', 'parts' => ['blade' => 'Dran Sword', 'ratchet' => '3-60', 'bit' => 'Flat']],
            ['line' => 'bx', 'parts' => []], // empty -> skipped
            ['line' => 'bx', 'parts' => []], // empty -> skipped
        ]);

        $this->put("/teams/{$team->id}/combos", $payload)->assertRedirect();

        $this->assertDatabaseCount('beyblades', 1);
        $this->assertDatabaseHas('parts', ['type' => 'blade', 'name' => 'Dran Sword']);
        $this->assertFalse($team->fresh()->isComplete());
    }

    public function test_rejects_half_filled_combo(): void
    {
        $team = $this->team();
        $payload = $this->comboPayload($team, [
            ['line' => 'bx', 'parts' => ['blade' => 'Dran Sword']], // missing ratchet + bit
            ['line' => 'bx', 'parts' => []],
            ['line' => 'bx', 'parts' => []],
        ]);

        $this->put("/teams/{$team->id}/combos", $payload)
            ->assertSessionHasErrors('members.0.beyblades.0.parts.ratchet');
        $this->assertDatabaseCount('beyblades', 0);
    }

    public function test_update_replaces_previous_combos(): void
    {
        $team = $this->team();
        $first = $this->comboPayload($team, [
            ['line' => 'bx', 'parts' => ['blade' => 'A', 'ratchet' => '3-60', 'bit' => 'Flat']],
            ['line' => 'bx', 'parts' => []],
            ['line' => 'bx', 'parts' => []],
        ]);
        $this->put("/teams/{$team->id}/combos", $first);
        $this->assertDatabaseCount('beyblades', 1);

        $second = $this->comboPayload($team, [
            ['line' => 'bx', 'parts' => []],
            ['line' => 'bx', 'parts' => []],
            ['line' => 'bx', 'parts' => []],
        ]);
        $this->put("/teams/{$team->id}/combos", $second);
        $this->assertDatabaseCount('beyblades', 0);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=TeamComboTest`
Expected: FAIL (route `/teams/{team}/combos` 404).

- [ ] **Step 3: Implement the controller**

Create `app/Http/Controllers/TeamComboController.php`:
```php
<?php

namespace App\Http\Controllers;

use App\Http\Requests\TeamComboRequest;
use App\Models\Member;
use App\Models\Part;
use App\Models\Team;
use App\Support\BeybladeLines;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class TeamComboController extends Controller
{
    public function edit(Team $team)
    {
        $team->load('members.beyblades.parts');

        return Inertia::render('Teams/Combos', [
            'team' => [
                'id' => $team->id,
                'name' => $team->name,
                'members' => $team->members->sortBy('id')->values()->map(fn ($member) => [
                    'id' => $member->id,
                    'role' => $member->role,
                    'name' => $member->name,
                    'beyblades' => $member->beyblades
                        ->sortBy('position')
                        ->values()
                        ->map(fn ($b) => [
                            'line' => $b->line,
                            'parts' => $b->partsBySlot(),
                        ]),
                ]),
            ],
            'lines' => BeybladeLines::lines(),
            'slots' => BeybladeLines::SLOTS,
        ]);
    }

    public function update(TeamComboRequest $request, Team $team)
    {
        $data = $request->validated();

        DB::transaction(function () use ($team, $data) {
            foreach ($data['members'] as $memberData) {
                /** @var Member $member */
                $member = $team->members()->findOrFail($memberData['id']);
                $member->beyblades()->delete(); // cascade clears pivots

                $position = 1;
                foreach ($memberData['beyblades'] as $beyData) {
                    if (TeamComboRequest::isEmptyCombo($beyData)) {
                        continue;
                    }
                    $beyblade = $member->beyblades()->create([
                        'line' => $beyData['line'],
                        'position' => $position++,
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
        });

        return redirect()->route('teams.show', $team)->with('success', 'Combos guardados.');
    }
}
```

- [ ] **Step 4: Add the routes**

In `routes/web.php`, add after the `Route::resource('teams', ...)` line:
```php
use App\Http\Controllers\TeamComboController;

Route::get('/teams/{team}/combos', [TeamComboController::class, 'edit'])->name('combos.edit');
Route::put('/teams/{team}/combos', [TeamComboController::class, 'update'])->name('combos.update');
```

- [ ] **Step 5: Run test to verify it passes**

Run: `php artisan test --filter=TeamComboTest`
Expected: PASS (3 tests).

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/TeamComboController.php routes/web.php tests/Feature/TeamComboTest.php
git commit -m "feat: combo registration controller with partial save (phase 2)"
```

---

## Task 5: TeamSeeder

**Files:**
- Create: `database/seeders/TeamSeeder.php`
- Modify: `database/seeders/DatabaseSeeder.php`
- Test: `tests/Feature/TeamSeederTest.php`

**Interfaces:**
- Consumes: `Team` model.
- Produces: `TeamSeeder::run()` that creates 13 teams (idempotent by team name), each with 3
  members (captain/subcaptain/official) and no beyblades.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/TeamSeederTest.php`:
```php
<?php

namespace Tests\Feature;

use App\Models\Team;
use Database\Seeders\TeamSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeamSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeds_thirteen_teams_with_members_and_no_beyblades(): void
    {
        $this->seed(TeamSeeder::class);

        $this->assertDatabaseCount('teams', 13);
        $this->assertDatabaseCount('members', 39);
        $this->assertDatabaseCount('beyblades', 0);
        $this->assertDatabaseHas('members', ['name' => 'Ricardo', 'role' => 'captain']);
    }

    public function test_seeder_is_idempotent(): void
    {
        $this->seed(TeamSeeder::class);
        $this->seed(TeamSeeder::class);

        $this->assertDatabaseCount('teams', 13);
        $this->assertDatabaseCount('members', 39);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=TeamSeederTest`
Expected: FAIL (class `Database\Seeders\TeamSeeder` not found).

- [ ] **Step 3: Implement the seeder**

Create `database/seeders/TeamSeeder.php`:
```php
<?php

namespace Database\Seeders;

use App\Models\Team;
use Illuminate\Database\Seeder;

class TeamSeeder extends Seeder
{
    /** @var array<int, array{0:string,1:string,2:string,3:string}> name, captain, subcaptain, official */
    private const TEAMS = [
        ['Xplosivos', 'Ricardo', 'Cielo azul', 'Kristen'],
        ['Kilos Mortales', 'Fernando Velasquez', 'Jonathan Paoli', 'Luis Rodrigo Guzman'],
        ['Shadow break', 'Maui Perez', 'Xavi Monzón', 'José Ramos'],
        ['Gan Gan Galaxy', 'James Bojorquez', 'Heythan', 'Don Mario'],
        ['Dragon Knights', 'Yury', 'Guillermo', 'Derek'],
        ['Bladers Fury', 'Steve Solorzano', 'Luis Rios', 'Roberto Lopez'],
        ['Equipo Zooganico', 'Rafael Quevedo', 'Deniss Camey', 'Haydee Ramirez'],
        ['Triple extinción', 'Dany Florian', 'Melman Camargo', 'Jonathan López Marroquín'],
        ['The phantom thieves', 'Carlos Gonzalez', 'Linda choc', 'Haciel Garcia'],
        ['Triada de Asgard', 'Pipo Díaz', 'Jossie Marroquín', 'Jorge Sagastume'],
        ['Team Persona', 'Robin García', 'Fernando Camargo', 'Pana Poyo'],
        ['Blazing Bahamuts', 'Dylan', 'Pablo Morales', 'Luis Mora'],
        ['Sannins', 'Ricardo Sandoval', 'Andrés Lutin', 'Renato Arellano'],
    ];

    public function run(): void
    {
        foreach (self::TEAMS as [$name, $captain, $subcaptain, $official]) {
            $team = Team::firstOrCreate(['name' => $name]);

            $team->members()->updateOrCreate(['role' => 'captain'], ['name' => $captain]);
            $team->members()->updateOrCreate(['role' => 'subcaptain'], ['name' => $subcaptain]);
            $team->members()->updateOrCreate(['role' => 'official'], ['name' => $official]);
        }
    }
}
```

- [ ] **Step 4: Register it in DatabaseSeeder**

In `database/seeders/DatabaseSeeder.php`, set the `run()` body to:
```php
    public function run(): void
    {
        $this->call(TeamSeeder::class);
    }
```

- [ ] **Step 5: Run test to verify it passes**

Run: `php artisan test --filter=TeamSeederTest`
Expected: PASS (2 tests).

- [ ] **Step 6: Commit**

```bash
git add database/seeders/TeamSeeder.php database/seeders/DatabaseSeeder.php tests/Feature/TeamSeederTest.php
git commit -m "feat: seed initial tournament teams"
```

---

## Task 6: Teams/Create + Teams/Edit pages (names only)

**Files:**
- Modify: `resources/js/Pages/Teams/Create.vue`
- Modify: `resources/js/Pages/Teams/Edit.vue`
- Test: `resources/js/Pages/__tests__/TeamsCreate.test.js` (update), `resources/js/Pages/__tests__/TeamsEdit.test.js` (update)

**Interfaces:**
- Consumes: `useForm`, `Head`, `Link` from `@inertiajs/vue3`.
- Produces: Create posts `{ name, members:[{role,name}×3] }` to `/teams`; Edit puts the same
  shape to `/teams/{id}`, prefilled from `team.members`.

- [ ] **Step 1: Update the Create test**

Replace `resources/js/Pages/__tests__/TeamsCreate.test.js`:
```js
import { describe, it, expect, vi } from 'vitest';
import { mount } from '@vue/test-utils';
import Create from '../Teams/Create.vue';

vi.mock('@inertiajs/vue3', () => ({
    useForm: (data) => ({ ...data, post: vi.fn(), processing: false, errors: {} }),
    Link: { template: '<a><slot/></a>' },
    Head: { template: '<div><slot/></div>' },
}));

describe('Teams/Create', () => {
    it('renders a team name field and three member name fields', () => {
        const wrapper = mount(Create);
        // 1 team name + 3 member names = 4 text inputs
        expect(wrapper.findAll('input[type="text"]')).toHaveLength(4);
        expect(wrapper.text()).toContain('Capitán');
        expect(wrapper.text()).toContain('Subcapitán');
        expect(wrapper.text()).toContain('Oficial');
    });
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `npm run test -- TeamsCreate`
Expected: FAIL (old page still has MemberDeck stubs / different markup).

- [ ] **Step 3: Rewrite Create.vue (names only)**

Replace `resources/js/Pages/Teams/Create.vue`:
```vue
<script setup>
import { Head, useForm } from '@inertiajs/vue3';

const ROLES = [
    { key: 'captain', label: 'Capitán' },
    { key: 'subcaptain', label: 'Subcapitán' },
    { key: 'official', label: 'Oficial' },
];

const form = useForm({
    name: '',
    members: ROLES.map((r) => ({ role: r.key, name: '' })),
});

function submit() {
    form.post('/teams');
}
</script>

<template>
    <Head title="Registrar equipo" />

    <form class="max-w-lg space-y-6" @submit.prevent="submit">
        <div>
            <h1 class="text-3xl font-black"><span class="bx-gradient-text">Registrar equipo</span></h1>
            <p class="mt-1 text-sm text-zinc-400">Registra los nombres; los combos se agregan después.</p>
        </div>

        <div>
            <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-zinc-400">
                Nombre del equipo <span class="text-bx-magenta">*</span>
            </label>
            <input
                v-model="form.name"
                type="text"
                class="w-full rounded-md border border-white/10 bg-zinc-900 px-3 py-2 text-sm outline-none focus:border-bx-cyan focus:bx-glow"
                :class="{ 'border-bx-magenta': form.errors.name }"
            />
            <p v-if="form.errors.name" class="mt-1 text-xs text-bx-magenta">{{ form.errors.name }}</p>
        </div>

        <div v-for="(member, i) in form.members" :key="member.role">
            <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-zinc-400">
                {{ ROLES[i].label }} <span class="text-bx-magenta">*</span>
            </label>
            <input
                v-model="member.name"
                type="text"
                class="w-full rounded-md border border-white/10 bg-zinc-900 px-3 py-2 text-sm outline-none focus:border-bx-cyan focus:bx-glow"
                :class="{ 'border-bx-magenta': form.errors[`members.${i}.name`] }"
            />
            <p v-if="form.errors[`members.${i}.name`]" class="mt-1 text-xs text-bx-magenta">
                {{ form.errors[`members.${i}.name`] }}
            </p>
        </div>

        <button
            type="submit"
            :disabled="form.processing"
            class="rounded-lg bg-gradient-to-r from-bx-cyan to-bx-magenta px-6 py-3 font-bold text-zinc-950 transition hover:opacity-90 disabled:opacity-50"
        >
            Guardar equipo
        </button>
    </form>
</template>
```

- [ ] **Step 4: Run Create test to verify it passes**

Run: `npm run test -- TeamsCreate`
Expected: PASS.

- [ ] **Step 5: Update the Edit test**

Replace `resources/js/Pages/__tests__/TeamsEdit.test.js`:
```js
import { describe, it, expect, vi } from 'vitest';
import { mount } from '@vue/test-utils';
import Edit from '../Teams/Edit.vue';

vi.mock('@inertiajs/vue3', () => ({
    useForm: (data) => ({ ...data, put: vi.fn(), processing: false, errors: {} }),
    Head: { template: '<div><slot/></div>' },
    Link: { props: ['href'], template: '<a :href="href"><slot/></a>' },
}));

describe('Teams/Edit', () => {
    const team = {
        id: 7, name: 'Storm Riders',
        members: [
            { id: 1, role: 'captain', name: 'Aoi', beyblades: [] },
            { id: 2, role: 'subcaptain', name: 'Multi', beyblades: [] },
            { id: 3, role: 'official', name: 'Kazami', beyblades: [] },
        ],
    };

    it('prefills team and member names', () => {
        const wrapper = mount(Edit, { props: { team } });
        const values = wrapper.findAll('input[type="text"]').map((i) => i.element.value);
        expect(values).toEqual(['Storm Riders', 'Aoi', 'Multi', 'Kazami']);
    });
});
```

- [ ] **Step 6: Rewrite Edit.vue (names only)**

Replace `resources/js/Pages/Teams/Edit.vue`:
```vue
<script setup>
import { Head, useForm } from '@inertiajs/vue3';

const props = defineProps({ team: Object });

const ROLE_LABELS = { captain: 'Capitán', subcaptain: 'Subcapitán', official: 'Oficial' };

const form = useForm({
    name: props.team.name,
    members: props.team.members.map((m) => ({ role: m.role, name: m.name })),
});

function submit() {
    form.put(`/teams/${props.team.id}`);
}
</script>

<template>
    <Head :title="`Editar ${team.name}`" />

    <form class="max-w-lg space-y-6" @submit.prevent="submit">
        <h1 class="text-3xl font-black"><span class="bx-gradient-text">Editar nombres</span></h1>

        <div>
            <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-zinc-400">
                Nombre del equipo <span class="text-bx-magenta">*</span>
            </label>
            <input
                v-model="form.name"
                type="text"
                class="w-full rounded-md border border-white/10 bg-zinc-900 px-3 py-2 text-sm outline-none focus:border-bx-cyan focus:bx-glow"
                :class="{ 'border-bx-magenta': form.errors.name }"
            />
            <p v-if="form.errors.name" class="mt-1 text-xs text-bx-magenta">{{ form.errors.name }}</p>
        </div>

        <div v-for="(member, i) in form.members" :key="member.role">
            <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-zinc-400">
                {{ ROLE_LABELS[member.role] }} <span class="text-bx-magenta">*</span>
            </label>
            <input
                v-model="member.name"
                type="text"
                class="w-full rounded-md border border-white/10 bg-zinc-900 px-3 py-2 text-sm outline-none focus:border-bx-cyan focus:bx-glow"
                :class="{ 'border-bx-magenta': form.errors[`members.${i}.name`] }"
            />
            <p v-if="form.errors[`members.${i}.name`]" class="mt-1 text-xs text-bx-magenta">
                {{ form.errors[`members.${i}.name`] }}
            </p>
        </div>

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

- [ ] **Step 7: Run Edit test to verify it passes**

Run: `npm run test -- TeamsEdit`
Expected: PASS.

- [ ] **Step 8: Commit**

```bash
git add resources/js/Pages/Teams/Create.vue resources/js/Pages/Teams/Edit.vue resources/js/Pages/__tests__/TeamsCreate.test.js resources/js/Pages/__tests__/TeamsEdit.test.js
git commit -m "feat: names-only create and edit pages"
```

---

## Task 7: Teams/Combos page (phase 2)

**Files:**
- Create: `resources/js/Pages/Teams/Combos.vue`
- Test: `resources/js/Pages/__tests__/TeamsCombos.test.js`

**Interfaces:**
- Consumes: `useForm`, `Head`, `Link`; `MemberDeck` component.
- Produces: page building a form of `members:[{id, beyblades:[3]}]` prefilled from
  `team.members[*].beyblades` (padding each member to 3 combo slots, default line `bx`),
  putting to `/teams/{id}/combos`. Maps Inertia errors down to each `MemberDeck`.

- [ ] **Step 1: Write the failing test**

Create `resources/js/Pages/__tests__/TeamsCombos.test.js`:
```js
import { describe, it, expect, vi } from 'vitest';
import { mount } from '@vue/test-utils';
import Combos from '../Teams/Combos.vue';

vi.mock('@inertiajs/vue3', () => ({
    useForm: (data) => ({ ...data, put: vi.fn(), processing: false, errors: {} }),
    Head: { template: '<div><slot/></div>' },
    Link: { props: ['href'], template: '<a :href="href"><slot/></a>' },
}));

const stubs = {
    MemberDeck: { props: ['modelValue', 'roleLabel'], template: '<div class="md">{{ roleLabel }}:{{ modelValue.beyblades.length }}</div>' },
};

describe('Teams/Combos', () => {
    const team = {
        id: 7, name: 'Storm Riders',
        members: [
            { id: 1, role: 'captain', name: 'Aoi', beyblades: [{ line: 'cx', parts: { lock_chip: 'C' } }] },
            { id: 2, role: 'subcaptain', name: 'Multi', beyblades: [] },
            { id: 3, role: 'official', name: 'Kazami', beyblades: [] },
        ],
    };

    it('pads every member to three combo slots', () => {
        const wrapper = mount(Combos, {
            props: { team, lines: ['bx'], slots: { bx: ['blade', 'ratchet', 'bit'] } },
            global: { stubs },
        });
        const decks = wrapper.findAll('.md').map((n) => n.text());
        expect(decks).toEqual(['Capitán:3', 'Subcapitán:3', 'Oficial:3']);
    });
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `npm run test -- TeamsCombos`
Expected: FAIL (page missing).

- [ ] **Step 3: Implement the page**

Create `resources/js/Pages/Teams/Combos.vue`:
```vue
<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import MemberDeck from '../../Components/MemberDeck.vue';

const props = defineProps({ team: Object, lines: Array, slots: Object });

const ROLE_LABELS = { captain: 'Capitán', subcaptain: 'Subcapitán', official: 'Oficial' };

function padDeck(beyblades) {
    const deck = beyblades.map((b) => ({ line: b.line, parts: { ...b.parts } }));
    while (deck.length < 3) {
        deck.push({ line: 'bx', parts: {} });
    }
    return deck.slice(0, 3);
}

const form = useForm({
    members: props.team.members.map((m) => ({
        id: m.id,
        role: m.role,
        name: m.name,
        beyblades: padDeck(m.beyblades),
    })),
});

function updateMember(index, value) {
    form.members[index] = value;
}

function errorsFor(memberIndex) {
    const out = {};
    const prefix = `members.${memberIndex}.`;
    for (const [key, msg] of Object.entries(form.errors)) {
        if (key.startsWith(prefix)) out[key.slice(prefix.length)] = msg;
    }
    return out;
}

function submit() {
    form.put(`/teams/${props.team.id}/combos`);
}
</script>

<template>
    <Head :title="`Combos · ${team.name}`" />

    <form class="space-y-8" @submit.prevent="submit">
        <div>
            <Link :href="`/teams/${team.id}`" class="text-sm text-zinc-400 hover:text-bx-cyan">← {{ team.name }}</Link>
            <h1 class="mt-1 text-3xl font-black"><span class="bx-gradient-text">Registrar combos</span></h1>
            <p class="mt-1 text-sm text-zinc-400">Puedes dejar combos vacíos y completarlos luego.</p>
        </div>

        <MemberDeck
            v-for="(member, i) in form.members"
            :key="member.id"
            :model-value="member"
            :role-label="ROLE_LABELS[member.role]"
            :errors="errorsFor(i)"
            @update:model-value="(v) => updateMember(i, v)"
        />

        <button
            type="submit"
            :disabled="form.processing"
            class="rounded-lg bg-gradient-to-r from-bx-cyan to-bx-magenta px-6 py-3 font-bold text-zinc-950 transition hover:opacity-90 disabled:opacity-50"
        >
            Guardar combos
        </button>
    </form>
</template>
```

Note: `MemberDeck` shows a name input bound to `modelValue.name`; here the name is read-only
context (editing names happens in `Teams/Edit`). The name field still renders prefilled and
its edits are simply ignored by the combos endpoint (which only reads `id` + `beyblades`).

- [ ] **Step 4: Run test to verify it passes**

Run: `npm run test -- TeamsCombos`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add resources/js/Pages/Teams/Combos.vue resources/js/Pages/__tests__/TeamsCombos.test.js
git commit -m "feat: combo registration page with partial decks"
```

---

## Task 8: Teams/Show + Teams/Index (completeness badge, pending combos, links)

**Files:**
- Modify: `resources/js/Pages/Teams/Show.vue`
- Modify: `resources/js/Pages/Teams/Index.vue`
- Test: `resources/js/Pages/__tests__/TeamsIndex.test.js` (update), `resources/js/Pages/__tests__/TeamsShow.test.js` (update)

**Interfaces:**
- Consumes: prop shapes from Task 2 (`team.is_complete`, `team.beyblades_count`, members with
  `beyblades`); `SLOT_LABELS`, `LINES`.
- Produces: Index shows a per-team badge; Show shows badge, "Registrar/editar combos" link to
  `/teams/{id}/combos`, "Editar nombres" link to `/teams/{id}/edit`, and "Pendiente" for
  members without combos.

- [ ] **Step 1: Update the Index test**

Replace `resources/js/Pages/__tests__/TeamsIndex.test.js`:
```js
import { describe, it, expect, vi } from 'vitest';
import { mount } from '@vue/test-utils';
import Index from '../Teams/Index.vue';

vi.mock('@inertiajs/vue3', () => ({
    Link: { props: ['href'], template: '<a :href="href"><slot/></a>' },
    Head: { template: '<div><slot/></div>' },
}));

describe('Teams/Index', () => {
    const teams = [
        { id: 1, name: 'Storm Riders', beyblades_count: 9, is_complete: true },
        { id: 2, name: 'Dran Squad', beyblades_count: 4, is_complete: false },
    ];

    it('shows completeness badges', () => {
        const wrapper = mount(Index, { props: { teams } });
        expect(wrapper.text()).toContain('Completo');
        expect(wrapper.text()).toContain('Incompleto 4/9');
    });

    it('filters by name', async () => {
        const wrapper = mount(Index, { props: { teams } });
        await wrapper.find('input').setValue('storm');
        expect(wrapper.text()).toContain('Storm Riders');
        expect(wrapper.text()).not.toContain('Dran Squad');
    });
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `npm run test -- TeamsIndex`
Expected: FAIL (no badge markup).

- [ ] **Step 3: Update Index.vue (add badge)**

Replace the `<Link>` card block in `resources/js/Pages/Teams/Index.vue` (the
`v-for="team in filtered"` link) with:
```vue
        <Link
            v-for="team in filtered"
            :key="team.id"
            :href="`/teams/${team.id}`"
            class="group rounded-xl border border-white/10 bg-zinc-900/50 p-5 transition hover:border-bx-cyan hover:bx-glow"
        >
            <div class="flex items-start justify-between gap-2">
                <h2 class="text-lg font-bold group-hover:text-bx-cyan">{{ team.name }}</h2>
                <span
                    v-if="team.is_complete"
                    class="shrink-0 rounded-full bg-bx-cyan/15 px-2 py-0.5 text-xs font-bold text-bx-cyan"
                >
                    Completo
                </span>
                <span
                    v-else
                    class="shrink-0 rounded-full bg-bx-orange/15 px-2 py-0.5 text-xs font-bold text-bx-orange"
                >
                    Incompleto {{ team.beyblades_count }}/9
                </span>
            </div>
        </Link>
```

- [ ] **Step 4: Run Index test to verify it passes**

Run: `npm run test -- TeamsIndex`
Expected: PASS (2 tests).

- [ ] **Step 5: Update the Show test**

Replace `resources/js/Pages/__tests__/TeamsShow.test.js`:
```js
import { describe, it, expect, vi } from 'vitest';
import { mount } from '@vue/test-utils';
import Show from '../Teams/Show.vue';

vi.mock('@inertiajs/vue3', () => ({
    Link: { props: ['href'], template: '<a :href="href"><slot/></a>' },
    Head: { template: '<div><slot/></div>' },
    router: { delete: vi.fn() },
}));

describe('Teams/Show', () => {
    const team = {
        id: 7, name: 'Storm Riders', is_complete: false, beyblades_count: 1,
        members: [
            {
                id: 1, role: 'captain', name: 'Aoi',
                beyblades: [{ id: 1, line: 'bx', position: 1, parts: { blade: 'Dran Sword', ratchet: '3-60', bit: 'Flat' } }],
            },
            { id: 2, role: 'subcaptain', name: 'Multi', beyblades: [] },
            { id: 3, role: 'official', name: 'Kazami', beyblades: [] },
        ],
    };

    it('shows parts, pending state, badge and combos link', () => {
        const wrapper = mount(Show, { props: { team } });
        expect(wrapper.text()).toContain('Dran Sword');
        expect(wrapper.text()).toContain('Pendiente');
        expect(wrapper.text()).toContain('Incompleto 1/9');
        expect(wrapper.find('a[href="/teams/7/combos"]').exists()).toBe(true);
    });
});
```

- [ ] **Step 6: Run test to verify it fails**

Run: `npm run test -- TeamsShow`
Expected: FAIL (no pending/badge/combos link markup).

- [ ] **Step 7: Rewrite Show.vue**

Replace `resources/js/Pages/Teams/Show.vue`:
```vue
<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import { SLOT_LABELS, LINES } from '../../lib/beybladeLines';

const props = defineProps({ team: { type: Object, required: true } });

const ROLE_LABELS = { captain: 'Capitán', subcaptain: 'Subcapitán', official: 'Oficial' };

function lineLabel(value) {
    return LINES.find((l) => l.value === value)?.label ?? value;
}

function destroy() {
    if (confirm('¿Eliminar este equipo? Esta acción no se puede deshacer.')) {
        router.delete(`/teams/${props.team.id}`);
    }
}
</script>

<template>
    <Head :title="team.name" />

    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div>
            <Link href="/teams" class="text-sm text-zinc-400 hover:text-bx-cyan">← Equipos</Link>
            <div class="mt-1 flex items-center gap-3">
                <h1 class="text-3xl font-black">{{ team.name }}</h1>
                <span
                    v-if="team.is_complete"
                    class="rounded-full bg-bx-cyan/15 px-2 py-0.5 text-xs font-bold text-bx-cyan"
                >
                    Completo
                </span>
                <span
                    v-else
                    class="rounded-full bg-bx-orange/15 px-2 py-0.5 text-xs font-bold text-bx-orange"
                >
                    Incompleto {{ team.beyblades_count }}/9
                </span>
            </div>
        </div>
        <div class="flex gap-2">
            <Link
                :href="`/teams/${team.id}/combos`"
                class="rounded-lg bg-gradient-to-r from-bx-cyan to-bx-magenta px-4 py-2 text-sm font-bold text-zinc-950 transition hover:opacity-90"
            >
                Registrar/editar combos
            </Link>
            <Link
                :href="`/teams/${team.id}/edit`"
                class="rounded-lg border border-white/15 px-4 py-2 text-sm font-semibold hover:border-bx-cyan hover:text-bx-cyan"
            >
                Editar nombres
            </Link>
            <button
                class="rounded-lg border border-bx-magenta/50 px-4 py-2 text-sm font-semibold text-bx-magenta hover:bg-bx-magenta/10"
                @click="destroy"
            >
                Eliminar
            </button>
        </div>
    </div>

    <div class="space-y-6">
        <section
            v-for="member in team.members"
            :key="member.id"
            class="rounded-xl border border-white/10 bg-zinc-900/40 p-5"
        >
            <div class="mb-4 flex items-center gap-3">
                <span class="rounded bg-gradient-to-r from-bx-cyan to-bx-magenta px-2 py-0.5 text-xs font-black uppercase text-zinc-950">
                    {{ ROLE_LABELS[member.role] }}
                </span>
                <span class="text-lg font-bold">{{ member.name }}</span>
            </div>

            <div v-if="member.beyblades.length" class="grid gap-4 sm:grid-cols-3">
                <div
                    v-for="combo in member.beyblades"
                    :key="combo.id"
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
            <p v-else class="text-sm text-zinc-500">Pendiente — sin combos registrados.</p>
        </section>
    </div>
</template>
```

- [ ] **Step 8: Run Show test to verify it passes**

Run: `npm run test -- TeamsShow`
Expected: PASS.

- [ ] **Step 9: Commit**

```bash
git add resources/js/Pages/Teams/Show.vue resources/js/Pages/Teams/Index.vue resources/js/Pages/__tests__/TeamsIndex.test.js resources/js/Pages/__tests__/TeamsShow.test.js
git commit -m "feat: completeness badge, pending state and combos link"
```

---

## Task 9: Full verification + run seeder

**Files:** none (verification)

- [ ] **Step 1: Run both test suites**

Run: `php artisan test` then `npm run test`
Expected: all PASS.

- [ ] **Step 2: Build assets**

Run: `npm run build`
Expected: build succeeds.

- [ ] **Step 3: Seed the real database and verify counts**

Run:
```bash
php artisan db:seed --class=TeamSeeder
php artisan tinker --execute='echo "teams=".App\Models\Team::count()." members=".App\Models\Member::count()." beyblades=".App\Models\Beyblade::count();'
```
Expected: `teams=13 members=39 beyblades=0`.

- [ ] **Step 4: Manual walk-through**

Start `php artisan serve` and in the browser:
1. `/teams` lists 13 seeded teams, all with "Incompleto 0/9".
2. Open a team → "Pendiente" under each member; click "Registrar/editar combos".
3. Fill only the captain's first combo (BX) → save → returns to detail showing that combo,
   badge "Incompleto 1/9", others "Pendiente".
4. "Editar nombres" → change a member name → save → reflected in detail.
5. Register a new team via "+ Registrar equipo" (names only) → appears in the list.

- [ ] **Step 5: Final commit**

```bash
git add -A
git commit -m "chore: two-phase team registration complete"
```

---

## Notes

- The combo flow intentionally wipes and reinserts a team's beyblades on each save; with at
  most 9 rows per team this is simpler and safer than diffing, and keeps `position` contiguous.
- `MemberDeck` is reused unchanged in `Teams/Combos`; it renders a member name input that is
  not used by the combo endpoint (names are edited in `Teams/Edit`). This avoids a second
  component variant. If desired later, a `hideName` prop could suppress it.

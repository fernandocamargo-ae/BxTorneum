# BxTorneum — Registro de equipos y combos de Beyblade X — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Construir una app web pública (sin login) para registrar equipos de un torneo de Beyblade X (3 miembros por equipo, deck de 3 Beyblades por miembro) y consultarlos (listar, ver detalle, editar, borrar).

**Architecture:** Laravel 12 sirve páginas Vue 3 vía Inertia.js (sin API REST separada). Los controladores devuelven `Inertia::render` con props; las mutaciones van por formularios Inertia validados con Form Requests. El catálogo de piezas es híbrido (buscar-o-crear) con un endpoint JSON de autocompletado. La estética es la de Beyblade X (tema oscuro, acentos neón).

**Tech Stack:** Laravel 12, PHP 8.2, MySQL, Inertia.js (`inertiajs/inertia-laravel` + `@inertiajs/vue3`), Vue 3, Vite 7, Tailwind CSS 4, Vitest + Vue Test Utils (tests frontend), PHPUnit (tests backend).

## Global Constraints

- PHP `^8.2`, Laravel `^12.0` (ya instalados).
- Base de datos **MySQL** (cambiar `DB_CONNECTION` de `sqlite` a `mysql` en `.env`).
- Sin autenticación; todas las rutas públicas.
- Reglas de negocio duras (validadas en backend y reflejadas en frontend):
  - Un equipo = exactamente 3 miembros, roles únicos: `captain`, `subcaptain`, `official`.
  - Cada miembro = exactamente 3 Beyblades (`position` 1..3, única por miembro).
  - Cada Beyblade tiene una `line` que determina sus slots obligatorios (ver mapa).
  - `ratchet` es opcional solo en líneas Infinity (`bx_infinity`, `ux_infinity`).
- Mapa línea → slots (fuente de verdad, replicado en PHP y JS):
  - `bx`, `ux` → `[blade, ratchet, bit]`
  - `bx_infinity`, `ux_infinity` → `[blade, ratchet?, bit]`
  - `cx` → `[lock_chip, main_blade, assist_blade, ratchet, bit]`
  - `cx_infinity` → `[lock_chip, over_blade, metal_blade, assist_blade, ratchet, bit]`
- Tipos de pieza (`parts.type`): `blade, ratchet, bit, lock_chip, main_blade, assist_blade, over_blade, metal_blade`.
- Tema oscuro Beyblade X: base `zinc-950`, acentos cyan / magenta / orange, motivo "X", titulares bold/condensados.

## File Structure

**Backend (Laravel):**
- `database/migrations/*_create_teams_table.php` — tabla `teams`.
- `database/migrations/*_create_members_table.php` — tabla `members`.
- `database/migrations/*_create_parts_table.php` — tabla `parts`.
- `database/migrations/*_create_beyblades_table.php` — tabla `beyblades`.
- `database/migrations/*_create_beyblade_part_table.php` — pivote `beyblade_part`.
- `app/Support/BeybladeLines.php` — fuente de verdad PHP línea→slots.
- `app/Models/{Team,Member,Beyblade,Part}.php` — modelos + relaciones.
- `app/Http/Controllers/TeamController.php` — CRUD vía Inertia.
- `app/Http/Controllers/PartController.php` — endpoint de autocompletado.
- `app/Http/Requests/TeamRequest.php` — validación de equipo completo.
- `routes/web.php` — rutas.
- `tests/Feature/TeamRegistrationTest.php`, `tests/Feature/PartSearchTest.php` — feature tests.

**Frontend (Inertia + Vue):**
- `resources/views/app.blade.php` — root view de Inertia.
- `resources/js/app.js` — bootstrap de Inertia/Vue.
- `resources/css/app.css` — tema Tailwind Beyblade X.
- `resources/js/lib/beybladeLines.js` — fuente de verdad JS línea→slots.
- `resources/js/Layouts/AppLayout.vue` — layout con navbar.
- `resources/js/Components/PartAutocomplete.vue` — input con autocompletado híbrido.
- `resources/js/Components/BeybladeForm.vue` — un combo, campos dinámicos por línea.
- `resources/js/Components/MemberDeck.vue` — un miembro + sus 3 combos.
- `resources/js/Pages/Teams/{Index,Show,Create,Edit}.vue` — páginas.
- `resources/js/lib/__tests__/beybladeLines.test.js`, `resources/js/Components/__tests__/*.test.js` — tests Vitest.
- `vite.config.js`, `vitest.config.js`, `package.json` — config build/test.

---

## Task 0: Project setup (git, MySQL, Inertia, Vue, Vitest)

**Files:**
- Modify: `.env`, `vite.config.js`, `package.json`, `resources/js/app.js`, `bootstrap/app.php`
- Create: `resources/views/app.blade.php`, `vitest.config.js`, `resources/js/lib/__tests__/sanity.test.js`

**Interfaces:**
- Produces: stack Inertia+Vue operativo; `Inertia::render('Page', props)` renderiza `resources/js/Pages/Page.vue`; `npm run test` ejecuta Vitest.

- [ ] **Step 1: Init git so commits work**

```bash
cd "c:/Users/Fernando Camargo/Desktop/BxTorneum"
git init
git add -A
git commit -m "chore: baseline Laravel 12 skeleton"
```

- [ ] **Step 2: Point .env to MySQL**

Edit `.env`: set `DB_CONNECTION=mysql` and uncomment/set:
```
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=bxtorneum
DB_USERNAME=root
DB_PASSWORD=
```
Create the database: `mysql -u root -e "CREATE DATABASE IF NOT EXISTS bxtorneum;"` (or via your MySQL client).

- [ ] **Step 3: Install Inertia server-side adapter**

```bash
composer require inertiajs/inertia-laravel
php artisan inertia:middleware
```

Register the middleware in `bootstrap/app.php` inside `withMiddleware`:
```php
->withMiddleware(function (Middleware $middleware): void {
    $middleware->web(append: [
        \App\Http\Middleware\HandleInertiaRequests::class,
    ]);
})
```

- [ ] **Step 4: Create the Inertia root view**

Create `resources/views/app.blade.php`:
```blade
<!DOCTYPE html>
<html lang="es" class="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title inertia>BxTorneum</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @inertiaHead
</head>
<body class="bg-zinc-950 text-zinc-100 antialiased">
    @inertia
</body>
</html>
```

- [ ] **Step 5: Install Vue + Inertia client + Vitest**

```bash
npm install vue @inertiajs/vue3
npm install -D @vitejs/plugin-vue vitest @vue/test-utils jsdom
```

- [ ] **Step 6: Wire Vue plugin into Vite**

Replace `vite.config.js`:
```js
import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';
import vue from '@vitejs/plugin-vue';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
        }),
        vue(),
        tailwindcss(),
    ],
    server: {
        watch: { ignored: ['**/storage/framework/views/**'] },
    },
});
```

- [ ] **Step 7: Bootstrap Inertia in app.js**

Replace `resources/js/app.js`:
```js
import './bootstrap';
import { createApp, h } from 'vue';
import { createInertiaApp } from '@inertiajs/vue3';
import AppLayout from './Layouts/AppLayout.vue';

createInertiaApp({
    title: (title) => (title ? `${title} · BxTorneum` : 'BxTorneum'),
    resolve: (name) => {
        const pages = import.meta.glob('./Pages/**/*.vue', { eager: true });
        const page = pages[`./Pages/${name}.vue`];
        page.default.layout = page.default.layout ?? AppLayout;
        return page;
    },
    setup({ el, App, props, plugin }) {
        createApp({ render: () => h(App, props) })
            .use(plugin)
            .mount(el);
    },
});
```

- [ ] **Step 8: Add the Vitest config and test script**

Create `vitest.config.js`:
```js
import { defineConfig } from 'vitest/config';
import vue from '@vitejs/plugin-vue';

export default defineConfig({
    plugins: [vue()],
    test: { environment: 'jsdom', globals: true },
});
```

Add to `package.json` `"scripts"`:
```json
"test": "vitest run"
```

- [ ] **Step 9: Sanity test to confirm Vitest runs**

Create `resources/js/lib/__tests__/sanity.test.js`:
```js
import { describe, it, expect } from 'vitest';

describe('sanity', () => {
    it('runs vitest', () => {
        expect(1 + 1).toBe(2);
    });
});
```

- [ ] **Step 10: Run the sanity test**

Run: `npm run test`
Expected: PASS (1 test passed).

- [ ] **Step 11: Commit**

```bash
git add -A
git commit -m "chore: set up Inertia + Vue + Vitest + MySQL"
```

---

## Task 1: Database migrations

**Files:**
- Create: 5 migrations under `database/migrations/`
- Test: covered indirectly by Task 4/5 feature tests (migrations run in test setup)

**Interfaces:**
- Produces: tables `teams`, `members`, `parts`, `beyblades`, `beyblade_part` with the columns, enums, FKs and unique constraints from Global Constraints.

- [ ] **Step 1: Create migration files**

```bash
php artisan make:migration create_teams_table
php artisan make:migration create_members_table
php artisan make:migration create_parts_table
php artisan make:migration create_beyblades_table
php artisan make:migration create_beyblade_part_table
```

- [ ] **Step 2: Fill `create_teams_table`**

```php
public function up(): void
{
    Schema::create('teams', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->timestamps();
    });
}
public function down(): void { Schema::dropIfExists('teams'); }
```

- [ ] **Step 3: Fill `create_members_table`**

```php
public function up(): void
{
    Schema::create('members', function (Blueprint $table) {
        $table->id();
        $table->foreignId('team_id')->constrained()->cascadeOnDelete();
        $table->enum('role', ['captain', 'subcaptain', 'official']);
        $table->string('name');
        $table->timestamps();
        $table->unique(['team_id', 'role']);
    });
}
public function down(): void { Schema::dropIfExists('members'); }
```

- [ ] **Step 4: Fill `create_parts_table`**

```php
public function up(): void
{
    Schema::create('parts', function (Blueprint $table) {
        $table->id();
        $table->enum('type', [
            'blade', 'ratchet', 'bit', 'lock_chip',
            'main_blade', 'assist_blade', 'over_blade', 'metal_blade',
        ]);
        $table->string('name');
        $table->timestamps();
        $table->unique(['type', 'name']);
    });
}
public function down(): void { Schema::dropIfExists('parts'); }
```

- [ ] **Step 5: Fill `create_beyblades_table`**

```php
public function up(): void
{
    Schema::create('beyblades', function (Blueprint $table) {
        $table->id();
        $table->foreignId('member_id')->constrained()->cascadeOnDelete();
        $table->enum('line', ['bx', 'ux', 'bx_infinity', 'ux_infinity', 'cx', 'cx_infinity']);
        $table->unsignedTinyInteger('position');
        $table->timestamps();
        $table->unique(['member_id', 'position']);
    });
}
public function down(): void { Schema::dropIfExists('beyblades'); }
```

- [ ] **Step 6: Fill `create_beyblade_part_table`**

```php
public function up(): void
{
    Schema::create('beyblade_part', function (Blueprint $table) {
        $table->id();
        $table->foreignId('beyblade_id')->constrained()->cascadeOnDelete();
        $table->foreignId('part_id')->constrained();
        $table->enum('slot', [
            'blade', 'ratchet', 'bit', 'lock_chip',
            'main_blade', 'assist_blade', 'over_blade', 'metal_blade',
        ]);
        $table->timestamps();
        $table->unique(['beyblade_id', 'slot']);
    });
}
public function down(): void { Schema::dropIfExists('beyblade_part'); }
```

- [ ] **Step 7: Run migrations**

Run: `php artisan migrate`
Expected: all 5 tables created without error.

- [ ] **Step 8: Commit**

```bash
git add database/migrations
git commit -m "feat: add teams, members, beyblades, parts schema"
```

---

## Task 2: Shared line→slots source of truth (PHP + JS)

**Files:**
- Create: `app/Support/BeybladeLines.php`
- Create: `resources/js/lib/beybladeLines.js`
- Test: `resources/js/lib/__tests__/beybladeLines.test.js`, `tests/Unit/BeybladeLinesTest.php`

**Interfaces:**
- Produces (PHP): `BeybladeLines::SLOTS` (array line => [slots]), `BeybladeLines::OPTIONAL` (array line => [optional slots]), `BeybladeLines::lines(): array`, `BeybladeLines::slotsFor(string $line): array`, `BeybladeLines::isOptional(string $line, string $slot): bool`.
- Produces (JS): `LINES` (array of `{value,label}`), `SLOTS` (object line→slot[]), `OPTIONAL` (object line→slot[]), `slotsFor(line)`, `isOptional(line, slot)`, `SLOT_LABELS` (object slot→label).

- [ ] **Step 1: Write the failing JS test**

Create `resources/js/lib/__tests__/beybladeLines.test.js`:
```js
import { describe, it, expect } from 'vitest';
import { slotsFor, isOptional, LINES, SLOTS } from '../beybladeLines';

describe('beybladeLines', () => {
    it('returns standard slots for bx', () => {
        expect(slotsFor('bx')).toEqual(['blade', 'ratchet', 'bit']);
    });
    it('returns six slots for cx_infinity', () => {
        expect(slotsFor('cx_infinity')).toEqual([
            'lock_chip', 'over_blade', 'metal_blade', 'assist_blade', 'ratchet', 'bit',
        ]);
    });
    it('marks ratchet optional only for infinity lines', () => {
        expect(isOptional('bx_infinity', 'ratchet')).toBe(true);
        expect(isOptional('bx', 'ratchet')).toBe(false);
        expect(isOptional('cx_infinity', 'ratchet')).toBe(false);
    });
    it('exposes one entry per line', () => {
        expect(LINES.map((l) => l.value)).toEqual(Object.keys(SLOTS));
    });
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `npm run test -- beybladeLines`
Expected: FAIL (cannot find module `../beybladeLines`).

- [ ] **Step 3: Implement the JS module**

Create `resources/js/lib/beybladeLines.js`:
```js
export const SLOTS = {
    bx: ['blade', 'ratchet', 'bit'],
    ux: ['blade', 'ratchet', 'bit'],
    bx_infinity: ['blade', 'ratchet', 'bit'],
    ux_infinity: ['blade', 'ratchet', 'bit'],
    cx: ['lock_chip', 'main_blade', 'assist_blade', 'ratchet', 'bit'],
    cx_infinity: ['lock_chip', 'over_blade', 'metal_blade', 'assist_blade', 'ratchet', 'bit'],
};

export const OPTIONAL = {
    bx_infinity: ['ratchet'],
    ux_infinity: ['ratchet'],
};

export const LINES = [
    { value: 'bx', label: 'BX' },
    { value: 'ux', label: 'UX' },
    { value: 'bx_infinity', label: 'BX Infinity' },
    { value: 'ux_infinity', label: 'UX Infinity' },
    { value: 'cx', label: 'CX' },
    { value: 'cx_infinity', label: 'CX Infinity' },
];

export const SLOT_LABELS = {
    blade: 'Blade',
    ratchet: 'Ratchet',
    bit: 'Bit',
    lock_chip: 'Lock Chip',
    main_blade: 'Main Blade',
    assist_blade: 'Assist Blade',
    over_blade: 'Over Blade',
    metal_blade: 'Metal Blade',
};

export function slotsFor(line) {
    return SLOTS[line] ?? [];
}

export function isOptional(line, slot) {
    return (OPTIONAL[line] ?? []).includes(slot);
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `npm run test -- beybladeLines`
Expected: PASS (4 tests).

- [ ] **Step 5: Write the failing PHP test**

```bash
php artisan make:test BeybladeLinesTest --unit
```
Replace `tests/Unit/BeybladeLinesTest.php`:
```php
<?php

namespace Tests\Unit;

use App\Support\BeybladeLines;
use PHPUnit\Framework\TestCase;

class BeybladeLinesTest extends TestCase
{
    public function test_returns_standard_slots_for_bx(): void
    {
        $this->assertSame(['blade', 'ratchet', 'bit'], BeybladeLines::slotsFor('bx'));
    }

    public function test_ratchet_optional_only_for_infinity(): void
    {
        $this->assertTrue(BeybladeLines::isOptional('bx_infinity', 'ratchet'));
        $this->assertFalse(BeybladeLines::isOptional('bx', 'ratchet'));
        $this->assertFalse(BeybladeLines::isOptional('cx_infinity', 'ratchet'));
    }
}
```

- [ ] **Step 6: Run test to verify it fails**

Run: `php artisan test --filter=BeybladeLinesTest`
Expected: FAIL (class `App\Support\BeybladeLines` not found).

- [ ] **Step 7: Implement the PHP class**

Create `app/Support/BeybladeLines.php`:
```php
<?php

namespace App\Support;

class BeybladeLines
{
    public const SLOTS = [
        'bx' => ['blade', 'ratchet', 'bit'],
        'ux' => ['blade', 'ratchet', 'bit'],
        'bx_infinity' => ['blade', 'ratchet', 'bit'],
        'ux_infinity' => ['blade', 'ratchet', 'bit'],
        'cx' => ['lock_chip', 'main_blade', 'assist_blade', 'ratchet', 'bit'],
        'cx_infinity' => ['lock_chip', 'over_blade', 'metal_blade', 'assist_blade', 'ratchet', 'bit'],
    ];

    public const OPTIONAL = [
        'bx_infinity' => ['ratchet'],
        'ux_infinity' => ['ratchet'],
    ];

    public static function lines(): array
    {
        return array_keys(self::SLOTS);
    }

    public static function slotsFor(string $line): array
    {
        return self::SLOTS[$line] ?? [];
    }

    public static function isOptional(string $line, string $slot): bool
    {
        return in_array($slot, self::OPTIONAL[$line] ?? [], true);
    }

    /** Slots that must be present for a line (excludes optional ones). */
    public static function requiredSlotsFor(string $line): array
    {
        return array_values(array_filter(
            self::slotsFor($line),
            fn (string $slot) => ! self::isOptional($line, $slot),
        ));
    }
}
```

- [ ] **Step 8: Run test to verify it passes**

Run: `php artisan test --filter=BeybladeLinesTest`
Expected: PASS.

- [ ] **Step 9: Commit**

```bash
git add app/Support resources/js/lib tests/Unit/BeybladeLinesTest.php
git commit -m "feat: add shared line-to-slots source of truth (PHP + JS)"
```

---

## Task 3: Eloquent models + relationships

**Files:**
- Create: `app/Models/{Team,Member,Beyblade,Part}.php`
- Test: `tests/Feature/TeamModelTest.php`

**Interfaces:**
- Consumes: tables from Task 1.
- Produces:
  - `Team` hasMany `members`; `Team::members()`.
  - `Member` belongsTo `team`; hasMany `beyblades`; `$casts` none special.
  - `Beyblade` belongsTo `member`; belongsToMany `parts` with pivot `slot`; helper `partsBySlot(): array` (slot => part name).
  - `Part::firstOrCreate(['type'=>..,'name'=>..])` for hybrid catalog.
  - All models `protected $guarded = []`.

- [ ] **Step 1: Write the failing test**

```bash
php artisan make:test TeamModelTest
```
Replace `tests/Feature/TeamModelTest.php`:
```php
<?php

namespace Tests\Feature;

use App\Models\Beyblade;
use App\Models\Member;
use App\Models\Part;
use App\Models\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeamModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_team_aggregates_members_beyblades_and_parts(): void
    {
        $team = Team::create(['name' => 'Storm']);
        $member = $team->members()->create(['role' => 'captain', 'name' => 'Aoi']);
        $beyblade = $member->beyblades()->create(['line' => 'bx', 'position' => 1]);

        $blade = Part::firstOrCreate(['type' => 'blade', 'name' => 'Dran Sword']);
        $beyblade->parts()->attach($blade->id, ['slot' => 'blade']);

        $this->assertCount(1, $team->members);
        $this->assertCount(1, $member->beyblades);
        $this->assertSame('Dran Sword', $beyblade->partsBySlot()['blade']);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=TeamModelTest`
Expected: FAIL (class `App\Models\Team` not found).

- [ ] **Step 3: Implement the models**

Create `app/Models/Team.php`:
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Team extends Model
{
    protected $guarded = [];

    public function members()
    {
        return $this->hasMany(Member::class);
    }
}
```

Create `app/Models/Member.php`:
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Member extends Model
{
    protected $guarded = [];

    public function team()
    {
        return $this->belongsTo(Team::class);
    }

    public function beyblades()
    {
        return $this->hasMany(Beyblade::class);
    }
}
```

Create `app/Models/Beyblade.php`:
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Beyblade extends Model
{
    protected $guarded = [];

    public function member()
    {
        return $this->belongsTo(Member::class);
    }

    public function parts()
    {
        return $this->belongsToMany(Part::class)->withPivot('slot');
    }

    /** @return array<string,string> slot => part name */
    public function partsBySlot(): array
    {
        return $this->parts
            ->mapWithKeys(fn (Part $part) => [$part->pivot->slot => $part->name])
            ->all();
    }
}
```

Create `app/Models/Part.php`:
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Part extends Model
{
    protected $guarded = [];
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=TeamModelTest`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Models tests/Feature/TeamModelTest.php
git commit -m "feat: add Eloquent models and relationships"
```

---

## Task 4: TeamRequest validation

**Files:**
- Create: `app/Http/Requests/TeamRequest.php`
- Test: `tests/Feature/TeamRegistrationTest.php` (validation cases)

**Interfaces:**
- Consumes: `BeybladeLines` from Task 2.
- Produces: `TeamRequest` with `authorize(): true` and `rules()` validating the full nested payload:
  ```
  name: required string
  members: array size 3
  members.*.role: required in captain,subcaptain,official; distinct across members
  members.*.name: required string
  members.*.beyblades: array size 3
  members.*.beyblades.*.line: required in BeybladeLines::lines()
  members.*.beyblades.*.parts: array; for each required slot of the chosen line a non-empty string is present
  ```
  Per-line slot validation done in `withValidator()` using `BeybladeLines::requiredSlotsFor()`.

- [ ] **Step 1: Write the failing test (rejects incomplete team)**

```bash
php artisan make:test TeamRegistrationTest
```
Create `tests/Feature/TeamRegistrationTest.php`:
```php
<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeamRegistrationTest extends TestCase
{
    use RefreshDatabase;

    private function validPayload(): array
    {
        $deck = fn () => [
            ['line' => 'bx', 'parts' => ['blade' => 'Dran Sword', 'ratchet' => '3-60', 'bit' => 'Flat']],
            ['line' => 'cx', 'parts' => ['lock_chip' => 'C', 'main_blade' => 'M', 'assist_blade' => 'A', 'ratchet' => '4-80', 'bit' => 'Ball']],
            ['line' => 'bx_infinity', 'parts' => ['blade' => 'Hells', 'bit' => 'Point']],
        ];

        return [
            'name' => 'Storm Riders',
            'members' => [
                ['role' => 'captain', 'name' => 'Aoi', 'beyblades' => $deck()],
                ['role' => 'subcaptain', 'name' => 'Multi', 'beyblades' => $deck()],
                ['role' => 'official', 'name' => 'Kazami', 'beyblades' => $deck()],
            ],
        ];
    }

    public function test_rejects_team_with_missing_members(): void
    {
        $payload = $this->validPayload();
        $payload['members'] = array_slice($payload['members'], 0, 2);

        $this->post('/teams', $payload)->assertSessionHasErrors('members');
    }

    public function test_rejects_beyblade_missing_required_slot(): void
    {
        $payload = $this->validPayload();
        unset($payload['members'][0]['beyblades'][0]['parts']['ratchet']); // bx requires ratchet

        $this->post('/teams', $payload)
            ->assertSessionHasErrors('members.0.beyblades.0.parts.ratchet');
    }

    public function test_accepts_valid_team(): void
    {
        $this->post('/teams', $this->validPayload())->assertRedirect();
        $this->assertDatabaseHas('teams', ['name' => 'Storm Riders']);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=TeamRegistrationTest`
Expected: FAIL (route `/teams` not defined / 404).

- [ ] **Step 3: Implement TeamRequest**

```bash
php artisan make:request TeamRequest
```
Replace `app/Http/Requests/TeamRequest.php`:
```php
<?php

namespace App\Http\Requests;

use App\Support\BeybladeLines;
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
            'members.*.beyblades' => ['required', 'array', 'size:3'],
            'members.*.beyblades.*.line' => ['required', Rule::in(BeybladeLines::lines())],
            'members.*.beyblades.*.parts' => ['required', 'array'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            foreach ($this->input('members', []) as $mi => $member) {
                foreach ($member['beyblades'] ?? [] as $bi => $beyblade) {
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

(The `test_accepts_valid_team` assertion will pass once Task 5 adds the controller/route. After this step the two rejection tests should pass; the accept test stays red until Task 5.)

- [ ] **Step 4: Add a temporary route so validation runs**

In `routes/web.php` add (temporary, replaced in Task 5):
```php
use App\Http\Requests\TeamRequest;
Route::post('/teams', function (TeamRequest $request) {
    return redirect('/');
});
```

- [ ] **Step 5: Run the rejection tests**

Run: `php artisan test --filter=TeamRegistrationTest`
Expected: `test_rejects_team_with_missing_members` and `test_rejects_beyblade_missing_required_slot` PASS; `test_accepts_valid_team` may PASS too (redirect + no DB write → the `assertDatabaseHas` will FAIL). Leave the accept test red; Task 5 makes it green.

- [ ] **Step 6: Commit**

```bash
git add app/Http/Requests/TeamRequest.php routes/web.php tests/Feature/TeamRegistrationTest.php
git commit -m "feat: validate full team payload with per-line slot rules"
```

---

## Task 5: TeamController + routes (store/index/show/create/edit/update/destroy)

**Files:**
- Create: `app/Http/Controllers/TeamController.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/TeamRegistrationTest.php` (accept test goes green), add show/destroy assertions

**Interfaces:**
- Consumes: `TeamRequest`, models, `BeybladeLines`.
- Produces routes (named):
  - `GET /teams` → `teams.index` → `Inertia::render('Teams/Index', ['teams' => ...])`
  - `GET /teams/create` → `teams.create` → `Inertia::render('Teams/Create', ['lines' => ..., 'slots' => ...])`
  - `POST /teams` → `teams.store`
  - `GET /teams/{team}` → `teams.show` → `Inertia::render('Teams/Show', ['team' => ...])`
  - `GET /teams/{team}/edit` → `teams.edit` → `Inertia::render('Teams/Edit', ['team' => ..., 'lines' => ..., 'slots' => ...])`
  - `PUT /teams/{team}` → `teams.update`
  - `DELETE /teams/{team}` → `teams.destroy`
- Team-shape prop sent to frontend (used by Show/Edit/Index):
  ```
  { id, name, members: [ { id, role, name,
      beyblades: [ { id, line, position, parts: { slot: name, ... } } ] } ] }
  ```

- [ ] **Step 1: Extend the feature test (show + destroy)**

Append to `tests/Feature/TeamRegistrationTest.php`:
```php
    public function test_persists_nested_team_and_shows_it(): void
    {
        $this->post('/teams', $this->validPayload())->assertRedirect();

        $this->assertDatabaseHas('members', ['role' => 'captain', 'name' => 'Aoi']);
        $this->assertDatabaseHas('parts', ['type' => 'blade', 'name' => 'Dran Sword']);
        $this->assertDatabaseCount('beyblades', 9);

        $teamId = \App\Models\Team::first()->id;
        $this->get("/teams/{$teamId}")->assertOk();
    }

    public function test_deletes_team_and_cascades(): void
    {
        $this->post('/teams', $this->validPayload());
        $teamId = \App\Models\Team::first()->id;

        $this->delete("/teams/{$teamId}")->assertRedirect('/teams');
        $this->assertDatabaseCount('teams', 0);
        $this->assertDatabaseCount('beyblades', 0);
    }
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=TeamRegistrationTest`
Expected: FAIL (no controller persisting data; show route 404).

- [ ] **Step 3: Implement the controller**

```bash
php artisan make:controller TeamController
```
Replace `app/Http/Controllers/TeamController.php`:
```php
<?php

namespace App\Http\Controllers;

use App\Http\Requests\TeamRequest;
use App\Models\Part;
use App\Models\Team;
use App\Support\BeybladeLines;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class TeamController extends Controller
{
    public function index()
    {
        $teams = Team::withCount('members')
            ->orderBy('name')
            ->get(['id', 'name']);

        return Inertia::render('Teams/Index', ['teams' => $teams]);
    }

    public function create()
    {
        return Inertia::render('Teams/Create', [
            'lines' => BeybladeLines::lines(),
            'slots' => BeybladeLines::SLOTS,
        ]);
    }

    public function store(TeamRequest $request)
    {
        $team = DB::transaction(fn () => $this->persist(new Team(), $request->validated()));

        return redirect()->route('teams.show', $team)->with('success', 'Equipo registrado.');
    }

    public function show(Team $team)
    {
        return Inertia::render('Teams/Show', ['team' => $this->transform($team)]);
    }

    public function edit(Team $team)
    {
        return Inertia::render('Teams/Edit', [
            'team' => $this->transform($team),
            'lines' => BeybladeLines::lines(),
            'slots' => BeybladeLines::SLOTS,
        ]);
    }

    public function update(TeamRequest $request, Team $team)
    {
        DB::transaction(function () use ($team, $request) {
            $team->members()->delete(); // cascade removes beyblades + pivots
            $this->persist($team, $request->validated());
        });

        return redirect()->route('teams.show', $team)->with('success', 'Equipo actualizado.');
    }

    public function destroy(Team $team)
    {
        $team->delete();

        return redirect()->route('teams.index')->with('success', 'Equipo eliminado.');
    }

    private function persist(Team $team, array $data): Team
    {
        $team->fill(['name' => $data['name']])->save();

        foreach ($data['members'] as $memberData) {
            $member = $team->members()->create([
                'role' => $memberData['role'],
                'name' => $memberData['name'],
            ]);

            foreach ($memberData['beyblades'] as $i => $beyData) {
                $beyblade = $member->beyblades()->create([
                    'line' => $beyData['line'],
                    'position' => $i + 1,
                ]);

                foreach (BeybladeLines::slotsFor($beyData['line']) as $slot) {
                    $name = trim((string) ($beyData['parts'][$slot] ?? ''));
                    if ($name === '') {
                        continue; // optional slot left empty
                    }
                    $part = Part::firstOrCreate(['type' => $slot, 'name' => $name]);
                    $beyblade->parts()->attach($part->id, ['slot' => $slot]);
                }
            }
        }

        return $team;
    }

    private function transform(Team $team): array
    {
        $team->load('members.beyblades.parts');

        return [
            'id' => $team->id,
            'name' => $team->name,
            'members' => $team->members->map(fn ($member) => [
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

- [ ] **Step 4: Replace routes**

Replace `routes/web.php`:
```php
<?php

use App\Http\Controllers\PartController;
use App\Http\Controllers\TeamController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route('teams.index'));

Route::resource('teams', TeamController::class)->except(['show']);
Route::get('/teams/{team}', [TeamController::class, 'show'])->name('teams.show');

Route::get('/parts/search', [PartController::class, 'search'])->name('parts.search');
```

(Note: `PartController` is created in Task 6. If running this task's tests before Task 6, temporarily comment the parts route.)

- [ ] **Step 5: Run test to verify it passes**

Run: `php artisan test --filter=TeamRegistrationTest`
Expected: PASS (all 5 tests, once PartController route is present or commented).

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/TeamController.php routes/web.php tests/Feature/TeamRegistrationTest.php
git commit -m "feat: team CRUD controller with nested persistence via Inertia"
```

---

## Task 6: PartController autocomplete endpoint

**Files:**
- Create: `app/Http/Controllers/PartController.php`
- Test: `tests/Feature/PartSearchTest.php`

**Interfaces:**
- Consumes: `Part` model.
- Produces: `GET /parts/search?type={type}&q={query}` → JSON array of up to 10 distinct `name` strings of that `type`, case-insensitive `LIKE` match, ordered by name. Invalid/missing `type` → empty array.

- [ ] **Step 1: Write the failing test**

```bash
php artisan make:test PartSearchTest
```
Replace `tests/Feature/PartSearchTest.php`:
```php
<?php

namespace Tests\Feature;

use App\Models\Part;
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

        $this->getJson('/parts/search?type=ratchet&q=3-6')
            ->assertOk()
            ->assertExactJson(['3-60']);
    }

    public function test_invalid_type_returns_empty(): void
    {
        $this->getJson('/parts/search?type=nope&q=x')->assertOk()->assertExactJson([]);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=PartSearchTest`
Expected: FAIL (PartController / route missing).

- [ ] **Step 3: Implement the controller**

```bash
php artisan make:controller PartController
```
Replace `app/Http/Controllers/PartController.php`:
```php
<?php

namespace App\Http\Controllers;

use App\Models\Part;
use App\Support\BeybladeLines;
use Illuminate\Http\Request;

class PartController extends Controller
{
    private const TYPES = [
        'blade', 'ratchet', 'bit', 'lock_chip',
        'main_blade', 'assist_blade', 'over_blade', 'metal_blade',
    ];

    public function search(Request $request)
    {
        $type = (string) $request->query('type');
        $q = trim((string) $request->query('q', ''));

        if (! in_array($type, self::TYPES, true)) {
            return response()->json([]);
        }

        $names = Part::query()
            ->where('type', $type)
            ->when($q !== '', fn ($query) => $query->where('name', 'like', "%{$q}%"))
            ->orderBy('name')
            ->limit(10)
            ->pluck('name');

        return response()->json($names);
    }
}
```

- [ ] **Step 4: Ensure the parts route is active**

Confirm `routes/web.php` has the `/parts/search` line from Task 5 (uncomment if you commented it).

- [ ] **Step 5: Run test to verify it passes**

Run: `php artisan test --filter=PartSearchTest`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/PartController.php
git commit -m "feat: parts autocomplete search endpoint"
```

---

## Task 7: Tailwind theme + AppLayout (Beyblade X aesthetic)

**Files:**
- Modify: `resources/css/app.css`
- Create: `resources/js/Layouts/AppLayout.vue`

**Interfaces:**
- Consumes: Inertia (`Link`, `usePage`).
- Produces: `AppLayout.vue` default-exported component with a `<slot/>`; brand utility classes available (`.bx-glow`, `.bx-gradient-text`); flash message banner reading `$page.props.flash.success`.

- [ ] **Step 1: Add brand theme tokens and utilities to app.css**

Append to `resources/css/app.css`:
```css
@theme {
    --color-bx-cyan: #22d3ee;
    --color-bx-magenta: #e11d8f;
    --color-bx-orange: #fb923c;
}

@utility bx-gradient-text {
    background-image: linear-gradient(90deg, var(--color-bx-cyan), var(--color-bx-magenta), var(--color-bx-orange));
    background-clip: text;
    -webkit-background-clip: text;
    color: transparent;
}

@utility bx-glow {
    box-shadow: 0 0 0 1px rgb(34 211 238 / 0.25), 0 0 24px -6px rgb(225 29 143 / 0.45);
}
```

- [ ] **Step 2: Expose flash messages from the Inertia middleware**

In `app/Http/Middleware/HandleInertiaRequests.php`, in `share()`, merge:
```php
return [
    ...parent::share($request),
    'flash' => [
        'success' => fn () => $request->session()->get('success'),
    ],
];
```

- [ ] **Step 3: Create the layout**

Create `resources/js/Layouts/AppLayout.vue`:
```vue
<script setup>
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const page = usePage();
const flash = computed(() => page.props.flash?.success);
</script>

<template>
    <div class="min-h-screen bg-zinc-950 text-zinc-100">
        <header class="border-b border-white/10 bg-zinc-900/60 backdrop-blur">
            <div class="mx-auto flex max-w-5xl items-center justify-between px-4 py-4">
                <Link href="/teams" class="flex items-center gap-2 text-2xl font-black tracking-tight">
                    <span class="bx-gradient-text">BEYBLADE</span>
                    <span class="rounded bg-gradient-to-br from-bx-cyan to-bx-magenta px-2 leading-7 text-zinc-950">X</span>
                    <span class="text-sm font-medium text-zinc-400">Torneum</span>
                </Link>
                <Link
                    href="/teams/create"
                    class="rounded-lg bg-gradient-to-r from-bx-cyan to-bx-magenta px-4 py-2 text-sm font-bold text-zinc-950 transition hover:opacity-90"
                >
                    + Registrar equipo
                </Link>
            </div>
        </header>

        <main class="mx-auto max-w-5xl px-4 py-8">
            <div
                v-if="flash"
                class="mb-6 rounded-lg border border-bx-cyan/40 bg-bx-cyan/10 px-4 py-3 text-sm text-bx-cyan"
            >
                {{ flash }}
            </div>
            <slot />
        </main>
    </div>
</template>
```

- [ ] **Step 4: Build to verify no errors**

Run: `npm run build`
Expected: build succeeds, no Vue/Tailwind errors.

- [ ] **Step 5: Commit**

```bash
git add resources/css/app.css resources/js/Layouts app/Http/Middleware/HandleInertiaRequests.php
git commit -m "feat: Beyblade X theme tokens and app layout"
```

---

## Task 8: PartAutocomplete component

**Files:**
- Create: `resources/js/Components/PartAutocomplete.vue`
- Test: `resources/js/Components/__tests__/PartAutocomplete.test.js`

**Interfaces:**
- Consumes: `axios` (already a dependency), `SLOT_LABELS`.
- Produces: component with props `type: String`, `modelValue: String`, `label: String`, `required: Boolean`, `error: String`; emits `update:modelValue`. On input it debounces a GET to `/parts/search?type=&q=` and shows a suggestion dropdown; clicking a suggestion sets the value. Free text is always allowed (hybrid).

- [ ] **Step 1: Write the failing test**

Create `resources/js/Components/__tests__/PartAutocomplete.test.js`:
```js
import { describe, it, expect, vi, beforeEach } from 'vitest';
import { mount, flushPromises } from '@vue/test-utils';
import axios from 'axios';
import PartAutocomplete from '../PartAutocomplete.vue';

vi.mock('axios');

describe('PartAutocomplete', () => {
    beforeEach(() => vi.clearAllMocks());

    it('emits typed value and fetches suggestions', async () => {
        axios.get.mockResolvedValue({ data: ['3-60', '3-80'] });

        const wrapper = mount(PartAutocomplete, {
            props: { type: 'ratchet', modelValue: '', label: 'Ratchet' },
        });

        await wrapper.find('input').setValue('3-6');
        expect(wrapper.emitted('update:modelValue').at(-1)).toEqual(['3-6']);

        await new Promise((r) => setTimeout(r, 250));
        await flushPromises();

        expect(axios.get).toHaveBeenCalledWith('/parts/search', {
            params: { type: 'ratchet', q: '3-6' },
        });
        expect(wrapper.text()).toContain('3-60');
    });

    it('selecting a suggestion emits it', async () => {
        axios.get.mockResolvedValue({ data: ['Flat', 'Ball'] });
        const wrapper = mount(PartAutocomplete, {
            props: { type: 'bit', modelValue: '', label: 'Bit' },
        });

        await wrapper.find('input').setValue('Fl');
        await new Promise((r) => setTimeout(r, 250));
        await flushPromises();

        await wrapper.findAll('li')[0].trigger('mousedown');
        expect(wrapper.emitted('update:modelValue').at(-1)).toEqual(['Flat']);
    });
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `npm run test -- PartAutocomplete`
Expected: FAIL (cannot find component).

- [ ] **Step 3: Implement the component**

Create `resources/js/Components/PartAutocomplete.vue`:
```vue
<script setup>
import axios from 'axios';
import { ref, watch } from 'vue';

const props = defineProps({
    type: { type: String, required: true },
    modelValue: { type: String, default: '' },
    label: { type: String, default: '' },
    required: { type: Boolean, default: false },
    error: { type: String, default: '' },
});
const emit = defineEmits(['update:modelValue']);

const suggestions = ref([]);
const open = ref(false);
let timer = null;

function onInput(event) {
    const value = event.target.value;
    emit('update:modelValue', value);
    clearTimeout(timer);
    if (value.trim() === '') {
        suggestions.value = [];
        open.value = false;
        return;
    }
    timer = setTimeout(() => fetchSuggestions(value), 200);
}

async function fetchSuggestions(q) {
    const { data } = await axios.get('/parts/search', {
        params: { type: props.type, q },
    });
    suggestions.value = data;
    open.value = data.length > 0;
}

function select(name) {
    emit('update:modelValue', name);
    open.value = false;
}

watch(() => props.modelValue, () => {});
</script>

<template>
    <div class="relative">
        <label v-if="label" class="mb-1 block text-xs font-semibold uppercase tracking-wide text-zinc-400">
            {{ label }}
            <span v-if="required" class="text-bx-magenta">*</span>
            <span v-else class="text-zinc-600">(opcional)</span>
        </label>
        <input
            :value="modelValue"
            type="text"
            autocomplete="off"
            class="w-full rounded-md border border-white/10 bg-zinc-900 px-3 py-2 text-sm text-zinc-100 outline-none focus:border-bx-cyan focus:bx-glow"
            :class="{ 'border-bx-magenta': error }"
            @input="onInput"
            @focus="open = suggestions.length > 0"
            @blur="open = false"
        />
        <ul
            v-if="open"
            class="absolute z-20 mt-1 max-h-44 w-full overflow-auto rounded-md border border-white/10 bg-zinc-900 shadow-xl"
        >
            <li
                v-for="name in suggestions"
                :key="name"
                class="cursor-pointer px-3 py-2 text-sm text-zinc-200 hover:bg-bx-cyan/20"
                @mousedown="select(name)"
            >
                {{ name }}
            </li>
        </ul>
        <p v-if="error" class="mt-1 text-xs text-bx-magenta">{{ error }}</p>
    </div>
</template>
```

- [ ] **Step 4: Run test to verify it passes**

Run: `npm run test -- PartAutocomplete`
Expected: PASS (2 tests).

- [ ] **Step 5: Commit**

```bash
git add resources/js/Components/PartAutocomplete.vue resources/js/Components/__tests__/PartAutocomplete.test.js
git commit -m "feat: hybrid part autocomplete component"
```

---

## Task 9: BeybladeForm component (dynamic fields by line)

**Files:**
- Create: `resources/js/Components/BeybladeForm.vue`
- Test: `resources/js/Components/__tests__/BeybladeForm.test.js`

**Interfaces:**
- Consumes: `LINES`, `slotsFor`, `isOptional`, `SLOT_LABELS` from `beybladeLines.js`; `PartAutocomplete`.
- Produces: component with props `modelValue: Object` (`{ line, parts }`), `errors: Object` (slot→msg), `index: Number`; emits `update:modelValue`. Renders a line `<select>` and one `PartAutocomplete` per slot of the selected line; for Infinity lines renders a "¿Lleva ratchet?" checkbox that toggles the ratchet field.

- [ ] **Step 1: Write the failing test**

Create `resources/js/Components/__tests__/BeybladeForm.test.js`:
```js
import { describe, it, expect } from 'vitest';
import { mount } from '@vue/test-utils';
import BeybladeForm from '../BeybladeForm.vue';

const stubs = { PartAutocomplete: { props: ['type', 'label'], template: '<div class="pa">{{ type }}</div>' } };

describe('BeybladeForm', () => {
    it('renders three slots for bx', () => {
        const wrapper = mount(BeybladeForm, {
            props: { modelValue: { line: 'bx', parts: {} }, errors: {}, index: 0 },
            global: { stubs },
        });
        const types = wrapper.findAll('.pa').map((n) => n.text());
        expect(types).toEqual(['blade', 'ratchet', 'bit']);
    });

    it('renders six slots for cx_infinity', () => {
        const wrapper = mount(BeybladeForm, {
            props: { modelValue: { line: 'cx_infinity', parts: {} }, errors: {}, index: 0 },
            global: { stubs },
        });
        expect(wrapper.findAll('.pa')).toHaveLength(6);
    });

    it('hides ratchet on infinity when toggle is off', async () => {
        const wrapper = mount(BeybladeForm, {
            props: { modelValue: { line: 'bx_infinity', parts: {} }, errors: {}, index: 0 },
            global: { stubs },
        });
        // default: ratchet hidden until toggled on
        const types = wrapper.findAll('.pa').map((n) => n.text());
        expect(types).toEqual(['blade', 'bit']);

        await wrapper.find('input[type="checkbox"]').setValue(true);
        const after = wrapper.findAll('.pa').map((n) => n.text());
        expect(after).toEqual(['blade', 'ratchet', 'bit']);
    });
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `npm run test -- BeybladeForm`
Expected: FAIL (component missing).

- [ ] **Step 3: Implement the component**

Create `resources/js/Components/BeybladeForm.vue`:
```vue
<script setup>
import { computed, ref, watch } from 'vue';
import { LINES, slotsFor, isOptional, SLOT_LABELS } from '../lib/beybladeLines';
import PartAutocomplete from './PartAutocomplete.vue';

const props = defineProps({
    modelValue: { type: Object, required: true },
    errors: { type: Object, default: () => ({}) },
    index: { type: Number, default: 0 },
});
const emit = defineEmits(['update:modelValue']);

// For infinity lines ratchet is optional; track whether the user includes it.
const includeRatchet = ref(Boolean(props.modelValue.parts?.ratchet));

const visibleSlots = computed(() => {
    const line = props.modelValue.line;
    return slotsFor(line).filter((slot) => {
        if (slot === 'ratchet' && isOptional(line, slot)) {
            return includeRatchet.value;
        }
        return true;
    });
});

function updateLine(event) {
    const line = event.target.value;
    includeRatchet.value = false;
    emit('update:modelValue', { line, parts: {} });
}

function updatePart(slot, value) {
    emit('update:modelValue', {
        ...props.modelValue,
        parts: { ...props.modelValue.parts, [slot]: value },
    });
}

function label(slot) {
    return SLOT_LABELS[slot] ?? slot;
}

function required(slot) {
    return !isOptional(props.modelValue.line, slot);
}

// When toggling ratchet off, clear its value.
watch(includeRatchet, (on) => {
    if (!on && props.modelValue.parts?.ratchet) {
        updatePart('ratchet', '');
    }
});

const hasOptionalRatchet = computed(() => isOptional(props.modelValue.line, 'ratchet'));
</script>

<template>
    <div class="rounded-lg border border-white/10 bg-zinc-900/50 p-4">
        <div class="mb-3 flex items-center justify-between">
            <span class="text-xs font-bold uppercase tracking-widest text-bx-cyan">Combo {{ index + 1 }}</span>
            <select
                :value="modelValue.line"
                class="rounded-md border border-white/10 bg-zinc-900 px-2 py-1 text-sm text-zinc-100 outline-none focus:border-bx-cyan"
                @change="updateLine"
            >
                <option v-for="line in LINES" :key="line.value" :value="line.value">{{ line.label }}</option>
            </select>
        </div>

        <label v-if="hasOptionalRatchet" class="mb-3 flex items-center gap-2 text-xs text-zinc-400">
            <input v-model="includeRatchet" type="checkbox" class="accent-bx-cyan" />
            ¿Lleva ratchet?
        </label>

        <div class="grid grid-cols-2 gap-3">
            <PartAutocomplete
                v-for="slot in visibleSlots"
                :key="slot"
                :type="slot"
                :label="label(slot)"
                :required="required(slot)"
                :model-value="modelValue.parts[slot] ?? ''"
                :error="errors[slot] ?? ''"
                @update:model-value="(v) => updatePart(slot, v)"
            />
        </div>
    </div>
</template>
```

- [ ] **Step 4: Run test to verify it passes**

Run: `npm run test -- BeybladeForm`
Expected: PASS (3 tests).

- [ ] **Step 5: Commit**

```bash
git add resources/js/Components/BeybladeForm.vue resources/js/Components/__tests__/BeybladeForm.test.js
git commit -m "feat: dynamic beyblade combo form by line"
```

---

## Task 10: MemberDeck component

**Files:**
- Create: `resources/js/Components/MemberDeck.vue`
- Test: `resources/js/Components/__tests__/MemberDeck.test.js`

**Interfaces:**
- Consumes: `BeybladeForm`.
- Produces: component with props `modelValue: Object` (`{ role, name, beyblades: [3] }`), `roleLabel: String`, `errors: Object` (keyed `beyblades.{i}.parts.{slot}` and `name`); emits `update:modelValue`. Renders the role title, a name input, and 3 `BeybladeForm`s.

- [ ] **Step 1: Write the failing test**

Create `resources/js/Components/__tests__/MemberDeck.test.js`:
```js
import { describe, it, expect } from 'vitest';
import { mount } from '@vue/test-utils';
import MemberDeck from '../MemberDeck.vue';

const stubs = { BeybladeForm: { props: ['modelValue', 'index'], template: '<div class="bf"/>' } };

describe('MemberDeck', () => {
    const member = {
        role: 'captain', name: '',
        beyblades: [{ line: 'bx', parts: {} }, { line: 'bx', parts: {} }, { line: 'bx', parts: {} }],
    };

    it('renders the role label and three combo forms', () => {
        const wrapper = mount(MemberDeck, {
            props: { modelValue: member, roleLabel: 'Capitán', errors: {} },
            global: { stubs },
        });
        expect(wrapper.text()).toContain('Capitán');
        expect(wrapper.findAll('.bf')).toHaveLength(3);
    });

    it('emits updated name', async () => {
        const wrapper = mount(MemberDeck, {
            props: { modelValue: member, roleLabel: 'Capitán', errors: {} },
            global: { stubs },
        });
        await wrapper.find('input[type="text"]').setValue('Aoi');
        expect(wrapper.emitted('update:modelValue').at(-1)[0].name).toBe('Aoi');
    });
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `npm run test -- MemberDeck`
Expected: FAIL (component missing).

- [ ] **Step 3: Implement the component**

Create `resources/js/Components/MemberDeck.vue`:
```vue
<script setup>
import BeybladeForm from './BeybladeForm.vue';

const props = defineProps({
    modelValue: { type: Object, required: true },
    roleLabel: { type: String, required: true },
    errors: { type: Object, default: () => ({}) },
});
const emit = defineEmits(['update:modelValue']);

function updateName(event) {
    emit('update:modelValue', { ...props.modelValue, name: event.target.value });
}

function updateBeyblade(index, value) {
    const beyblades = [...props.modelValue.beyblades];
    beyblades[index] = value;
    emit('update:modelValue', { ...props.modelValue, beyblades });
}

function beybladeErrors(index) {
    const out = {};
    const prefix = `beyblades.${index}.parts.`;
    for (const [key, msg] of Object.entries(props.errors)) {
        if (key.startsWith(prefix)) out[key.slice(prefix.length)] = msg;
    }
    return out;
}
</script>

<template>
    <section class="rounded-xl border border-white/10 bg-zinc-900/40 p-5">
        <div class="mb-4 flex items-center gap-3">
            <span class="rounded bg-gradient-to-r from-bx-cyan to-bx-magenta px-2 py-0.5 text-xs font-black uppercase text-zinc-950">
                {{ roleLabel }}
            </span>
        </div>

        <div class="mb-5">
            <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-zinc-400">
                Nombre del jugador <span class="text-bx-magenta">*</span>
            </label>
            <input
                :value="modelValue.name"
                type="text"
                class="w-full rounded-md border border-white/10 bg-zinc-900 px-3 py-2 text-sm text-zinc-100 outline-none focus:border-bx-cyan focus:bx-glow"
                :class="{ 'border-bx-magenta': errors.name }"
                @input="updateName"
            />
            <p v-if="errors.name" class="mt-1 text-xs text-bx-magenta">{{ errors.name }}</p>
        </div>

        <div class="space-y-4">
            <BeybladeForm
                v-for="(beyblade, i) in modelValue.beyblades"
                :key="i"
                :index="i"
                :model-value="beyblade"
                :errors="beybladeErrors(i)"
                @update:model-value="(v) => updateBeyblade(i, v)"
            />
        </div>
    </section>
</template>
```

- [ ] **Step 4: Run test to verify it passes**

Run: `npm run test -- MemberDeck`
Expected: PASS (2 tests).

- [ ] **Step 5: Commit**

```bash
git add resources/js/Components/MemberDeck.vue resources/js/Components/__tests__/MemberDeck.test.js
git commit -m "feat: member deck component with 3 combos"
```

---

## Task 11: Teams/Create page + form submission

**Files:**
- Create: `resources/js/Pages/Teams/Create.vue`
- Test: `resources/js/Pages/__tests__/TeamsCreate.test.js`

**Interfaces:**
- Consumes: `useForm` from `@inertiajs/vue3`, `MemberDeck`.
- Produces: page building the nested form (`name` + 3 members each with 3 `bx`-default combos) and posting to `teams.store` via `form.post`. Maps Inertia validation errors (`members.0.beyblades.1.parts.bit`) down to `MemberDeck` via an `errorsFor(memberIndex)` helper.

- [ ] **Step 1: Write the failing test**

Create `resources/js/Pages/__tests__/TeamsCreate.test.js`:
```js
import { describe, it, expect, vi } from 'vitest';
import { mount } from '@vue/test-utils';
import Create from '../Teams/Create.vue';

vi.mock('@inertiajs/vue3', () => ({
    useForm: (data) => ({ ...data, post: vi.fn(), processing: false, errors: {} }),
    Link: { template: '<a><slot/></a>' },
    Head: { template: '<div><slot/></div>' },
}));

const stubs = { MemberDeck: { props: ['modelValue', 'roleLabel'], template: '<div class="md">{{ roleLabel }}</div>' } };

describe('Teams/Create', () => {
    it('renders three member decks with role labels', () => {
        const wrapper = mount(Create, {
            props: { lines: ['bx'], slots: { bx: ['blade', 'ratchet', 'bit'] } },
            global: { stubs },
        });
        const labels = wrapper.findAll('.md').map((n) => n.text());
        expect(labels).toEqual(['Capitán', 'Subcapitán', 'Oficial']);
    });
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `npm run test -- TeamsCreate`
Expected: FAIL (page missing).

- [ ] **Step 3: Implement the page**

Create `resources/js/Pages/Teams/Create.vue`:
```vue
<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import MemberDeck from '../../Components/MemberDeck.vue';

defineProps({ lines: Array, slots: Object });

const ROLES = [
    { key: 'captain', label: 'Capitán' },
    { key: 'subcaptain', label: 'Subcapitán' },
    { key: 'official', label: 'Oficial' },
];

function emptyBeyblade() {
    return { line: 'bx', parts: {} };
}

const form = useForm({
    name: '',
    members: ROLES.map((role) => ({
        role: role.key,
        name: '',
        beyblades: [emptyBeyblade(), emptyBeyblade(), emptyBeyblade()],
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
    form.post('/teams');
}
</script>

<template>
    <Head title="Registrar equipo" />

    <form class="space-y-8" @submit.prevent="submit">
        <div>
            <h1 class="text-3xl font-black"><span class="bx-gradient-text">Registrar equipo</span></h1>
            <p class="mt-1 text-sm text-zinc-400">3 miembros, cada uno con un deck de 3 combos.</p>
        </div>

        <div>
            <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-zinc-400">
                Nombre del equipo <span class="text-bx-magenta">*</span>
            </label>
            <input
                v-model="form.name"
                type="text"
                class="w-full max-w-md rounded-md border border-white/10 bg-zinc-900 px-3 py-2 text-sm outline-none focus:border-bx-cyan focus:bx-glow"
                :class="{ 'border-bx-magenta': form.errors.name }"
            />
            <p v-if="form.errors.name" class="mt-1 text-xs text-bx-magenta">{{ form.errors.name }}</p>
            <p v-if="form.errors.members" class="mt-1 text-xs text-bx-magenta">{{ form.errors.members }}</p>
        </div>

        <MemberDeck
            v-for="(member, i) in form.members"
            :key="ROLES[i].key"
            :model-value="member"
            :role-label="ROLES[i].label"
            :errors="errorsFor(i)"
            @update:model-value="(v) => updateMember(i, v)"
        />

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

- [ ] **Step 4: Run test to verify it passes**

Run: `npm run test -- TeamsCreate`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add resources/js/Pages/Teams/Create.vue resources/js/Pages/__tests__/TeamsCreate.test.js
git commit -m "feat: team registration page"
```

---

## Task 12: Teams/Index page

**Files:**
- Create: `resources/js/Pages/Teams/Index.vue`
- Test: `resources/js/Pages/__tests__/TeamsIndex.test.js`

**Interfaces:**
- Consumes: `Link`, `Head`; prop `teams: [{ id, name, members_count }]`.
- Produces: page listing teams as cards linking to `teams.show`, a name filter input (client-side `computed`), and an empty state.

- [ ] **Step 1: Write the failing test**

Create `resources/js/Pages/__tests__/TeamsIndex.test.js`:
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
        { id: 1, name: 'Storm Riders', members_count: 3 },
        { id: 2, name: 'Dran Squad', members_count: 3 },
    ];

    it('lists all teams', () => {
        const wrapper = mount(Index, { props: { teams } });
        expect(wrapper.text()).toContain('Storm Riders');
        expect(wrapper.text()).toContain('Dran Squad');
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
Expected: FAIL (page missing).

- [ ] **Step 3: Implement the page**

Create `resources/js/Pages/Teams/Index.vue`:
```vue
<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({ teams: { type: Array, default: () => [] } });

const query = ref('');
const filtered = computed(() =>
    props.teams.filter((t) => t.name.toLowerCase().includes(query.value.trim().toLowerCase())),
);
</script>

<template>
    <Head title="Equipos" />

    <div class="mb-6 flex items-center justify-between gap-4">
        <h1 class="text-3xl font-black"><span class="bx-gradient-text">Equipos</span></h1>
        <input
            v-model="query"
            type="search"
            placeholder="Buscar equipo…"
            class="w-56 rounded-md border border-white/10 bg-zinc-900 px-3 py-2 text-sm outline-none focus:border-bx-cyan focus:bx-glow"
        />
    </div>

    <div v-if="filtered.length" class="grid gap-4 sm:grid-cols-2">
        <Link
            v-for="team in filtered"
            :key="team.id"
            :href="`/teams/${team.id}`"
            class="group rounded-xl border border-white/10 bg-zinc-900/50 p-5 transition hover:border-bx-cyan hover:bx-glow"
        >
            <h2 class="text-lg font-bold group-hover:text-bx-cyan">{{ team.name }}</h2>
            <p class="mt-1 text-sm text-zinc-400">{{ team.members_count }} miembros</p>
        </Link>
    </div>

    <div v-else class="rounded-xl border border-dashed border-white/15 p-12 text-center text-zinc-400">
        <p class="mb-4">No hay equipos todavía.</p>
        <Link href="/teams/create" class="font-bold text-bx-cyan hover:underline">Registrar el primero →</Link>
    </div>
</template>
```

- [ ] **Step 4: Run test to verify it passes**

Run: `npm run test -- TeamsIndex`
Expected: PASS (2 tests).

- [ ] **Step 5: Commit**

```bash
git add resources/js/Pages/Teams/Index.vue resources/js/Pages/__tests__/TeamsIndex.test.js
git commit -m "feat: teams index page with filter"
```

---

## Task 13: Teams/Show page (detail + delete)

**Files:**
- Create: `resources/js/Pages/Teams/Show.vue`
- Test: `resources/js/Pages/__tests__/TeamsShow.test.js`

**Interfaces:**
- Consumes: `Link`, `Head`, `router` from `@inertiajs/vue3`; `SLOT_LABELS` from `beybladeLines.js`; prop `team` (transform shape from Task 5).
- Produces: page rendering each member with role badge, name, and a stat card per combo (line label + slot→name rows). Edit link to `teams.edit`; delete button calls `router.delete('/teams/{id}')` after `confirm`.

- [ ] **Step 1: Write the failing test**

Create `resources/js/Pages/__tests__/TeamsShow.test.js`:
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
        id: 7, name: 'Storm Riders',
        members: [{
            id: 1, role: 'captain', name: 'Aoi',
            beyblades: [{ id: 1, line: 'bx', position: 1, parts: { blade: 'Dran Sword', ratchet: '3-60', bit: 'Flat' } }],
        }],
    };

    it('shows team name, member and combo parts', () => {
        const wrapper = mount(Show, { props: { team } });
        expect(wrapper.text()).toContain('Storm Riders');
        expect(wrapper.text()).toContain('Aoi');
        expect(wrapper.text()).toContain('Dran Sword');
        expect(wrapper.text()).toContain('BX');
    });
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `npm run test -- TeamsShow`
Expected: FAIL (page missing).

- [ ] **Step 3: Implement the page**

Create `resources/js/Pages/Teams/Show.vue`:
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

    <div class="mb-6 flex items-center justify-between gap-4">
        <div>
            <Link href="/teams" class="text-sm text-zinc-400 hover:text-bx-cyan">← Equipos</Link>
            <h1 class="mt-1 text-3xl font-black">{{ team.name }}</h1>
        </div>
        <div class="flex gap-2">
            <Link
                :href="`/teams/${team.id}/edit`"
                class="rounded-lg border border-white/15 px-4 py-2 text-sm font-semibold hover:border-bx-cyan hover:text-bx-cyan"
            >
                Editar
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

            <div class="grid gap-4 sm:grid-cols-3">
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
        </section>
    </div>
</template>
```

- [ ] **Step 4: Run test to verify it passes**

Run: `npm run test -- TeamsShow`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add resources/js/Pages/Teams/Show.vue resources/js/Pages/__tests__/TeamsShow.test.js
git commit -m "feat: team detail page with delete"
```

---

## Task 14: Teams/Edit page

**Files:**
- Create: `resources/js/Pages/Teams/Edit.vue`
- Test: `resources/js/Pages/__tests__/TeamsEdit.test.js`

**Interfaces:**
- Consumes: `useForm`, `Head`, `MemberDeck`; props `team` (transform shape), `lines`, `slots`.
- Produces: page pre-filling `useForm` from `team` (mapping each beyblade's `parts` object straight in) and submitting via `form.put('/teams/{id}')`. Same `errorsFor` / `updateMember` helpers as Create.

- [ ] **Step 1: Write the failing test**

Create `resources/js/Pages/__tests__/TeamsEdit.test.js`:
```js
import { describe, it, expect, vi } from 'vitest';
import { mount } from '@vue/test-utils';
import Edit from '../Teams/Edit.vue';

vi.mock('@inertiajs/vue3', () => ({
    useForm: (data) => ({ ...data, put: vi.fn(), processing: false, errors: {} }),
    Head: { template: '<div><slot/></div>' },
    Link: { props: ['href'], template: '<a :href="href"><slot/></a>' },
}));

const stubs = { MemberDeck: { props: ['modelValue', 'roleLabel'], template: '<div class="md">{{ modelValue.name }}</div>' } };

describe('Teams/Edit', () => {
    const team = {
        id: 7, name: 'Storm Riders',
        members: [
            { id: 1, role: 'captain', name: 'Aoi', beyblades: [{ line: 'bx', parts: { blade: 'Dran' } }] },
            { id: 2, role: 'subcaptain', name: 'Multi', beyblades: [{ line: 'bx', parts: {} }] },
            { id: 3, role: 'official', name: 'Kazami', beyblades: [{ line: 'bx', parts: {} }] },
        ],
    };

    it('prefills member names', () => {
        const wrapper = mount(Edit, {
            props: { team, lines: ['bx'], slots: { bx: ['blade', 'ratchet', 'bit'] } },
            global: { stubs },
        });
        expect(wrapper.text()).toContain('Aoi');
        expect(wrapper.text()).toContain('Kazami');
    });
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `npm run test -- TeamsEdit`
Expected: FAIL (page missing).

- [ ] **Step 3: Implement the page**

Create `resources/js/Pages/Teams/Edit.vue`:
```vue
<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import MemberDeck from '../../Components/MemberDeck.vue';

const props = defineProps({ team: Object, lines: Array, slots: Object });

const ROLE_LABELS = { captain: 'Capitán', subcaptain: 'Subcapitán', official: 'Oficial' };

const form = useForm({
    name: props.team.name,
    members: props.team.members.map((member) => ({
        role: member.role,
        name: member.name,
        beyblades: member.beyblades.map((b) => ({ line: b.line, parts: { ...b.parts } })),
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
    form.put(`/teams/${props.team.id}`);
}
</script>

<template>
    <Head :title="`Editar ${team.name}`" />

    <form class="space-y-8" @submit.prevent="submit">
        <h1 class="text-3xl font-black"><span class="bx-gradient-text">Editar equipo</span></h1>

        <div>
            <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-zinc-400">
                Nombre del equipo <span class="text-bx-magenta">*</span>
            </label>
            <input
                v-model="form.name"
                type="text"
                class="w-full max-w-md rounded-md border border-white/10 bg-zinc-900 px-3 py-2 text-sm outline-none focus:border-bx-cyan focus:bx-glow"
                :class="{ 'border-bx-magenta': form.errors.name }"
            />
            <p v-if="form.errors.name" class="mt-1 text-xs text-bx-magenta">{{ form.errors.name }}</p>
        </div>

        <MemberDeck
            v-for="(member, i) in form.members"
            :key="member.role"
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
            Guardar cambios
        </button>
    </form>
</template>
```

- [ ] **Step 4: Run test to verify it passes**

Run: `npm run test -- TeamsEdit`
Expected: PASS.

- [ ] **Step 5: Run the full test suites**

Run: `npm run test` then `php artisan test`
Expected: all frontend and backend tests PASS.

- [ ] **Step 6: Commit**

```bash
git add resources/js/Pages/Teams/Edit.vue resources/js/Pages/__tests__/TeamsEdit.test.js
git commit -m "feat: team edit page"
```

---

## Task 15: End-to-end manual verification

**Files:** none (manual check)

- [ ] **Step 1: Build assets and start the app**

```bash
npm run build
php artisan serve
```

- [ ] **Step 2: Walk the happy path in the browser**

Visit `http://127.0.0.1:8000`:
1. Redirects to `/teams` (empty state).
2. Click "Registrar equipo" → fill team name, 3 members, 9 combos (try BX, CX, and a BX Infinity with the ratchet toggle off). Confirm autocomplete suggests previously typed parts.
3. Submit → lands on the team detail with all combos rendered, flash banner visible.
4. Back on `/teams`, the team appears; the filter works.
5. Edit the team, change a part, save → detail reflects the change.
6. Delete the team → returns to `/teams` empty state.

- [ ] **Step 3: Verify validation**

Submit an incomplete team (e.g. missing a bit on one combo) → inline error appears under that field and the form is not saved.

- [ ] **Step 4: Final commit**

```bash
git add -A
git commit -m "chore: BxTorneum team registry complete"
```

---

## Notes on division of labor

The original plan was for the user to build the backend to learn. The user later asked the assistant to implement everything and read the code afterward. This plan therefore covers backend + frontend with full code. Backend lives under `app/`, `database/`, `routes/`; frontend under `resources/js/`. Each is committed separately so the user can review them as distinct units.

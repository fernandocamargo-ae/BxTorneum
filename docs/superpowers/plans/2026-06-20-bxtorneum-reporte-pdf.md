# BxTorneum — Reporte global en PDF (protegido) — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Exportar un PDF global del torneo (todos los equipos, miembros y combos), generado en el servidor con dompdf y protegido por una contraseña única de `.env`.

**Architecture:** `barryvdh/laravel-dompdf` genera el PDF desde una vista Blade clara/print-friendly. `POST /report/pdf` valida la contraseña (`hash_equals` contra `config('report.password')`) antes de generar. El frontend tiene un botón "Exportar PDF" en la página de Equipos que abre un modal de contraseña y descarga vía `axios` (responseType blob).

**Tech Stack:** Laravel 12, PHP 8.2, MySQL, barryvdh/laravel-dompdf, Inertia + Vue 3, Tailwind 4, PHPUnit, Vitest.

## Global Constraints

- Contraseña única en `.env` como `REPORT_PASSWORD`, leída vía `config('report.password')` (compatible con `config:cache`).
- Descarga por `POST /report/pdf` (no GET). Comparación con `hash_equals`; si la config está vacía o no coincide → 403; falta `password` → 422.
- PDF claro/print-friendly con acentos Beyblade X; CSS compatible con dompdf (tablas, sin flex/grid).
- Reporte global: todos los equipos ordenados por nombre; miembros en orden captain → subcaptain → official; combos por posición.
- Etiquetas legibles desde `App\Support\BeybladeLines` (líneas y slots) — fuente única.

## File Structure

- `config/report.php` — **nuevo**, expone `password` desde `REPORT_PASSWORD`.
- `.env.example` — añadir `REPORT_PASSWORD=`.
- `app/Support/BeybladeLines.php` — añadir `LINE_LABELS`/`SLOT_LABELS` + `lineLabel()`/`slotLabel()`.
- `app/Http/Controllers/ReportController.php` — **nuevo**, valida contraseña y genera el PDF.
- `resources/views/reports/teams.blade.php` — **nueva**, plantilla del PDF.
- `routes/web.php` — añadir la ruta `report.pdf`.
- `tests/Feature/ReportPdfTest.php` — **nuevo**.
- `resources/js/Components/ReportExport.vue` — **nuevo**, botón + modal + descarga.
- `resources/js/Components/__tests__/ReportExport.test.js` — **nuevo**.
- `resources/js/Pages/Teams/Index.vue` — montar `<ReportExport />` en el encabezado.

---

## Task 1: Backend — dompdf, password gate, PDF view

**Files:**
- Create: `config/report.php`, `app/Http/Controllers/ReportController.php`, `resources/views/reports/teams.blade.php`, `tests/Feature/ReportPdfTest.php`
- Modify: `.env.example`, `app/Support/BeybladeLines.php`, `routes/web.php`

**Interfaces:**
- Consumes: `Team` (`with('members.beyblades.parts')`, `isComplete()`, `beybladesCount()`), `Beyblade::partsBySlot()`, `BeybladeLines`.
- Produces: `POST /teams … /report/pdf` (name `report.pdf`) returning a PDF download named `reporte-bxtorneum.pdf` when the password matches; `BeybladeLines::lineLabel(string): string` and `BeybladeLines::slotLabel(string): string`.

- [ ] **Step 1: Install dompdf**

```bash
composer require barryvdh/laravel-dompdf
```
Expected: package installed and auto-discovered (`barryvdh/laravel-dompdf … DONE`).

- [ ] **Step 2: Add label maps to BeybladeLines**

In `app/Support/BeybladeLines.php`, add these consts and methods inside the class (after the existing `OPTIONAL` const):
```php
    public const LINE_LABELS = [
        'bx' => 'BX',
        'ux' => 'UX',
        'bx_infinity' => 'BX Infinity',
        'ux_infinity' => 'UX Infinity',
        'cx' => 'CX',
        'cx_infinity' => 'CX Infinity',
    ];

    public const SLOT_LABELS = [
        'blade' => 'Blade',
        'ratchet' => 'Ratchet',
        'bit' => 'Bit',
        'lock_chip' => 'Lock Chip',
        'main_blade' => 'Main Blade',
        'assist_blade' => 'Assist Blade',
        'over_blade' => 'Over Blade',
        'metal_blade' => 'Metal Blade',
    ];

    public static function lineLabel(string $line): string
    {
        return self::LINE_LABELS[$line] ?? $line;
    }

    public static function slotLabel(string $slot): string
    {
        return self::SLOT_LABELS[$slot] ?? $slot;
    }
```

- [ ] **Step 3: Create the config file**

Create `config/report.php`:
```php
<?php

return [
    'password' => env('REPORT_PASSWORD'),
];
```

- [ ] **Step 4: Add REPORT_PASSWORD to .env.example**

In `.env.example`, add after the `DB_PASSWORD` line block (before `SESSION_DRIVER`):
```
# --- Reporte PDF ---
REPORT_PASSWORD=        # <<< COMPLETAR: contraseña para descargar el PDF del torneo
```

- [ ] **Step 5: Write the failing feature test**

Create `tests/Feature/ReportPdfTest.php`:
```php
<?php

namespace Tests\Feature;

use App\Models\Part;
use App\Models\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportPdfTest extends TestCase
{
    use RefreshDatabase;

    private function seedTeam(): void
    {
        $team = Team::create(['name' => 'Xplosivos']);
        $captain = $team->members()->create(['role' => 'captain', 'name' => 'Ricardo']);
        $team->members()->create(['role' => 'subcaptain', 'name' => 'Cielo']);
        $team->members()->create(['role' => 'official', 'name' => 'Kristen']);
        $beyblade = $captain->beyblades()->create(['line' => 'bx', 'position' => 1]);
        $blade = Part::firstOrCreate(['type' => 'blade', 'name' => 'Dran Sword']);
        $beyblade->parts()->attach($blade->id, ['slot' => 'blade']);
    }

    public function test_downloads_pdf_with_correct_password(): void
    {
        config(['report.password' => 'secret123']);
        $this->seedTeam();

        $response = $this->postJson('/report/pdf', ['password' => 'secret123']);

        $response->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('content-type'));
    }

    public function test_rejects_wrong_password(): void
    {
        config(['report.password' => 'secret123']);

        $this->postJson('/report/pdf', ['password' => 'nope'])->assertForbidden();
    }

    public function test_requires_password(): void
    {
        config(['report.password' => 'secret123']);

        $this->postJson('/report/pdf', [])->assertStatus(422);
    }

    public function test_rejects_when_no_password_configured(): void
    {
        config(['report.password' => null]);

        $this->postJson('/report/pdf', ['password' => 'anything'])->assertForbidden();
    }
}
```

- [ ] **Step 6: Run test to verify it fails**

Run: `php artisan test --filter=ReportPdfTest`
Expected: FAIL (route `/report/pdf` 404 / controller missing).

- [ ] **Step 7: Create the controller**

Create `app/Http/Controllers/ReportController.php`:
```php
<?php

namespace App\Http\Controllers;

use App\Models\Team;
use App\Support\BeybladeLines;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    private const ROLE_LABELS = [
        'captain' => 'Capitán',
        'subcaptain' => 'Subcapitán',
        'official' => 'Oficial',
    ];

    private const ROLE_ORDER = ['captain', 'subcaptain', 'official'];

    public function download(Request $request)
    {
        $request->validate(['password' => ['required', 'string']]);

        $expected = config('report.password');
        if (blank($expected) || ! hash_equals((string) $expected, (string) $request->input('password'))) {
            abort(403, 'Contraseña incorrecta.');
        }

        $teams = Team::with('members.beyblades.parts')
            ->orderBy('name')
            ->get()
            ->map(fn (Team $team) => [
                'name' => $team->name,
                'is_complete' => $team->isComplete(),
                'beyblades_count' => $team->beybladesCount(),
                'members' => $team->members
                    ->sortBy(fn ($member) => array_search($member->role, self::ROLE_ORDER, true))
                    ->values()
                    ->map(fn ($member) => [
                        'role_label' => self::ROLE_LABELS[$member->role] ?? $member->role,
                        'name' => $member->name,
                        'beyblades' => $member->beyblades
                            ->sortBy('position')
                            ->values()
                            ->map(fn ($beyblade) => [
                                'line_label' => BeybladeLines::lineLabel($beyblade->line),
                                'parts' => collect($beyblade->partsBySlot())
                                    ->map(fn ($name, $slot) => [
                                        'slot_label' => BeybladeLines::slotLabel($slot),
                                        'name' => $name,
                                    ])
                                    ->values()
                                    ->all(),
                            ])
                            ->all(),
                    ])
                    ->all(),
            ])
            ->all();

        $pdf = Pdf::loadView('reports.teams', [
            'teams' => $teams,
            'generatedAt' => now()->format('d/m/Y H:i'),
        ]);

        return $pdf->download('reporte-bxtorneum.pdf');
    }
}
```

- [ ] **Step 8: Create the PDF Blade view**

Create `resources/views/reports/teams.blade.php`:
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
        .team { padding: 6px 24px 14px; page-break-inside: avoid; }
        .team h2 { font-size: 15px; margin: 12px 0 4px; border-bottom: 2px solid #0e7490; padding-bottom: 2px; }
        .badge { font-size: 9px; font-weight: bold; padding: 1px 6px; border-radius: 8px; }
        .badge-ok { background: #cffafe; color: #0e7490; }
        .badge-no { background: #ffedd5; color: #c2410c; }
        table { width: 100%; border-collapse: collapse; margin-top: 6px; }
        th, td { border: 1px solid #e4e4e7; padding: 4px 6px; text-align: left; vertical-align: top; }
        th { background: #f4f4f5; font-size: 10px; text-transform: uppercase; letter-spacing: .5px; }
        .role { font-weight: bold; white-space: nowrap; }
        .combo { margin-bottom: 4px; }
        .combo-line { font-weight: bold; color: #0e7490; }
        .pending { color: #a1a1aa; font-style: italic; }
    </style>
</head>
<body>
    <div class="header">
        <h1>BEYBLADE <span class="x">X</span> &middot; Torneum</h1>
        <div class="sub">Reporte de equipos</div>
    </div>
    <div class="meta">Generado el {{ $generatedAt }} &middot; {{ count($teams) }} equipos</div>

    @foreach ($teams as $team)
        <div class="team">
            <h2>
                {{ $team['name'] }}
                @if ($team['is_complete'])
                    <span class="badge badge-ok">Completo</span>
                @else
                    <span class="badge badge-no">Incompleto {{ $team['beyblades_count'] }}/9</span>
                @endif
            </h2>
            <table>
                <thead>
                    <tr>
                        <th style="width: 18%">Rol</th>
                        <th style="width: 22%">Jugador</th>
                        <th>Combos</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($team['members'] as $member)
                        <tr>
                            <td class="role">{{ $member['role_label'] }}</td>
                            <td>{{ $member['name'] }}</td>
                            <td>
                                @forelse ($member['beyblades'] as $combo)
                                    <div class="combo">
                                        <span class="combo-line">{{ $combo['line_label'] }}:</span>
                                        @foreach ($combo['parts'] as $part)
                                            {{ $part['slot_label'] }} {{ $part['name'] }}@if (! $loop->last) &middot; @endif
                                        @endforeach
                                    </div>
                                @empty
                                    <span class="pending">Pendiente</span>
                                @endforelse
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endforeach
</body>
</html>
```

- [ ] **Step 9: Add the route**

In `routes/web.php`, add the import with the others and the route after the combos routes:
```php
use App\Http\Controllers\ReportController;

Route::post('/report/pdf', [ReportController::class, 'download'])->name('report.pdf');
```

- [ ] **Step 10: Run test to verify it passes**

Run: `php artisan test --filter=ReportPdfTest`
Expected: PASS (4 tests).

- [ ] **Step 11: Run the full backend suite**

Run: `php artisan test`
Expected: PASS (no regressions).

- [ ] **Step 12: Commit**

```bash
git add config/report.php app/Http/Controllers/ReportController.php resources/views/reports app/Support/BeybladeLines.php routes/web.php tests/Feature/ReportPdfTest.php .env.example
git commit -m "feat: password-protected global tournament PDF report"
```

---

## Task 2: Frontend — ReportExport component + Index button

**Files:**
- Create: `resources/js/Components/ReportExport.vue`, `resources/js/Components/__tests__/ReportExport.test.js`
- Modify: `resources/js/Pages/Teams/Index.vue`

**Interfaces:**
- Consumes: `axios` (global, already configured); the backend route `POST /report/pdf`.
- Produces: `ReportExport.vue` — a button that opens a password modal and downloads the PDF via `axios.post('/report/pdf', { password }, { responseType: 'blob' })`, showing "Contraseña incorrecta." on failure.

- [ ] **Step 1: Write the failing test**

Create `resources/js/Components/__tests__/ReportExport.test.js`:
```js
import { describe, it, expect, vi, beforeEach } from 'vitest';
import { mount, flushPromises } from '@vue/test-utils';
import axios from 'axios';
import ReportExport from '../ReportExport.vue';

vi.mock('axios');

describe('ReportExport', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        global.URL.createObjectURL = vi.fn(() => 'blob:x');
        global.URL.revokeObjectURL = vi.fn();
    });

    it('opens the modal and posts the password to download', async () => {
        axios.post.mockResolvedValue({ data: new Blob(['pdf']) });
        const wrapper = mount(ReportExport);

        await wrapper.find('button').trigger('click'); // open modal
        await wrapper.find('input[type="password"]').setValue('secret');
        await wrapper.findAll('button').at(-1).trigger('click'); // descargar
        await flushPromises();

        expect(axios.post).toHaveBeenCalledWith(
            '/report/pdf',
            { password: 'secret' },
            { responseType: 'blob' },
        );
    });

    it('shows an error when the password is rejected', async () => {
        axios.post.mockRejectedValue(new Error('403'));
        const wrapper = mount(ReportExport);

        await wrapper.find('button').trigger('click');
        await wrapper.find('input[type="password"]').setValue('bad');
        await wrapper.findAll('button').at(-1).trigger('click');
        await flushPromises();

        expect(wrapper.text()).toContain('Contraseña incorrecta');
    });
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `npm run test -- ReportExport`
Expected: FAIL (component missing).

- [ ] **Step 3: Create the component**

Create `resources/js/Components/ReportExport.vue`:
```vue
<script setup>
import axios from 'axios';
import { ref } from 'vue';

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
        const res = await axios.post('/report/pdf', { password: password.value }, { responseType: 'blob' });
        const url = URL.createObjectURL(res.data);
        const link = document.createElement('a');
        link.href = url;
        link.download = 'reporte-bxtorneum.pdf';
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

<template>
    <button
        type="button"
        class="rounded-md border border-white/15 px-4 py-2 text-sm font-semibold text-zinc-200 transition hover:border-bx-cyan hover:text-bx-cyan"
        @click="show"
    >
        Exportar PDF
    </button>

    <div
        v-if="open"
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 p-4"
        @click.self="close"
    >
        <div class="w-full max-w-sm rounded-xl border border-white/10 bg-zinc-900 p-6">
            <h2 class="mb-1 text-lg font-bold">Exportar reporte PDF</h2>
            <p class="mb-4 text-sm text-zinc-400">Ingresa la contraseña para descargar el reporte del torneo.</p>

            <input
                v-model="password"
                type="password"
                placeholder="Contraseña"
                class="w-full rounded-md border border-white/10 bg-zinc-950 px-3 py-2 text-sm outline-none focus:border-bx-cyan focus:bx-glow"
                :class="{ 'border-bx-magenta': error }"
                @keyup.enter="download"
            />
            <p v-if="error" class="mt-1 text-xs text-bx-magenta">{{ error }}</p>

            <div class="mt-5 flex justify-end gap-2">
                <button
                    type="button"
                    class="rounded-md border border-white/15 px-4 py-2 text-sm font-semibold text-zinc-300 hover:text-zinc-100"
                    @click="close"
                >
                    Cancelar
                </button>
                <button
                    type="button"
                    :disabled="processing"
                    class="rounded-md bg-gradient-to-r from-bx-cyan to-bx-magenta px-4 py-2 text-sm font-bold text-zinc-950 transition hover:opacity-90 disabled:opacity-50"
                    @click="download"
                >
                    Descargar
                </button>
            </div>
        </div>
    </div>
</template>
```

- [ ] **Step 4: Run test to verify it passes**

Run: `npm run test -- ReportExport`
Expected: PASS (2 tests).

- [ ] **Step 5: Mount ReportExport in the Index header**

In `resources/js/Pages/Teams/Index.vue`, add the import and place the button next to the search input. Update the `<script setup>` import line:
```js
import { Head, Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import ReportExport from '../../Components/ReportExport.vue';
```
Replace the header block:
```vue
    <div class="mb-6 flex items-center justify-between gap-4">
        <h1 class="text-3xl font-black"><span class="bx-gradient-text">Equipos</span></h1>
        <div class="flex items-center gap-3">
            <input
                v-model="query"
                type="search"
                placeholder="Buscar equipo…"
                class="w-56 rounded-md border border-white/10 bg-zinc-900 px-3 py-2 text-sm outline-none focus:border-bx-cyan focus:bx-glow"
            />
            <ReportExport />
        </div>
    </div>
```

- [ ] **Step 6: Run the full frontend suite**

Run: `npm run test`
Expected: PASS (all Vitest files green; `TeamsIndex` still finds its search input as the first `input`).

- [ ] **Step 7: Commit**

```bash
git add resources/js/Components/ReportExport.vue resources/js/Components/__tests__/ReportExport.test.js resources/js/Pages/Teams/Index.vue
git commit -m "feat: PDF export button with password modal on teams index"
```

---

## Task 3: Verification

**Files:** none (verification) + local `.env`

- [ ] **Step 1: Run both suites**

Run: `php artisan test` then `npm run test`
Expected: all PASS.

- [ ] **Step 2: Build**

Run: `npm run build`
Expected: build succeeds.

- [ ] **Step 3: Set a local password and start the app**

Add to the local `.env` (not committed): `REPORT_PASSWORD=demo1234`, then:
```bash
php artisan config:clear
php artisan serve
```

- [ ] **Step 4: Manual check**

In the browser on `/teams`:
1. Click "Exportar PDF" → modal opens.
2. Enter a wrong password → "Contraseña incorrecta." shown, no download.
3. Enter `demo1234` → `reporte-bxtorneum.pdf` downloads; open it: light layout, BEYBLADE X header, generation date, one block per team with the role/player/combos table and completeness badges; members without combos show "Pendiente".

- [ ] **Step 5: Final commit (if anything pending)**

```bash
git add -A
git commit -m "chore: PDF report feature complete" || echo "nothing to commit"
```

---

## Notes

- `config('report.password')` (not `env()`) is read in the controller so the gate keeps working under `config:cache` in production.
- The download uses `axios` with `responseType: 'blob'`; on any error the modal shows a fixed "Contraseña incorrecta." message (the only expected failure is a 403).
- The PDF view avoids flexbox/grid and uses table layout + DejaVu Sans for dompdf compatibility and proper accented characters (á, ñ, í).
- `REPORT_PASSWORD` must be set in the server `.env` (already added to `.env.example`); without it the endpoint returns 403 for everyone.

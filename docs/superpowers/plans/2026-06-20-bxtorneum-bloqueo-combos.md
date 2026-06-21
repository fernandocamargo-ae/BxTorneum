# BxTorneum — Bloqueo de combos registrados — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Hacer que un combo, una vez registrado, no se pueda editar ni borrar (bloqueo por combo individual): el guardado de combos pasa a ser append-only y la página muestra los combos registrados en solo-lectura.

**Architecture:** Sin cambios de esquema; el bloqueo se deriva de la existencia de la fila `beyblade`. `TeamComboController::update` deja de borrar/reinsertar y solo agrega combos nuevos a los slots vacíos (posiciones libres). `TeamComboRequest` añade la cota existentes+nuevos ≤ 3 por miembro. La página de combos separa, por miembro, los combos bloqueados (tarjetas read-only) de los slots vacíos (formularios).

**Tech Stack:** Laravel 12, PHP 8.2, MySQL, Inertia.js + Vue 3, Tailwind 4, PHPUnit, Vitest.

## Global Constraints

- Sin cambios de esquema de base de datos.
- Bloqueo por combo: un `beyblade` existente es inmutable (nunca update ni delete en el flujo de combos).
- `update` es append-only: agrega combos no vacíos a las posiciones libres del miembro (`max(position)+1 … 3`).
- Validación: combo vacío se ignora; combo no vacío requiere todas las piezas de su línea (`ratchet` opcional solo en Infinity vía `BeybladeLines::requiredSlotsFor`); además `existentes + nuevos_no_vacíos ≤ 3` por miembro.
- Piezas vía `Part::firstOrCreate(['type'=>slot,'name'=>name])`.
- Copy: el botón del detalle pasa de "Registrar/editar combos" a "Registrar combos".
- Roles: captain/subcaptain/official → Capitán/Subcapitán/Oficial.

## File Structure

- `app/Http/Requests/TeamComboRequest.php` — añadir la cota existentes+nuevos ≤ 3 en `withValidator`.
- `app/Http/Controllers/TeamComboController.php` — `update` append-only (quitar el wipe).
- `tests/Feature/TeamComboTest.php` — reescribir para el comportamiento de bloqueo/append.
- `resources/js/Components/MemberDeck.vue` — aceptar `lockedCombos` y renderizarlos read-only encima de los formularios editables.
- `resources/js/Components/__tests__/MemberDeck.test.js` — caso de combos bloqueados.
- `resources/js/Pages/Teams/Combos.vue` — separar bloqueados/editables; enviar solo combos nuevos; ocultar botón si el equipo está completo.
- `resources/js/Pages/__tests__/TeamsCombos.test.js` — actualizar al nuevo render.
- `resources/js/Pages/Teams/Show.vue` — cambio de copy del botón.

---

## Task 1: Backend append-only update + cap validation

**Files:**
- Modify: `app/Http/Requests/TeamComboRequest.php`
- Modify: `app/Http/Controllers/TeamComboController.php`
- Test: `tests/Feature/TeamComboTest.php` (rewrite)

**Interfaces:**
- Consumes: `App\Support\BeybladeLines` (`lines()`, `requiredSlotsFor()`, `slotsFor()`), models `Team`/`Member`/`Beyblade`/`Part`, `TeamComboRequest::isEmptyCombo(array): bool`.
- Produces: `PUT /teams/{team}/combos` is append-only; existing beyblades are never modified/deleted; new non-empty combos are inserted at positions `max(existing position)+1 … 3`; payload exceeding 3 per member (existing+new) is rejected with error key `members.{m}.beyblades`.

- [ ] **Step 1: Rewrite the feature test for locking/append**

Replace `tests/Feature/TeamComboTest.php` with:
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

    /** Build a PUT payload: captain gets $captainDecks, others get $others (default empty). */
    private function payload(Team $team, array $captainDecks, array $others = []): array
    {
        $members = $team->members->sortBy('id')->values();
        $blank = ['line' => 'bx', 'parts' => []];
        $others = $others ?: [$blank, $blank, $blank];

        return [
            'members' => [
                ['id' => $members[0]->id, 'beyblades' => $captainDecks],
                ['id' => $members[1]->id, 'beyblades' => $others],
                ['id' => $members[2]->id, 'beyblades' => $others],
            ],
        ];
    }

    private function bx(string $blade, string $ratchet = '3-60', string $bit = 'Flat'): array
    {
        return ['line' => 'bx', 'parts' => ['blade' => $blade, 'ratchet' => $ratchet, 'bit' => $bit]];
    }

    public function test_registers_first_combo(): void
    {
        $team = $this->team();

        $this->put("/teams/{$team->id}/combos", $this->payload($team, [$this->bx('Dran Sword')]))
            ->assertRedirect();

        $this->assertDatabaseCount('beyblades', 1);
        $this->assertDatabaseHas('parts', ['type' => 'blade', 'name' => 'Dran Sword']);
    }

    public function test_existing_combo_is_immutable_and_new_one_is_appended(): void
    {
        $team = $this->team();
        // Register combo 1 = "Aero" at position 1.
        $this->put("/teams/{$team->id}/combos", $this->payload($team, [$this->bx('Aero')]));
        $captain = $team->members->firstWhere('role', 'captain');
        $this->assertSame(1, $captain->beyblades()->count());

        // Second submit registers a new combo "Dran" — must NOT touch the existing one.
        $this->put("/teams/{$team->id}/combos", $this->payload($team, [$this->bx('Dran')]));

        $captain->refresh();
        $this->assertSame(2, $captain->beyblades()->count());
        $pos1 = $captain->beyblades()->where('position', 1)->first();
        $pos2 = $captain->beyblades()->where('position', 2)->first();
        $this->assertSame('Aero', $pos1->partsBySlot()['blade']);   // unchanged / locked
        $this->assertSame('Dran', $pos2->partsBySlot()['blade']);   // appended
    }

    public function test_rejects_half_filled_combo(): void
    {
        $team = $this->team();
        $payload = $this->payload($team, [['line' => 'bx', 'parts' => ['blade' => 'Only blade']]]);

        $this->put("/teams/{$team->id}/combos", $payload)
            ->assertSessionHasErrors('members.0.beyblades.0.parts.ratchet');
        $this->assertDatabaseCount('beyblades', 0);
    }

    public function test_empty_combos_without_line_are_ignored(): void
    {
        $team = $this->team();
        $payload = $this->payload($team, [['parts' => []], ['parts' => []], ['parts' => []]]);

        $this->put("/teams/{$team->id}/combos", $payload)->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseCount('beyblades', 0);
    }

    public function test_rejects_exceeding_three_per_member(): void
    {
        $team = $this->team();
        // Pre-register 2 combos for the captain.
        $this->put("/teams/{$team->id}/combos", $this->payload($team, [$this->bx('A')]));
        $this->put("/teams/{$team->id}/combos", $this->payload($team, [$this->bx('B')]));
        $captain = $team->members->firstWhere('role', 'captain');
        $this->assertSame(2, $captain->beyblades()->count());

        // Now submit 2 more new combos => existing(2)+new(2)=4 > 3 => rejected, no change.
        $this->put("/teams/{$team->id}/combos", $this->payload($team, [$this->bx('C'), $this->bx('D')]))
            ->assertSessionHasErrors('members.0.beyblades');
        $this->assertSame(2, $captain->fresh()->beyblades()->count());
    }

    public function test_deleting_team_clears_beyblades(): void
    {
        $team = $this->team();
        $this->put("/teams/{$team->id}/combos", $this->payload($team, [$this->bx('A')]));

        $this->delete("/teams/{$team->id}")->assertRedirect('/teams');
        $this->assertDatabaseCount('teams', 0);
        $this->assertDatabaseCount('beyblades', 0);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=TeamComboTest`
Expected: FAIL — `test_existing_combo_is_immutable_and_new_one_is_appended` and `test_rejects_exceeding_three_per_member` fail because the current `update` wipes and reinserts (existing combo lost; no cap).

- [ ] **Step 3: Add the cap rule to TeamComboRequest::withValidator**

In `app/Http/Requests/TeamComboRequest.php`, replace the entire `withValidator` method with:
```php
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $team = $this->route('team');

            foreach ($this->input('members', []) as $mi => $member) {
                $newNonEmpty = 0;

                foreach ($member['beyblades'] ?? [] as $bi => $beyblade) {
                    if (self::isEmptyCombo($beyblade)) {
                        continue;
                    }
                    $newNonEmpty++;

                    $line = $beyblade['line'] ?? null;
                    if (! $line || ! in_array($line, BeybladeLines::lines(), true)) {
                        $validator->errors()->add(
                            "members.$mi.beyblades.$bi.line",
                            'Selecciona una línea válida para el combo.'
                        );
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

                $existing = 0;
                if ($team && isset($member['id'])) {
                    $memberModel = $team->members()->find($member['id']);
                    $existing = $memberModel ? $memberModel->beyblades()->count() : 0;
                }
                if ($existing + $newNonEmpty > 3) {
                    $validator->errors()->add(
                        "members.$mi.beyblades",
                        'Este miembro ya tiene todos sus combos registrados o se exceden los 3.'
                    );
                }
            }
        });
    }
```

(Leave `rules()` and `isEmptyCombo()` unchanged. `BeybladeLines` is already imported.)

- [ ] **Step 4: Make TeamComboController::update append-only**

In `app/Http/Controllers/TeamComboController.php`, replace the entire `update` method with:
```php
    public function update(TeamComboRequest $request, Team $team)
    {
        $data = $request->validated();

        DB::transaction(function () use ($team, $data) {
            foreach ($data['members'] as $memberData) {
                $member = $team->members()->findOrFail($memberData['id']);
                $position = (int) $member->beyblades()->max('position'); // 0 when none exist

                foreach ($memberData['beyblades'] as $beyData) {
                    if (TeamComboRequest::isEmptyCombo($beyData)) {
                        continue;
                    }
                    $position++;
                    if ($position > 3) {
                        break; // defensive; request already caps this
                    }
                    $beyblade = $member->beyblades()->create([
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
        });

        return redirect()->route('teams.show', $team)->with('success', 'Combos registrados.');
    }
```

The only behavioral change from the previous version: the line `$member->beyblades()->delete();` is removed, and `$position` starts from the member's current max position instead of `1`. Keep the `edit` method and imports unchanged.

- [ ] **Step 5: Run test to verify it passes**

Run: `php artisan test --filter=TeamComboTest`
Expected: PASS (6 tests).

- [ ] **Step 6: Run the full backend suite**

Run: `php artisan test`
Expected: PASS (no regressions).

- [ ] **Step 7: Commit**

```bash
git add app/Http/Requests/TeamComboRequest.php app/Http/Controllers/TeamComboController.php tests/Feature/TeamComboTest.php
git commit -m "feat: combos are append-only; registered combos are locked"
```

---

## Task 2: MemberDeck renders locked combos read-only

**Files:**
- Modify: `resources/js/Components/MemberDeck.vue`
- Test: `resources/js/Components/__tests__/MemberDeck.test.js`

**Interfaces:**
- Consumes: `SLOT_LABELS`, `LINES` from `resources/js/lib/beybladeLines.js`; existing props.
- Produces: `MemberDeck` accepts a new prop `lockedCombos: Array` (default `[]`) of `{ line, parts }`; renders each as a read-only card (line label + parts + "🔒 Registrado") above the editable `BeybladeForm`s; editable forms still come from `modelValue.beyblades`.

- [ ] **Step 1: Add the failing test case**

In `resources/js/Components/__tests__/MemberDeck.test.js`, add this test inside the `describe('MemberDeck', ...)` block (after the existing tests, before the closing `});`):
```js
    it('renders locked combos read-only and forms only for editable slots', () => {
        const wrapper = mount(MemberDeck, {
            props: {
                modelValue: { role: 'captain', name: 'Aoi', beyblades: [{ line: 'bx', parts: {} }, { line: 'bx', parts: {} }] },
                roleLabel: 'Capitán',
                errors: {},
                lockedCombos: [{ line: 'bx', parts: { blade: 'Aero Pegasus', ratchet: '9-60', bit: 'Rush' } }],
            },
            global: { stubs },
        });
        expect(wrapper.text()).toContain('Aero Pegasus');
        expect(wrapper.text()).toContain('Registrado');
        // one locked card + two editable BeybladeForm stubs
        expect(wrapper.findAll('.bf')).toHaveLength(2);
    });
```

- [ ] **Step 2: Run test to verify it fails**

Run: `npm run test -- MemberDeck`
Expected: FAIL (no "Registrado"/"Aero Pegasus" rendered; `lockedCombos` prop ignored).

- [ ] **Step 3: Add lockedCombos support to MemberDeck.vue**

In `resources/js/Components/MemberDeck.vue`, update the `<script setup>` imports and props. Replace the top of the script (the import line and the `defineProps` call) with:
```js
import BeybladeForm from './BeybladeForm.vue';
import { SLOT_LABELS, LINES } from '../lib/beybladeLines';

const props = defineProps({
    modelValue: { type: Object, required: true },
    roleLabel: { type: String, required: true },
    errors: { type: Object, default: () => ({}) },
    nameReadonly: { type: Boolean, default: false },
    lockedCombos: { type: Array, default: () => [] },
});

function lineLabel(value) {
    return LINES.find((l) => l.value === value)?.label ?? value;
}
```
(Keep the existing `emit`, `updateName`, `updateBeyblade`, and `beybladeErrors` functions as they are.)

- [ ] **Step 4: Render the locked cards in the template**

In `resources/js/Components/MemberDeck.vue`, replace the `<div class="space-y-4">` block that holds the `BeybladeForm` loop with:
```vue
        <div v-if="lockedCombos.length" class="mb-4 grid gap-3 sm:grid-cols-3">
            <div
                v-for="(combo, i) in lockedCombos"
                :key="`locked-${i}`"
                class="rounded-lg border border-bx-cyan/30 bg-zinc-950/60 p-4"
            >
                <div class="mb-2 flex items-center justify-between gap-2">
                    <span class="text-xs font-bold uppercase tracking-widest text-bx-cyan">{{ lineLabel(combo.line) }}</span>
                    <span class="text-xs font-semibold text-bx-cyan">🔒 Registrado</span>
                </div>
                <dl class="space-y-1 text-sm">
                    <div v-for="(name, slot) in combo.parts" :key="slot" class="flex justify-between gap-2">
                        <dt class="text-zinc-500">{{ SLOT_LABELS[slot] ?? slot }}</dt>
                        <dd class="font-medium text-zinc-200">{{ name }}</dd>
                    </div>
                </dl>
            </div>
        </div>

        <div v-if="modelValue.beyblades.length" class="space-y-4">
            <BeybladeForm
                v-for="(beyblade, i) in modelValue.beyblades"
                :key="i"
                :index="i"
                :model-value="beyblade"
                :errors="beybladeErrors(i)"
                @update:model-value="(v) => updateBeyblade(i, v)"
            />
        </div>
```

- [ ] **Step 5: Run test to verify it passes**

Run: `npm run test -- MemberDeck`
Expected: PASS (4 tests: the 3 existing + the new one).

- [ ] **Step 6: Commit**

```bash
git add resources/js/Components/MemberDeck.vue resources/js/Components/__tests__/MemberDeck.test.js
git commit -m "feat: MemberDeck shows locked combos read-only"
```

---

## Task 3: Combos page splits locked/editable + Show copy

**Files:**
- Modify: `resources/js/Pages/Teams/Combos.vue`
- Modify: `resources/js/Pages/Teams/Show.vue`
- Test: `resources/js/Pages/__tests__/TeamsCombos.test.js`

**Interfaces:**
- Consumes: `MemberDeck` (with `lockedCombos` from Task 2), `useForm`/`Head`/`Link`.
- Produces: the combos form holds, per member, only the editable slots (`3 - existing`); locked combos are passed to `MemberDeck` from the original `team` prop; the form puts `{ members:[{id, role, name, beyblades:[editable]}] }` to `/teams/{id}/combos`; the "Guardar combos" button is hidden when every member already has 3 combos. The detail button text becomes "Registrar combos".

- [ ] **Step 1: Update the Combos page test**

Replace `resources/js/Pages/__tests__/TeamsCombos.test.js` with:
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
    MemberDeck: {
        props: ['modelValue', 'roleLabel', 'lockedCombos'],
        template: '<div class="md">{{ roleLabel }}:locked{{ lockedCombos.length }}:edit{{ modelValue.beyblades.length }}</div>',
    },
};

const combo = (blade) => ({ line: 'bx', parts: { blade, ratchet: '3-60', bit: 'Flat' } });

describe('Teams/Combos', () => {
    it('shows locked combos and forms only for empty slots', () => {
        const team = {
            id: 7, name: 'Storm Riders',
            members: [
                { id: 1, role: 'captain', name: 'Aoi', beyblades: [combo('Aero')] },        // 1 locked, 2 editable
                { id: 2, role: 'subcaptain', name: 'Multi', beyblades: [] },                  // 0 locked, 3 editable
                { id: 3, role: 'official', name: 'Kazami', beyblades: [combo('A'), combo('B'), combo('C')] }, // 3 locked, 0 editable
            ],
        };
        const wrapper = mount(Combos, { props: { team, lines: ['bx'], slots: { bx: ['blade', 'ratchet', 'bit'] } }, global: { stubs } });
        const decks = wrapper.findAll('.md').map((n) => n.text());
        expect(decks).toEqual(['Capitán:locked1:edit2', 'Subcapitán:locked0:edit3', 'Oficial:locked3:edit0']);
        // not fully locked -> save button present
        expect(wrapper.find('button[type="submit"]').exists()).toBe(true);
    });

    it('hides the save button when every member is fully locked', () => {
        const full = [combo('A'), combo('B'), combo('C')];
        const team = {
            id: 7, name: 'Storm Riders',
            members: [
                { id: 1, role: 'captain', name: 'Aoi', beyblades: full },
                { id: 2, role: 'subcaptain', name: 'Multi', beyblades: full },
                { id: 3, role: 'official', name: 'Kazami', beyblades: full },
            ],
        };
        const wrapper = mount(Combos, { props: { team, lines: ['bx'], slots: { bx: ['blade', 'ratchet', 'bit'] } }, global: { stubs } });
        expect(wrapper.find('button[type="submit"]').exists()).toBe(false);
    });
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `npm run test -- TeamsCombos`
Expected: FAIL (current page pads to 3 editable slots and passes no `lockedCombos`).

- [ ] **Step 3: Rewrite Combos.vue**

Replace `resources/js/Pages/Teams/Combos.vue` with:
```vue
<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import MemberDeck from '../../Components/MemberDeck.vue';

const props = defineProps({ team: Object, lines: Array, slots: Object });

const ROLE_LABELS = { captain: 'Capitán', subcaptain: 'Subcapitán', official: 'Oficial' };

function emptySlots(n) {
    return Array.from({ length: n }, () => ({ line: 'bx', parts: {} }));
}

const form = useForm({
    members: props.team.members.map((m) => ({
        id: m.id,
        role: m.role,
        name: m.name,
        beyblades: emptySlots(3 - m.beyblades.length),
    })),
});

const allLocked = computed(() => props.team.members.every((m) => m.beyblades.length >= 3));

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
    <Head :title="`Registrar combos · ${team.name}`" />

    <form class="space-y-8" @submit.prevent="submit">
        <div>
            <Link :href="`/teams/${team.id}`" class="text-sm text-zinc-400 hover:text-bx-cyan">← {{ team.name }}</Link>
            <h1 class="mt-1 text-3xl font-black"><span class="bx-gradient-text">Registrar combos</span></h1>
            <p class="mt-1 text-sm text-zinc-400">
                Los combos registrados quedan bloqueados. Puedes registrar los slots vacíos en cualquier momento.
            </p>
        </div>

        <MemberDeck
            v-for="(member, i) in form.members"
            :key="member.id"
            :model-value="member"
            :role-label="ROLE_LABELS[member.role]"
            :locked-combos="team.members[i].beyblades"
            :errors="errorsFor(i)"
            name-readonly
            @update:model-value="(v) => updateMember(i, v)"
        />

        <button
            v-if="!allLocked"
            type="submit"
            :disabled="form.processing"
            class="rounded-lg bg-gradient-to-r from-bx-cyan to-bx-magenta px-6 py-3 font-bold text-zinc-950 transition hover:opacity-90 disabled:opacity-50"
        >
            Guardar combos
        </button>
        <p v-else class="text-sm font-semibold text-bx-cyan">🔒 Todos los combos de este equipo están registrados.</p>
    </form>
</template>
```

- [ ] **Step 4: Run test to verify it passes**

Run: `npm run test -- TeamsCombos`
Expected: PASS (2 tests).

- [ ] **Step 5: Update the Show button copy**

In `resources/js/Pages/Teams/Show.vue`, change the combos link text from `Registrar/editar combos` to `Registrar combos`. The link block becomes:
```vue
            <Link
                :href="`/teams/${team.id}/combos`"
                class="rounded-lg bg-gradient-to-r from-bx-cyan to-bx-magenta px-4 py-2 text-sm font-bold text-zinc-950 transition hover:opacity-90"
            >
                Registrar combos
            </Link>
```

- [ ] **Step 6: Run the full frontend suite**

Run: `npm run test`
Expected: PASS (all Vitest files green, including `TeamsShow` which asserts the link by `href`, not text).

- [ ] **Step 7: Commit**

```bash
git add resources/js/Pages/Teams/Combos.vue resources/js/Pages/Teams/Show.vue resources/js/Pages/__tests__/TeamsCombos.test.js
git commit -m "feat: combos page locks registered combos; rename action to Registrar combos"
```

---

## Task 4: Full verification

**Files:** none (verification)

- [ ] **Step 1: Run both suites**

Run: `php artisan test` then `npm run test`
Expected: all PASS.

- [ ] **Step 2: Build**

Run: `npm run build`
Expected: build succeeds.

- [ ] **Step 3: Manual walk-through**

Start `php artisan serve` and in the browser, on a seeded team:
1. Open a team with one registered combo (e.g. "Team Persona") → the captain's combo shows as a read-only card with "🔒 Registrado", and two editable combo forms appear below it; the other members show three empty forms.
2. Register the captain's second combo → returns to detail; reopen "Registrar combos": now two locked cards + one form for the captain.
3. Complete all 9 → the combos page shows only locked cards and the "Guardar combos" button is replaced by "🔒 Todos los combos… registrados".
4. Confirm a previously registered combo's parts never change across submits.

- [ ] **Step 4: Final commit (if any build artifacts tracked)**

```bash
git add -A
git commit -m "chore: per-combo locking complete" || echo "nothing to commit"
```

---

## Notes

- The lock is enforced in two independent layers: the controller never deletes/updates existing beyblades (append-only), and the request caps `existing + new ≤ 3`. The frontend only renders forms for empty slots, so the normal flow never submits a locked slot — but the backend stays safe against a crafted payload.
- `MemberDeck` keeps its name input (read-only on this page via `nameReadonly`); locked combo cards render above the editable forms.
- No schema migration is needed; "registered" is derived from the existence of the `beyblade` row.

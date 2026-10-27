# Torneos clandestinos — Plan de implementación

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Separar torneos en oficiales (solo admin) y clandestinos (cualquier usuario con sesión), pudiendo correr varios a la vez, sin que los clandestinos cuenten para nada oficial.

**Architecture:** Se añade una columna `type` a `tournaments` y una `TournamentPolicy` que decide quién administra cada torneo. El motor deja de resolver "el torneo activo implícito" y pasa a operar por id (rutas `/tournament/{tournament}`), conservando intacto el flujo oficial que ya está en producción. La UI gana pestañas "Oficial | Clandestinos" y una página de lista + creación de clandestinos.

**Tech Stack:** Laravel 12 (PHP 8.3), Inertia.js, Vue 3 `<script setup>`, PHPUnit (RefreshDatabase), Vitest.

**Spec:** [docs/superpowers/specs/2026-10-27-torneos-clandestinos-design.md](../specs/2026-10-27-torneos-clandestinos-design.md)

## Global Constraints

- Laravel 12 / PHP 8.3; las policies se auto-descubren (`App\Policies\TournamentPolicy` ↔ `App\Models\Tournament`), no se registran a mano.
- Copy de UI en español.
- Assets de Vite se compilan y se commitean (`public/build`); el deploy corre `php artisan migrate --force` y `systemctl restart php8.3-fpm`.
- La migración es aditiva (columna nueva + backfill), sin pérdida de datos.
- Tipos de torneo: enum `type` con valores exactos `'official'` y `'clandestine'`.
- Concurrencia: máximo **un** oficial activo (`status != 'completed'`); clandestinos sin límite.
- Commits terminan con: `Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>`.

## Review Focus

- **Invitado que termina un clandestino** no debe perder su sesión mientras sigue en el oficial → test en Task 4 (`endGuestSessions` solo en oficiales).
- **Segundo oficial activo** debe abortar 422 mientras haya uno sin completar → test en Task 4.
- **No-admin golpeando rutas de administración de un oficial** (rounds/cut/match/update/removeEntry) debe recibir 403 → tests en Task 3.
- **Orden de rutas:** `GET /tournament/clandestinos` e `/tournament/history` no deben ser capturadas por `/tournament/{tournament}` → test en Task 5.
- **PDF por torneo:** pedir el PDF de un clandestino por id devuelve a los inscritos de ese torneo, no de otro activo → test en Task 3.

---

### Task 1: Columna `type` + helpers del modelo + backfill

**Files:**
- Create: `database/migrations/2026_10_27_000000_add_type_to_tournaments_table.php`
- Modify: `app/Models/Tournament.php`
- Test: `tests/Feature/TournamentTypeTest.php`

**Interfaces:**
- Produces: `tournaments.type` (`enum('official','clandestine')`, default `'official'`); `Tournament::isOfficial(): bool`, `Tournament::isClandestine(): bool`.

- [ ] **Step 1: Write the failing test** — `tests/Feature/TournamentTypeTest.php`

```php
public function test_existing_tournaments_are_backfilled_as_official(): void
{
    // Row inserted before relying on the default, to prove the backfill set it.
    $id = DB::table('tournaments')->insertGetId([
        'name' => 'Viejo', 'status' => 'completed', 'swiss_rounds' => 3, 'cut_size' => 4,
        'current_round' => 0, 'created_by_user_id' => User::factory()->create()->id,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $this->assertSame('official', Tournament::find($id)->type);
}

public function test_type_helpers(): void
{
    $official = new Tournament(['type' => 'official']);
    $clandestine = new Tournament(['type' => 'clandestine']);
    $this->assertTrue($official->isOfficial());
    $this->assertTrue($clandestine->isClandestine());
    $this->assertFalse($official->isClandestine());
}
```

Nota: para `test_existing_tournaments_are_backfilled_as_official`, insertar la fila **antes** de que exista la columna no es posible con RefreshDatabase (la migración ya corrió). En su lugar, insertar sin especificar `type` y comprobar que cae en `'official'` por el default; el backfill real sobre datos viejos se verifica en el deploy.

- [ ] **Step 2: Run to verify it fails** — `php artisan test --filter=TournamentTypeTest` → FAIL (columna/método inexistente).

- [ ] **Step 3: Implement the migration**

`up()`: `$table->enum('type', ['official','clandestine'])->default('official')->after('name');` seguido de `DB::table('tournaments')->update(['type' => 'official']);`. `down()`: `$table->dropColumn('type');`.

- [ ] **Step 4: Implement helpers in `app/Models/Tournament.php`**

`public function isOfficial(): bool { return $this->type === 'official'; }` y `isClandestine()` como su negación directa sobre `=== 'clandestine'`.

- [ ] **Step 5: Run to verify it passes** — `php artisan test --filter=TournamentTypeTest` → PASS.

- [ ] **Step 6: Commit** — `feat: add type column to tournaments with official backfill`

---

### Task 2: TournamentPolicy

**Files:**
- Create: `app/Policies/TournamentPolicy.php`
- Test: `tests/Feature/TournamentPolicyTest.php`

**Interfaces:**
- Produces: `TournamentPolicy::manage(?User, Tournament): bool`, `createOfficial(?User): bool`, `createClandestine(?User): bool`.
  - `manage`: oficial → `$user?->is_admin === true`; clandestino → `$user && ! $user->is_guest`.
  - `createOfficial`: `$user?->is_admin === true`.
  - `createClandestine`: `$user && ! $user->is_guest`.

- [ ] **Step 1: Write the failing test** — tabla de casos con `Gate::forUser($user)->allows('manage', $tournament)` y `->allows('createClandestine')`:
  - admin administra oficial y clandestino → true.
  - no-admin (no guest) administra clandestino → true; administra oficial → false.
  - guest administra clandestino → false; crea clandestino → false.
  - no-admin crea oficial → false; crea clandestino → true.

- [ ] **Step 2: Run to verify it fails** — `php artisan test --filter=TournamentPolicyTest` → FAIL.

- [ ] **Step 3: Implement `app/Policies/TournamentPolicy.php`** con los tres métodos y la lógica de arriba. Sin registro manual (auto-discovery de Laravel 12).

- [ ] **Step 4: Run to verify it passes** → PASS.

- [ ] **Step 5: Commit** — `feat: add TournamentPolicy for official/clandestine management`

---

### Task 3: Motor por-torneo + autorización (flujo oficial preservado)

Convierte el motor de "torneo activo implícito" a "torneo por id", autorizado por la Policy, **sin cambiar el comportamiento del torneo oficial**. Backend y frontend cambian juntos para que la app quede funcionando en este commit.

**Files:**
- Modify: `routes/web.php`
- Modify: `app/Http/Controllers/TournamentController.php` (`store`, `update`, `removeEntry`, `join`, `leave`, `cut`, `show`)
- Modify: `app/Http/Controllers/TournamentRoundController.php` (`store`)
- Modify: `app/Http/Controllers/TournamentMatchController.php` (`update` — authorize + `endGuestSessions` solo si oficial)
- Modify: `app/Http/Controllers/TournamentReportController.php` (`download(Request, Tournament)`)
- Modify: `resources/js/Pages/Tournament/Show.vue` (URLs por-torneo, prop `can_manage`)
- Test: `tests/Feature/TournamentControllerTest.php` (existente — actualizar), `tests/Feature/TournamentManagementAuthTest.php` (nuevo), `resources/js/Pages/__tests__/TournamentShow.test.js` (existente — actualizar)

**Interfaces:**
- Consumes: `TournamentPolicy` (Task 2); `Tournament::isOfficial()` (Task 1).
- Produces: rutas con nombres conservados pero por-torneo (ver abajo); prop booleana `can_manage` en `Tournament/Show`; firma `show(?Tournament $tournament = null)`.

**Rutas (dentro del grupo `auth`, fuera del middleware `admin`):**
```
GET    /tournament                               tournament.show         -> show() (oficial activo/implícito)
GET    /tournament/history                        tournament.history
GET    /tournament/history/{tournament}           tournament.history.show  (SIN cambios)
GET    /tournament/clandestinos                    (Task 5)
GET    /tournament/{tournament}                    tournament.view         -> show($tournament)
POST   /tournament                                 tournament.store        -> store() OFICIAL
PATCH  /tournament/{tournament}                     tournament.update
DELETE /tournament/{tournament}/entries/{entry}     tournament.entries.destroy
POST   /tournament/{tournament}/join               tournament.join
DELETE /tournament/{tournament}/leave              tournament.leave
POST   /tournament/{tournament}/rounds             tournament.rounds.store
POST   /tournament/{tournament}/cut                tournament.cut
PATCH  /tournament/matches/{match}                 tournament.matches.update
POST   /tournament/{tournament}/report/pdf          tournament.report.pdf   (throttle:5,1)
```
Las estáticas (`/tournament/history`, `/tournament/clandestinos`) van **antes** de `/tournament/{tournament}`. El middleware `admin` ya **no** envuelve estas rutas; la autorización vive en los controladores vía Policy.

- [ ] **Step 1: Write failing feature tests** — `tests/Feature/TournamentManagementAuthTest.php`:
  - admin puede `POST /tournament` (crear oficial) → redirect; no-admin → 403.
  - dado un oficial en curso, no-admin que hace `POST /tournament/{id}/rounds`, `POST /tournament/{id}/cut`, `PATCH /tournament/{id}`, `DELETE /tournament/{id}/entries/{entry}` → 403; admin → ok.
  - `PATCH /tournament/matches/{match}` de un oficial por no-admin → 403.
  - `POST /tournament/{id}/report/pdf` con password correcto devuelve PDF de los inscritos de **ese** torneo (assert `assertDownload('reporte-torneo.pdf')`).
  - cualquier usuario con sesión puede `POST /tournament/{id}/join` y `DELETE /tournament/{id}/leave`.

- [ ] **Step 2: Run to verify they fail** — `php artisan test --filter=TournamentManagementAuthTest` → FAIL (rutas viejas / sin policy).

- [ ] **Step 3: Rewrite routes** en `routes/web.php` según la tabla de arriba (sin el wrap de `admin`).

- [ ] **Step 4: Refactor controllers a por-id + authorize**
  - `TournamentController@store`: `$this->authorize('createOfficial', Tournament::class)`; crea con `type => 'official'`; conserva el guard de "oficial activo" pero scoped: `Tournament::where('type','official')->where('status','!=','completed')->exists()`.
  - `update`/`removeEntry`/`cut`: reciben `Tournament $tournament`, `$this->authorize('manage', $tournament)`, misma lógica que hoy pero sobre `$tournament` (ya no `firstOrFail()` implícito).
  - `join`/`leave`: reciben `Tournament $tournament`; sin authorize (solo `auth`); misma lógica de inscripción/deck.
  - `show(?Tournament $tournament = null)`: si `$tournament` es null, resuelve el oficial activo (`type='official', status!='completed'`) o el último oficial completado — comportamiento idéntico a hoy; si viene id, usa ese. Añade prop `'can_manage' => $request->user()->can('manage', $tournament)` (y `false` cuando no hay torneo). Redirecciones de `store`/`cut`/`rounds` usan `route('tournament.view', $tournament)` salvo `store` oficial que puede seguir a `tournament.show`.
  - `TournamentRoundController@store(Tournament $tournament)`: `$this->authorize('manage', $tournament)`; misma lógica suiza/corte/eliminatoria sobre `$tournament`.
  - `TournamentMatchController@update`: añadir `$this->authorize('manage', $match->round->tournament)`; envolver `endGuestSessions($tournament)` en `if ($tournament->isOfficial())`.
  - `TournamentReportController@download(Request $request, Tournament $tournament)`: quitar el lookup implícito; usar `$tournament` directo.

- [ ] **Step 5: Update `Show.vue`** — reemplazar cada URL hardcodeada por su forma por-id usando `tournament.id`:
  - `/tournament/join` → `/tournament/${tournament.id}/join`; `/tournament/leave` → `.../leave`; `/tournament/rounds` → `.../rounds`; `/tournament/cut` → `.../cut`; `/tournament/entries/${entry.id}` → `/tournament/${tournament.id}/entries/${entry.id}`; `/tournament/matches/${matchId}` (sin cambio de forma — ya es por match); `ReportExport endpoint` → `/tournament/${tournament.id}/report/pdf`.
  - Reemplazar el gate de administración: donde hoy usa `isAdmin` para mostrar botones de gestión (generar ronda, corte, corregir resultado, editar, quitar inscrito), usar la nueva prop `can_manage`. Mantener `can_create_tournament` para el bloque de crear oficial (solo admin).
  - Declarar `can_manage: { type: Boolean, default: false }` en props.

- [ ] **Step 6: Update existing tests** — `tests/Feature/TournamentControllerTest.php` a las rutas por-id; `resources/js/Pages/__tests__/TournamentShow.test.js`: pasar `tournament.id`, prop `can_manage: true` donde antes asumía admin, y afirmar que `router.post`/`patch`/`delete` usan las URLs por-id.

- [ ] **Step 7: Run suites** — `php artisan test --filter=Tournament` y `npm run test` → PASS.

- [ ] **Step 8: Commit** — `refactor: operate tournaments per-id, authorize via policy`

---

### Task 4: Creación de clandestinos + concurrencia + visibilidad oficial

**Files:**
- Modify: `routes/web.php` (añadir `POST /tournament/clandestinos`)
- Modify: `app/Http/Controllers/TournamentController.php` (`storeClandestine`, filtro `type='official'` en `history`)
- Test: `tests/Feature/ClandestineTournamentTest.php`

**Interfaces:**
- Consumes: `TournamentPolicy::createClandestine` (Task 2); rutas por-id (Task 3).
- Produces: ruta `POST /tournament/clandestinos` (`tournament.clandestinos.store`); `TournamentController@storeClandestine`.

- [ ] **Step 1: Write failing tests** — `tests/Feature/ClandestineTournamentTest.php`:
  - no-admin con sesión crea clandestino (`type='clandestine'`, `created_by_user_id` = él) → redirect; guest → 403.
  - pueden existir **dos** clandestinos activos a la vez (crear dos, ambos `status='registration'`).
  - existiendo un oficial activo, `POST /tournament` de un segundo oficial → 422; pero sí se puede crear un clandestino al mismo tiempo.
  - no-admin administra un clandestino: `POST /tournament/{id}/rounds` con 2 inscritos genera Ronda 1 → ok.
  - **Invitado y sesiones:** un guest inscrito en un oficial activo y en un clandestino; al completarse el clandestino (reportar su final), la sesión del guest **sigue viva** (`sessions` conserva su fila); al completarse el oficial, se borra.
  - `history()` no incluye clandestinos completados.

- [ ] **Step 2: Run to verify they fail** → FAIL.

- [ ] **Step 3: Implement**
  - Ruta `POST /tournament/clandestinos` → `TournamentController@storeClandestine`.
  - `storeClandestine(StoreTournamentRequest $request)`: `$this->authorize('createClandestine', Tournament::class)`; crea igual que `store` pero `type => 'clandestine'` y **sin** guard de activo.
  - `history()`: añadir `->where('type', 'official')`.

- [ ] **Step 4: Run to verify they pass** → PASS.

- [ ] **Step 5: Commit** — `feat: create and manage clandestine tournaments`

---

### Task 5: UI — pestañas, lista de clandestinos y botón de crear

**Files:**
- Create: `resources/js/Pages/Tournament/Clandestine/Index.vue`
- Create: `resources/js/Components/TournamentTabs.vue`
- Modify: `app/Http/Controllers/TournamentController.php` (`clandestineIndex`)
- Modify: `routes/web.php` (añadir `GET /tournament/clandestinos`)
- Modify: `resources/js/Pages/Tournament/Show.vue` (insertar `<TournamentTabs>` + badge de tipo)
- Test: `tests/Feature/ClandestineIndexTest.php`, `resources/js/Pages/__tests__/ClandestineIndex.test.js`

**Interfaces:**
- Consumes: rutas y `storeClandestine` (Task 4); prop `can_manage` (Task 3).
- Produces: ruta `GET /tournament/clandestinos` (`tournament.clandestinos.index`) → `Tournament/Clandestine/Index`; componente `TournamentTabs` (dos enlaces: Oficial `/tournament`, Clandestinos `/tournament/clandestinos`).

- [ ] **Step 1: Write failing backend test** — `tests/Feature/ClandestineIndexTest.php`:
  - `GET /tournament/clandestinos` responde `Inertia` componente `Tournament/Clandestine/Index` con `tournaments` (solo `type='clandestine'`), cada uno con `id,name,status,entries_count,creator_nickname`.
  - **Orden de rutas:** `GET /tournament/clandestinos` NO cae en el binding de `/tournament/{tournament}` (no 404 por model-binding).

- [ ] **Step 2: Run to verify it fails** → FAIL.

- [ ] **Step 3: Implement `clandestineIndex()`** en `TournamentController`: lista `Tournament::where('type','clandestine')` con `withCount('entries')` y `createdBy:id,nickname`, activos primero (`orderByRaw("status='completed'")`) luego por `created_at desc`; mapea a los campos de arriba. Registrar la ruta **antes** de `/tournament/{tournament}`.

- [ ] **Step 4: Run to verify it passes** → PASS.

- [ ] **Step 5: Write failing Vitest** — `resources/js/Pages/__tests__/ClandestineIndex.test.js`:
  - con `tournaments: []` muestra vacío ("Todavía no hay torneos clandestinos").
  - con una fila, muestra nombre, creador y badge de estado; muestra el botón "+ Crear torneo clandestino" cuando `auth.user` existe (no guest).
  - al enviar el formulario de crear, hace `post('/tournament/clandestinos')`.

- [ ] **Step 6: Implement the Vue** — `Clandestine/Index.vue`: `<TournamentTabs active="clandestinos" />`, lista de filas (nombre + badge Clandestino + estado + creador + enlace a `/tournament/${id}`), y un formulario (nombre, swiss_rounds, cut_size) con `useForm().post('/tournament/clandestinos')`, visible si `usePage().props.auth.user && !auth.user.is_guest`. `TournamentTabs.vue`: dos `<Link>` con subrayado del activo. En `Show.vue` insertar `<TournamentTabs active="oficial" />` arriba y un badge `tournament.type === 'clandestine' ? 'Clandestino' : 'Oficial'` en el encabezado (requiere exponer `type` en la prop `tournament` del `show()` — añadir `'type' => $tournament->type`).

- [ ] **Step 7: Run both suites** — `php artisan test --filter=Clandestine` y `npm run test` → PASS.

- [ ] **Step 8: Build assets + commit** — `npm run build`; commit `feat: clandestine tab, list and create UI`

---

### Task 6: Verificación completa + build + despliegue

**Files:** (ninguno nuevo)

- [ ] **Step 1:** `php artisan test` completo → PASS.
- [ ] **Step 2:** `npm run test` completo → PASS.
- [ ] **Step 3:** `npm run build` y confirmar `public/build` actualizado; commit si hay cambios.
- [ ] **Step 4:** Push a master.
- [ ] **Step 5 (requiere autorización del usuario):** desplegar al VPS: `git pull --ff-only`, `php artisan migrate --force`, `config/route/view:cache`, `systemctl restart php8.3-fpm`. Smoke test de `/tournament` y `/tournament/clandestinos`.

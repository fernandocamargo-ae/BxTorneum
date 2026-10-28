# Torneo por equipos — Plan de implementación

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Restaurar el registro de equipos y añadir un apartado "Torneo por equipos" (Oficial + Clandestino) donde compiten equipos, reutilizando el mismo motor de emparejamiento y la misma vista que el torneo individual.

**Architecture:** Los torneos ganan una columna `format` (`individual`|`teams`). Una `tournament_entry` puede apuntar a un `team_id` en vez de a `user_id`/`deck_id`. Toda la lógica de emparejamiento (`TournamentPairing`, `TournamentStandings`, `TournamentRoundController`, `TournamentMatchController`, el corte) ya opera sobre `entry_id`/`winner_entry_id` y se reutiliza; los únicos puntos a ajustar son donde el código deref­erencia `entry->user`. `Tournament/Show.vue` se reutiliza con un prop `format`.

**Tech Stack:** Laravel 12 (PHP 8.3), Inertia.js, Vue 3 `<script setup>`, MySQL, Vite (assets committeados en `public/build`), PHPUnit + Vitest.

**Spec:** `docs/superpowers/specs/2026-10-28-bxtorneum-torneo-por-equipos-design.md`

## Global Constraints

- TDD estricto (RED→GREEN) en cada tarea; suite PHPUnit y Vitest verdes antes de dar una tarea por hecha. Comando PHP: `php artisan test`; comando JS: `npm run test`.
- Seguir los patrones existentes del repo (Inertia + `useForm`/`router`, Tailwind con tokens `bx-cyan`/`bx-magenta`, `$guarded = []` en modelos, policies auto-descubiertas).
- No romper ningún test existente (torneo individual, equipos, decks, etc.).
- El torneo por equipos **no** alimenta "Campeones del mes" ni el historial individual.
- El resultado de un match de equipos se captura marcando al equipo ganador (igual que individual); no hay duelos por integrante.
- Los equipos solo pueden entrar a un torneo si están **completos** (9 beys).
- Un equipo es `App\Models\Team` con `members` (Capitán/Subcapitán/Oficial), cada `Member` con `beyblades`; `Team::isComplete()` = 9 beys. No se cambia cómo se registran los equipos.

## Review Focus

- **Generar ronda con entries de equipo:** `TournamentRoundFactory::notifyOne()` hace `$entry->user->notify(...)`; con equipos `user` es NULL y truena al generar cualquier ronda. Cubierto en Task 3, y ejercido de punta a punta en Task 8.
- **PDF de jugadores en torneo por equipos:** `TournamentReportController` deref­erencia `entry->user`/`entry->deck`; un admin que presione "Exportar PDF" en un torneo por equipos provoca 500. Cubierto en Task 9 (rechazo backend + botón oculto).
- **Equipo editado/borrado a media competencia:** bajar un equipo de 9 beys o borrarlo mientras tiene inscripción en un torneo no terminado corrompe bracket/historial. Cubierto en Task 10 (lock + FK restrict).
- **Candado de "oficial activo" cruzado entre formatos:** el `store()` individual hoy bloquea si existe cualquier `type='official'` no terminado; sin acotar por `format` un torneo por equipos oficial bloquearía crear uno individual y viceversa. Cubierto en Task 5.
- **Bye con número impar de equipos:** la entry con bye no tiene usuario; `notifyPlayers` ya salta byes y standings/pairing son por entry. Ejercido en Task 8 con un campo impar.

## File Structure

**Nuevos:**
- `database/migrations/2026_10_28_000000_add_format_to_tournaments_table.php`
- `database/migrations/2026_10_28_000100_allow_team_entries_in_tournament_entries.php`
- `app/Http/Controllers/TeamTournamentController.php`
- `resources/js/Pages/Tournament/Teams/Index.vue` (lista de clandestinos por equipos)
- Tests: `tests/Unit/TournamentRoundFactoryTeamTest.php`, `tests/Feature/TeamTournamentCreateTest.php`, `tests/Feature/TeamTournamentShowTest.php`, `tests/Feature/TeamTournamentRegistrationTest.php`, `tests/Feature/TeamTournamentFlowTest.php`, `tests/Feature/TeamTournamentReportGuardTest.php`, `tests/Feature/TeamLifecycleLockTest.php`, `tests/Feature/TeamTournamentListingsTest.php`, `resources/js/Pages/__tests__/TournamentShowTeams.test.js`

**Modificados:**
- `routes/web.php` (reactivar registro de equipos; rutas de torneo por equipos)
- `app/Models/Tournament.php` (`isTeams()`), `app/Models/TournamentEntry.php` (`team()`)
- `app/Support/TournamentRoundFactory.php` (saltar notificación sin usuario)
- `app/Http/Controllers/TournamentController.php` (acotar por `format`; `redirectToTournament` y `removeEntry` team-safe)
- `app/Http/Controllers/TournamentReportController.php` (rechazar formato equipos)
- `app/Http/Controllers/TeamController.php`, `app/Http/Controllers/TeamComboController.php` (lock de ciclo de vida)
- `resources/js/Components/TournamentTabs.vue` (variante individual/equipos)
- `resources/js/Pages/Tournament/Show.vue` (prop `format`: bloque de inscripción, ocultar PDF, tabs/títulos)
- `resources/js/Pages/Tournament/Clandestine/Index.vue` (si se parametriza) o nuevo `Teams/Index.vue`
- `resources/js/Layouts/AppLayout.vue` (nav: "Equipos" + "Torneo por equipos")

---

## Task 1: Columna `format` en tournaments

**Files:**
- Create: `database/migrations/2026_10_28_000000_add_format_to_tournaments_table.php`
- Modify: `app/Models/Tournament.php`
- Test: `tests/Feature/TeamTournamentCreateTest.php` (se amplía en Task 5; aquí solo el caso de modelo/migración)

**Interfaces:**
- Produces: `tournaments.format` enum(`'individual'`,`'teams'`) default `'individual'`; `Tournament::isTeams(): bool` (true si `format === 'teams'`).

- [ ] **Step 1: Test** en `TeamTournamentCreateTest`:
```php
public function test_tournaments_default_to_individual_format_and_report_it(): void {
    $t = Tournament::create(['name'=>'X','type'=>'official','status'=>'registration','swiss_rounds'=>3,'cut_size'=>4,'current_round'=>0]);
    $this->assertSame('individual', $t->refresh()->format);
    $this->assertFalse($t->isTeams());
    $teams = Tournament::create(['name'=>'Y','type'=>'official','format'=>'teams','status'=>'registration','swiss_rounds'=>3,'cut_size'=>4,'current_round'=>0]);
    $this->assertTrue($teams->isTeams());
}
```
- [ ] **Step 2: Run** `php artisan test --filter=test_tournaments_default_to_individual_format_and_report_it` → FAIL (columna/method ausentes).
- [ ] **Step 3:** Migración: `$table->enum('format',['individual','teams'])->default('individual')->after('type')`; backfill `DB::table('tournaments')->update(['format'=>'individual'])` en `up()`. `down()`: `dropColumn('format')`. Añadir `public function isTeams(): bool { return $this->format === 'teams'; }` en `Tournament`.
- [ ] **Step 4: Run** el test → PASS. Luego `php artisan test` completo → verde.
- [ ] **Step 5: Commit** `feat: add format column (individual/teams) to tournaments`.

## Task 2: Entries de equipo

**Files:**
- Create: `database/migrations/2026_10_28_000100_allow_team_entries_in_tournament_entries.php`
- Modify: `app/Models/TournamentEntry.php`
- Test: `tests/Feature/TeamTournamentCreateTest.php`

**Interfaces:**
- Consumes: `teams` table (existente).
- Produces: `tournament_entries.team_id` nullable FK→`teams` on-delete **restrict**; `user_id` y `deck_id` nullable; `unique(tournament_id, team_id)`. `TournamentEntry::team()` belongsTo `Team`.

- [ ] **Step 1: Test:**
```php
public function test_a_tournament_entry_can_reference_a_team_without_user_or_deck(): void {
    $team = Team::factory()->create(); // ver nota de factory abajo
    $t = Tournament::create([...,'format'=>'teams']);
    $entry = TournamentEntry::create(['tournament_id'=>$t->id,'team_id'=>$team->id]);
    $this->assertNull($entry->user_id);
    $this->assertNull($entry->deck_id);
    $this->assertTrue($entry->team->is($team));
}
```
Nota: si no existe `TeamFactory`, crear el equipo con `Team::create(['name'=>'E'])` (no requiere members para esta prueba).
- [ ] **Step 2: Run** → FAIL.
- [ ] **Step 3:** Migración que: `->foreignId('team_id')->nullable()->after('tournament_id')->constrained('teams')->restrictOnDelete()`; vuelve `user_id` y `deck_id` nullable (`->nullable()->change()`; si una FK impide `change()` en MySQL, soltar y recrear la FK dentro de la migración); `->unique(['tournament_id','team_id'])`. Añadir `team()` belongsTo en el modelo. `down()` revierte.
- [ ] **Step 4: Run** test → PASS; `php artisan test` → verde (ojo: entries individuales existentes siguen válidos).
- [ ] **Step 5: Commit** `feat: allow tournament entries to reference a team`.

## Task 3: Saltar notificación sin usuario en la generación de rondas

**Files:**
- Modify: `app/Support/TournamentRoundFactory.php`
- Test: `tests/Unit/TournamentRoundFactoryTeamTest.php`

**Interfaces:**
- Consumes: entries con `user_id` NULL (Task 2).
- Produces: `TournamentRoundFactory::create()` genera rondas para entries de equipo sin tocar la lógica de pairing.

- [ ] **Step 1: Test** (crea un torneo `format=teams` con 2 entries de equipo y llama `TournamentRoundFactory::create($t,'swiss',[[e1,e2]])`):
```php
public function test_generating_a_round_for_team_entries_does_not_require_a_user(): void {
    // 2 team entries, sin user. Debe crear el round + match sin lanzar excepción.
    $round = TournamentRoundFactory::create($tournament, 'swiss', [[$e1->id, $e2->id]]);
    $this->assertCount(1, $round->matches);
    $this->assertDatabaseHas('tournaments', ['id'=>$tournament->id,'status'=>'swiss','current_round'=>1]);
}
```
- [ ] **Step 2: Run** → FAIL con error por `$entry->user` null en `notifyOne`.
- [ ] **Step 3:** En `notifyOne()`, al inicio: `if ($entry->user === null) { return; }` (los equipos no tienen cuenta a quién notificar; el pairing ya ocurrió antes de notificar). No tocar `create()` ni `notifyPlayers()` salvo ese guard.
- [ ] **Step 4: Run** test → PASS; `php artisan test` → verde (los torneos individuales siguen notificando).
- [ ] **Step 5: Commit** `fix: skip round-pairing notification for entries without a user`.

## Task 4: Reactivar el registro de equipos + link en el nav

**Files:**
- Modify: `routes/web.php` (descomentar el bloque de equipos, incluyendo `combos.edit`/`combos.update`)
- Modify: `resources/js/Layouts/AppLayout.vue` (link "Equipos" en desktop y móvil)
- Test: suite existente de equipos (`php artisan test --filter=Team` y Vitest `Teams*`).

**Interfaces:**
- Produces: rutas `teams.*` y `combos.*` activas; "Equipos" visible en el nav para usuarios autenticados.

- [ ] **Step 1: Run** `php artisan test --filter=Team` → anotar estado actual (las pruebas que dependen de rutas fallan mientras están comentadas). Este es el RED de referencia.
- [ ] **Step 2:** Descomentar en `routes/web.php` las líneas `Route::resource('teams', ...)` y las dos de `combos`. Añadir en `AppLayout.vue` (desktop y móvil, dentro de `v-if="user"`) un `<Link href="/teams">Equipos</Link>` siguiendo el estilo de los demás.
- [ ] **Step 3: Run** `php artisan test --filter=Team` → PASS; `npm run test` (Teams specs) → PASS.
- [ ] **Step 4: Commit** `feat: restore team registration (routes + nav)`.

## Task 5: Crear torneos por equipos (oficial + clandestino) y acotar formatos

**Files:**
- Create: `app/Http/Controllers/TeamTournamentController.php` (métodos `storeOfficial`, `storeClandestine`)
- Modify: `app/Http/Controllers/TournamentController.php` (`store()`/`show()`/`history()` acotados a `format='individual'`)
- Modify: `routes/web.php` (grupo `/torneo-equipos`: store oficial y clandestino)
- Test: `tests/Feature/TeamTournamentCreateTest.php`

**Interfaces:**
- Consumes: `TournamentPolicy::createOfficial`/`createClandestine` (existentes, por `type`).
- Produces: rutas con nombre `team-tournament.store` (POST `/torneo-equipos`), `team-tournament.clandestinos.store` (POST `/torneo-equipos/clandestinos`). Torneos creados con `format='teams'` y `type` oficial/clandestino.

- [ ] **Step 1: Tests** (en `TeamTournamentCreateTest`): admin crea oficial-equipos (201/redirect, `format='teams'`,`type='official'`); amateur crea clandestino-equipos; jugador raso NO puede crear ninguno (403); un oficial-equipos activo **no** bloquea crear un oficial-individual (y viceversa) — candado por `(type,format)`.
```php
public function test_an_active_team_official_does_not_block_an_individual_official(): void {
    Tournament::create([...,'type'=>'official','format'=>'teams','status'=>'registration',...]);
    $this->actingAs($admin)->post('/tournament', ['name'=>'Ind','swiss_rounds'=>3,'cut_size'=>4])->assertRedirect();
}
```
- [ ] **Step 2: Run** → FAIL (rutas/método ausentes; candado individual aún global).
- [ ] **Step 3:**
  - `TeamTournamentController@storeOfficial`: `authorize('createOfficial', Tournament::class)`; `abort_if` si existe `type=official AND format=teams AND status!=completed`; crear con `format='teams'`, redirigir a `team-tournament.show`.
  - `@storeClandestine`: `authorize('createClandestine', ...)`; crear `format='teams'`, redirigir a la vista del torneo por id (ruta de Task 6).
  - En `TournamentController@store`: añadir `->where('format','individual')` al `abort_if`. En `@show` (resolución de activo por defecto) y `@history`: añadir `->where('format','individual')`.
  - Rutas nuevas en el grupo auth.
- [ ] **Step 4: Run** tests → PASS; `php artisan test` → verde (los tests individuales existentes siguen pasando).
- [ ] **Step 5: Commit** `feat: create team tournaments (official/clandestine), scope formats`.

## Task 6: Vista del torneo por equipos (`show`)

**Files:**
- Modify: `app/Http/Controllers/TeamTournamentController.php` (`show(?Tournament $tournament = null)`)
- Modify: `routes/web.php` (GET `/torneo-equipos` → `team-tournament.show`; GET `/torneo-equipos/{tournament}` → `team-tournament.view`)
- Test: `tests/Feature/TeamTournamentShowTest.php`

**Interfaces:**
- Consumes: entries con `team`.
- Produces: render de `Tournament/Show` con props: `tournament` (incluye `format:'teams'`, `champion_nickname` = nombre del equipo campeón), `entries` (`{id, nickname: team.name, avatar_url: null}`), `standings` (con `nickname` = team.name), `current_round_matches` (nicknames = team.name), `can_manage`, `teams_pool` (equipos completos aún no inscritos: `{id,name}`), `format:'teams'`. Sin `my_entry_id`/deck (los jugadores no son entries).

- [ ] **Step 1: Tests:** `show` sin id resuelve el oficial-equipos activo (o el último completado); con equipos inscritos, `entries[].nickname` = nombre del equipo; `teams_pool` solo incluye equipos **completos** no inscritos; `format` = `'teams'`.
- [ ] **Step 2: Run** → FAIL.
- [ ] **Step 3:** Implementar `show()` espejando `TournamentController@show` pero: resolver activo con `format='teams'`; cargar `entries()->with('team')`; mapear nickname=team.name, avatar_url=null; standings con nombres de equipo (helper local análogo a `standingsWithNicknames`); matches con team.name; `champion_nickname` = `$tournament->champion?->team?->name`; `teams_pool` = equipos completos (`Team::all()->filter->isComplete()`) menos los ya inscritos; pasar `'format'=>'teams'`. No incluir `my_entry_id`/deck.
- [ ] **Step 4: Run** tests → PASS; `php artisan test` → verde.
- [ ] **Step 5: Commit** `feat: team tournament show (teams as entrants)`.

## Task 7: Inscribir y quitar equipos en registro

**Files:**
- Modify: `app/Http/Controllers/TeamTournamentController.php` (`addTeam(Request, Tournament)`)
- Modify: `app/Http/Controllers/TournamentController.php` (`removeEntry` team-safe)
- Modify: `routes/web.php` (POST `/torneo-equipos/{tournament}/equipos` → `team-tournament.teams.add`)
- Test: `tests/Feature/TeamTournamentRegistrationTest.php`

**Interfaces:**
- Consumes: `team-tournament.view`, `tournament.entries.destroy` (ruta per-id existente).
- Produces: endpoint para agregar un equipo completo como entry.

- [ ] **Step 1: Tests:** manager agrega equipo completo → entry creada; equipo **incompleto** (<9) → 422; equipo ya inscrito → 422; agregar fuera de `registration` → 422; no-manager → 403; `removeEntry` quita la entry de equipo y el flash usa el **nombre del equipo** (no truena por `user` null).
- [ ] **Step 2: Run** → FAIL.
- [ ] **Step 3:**
  - `addTeam`: `authorize('manage',$tournament)`; `abort_unless($tournament->status==='registration', 422, ...)`; validar `team_id` existe; cargar `Team` y `abort_unless($team->isComplete(), 422, 'El equipo debe estar completo (9 beys).')`; `abort_if` ya inscrito; crear entry `['tournament_id'=>..,'team_id'=>..]`; `back()->with('success', "Se agregó \"{$team->name}\".")`.
  - `TournamentController@removeEntry`: obtener nombre con `$entry->user?->nickname ?? $entry->team?->name ?? '?'` para el mensaje. Resto igual.
- [ ] **Step 4: Run** tests → PASS; `php artisan test` → verde (removeEntry individual sigue igual).
- [ ] **Step 5: Commit** `feat: add/remove teams during team tournament registration`.

## Task 8: Flujo completo del torneo por equipos (rondas, corte, resultado)

**Files:**
- Modify: `app/Http/Controllers/TournamentController.php` (`redirectToTournament` team-aware)
- Test: `tests/Feature/TeamTournamentFlowTest.php`

**Interfaces:**
- Consumes: rutas per-id existentes `tournament.rounds.store`, `tournament.cut`, `tournament.matches.update`, y `redirectToTournament`.
- Produces: un torneo por equipos se juega de principio a fin reutilizando el motor; el corte redirige a la vista por equipos.

- [ ] **Step 1: Test** end-to-end: crear oficial-equipos, inscribir N equipos (probar también **N impar** para ejercer el bye), generar ronda 1 vía `POST /tournament/{id}/rounds`, reportar ganadores vía `PATCH /tournament/matches/{match}`, avanzar rondas suizas, `POST /tournament/{id}/cut`, jugar eliminatorias hasta la final; aserciones: `status` transita swiss→elimination→completed, `champion_entry_id` corresponde al equipo ganador, y el `cut` redirige a `team-tournament.view`/`show` (no a `/tournament`).
- [ ] **Step 2: Run** → FAIL (al menos la aserción del redirect del corte; si algo del motor asume usuario, también).
- [ ] **Step 3:** En `redirectToTournament`: si `$tournament->isTeams()`, redirigir a la ruta de la vista por equipos (oficial → `team-tournament.show`; clandestino → `team-tournament.view`). No cambiar la lógica individual.
- [ ] **Step 4: Run** test → PASS; `php artisan test` → verde.
- [ ] **Step 5: Commit** `test: full team tournament flow (swiss, cut, elimination, champion)`.

## Task 9: Blindar el PDF de jugadores para formato equipos

**Files:**
- Modify: `app/Http/Controllers/TournamentReportController.php`
- Modify: `resources/js/Pages/Tournament/Show.vue` (ocultar `ReportExport` si `format==='teams'`)
- Test: `tests/Feature/TeamTournamentReportGuardTest.php`

**Interfaces:**
- Produces: `download()` aborta (422) en torneos por equipos; el botón no se muestra.

- [ ] **Step 1: Test:** `POST /tournament/{teamTournament}/report/pdf` con contraseña correcta → 422 (no 500). (La prueba del PDF individual existente sigue verde.)
- [ ] **Step 2: Run** → FAIL (hoy intentaría leer `entry->user` → 500).
- [ ] **Step 3:** En `download()`, tras resolver `$tournament`: `abort_if($tournament->isTeams(), 422, 'El reporte de jugadores no aplica a torneos por equipos.')`. En `Show.vue`, envolver `<ReportExport>` con `v-if="format !== 'teams'"` (prop `format` se agrega en Task 11).
- [ ] **Step 4: Run** test → PASS; `php artisan test` → verde.
- [ ] **Step 5: Commit** `feat: disable players PDF for team tournaments`.

## Task 10: Lock de ciclo de vida del equipo inscrito

**Files:**
- Modify: `app/Http/Controllers/TeamController.php` (`destroy`, `update`), `app/Http/Controllers/TeamComboController.php` (`update`)
- Modify: `app/Models/Team.php` (helper `isLockedByActiveTournament(): bool`)
- Test: `tests/Feature/TeamLifecycleLockTest.php`

**Interfaces:**
- Consumes: `tournament_entries.team_id`.
- Produces: no se puede borrar un equipo, ni dejarlo < 9 beys, mientras tenga entry en un torneo `status != completed`.

- [ ] **Step 1: Tests:** equipo con entry en torneo activo → `DELETE /teams/{id}` 422 y el equipo sigue existiendo; `PUT /teams/{id}` que quitaría combos (o el endpoint de combos) → 422; el mismo equipo, con el torneo ya `completed`, **sí** se puede editar/borrar.
- [ ] **Step 2: Run** → FAIL.
- [ ] **Step 3:** `Team::isLockedByActiveTournament()` = existe `TournamentEntry` del equipo cuyo torneo tiene `status != 'completed'`. En `TeamController@destroy`: `abort_if($team->isLockedByActiveTournament(), 422, 'El equipo está inscrito en un torneo en curso.')`. En `@update` y `TeamComboController@update`: mismo guard (editar nombres/roles mientras está bloqueado también se rechaza, para no romper la identidad del inscrito). La FK ya es `restrict` (Task 2) como última red.
- [ ] **Step 4: Run** tests → PASS; `php artisan test` → verde (los tests de equipos de Task 4 no inscriben en torneos, siguen pasando).
- [ ] **Step 5: Commit** `feat: lock team edits/delete while enrolled in an active tournament`.

## Task 11: Frontend del torneo por equipos (Show, tabs, listados, nav)

**Files:**
- Modify: `resources/js/Pages/Tournament/Show.vue` (prop `format`, `teams_pool`; bloque de inscripción por equipos)
- Modify: `resources/js/Components/TournamentTabs.vue` (prop `variant: 'individual'|'teams'`)
- Create: `resources/js/Pages/Tournament/Teams/Index.vue` (lista clandestinos por equipos; patrón de `Clandestine/Index.vue`)
- Modify: `app/Http/Controllers/TeamTournamentController.php` (`clandestineIndex`, `history`, `historyShow`), `routes/web.php`, `resources/js/Layouts/AppLayout.vue` (link "Torneo por equipos")
- Test: `resources/js/Pages/__tests__/TournamentShowTeams.test.js`, `tests/Feature/TeamTournamentListingsTest.php`

**Interfaces:**
- Consumes: props de Task 6; rutas de Task 5/6.
- Produces: UI completa del apartado "Torneo por equipos" con pestañas Oficial|Clandestino y su listado/historial.

- [ ] **Step 1: Tests:**
  - Vitest: con `format='teams'`, `Show.vue` **no** muestra el botón "Unirme" ni el bloque de deck; en `registration` + `can_manage`, muestra el selector "Agregar equipo" alimentado por `teams_pool`; no muestra `ReportExport`; sí muestra entries con nombre de equipo.
  - Feature: `GET /torneo-equipos/clandestinos` lista torneos `type=clandestine, format=teams`; `GET /torneo-equipos/history` lista oficiales-equipos completados.
- [ ] **Step 2: Run** → FAIL.
- [ ] **Step 3:**
  - `Show.vue`: añadir props `format` (default `'individual'`) y `teams_pool` (default `[]`). Envolver el bloque "Unirme/deck/Tu duelo"/`my_entry_id` con `v-if="format !== 'teams'"`. Para `format==='teams'` + `registration` + `can_manage`, renderizar un `<select>` de `teams_pool` + botón que hace `router.post('/torneo-equipos/'+tournament.id+'/equipos', { team_id })`. Títulos: "Torneo por equipos" y `<TournamentTabs variant="teams" :active="..."/>`. Encabezado de standings "Equipo". `ReportExport` ya condicionado (Task 9).
  - `TournamentTabs.vue`: prop `variant`; cuando `teams`, los `href` apuntan a `/torneo-equipos` y `/torneo-equipos/clandestinos`.
  - `Teams/Index.vue`: copia adaptada de `Clandestine/Index.vue` apuntando a rutas de equipos (`tabs variant="teams"`, create → `/torneo-equipos/clandestinos`, cards → `/torneo-equipos/{id}`).
  - `TeamTournamentController`: `clandestineIndex()` (type=clandestine, format=teams, con `entries_count`+creador) renderiza `Tournament/Teams/Index`; `history()`/`historyShow()` espejan las individuales filtrando `format='teams'` y usando nombre de equipo para el campeón; renderizan vistas de historial reutilizadas o propias (seguir patrón individual).
  - Rutas: GET `/torneo-equipos/clandestinos`, `/torneo-equipos/history`, `/torneo-equipos/history/{tournament}`.
  - `AppLayout.vue`: link "Torneo por equipos" → `/torneo-equipos` (desktop y móvil).
- [ ] **Step 4: Run** Vitest + `php artisan test` → verde.
- [ ] **Step 5: Commit** `feat: team tournament UI (show, tabs, clandestine list, history, nav)`.

## Task 12: Recompilar assets

**Files:**
- Modify: `public/build/*` (artefactos Vite)

- [ ] **Step 1:** `npm run build`.
- [ ] **Step 2:** Verificar que no quedaron cambios de código sin commitear aparte de `public/build`.
- [ ] **Step 3: Commit** `build: recompile assets for team tournaments`.

## Self-Review (rellenado por quien escribe el plan)

- **Cobertura del spec:** registro restaurado (T4), torneo por equipos oficial+clandestino (T5), motor reutilizado (T1-3,6-8), resultado por equipo ganador (T8), vista igual a la captura (T11), PDF/campeones/historial aislados (T9,T5,T11), ciclo de vida (T10). ✔
- **Consistencia de tipos:** `format` (string), `isTeams()`, `team()`, `teams_pool` ({id,name}), rutas `team-tournament.*` — usadas igual en las tareas que las consumen. ✔
- **Review Focus:** cada punto tiene tarea/test (notificación T3/T8, PDF T9, ciclo de vida T10, candado por formato T5, bye T8). ✔
- **Proporción:** el plan usa firmas y nombres de test, no cuerpos completos; el motor no se reescribe, se reutiliza. ✔

# Equipos por jugadores — Plan de implementación

> **For agentic workers:** REQUIRED SUB-SKILL: superpowers:executing-plans (native) para implementar tarea por tarea. Pasos con checkbox `- [ ]`.

**Goal:** Convertir los equipos de miembros-de-texto a **membresía de usuarios** (crear/unirse/salir/expulsar/roles, máx 3, capitán gestiona) y reajustar el torneo por equipos para que la elegibilidad use el deck de torneo de cada integrante.

**Architecture:** `members` pasa a ser pivote usuario↔equipo con `role`. Se elimina `beyblades` (beys = deck de torneo personal). El torneo por equipos reutiliza todo; solo cambia la definición de "equipo listo". Datos de equipos viejos se resetean.

**Tech Stack:** Laravel 12, Inertia, Vue 3, MySQL, Vite, PHPUnit + Vitest.

**Spec:** docs/superpowers/specs/2026-10-28-bxtorneum-equipos-por-jugadores-design.md

## Global Constraints

- TDD RED→GREEN por tarea; suite PHP (`php artisan test`) y Vitest (`npm run test`) verdes antes de cerrar cada tarea.
- Un usuario en **un** equipo a la vez (unique user_id en members). Máx 3 por equipo. Roles únicos por equipo.
- Solo el **capitán** expulsa/cambia roles. Nadie se expulsa a sí mismo.
- Salir/expulsar/cambiar-rol/eliminar bloqueado mientras el equipo tenga entry en torneo `status != completed`.
- Reset de datos de equipos aprobado (empezar limpio).
- Rol libre al unirse: subcapitán, luego oficial.

## Review Focus

- **Capitán se sale** con otros miembros → capitanía al más antiguo; sin miembros → equipo borrado (Task 5).
- **Unirse** cuando ya se tiene equipo, o equipo lleno (3), o sin lugar de rol → rechazo (Task 4).
- **Expulsar/cambiar rol** por quien no es capitán, o sobre el capitán, o en torneo activo → rechazo (Task 6,7).
- **Torneo:** un equipo con <3 integrantes o con algún integrante sin deck de torneo → no elegible (`teams_pool`/`addTeam`) (Task 8).
- **Reset/migración:** no dejar filas huérfanas ni romper FK (team-format tournaments/entries borrados antes de reestructurar) (Task 1).

## File Structure

**Modificados:** database/migrations (nueva de reset+restructure), app/Models/Team.php, app/Models/Member.php, app/Http/Controllers/TeamController.php, app/Http/Requests/TeamRequest.php, routes/web.php, app/Http/Controllers/TeamTournamentController.php, app/Http/Controllers/TournamentController.php (removeEntry ya team-safe), resources/js/Pages/Teams/{Index,Create,Show}.vue, database/seeders/{TeamSeeder,TeamTournamentDemoSeeder}.php, AppLayout (si aplica).

**Eliminados:** app/Models/Beyblade.php (equipo), app/Http/Controllers/TeamComboController.php, app/Http/Requests/TeamComboRequest.php, resources/js/Pages/Teams/{Combos,Edit}.vue, tests viejos (TeamNamesTest, TeamComboTest, TeamCompletenessTest, TeamSeederTest, TeamsCombos/Edit vitest).

**Nuevos tests:** tests/Feature/TeamMembershipTest.php, TeamCaptainManagementTest.php, tests/Unit/TeamRulesTest.php, resources/js/Pages/__tests__/TeamsShow.test.js (rework).

---

## Task 1: Reset + reestructurar el modelo de miembros

**Files:** Create migration `..._restructure_members_as_user_memberships.php`; Modify app/Models/Member.php, app/Models/Team.php; delete app/Models/Beyblade.php. Test: tests/Unit/TeamRulesTest.php.

**Interfaces:** Produces `members(team_id,user_id,role enum)` unique(user_id), unique(team_id,role); `Member::user()`, `Member::team()`; `Team::members()`, `Team::captain()`.

- [ ] Step 1: Test `test_a_team_member_is_a_user_with_a_role` (crear team + member con user_id+role; `->user` es el User; unique user_id rechaza segundo equipo).
- [ ] Step 2: Run → FAIL.
- [ ] Step 3: Migración: dentro de transacción, borrar matches/rounds/entries de tournaments `format='teams'` y esos tournaments; borrar `beyblades`; `Schema::drop('members')` y recrear con (id, team_id FK cascade, user_id FK cascade, role enum, timestamps, unique(user_id), unique(team_id,role)); `DB::table('teams')->delete()`. `down()` recrea members viejo (name) y beyblades (best-effort). Actualizar `Member` (belongsTo user/team, sin beyblades), `Team` (members, captain()=members role captain). Borrar `Beyblade`.
- [ ] Step 4: Run → PASS; `php artisan test` (otros tests de equipos aún romperán — se arreglan en sus tareas; dejar constancia).
- [ ] Step 5: Commit `feat: restructure team members as user memberships`.

## Task 2: Reglas del modelo Team

**Files:** Modify app/Models/Team.php. Test: tests/Unit/TeamRulesTest.php.

**Interfaces:** `hasRoom():bool` (<3), `memberFor(User):?Member`, `firstOpenRole():?string` (subcaptain→official→null), `isTournamentReady():bool` (3 miembros y cada user con deck de torneo elegible), `promoteOldestToCaptain():void`.

- [ ] Step 1: Tests: hasRoom con 0/2/3; firstOpenRole; isTournamentReady false si <3 o si un miembro sin deck de torneo, true si 3 con deck; promoteOldest asciende al member más antiguo.
- [ ] Step 2: Run → FAIL.
- [ ] Step 3: Implementar. `isTournamentReady`: `members()->count()===3` y cada `member->user` tiene `decks()->where('is_tournament_deck',true)->whereHas('deckBeyblades')->exists()`.
- [ ] Step 4: Run → PASS.
- [ ] Step 5: Commit `feat: team membership rules (room, roles, tournament readiness, captaincy)`.

## Task 3: Crear equipo (solo nombre, creador=capitán)

**Files:** Modify TeamController@store, TeamRequest, routes. Test: TeamMembershipTest.

**Interfaces:** `POST /teams {name}` → crea team + member(captain). Rechaza si el usuario ya tiene equipo.

- [ ] Step 1: Tests: usuario sin equipo crea (redirect, member captain creado); usuario con equipo → 422; name requerido.
- [ ] Step 2: Run → FAIL.
- [ ] Step 3: `TeamRequest` rules `name required`. `store`: abort_if usuario ya en un equipo (member con su user_id); crear team(name, created_by_user_id) + member(role captain, user). `create()` sigue mostrando `Teams/Create`.
- [ ] Step 4: Run → PASS.
- [ ] Step 5: Commit `feat: create a team as its captain`.

## Task 4: Unirse (libre, si hay lugar)

**Files:** Modify TeamController@join, routes. Test: TeamMembershipTest.

**Interfaces:** `POST /teams/{team}/join` → añade al usuario con el primer rol libre.

- [ ] Step 1: Tests: sin equipo + equipo con lugar → entra (rol subcapitán, luego oficial para el 3º); con equipo → 422; equipo lleno (3) → 422.
- [ ] Step 2: Run → FAIL.
- [ ] Step 3: `join`: abort_if ya en equipo; abort_unless hasRoom; crear member(user, role=firstOpenRole()). back success.
- [ ] Step 4: Run → PASS.
- [ ] Step 5: Commit `feat: join a team with an open slot`.

## Task 5: Salirse (con promoción/disband)

**Files:** Modify TeamController@leave, routes, Team::isLockedByActiveTournament (reutilizado). Test: TeamMembershipTest.

**Interfaces:** `DELETE /teams/{team}/leave`.

- [ ] Step 1: Tests: integrante no-capitán sale (member borrado); capitán sale con otros → más antiguo pasa a capitán; capitán sale solo → equipo borrado; salir con torneo activo → 422; no-integrante → 422.
- [ ] Step 2: Run → FAIL.
- [ ] Step 3: `leave`: localizar member del usuario (abort_unless existe); abort_if isLockedByActiveTournament; borrar member; si era captain y quedan miembros → promoteOldestToCaptain; si no quedan → team->delete().
- [ ] Step 4: Run → PASS.
- [ ] Step 5: Commit `feat: leave a team (promote oldest or disband)`.

## Task 6: Expulsar (solo capitán)

**Files:** Modify TeamController@expelMember, routes. Test: TeamCaptainManagementTest.

**Interfaces:** `DELETE /teams/{team}/members/{member}`.

- [ ] Step 1: Tests: capitán expulsa a otro (borrado); capitán no se expulsa a sí mismo → 422; no-capitán → 403; con torneo activo → 422; member de otro equipo → 404.
- [ ] Step 2: Run → FAIL.
- [ ] Step 3: `expelMember`: verificar que el auth user es capitán del team (si no 403); abort_if member->user_id === auth (no a sí mismo); abort_unless member->team_id===team->id; abort_if isLockedByActiveTournament; borrar.
- [ ] Step 4: Run → PASS.
- [ ] Step 5: Commit `feat: captain can expel a member`.

## Task 7: Cambiar rol (solo capitán)

**Files:** Modify TeamController@updateMemberRole, routes. Test: TeamCaptainManagementTest.

**Interfaces:** `PATCH /teams/{team}/members/{member}/role {role}` (subcaptain|official).

- [ ] Step 1: Tests: capitán cambia el rol de un integrante; si el rol destino lo tiene otro, se intercambian; no sobre el capitán; no-capitán → 403; torneo activo → 422.
- [ ] Step 2: Run → FAIL.
- [ ] Step 3: `updateMemberRole`: capitán-check; validar role in subcaptain/official; abort si member es capitán; si otro member tiene ese role, intercambiar (swap) dentro de transacción; abort_if torneo activo.
- [ ] Step 4: Run → PASS.
- [ ] Step 5: Commit `feat: captain can set member roles (swap subcaptain/official)`.

## Task 8: Elegibilidad de torneo por el deck de cada integrante

**Files:** Modify TeamTournamentController (addTeam, teamsPool), tests de torneo (helpers completeTeam → 3 usuarios con deck). Test: TeamTournamentRegistrationTest/ShowTest (rework de helpers) + nuevo caso.

**Interfaces:** `addTeam`/`teamsPool` usan `Team::isTournamentReady()`.

- [ ] Step 1: Tests: equipo con 3 integrantes con deck → elegible (addTeam OK, aparece en teams_pool); equipo con 3 pero uno sin deck → 422/no aparece; equipo con <3 → 422. Reescribir helper `completeTeam` en los tests de torneo por equipos para crear 3 usuarios con deck de torneo.
- [ ] Step 2: Run → FAIL (hoy usa isComplete 9 beys / beyblades inexistentes).
- [ ] Step 3: En `addTeam`: `abort_unless($team->isTournamentReady(), 422, ...)`. En `teamsPool`: filtrar `isTournamentReady()`. Ajustar helpers de los tests de torneo.
- [ ] Step 4: Run → PASS; `php artisan test` del grupo de torneo por equipos verde.
- [ ] Step 5: Commit `feat: team tournament eligibility uses each member's tournament deck`.

## Task 9: Retirar combos/código viejo de equipos

**Files:** Delete TeamComboController, TeamComboRequest, resources/js/Pages/Teams/{Combos,Edit}.vue; remove combos routes; delete/replace old tests (TeamNamesTest, TeamComboTest, TeamCompletenessTest, TeamSeederTest, TeamsCombos/Edit vitest); update TeamSeeder (o eliminarlo del DatabaseSeeder). Test: suite completa verde.

- [ ] Step 1: Identificar referencias (routes `combos.*`, `teams.edit`, imports). Run suite → ver rojos de lo viejo.
- [ ] Step 2-3: Borrar archivos/rutas/tests obsoletos; `TeamSeeder` reescrito o retirado de `DatabaseSeeder` (no romper otros seeders).
- [ ] Step 4: `php artisan test` + `npm run test` → verde.
- [ ] Step 5: Commit `chore: remove per-team combos and name-based team code`.

## Task 10: UI de equipos (Index, Create, Show)

**Files:** resources/js/Pages/Teams/{Index,Create,Show}.vue; TeamController (props de show/index). Test: resources/js/Pages/__tests__/{TeamsIndex,TeamsShow,TeamsCreate}.test.js (rework).

**Interfaces:** props: index (`teams:[{id,name,members_count,has_room}]`, `my_team_id`); show (`team:{id,name,members:[{id,nickname,avatar_url,role,has_tournament_deck}], is_tournament_ready}`, `viewer:{is_member,is_captain,can_join}`).

- [ ] Step 1: Vitest: Index muestra "Registrar equipo" solo si el viewer no tiene equipo y "N/3"; Show muestra integrantes con rol, botón Unirme/Salir según viewer, y controles de capitán (Expulsar/rol) solo si is_captain.
- [ ] Step 2: Run → FAIL.
- [ ] Step 3: Implementar controller props + Vue. Create: solo nombre.
- [ ] Step 4: Vitest + `php artisan test` (feature de show/index) → verde.
- [ ] Step 5: Commit `feat: team management UI (roster, join/leave, captain controls)`.

## Task 11: Demo seeder + assets

**Files:** database/seeders/TeamTournamentDemoSeeder.php (al modelo nuevo: crea usuarios con deck de torneo, arma equipos de 3, inscribe); public/build.

- [ ] Step 1: Reescribir el seeder para crear usuarios+deck+equipos de 3 listos y un torneo por equipos completado + uno abierto.
- [ ] Step 2: `php artisan migrate:fresh` (dev) o correr seeder; `npm run build`.
- [ ] Step 3: Commit `chore: update team tournament demo seeder and rebuild assets`.

## Fase 2 (fuera de este plan, se avisa): invitado se une a un equipo dentro de un torneo por equipos; se destruye al terminar.

## Self-Review

- Cobertura: modelo (T1-2), crear/unir/salir/expulsar/rol (T3-7), torneo (T8), limpieza (T9), UI (T10), demo (T11). ✔
- Tipos: `role` enum consistente; `isTournamentReady`, `firstOpenRole`, `promoteOldestToCaptain` usados igual donde se consumen. ✔
- Review Focus: cada punto con tarea/test. ✔
- Proporción: firmas y nombres de test; sin cuerpos completos. ✔

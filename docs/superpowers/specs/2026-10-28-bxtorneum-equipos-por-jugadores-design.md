# Equipos por jugadores — Diseño

## Intención (el porqué)

Hoy un "equipo" es un nombre de equipo con 3 **nombres de texto** (Capitán/Subcapitán/Oficial)
y beys guardados por miembro. Fernando quiere que los equipos sean de **jugadores reales**
(usuarios): un jugador sin equipo puede **unirse** a uno con lugar, **salirse** y **cambiarse**;
el **capitán** (quien crea el equipo) **asigna roles** y **expulsa**. Máximo **3** integrantes.
Esto reemplaza el modelo de miembros-de-texto y reajusta el torneo por equipos para que los beys
de cada integrante vengan de **su propio deck de torneo** (de su perfil), unificando con el torneo
individual.

## Objetivos

1. Un equipo tiene **integrantes que son usuarios**, con rol `captain` / `subcaptain` / `official`.
2. **Crear equipo:** un usuario logueado crea un equipo con **solo el nombre**; queda como **capitán**.
   Solo puede crear/pertenecer si **no está ya en un equipo**.
3. **Unirse (libre):** un usuario **sin equipo** se une a cualquier equipo con **menos de 3**
   integrantes; toma el primer rol libre (subcapitán, luego oficial).
4. **Salirse / cambiarse:** un integrante puede salir; luego puede unirse a otro.
5. **Capitán gestiona:** expulsa integrantes (no a sí mismo) y **cambia los roles**
   (subcapitán ↔ oficial) de los otros dos.
6. **Capitán se sale:** la capitanía pasa al integrante **más antiguo**; si queda vacío, el equipo
   se **elimina**.
7. **Torneo por equipos:** un equipo queda **listo** cuando tiene **3 integrantes, cada uno con su
   deck de torneo** (is_tournament_deck con al menos un combo). Reemplaza el "9 beys" guardado en el
   equipo.
8. **Invitados en torneo (Fase 2):** dentro de un torneo por equipos, un invitado puede unirse a un
   equipo **solo para ese torneo**; su usuario invitado se destruye al terminar (mecanismo de
   invitados existente). Se construye al final y puede separarse.

## No-objetivos

- No se cambia el motor de emparejamiento ni la UI del bracket/grupos (ya hecho).
- No se tocan los decks/combos personales de los usuarios (ya existen en el perfil); el equipo
  **reutiliza** el deck de torneo de cada integrante, no guarda beys propios.
- Un jugador no puede estar en **dos equipos** a la vez.

## Anti-objetivos (fracaso aunque "funcione")

- Que queden dos conceptos de "miembro" (texto y usuario) conviviendo. El de texto se elimina.
- Que un integrante pueda estar en dos equipos, o que un equipo pueda exceder 3.
- Que expulsar/salir/eliminar un equipo inscrito en un torneo **en curso** corrompa el bracket.
- Que un usuario común pueda expulsar o cambiar roles (solo el capitán de ese equipo).

## Restricciones

- Laravel 12 (PHP 8.3) + Inertia + Vue 3 `<script setup>`, MySQL, Vite (assets committeados), TDD.
- Reutiliza el sistema de usuarios/decks existente (`User`, `Deck`, `DeckBeyblade`, is_tournament_deck).
- Reutiliza el sistema de invitados existente (creación/destrucción de usuarios invitados).
- La feature de torneo por equipos ya existe en esta rama; este diseño la reajusta, no la reemplaza.

## Modelo de datos

- **`teams`**: `id`, `name`, `created_by_user_id`, timestamps (sin cambios de forma).
- **`members`** (se reestructura): `id`, `team_id`, `user_id`, `role` enum(`captain`,`subcaptain`,`official`),
  timestamps. Índices: **unique(`user_id`)** (un equipo por usuario), **unique(`team_id`,`role`)**
  (un rol por equipo). Se **elimina** la columna `name`.
- **`beyblades`**: se **elimina** (los beys de cada integrante son su deck de torneo personal).
- **Reset de datos:** como el modelo viejo (nombres/beys) es incompatible, la migración **borra**
  los equipos existentes y sus datos de torneo por equipos (entries/rounds/matches de `format='teams'`)
  antes de reestructurar. Aprobado por el dueño ("empezar limpio"). En prod aún no hay torneos por
  equipos desplegados.

## Reglas / flujos

- **Pertenencia:** `User` tiene un `membership` (0 o 1 fila en `members`). "Sin equipo" = sin membership.
- **Crear:** `POST /teams` con `name`. Rechaza si el usuario ya tiene equipo. Crea equipo + member
  (role=captain, user=creador).
- **Unirse:** `POST /teams/{team}/join`. Rechaza si el usuario ya tiene equipo, o el equipo tiene 3.
  Asigna el primer rol libre: subcapitán si libre, si no oficial.
- **Salirse:** `DELETE /teams/{team}/leave`. Quita la membership del usuario. Si era capitán: promover
  al integrante más antiguo a capitán; si no queda nadie, borrar el equipo.
- **Expulsar:** `DELETE /teams/{team}/members/{member}` (solo capitán, no a sí mismo).
- **Cambiar rol:** `PATCH /teams/{team}/members/{member}/role` con `role` in subcapitán/oficial
  (solo capitán, no sobre el capitán). Si el rol destino lo ocupa otro, se intercambian.
- **Lock de torneo activo:** salir/expulsar/cambiar-rol/eliminar equipo se **bloquea** mientras el
  equipo tenga entry en un torneo `status != completed` (regla ya existente `isLockedByActiveTournament`,
  reutilizada). Crear/unirse no están bloqueados por esto.

## Integración con torneo por equipos

- **Elegibilidad ("completo"/listo):** `Team::isTournamentReady()` = tiene **3 integrantes** y **cada
  integrante tiene un deck de torneo elegible** (is_tournament_deck + al menos un combo). Reemplaza
  `isComplete()` (9 beys) en `addTeam` y en `teams_pool`.
- La inscripción, el motor, el bracket, el reporte de resultado y la vista **no cambian** (el equipo
  sigue siendo el "participante"; se reporta equipo ganador).
- `teams_pool` = equipos listos (3 integrantes con deck) no inscritos.

## UI (Inertia/Vue)

- **Teams/Index:** lista de equipos (nombre, N/3 integrantes, si tiene lugar). Botón "Registrar equipo"
  (solo si el usuario no tiene equipo). Mostrar "Mi equipo" destacado.
- **Teams/Create:** solo campo **Nombre del equipo** (el capitán es el creador).
- **Teams/Show:** integrantes (avatar + nickname + rol + si tienen deck de torneo). Para el viewer:
  botón **Unirme** (si no tiene equipo y hay lugar) / **Salir** (si es integrante). Para el **capitán**:
  **Expulsar** y **cambiar rol** de los otros, y aviso si el equipo no está listo para torneos.
- Se eliminan las vistas/el flujo de **combos por equipo** (`Teams/Combos`, `Teams/Edit` de nombres):
  los combos viven en el perfil del usuario.

## Migración de lo ya construido / tests

- Reescribir `TeamController` (create/join/leave/expel/role; quitar combos/edit-nombres), nuevo
  `TeamRequest` (solo name), eliminar `TeamComboController`/`TeamComboRequest`, `Beyblade`/combos de equipo.
- `Member` model: belongsTo user + team; sin beyblades.
- `Team`: `members()`, `captain()`, helpers `hasRoom()`, `isTournamentReady()`, reglas de capitanía.
- Reemplazar los helpers `completeTeam()` de los tests de torneo (ahora: 3 usuarios con deck de torneo).
- Actualizar/retirar tests viejos de equipos (nombres, combos, seeder, completeness) por los nuevos.
- Actualizar `TeamTournamentDemoSeeder` al modelo nuevo.

## Lo que queda a criterio del implementador

- Forma exacta de las rutas de gestión de miembros y nombres internos de métodos/props.
- Detalles de UI (orden de botones, textos) siguiendo el estilo existente.
- Cómo se presenta "cambiar rol" (botón que intercambia subcapitán/oficial vs. selector).

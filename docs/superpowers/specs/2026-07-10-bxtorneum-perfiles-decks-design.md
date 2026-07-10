# BxTorneum — Usuarios, perfiles y decks personales (+ reporte de jugadores)

**Fecha:** 2026-07-10
**Estado:** Diseño aprobado, pendiente de plan de implementación
**Relacionado:** sistema nuevo e independiente del registro de equipos/combos de torneo
existente (`Team`/`Member`/`Beyblade`), que **no se modifica** en esta iniciativa.

## Resumen

Hoy la app no tiene ningún sistema de login: es un panel abierto donde un admin captura
equipos y combos a mano para un torneo. Esta iniciativa agrega, en paralelo y sin tocar ese
flujo, un sistema de **cuentas de usuario** donde cada jugador puede:

- Registrarse con nombre completo, nickname (gamer tag), email y contraseña.
- Crear cualquier cantidad de **decks** (cada deck = un set de hasta 3 combos, igual formato
  que un combo de equipo hoy: línea BX/UX/CX/CX Infinity + sus piezas).
- Editar sus decks en cualquier momento (sin bloqueo, a diferencia de los combos de equipo).
- Marcar un deck como **público** (visible para otros usuarios logueados) o dejarlo privado.
- Marcar **un** deck como "deck de torneo" (el que usan en competencia); marcar uno nuevo
  desmarca automáticamente el anterior.

Y agrega un **reporte en PDF** (mismo patrón de contraseña que el reporte de equipos) con
todos los jugadores que tienen un deck de torneo marcado, incluyendo el detalle de ese deck.

## Fuera de alcance (explícito)

- Conectar estos usuarios/decks con el flujo de equipos de torneo (`Team`/`Member`). Son
  sistemas separados por ahora.
- Restringir el panel actual de equipos/combos/reporte a un rol admin.
- El torneo tipo "normal" (fase de grupos + eliminatorias) — es una iniciativa aparte.
- Verificación de email, login social, subida de avatar, clonar/duplicar decks, comentarios o
  "me gusta" en decks públicos.

## Modelo de datos

### `users` (se extiende la tabla estándar de Laravel/Breeze)
- Columnas ya existentes: `name` (se usa como **nombre completo**), `email`, `password`.
- Nueva columna: `nickname` (`string`, `unique`, `not null`). Formato: letras, números, `_`
  y `-` (regex `/^[A-Za-z0-9_-]+$/`), 3–32 caracteres.

### `decks`
- `id`, `user_id` (FK `users`, `cascadeOnDelete`), `name` (`string`, requerido),
  `visibility` (`enum: public,private`, default `private`), `is_tournament_deck`
  (`boolean`, default `false`), timestamps.
- Regla de exclusividad ("solo un deck de torneo por usuario") se aplica a nivel de
  aplicación (no hay índice único parcial portable en MySQL): al marcar un deck se desmarcan
  los demás del mismo usuario dentro de una transacción.

### `deck_beyblades` (espejo de `beyblades`, pero cuelga de un deck en vez de un member)
- `id`, `deck_id` (FK `decks`, `cascadeOnDelete`), `line` (mismos valores que
  `BeybladeLines::lines()`), `position` (`tinyint`, 1–3), timestamps.

### `deck_beyblade_part` (espejo de `beyblade_part`)
- `id`, `deck_beyblade_id` (FK, `cascadeOnDelete`), `part_id` (FK `parts`), `slot` (mismo enum
  de siempre), timestamps, `unique(deck_beyblade_id, slot)`.
- Reutiliza la tabla `parts` **existente** (mismo catálogo ya normalizado de blades, ratchets,
  bits, lock chips, etc.) — no se duplica vocabulario de piezas.

No se toca `teams`, `members`, `beyblades`, `beyblade_part`: quedan congeladas como historial.

## Autenticación

- Se instala **Laravel Breeze** (stack Inertia + Vue), sin verificación de email.
- `POST /register` (controlador de Breeze extendido): agrega `nickname` a la validación
  (`required|string|min:3|max:32|regex:/^[A-Za-z0-9_-]+$/|unique:users,nickname`) y al
  `fillable`/creación del usuario. El formulario de registro (`Auth/Register.vue`) agrega el
  campo Nickname junto a Nombre/Email/Contraseña.
- Login/logout/recuperar contraseña: estándar de Breeze, sin cambios de lógica (solo se
  restylean las vistas para que combinen con el tema oscuro cian/magenta existente).
- Página de perfil (`Profile/Edit.vue` de Breeze, extendida): permite editar nombre, nickname
  y contraseña.
- Middleware `auth` protege todas las rutas de `/decks/*`, `/players*` y la acción de marcar
  deck de torneo. El panel de equipos (`/teams/*`, `/parts/*`, `/report/pdf`) sigue **sin**
  middleware, igual que hoy.

## Backend — Decks

### Modelos
- `Deck` (`belongsTo User`, `hasMany DeckBeyblade`).
- `DeckBeyblade` (`belongsTo Deck`, `belongsToMany Part` con pivot `slot`, con
  `partsBySlot(): array` idéntico en comportamiento al de `Beyblade` — ordenado según
  `BeybladeLines::slotsFor($this->line)`).

### Rutas (grupo `auth`)
- `GET /decks` → `DeckController@index` — lista los decks del usuario autenticado (nombre,
  visibilidad, si es el de torneo, resumen de línea por combo).
- `GET /decks/create` → `DeckController@create`.
- `POST /decks` → `DeckController@store`.
- `GET /decks/{deck}/edit` → `DeckController@edit` (403 si `$deck->user_id !== auth()->id()`).
- `PUT /decks/{deck}` → `DeckController@update` (mismo chequeo de dueño).
- `DELETE /decks/{deck}` → `DeckController@destroy` (mismo chequeo de dueño).
- `PATCH /decks/{deck}/tournament` → `DeckController@markTournament` — marca este deck como
  el de torneo y desmarca cualquier otro del mismo usuario, en una transacción. Body opcional
  `{ value: boolean }` para permitir desmarcar (default `true`).

### Validación — `DeckRequest`
- `name`: `required|string|max:255`.
- `visibility`: `required|in:public,private`.
- `beyblades`: `present|array|max:3`, cada uno con `line` (uno de `BeybladeLines::lines()`) y
  `parts` (mismas reglas de slots requeridos/opcionales que `TeamComboRequest`, reutilizando
  `BeybladeLines::requiredSlotsFor`). Combos totalmente vacíos se ignoran (mismo helper
  `isEmptyCombo` que ya existe, movido o reutilizado desde `TeamComboRequest`).

### `DeckController@update` — estrategia de guardado
A diferencia de los combos de equipo (que se bloquean una vez creados), un deck se edita
libremente: en cada guardado se **reemplaza el set completo** de `deck_beyblades` del deck
dentro de una transacción (borrar los existentes, recrear desde el payload validado,
`Part::firstOrCreate` igual que hoy para las piezas). Esto evita lógica de diffing y es
consistente con "pueden editar sus decks en cualquier momento".

## Backend — Visibilidad y comunidad

- `GET /players` → `PlayerController@index` — lista usuarios que tienen al menos un deck
  público (nombre, nickname, cantidad de decks públicos). Requiere sesión.
- `GET /players/{user}` → `PlayerController@show` — perfil público de un jugador: sus decks
  con `visibility = public` únicamente (los privados nunca se exponen a otro usuario, ni
  siquiera por URL directa). Requiere sesión.
- La vista `/decks` (propia) sí muestra todos los decks del usuario, públicos y privados, con
  controles de editar/eliminar/marcar torneo.

## Backend — Reporte de jugadores (PDF)

- `POST /report/players/pdf` → `PlayerReportController@download` — mismo mecanismo que
  `ReportController@download` hoy: valida `password`, compara con `hash_equals` contra
  `config('report.password')` (se **reutiliza la misma contraseña** ya usada para el reporte
  de equipos, para no pedirle al admin gestionar dos secretos distintos), 403 si no coincide o
  si no hay contraseña configurada.
- Contenido: `User::whereHas('decks', fn ($q) => $q->where('is_tournament_deck', true))`,
  con el deck de torneo cargado (`decks.deckBeyblades.parts`). Por jugador: nombre completo,
  nickname, nombre del deck, y sus combos (línea + piezas por slot en orden canónico,
  reutilizando `BeybladeLines`).
- Vista `resources/views/reports/players.blade.php`, mismo estilo visual (claro,
  print-friendly, acento cian→magenta) que `reports/teams.blade.php`.
- Botón "Exportar PDF" reutilizando el componente `ReportExport.vue` existente (genérico:
  recibe la URL del endpoint y el nombre de archivo por props), ubicado en `/players`.

## Frontend — páginas nuevas

- `Pages/Decks/Index.vue`: lista de "mis decks" con badges (Público/Privado,
  ⭐ Deck de torneo), botones editar/eliminar/marcar como torneo, botón "+ Crear deck".
- `Pages/Decks/Create.vue` / `Pages/Decks/Edit.vue`: nombre, toggle público/privado, hasta 3
  bloques `<BeybladeForm>` (componente **reutilizado tal cual**, ya es genérico por props).
- `Pages/Players/Index.vue`: listado de jugadores con decks públicos.
- `Pages/Players/Show.vue`: perfil público de un jugador y sus decks públicos (solo lectura).
- Vistas de auth (`Auth/Login.vue`, `Auth/Register.vue`, `Auth/ForgotPassword.vue`, etc.):
  las que genera Breeze, restyleadas al tema oscuro existente.
- `AppLayout.vue` se extiende: si hay usuario autenticado, nav agrega enlaces "Mis decks" /
  "Jugadores" / nombre+logout; si es invitado, agrega "Iniciar sesión" / "Registrarme". El
  enlace "+ Registrar equipo" del panel de equipos se mantiene sin cambios.

## Reglas y manejo de errores

- Un usuario no puede ver/editar/eliminar decks de otro (403 en controlador, comprobando
  `$deck->user_id === auth()->id()` antes de cualquier acción de escritura o de `edit`).
- Un deck privado nunca aparece en `/players`, `/players/{user}` ni en el reporte PDF si su
  dueño no tiene *otro* deck marcado como torneo que sí sea el que se reporta (el reporte
  muestra el deck de torneo sin importar su visibilidad, ya que es información operativa para
  el organizador, no pública).
- Nickname duplicado → error de validación `422` con mensaje en el campo.
- Marcar un deck como torneo cuando ya había otro marcado → transacción atómica
  (desmarcar + marcar); si falla a mitad, rollback completo.

## Pruebas

**Backend (PHPUnit/Pest, siguiendo el patrón de `tests/Feature` ya existente):**
- Registro exige nickname único y con formato válido.
- Un usuario puede crear un deck con hasta 3 combos; combo vacío se ignora igual que hoy en
  equipos.
- Un usuario no puede editar/eliminar/ver-edición de un deck ajeno (403).
- Marcar un deck como torneo desmarca cualquier otro deck de torneo del mismo usuario.
- Un deck privado no aparece en `/players/{user}` para otro usuario ni en `/players` (index).
- Un deck público sí aparece en `/players/{user}` para otro usuario logueado.
- `/players` y `/players/{user}` responden 302 (redirect a login) para visitantes sin sesión.
- `POST /report/players/pdf`: contraseña correcta → 200 PDF; incorrecta o ausente → 403;
  solo incluye usuarios con `is_tournament_deck = true`; el orden de piezas en el PDF sigue
  `BeybladeLines::slotsFor`.

**Frontend (Vitest):** cobertura ligera enfocada en lo más propenso a errores — el formulario
de deck reutiliza `BeybladeForm`/`PartAutocomplete` ya probados, así que las pruebas nuevas se
concentran en: el toggle público/privado, el botón de marcar deck de torneo, y el guard de
nickname en el registro. No se busca cobertura exhaustiva de cada página nueva.

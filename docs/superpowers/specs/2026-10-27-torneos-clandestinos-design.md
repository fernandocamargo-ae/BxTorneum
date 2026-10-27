# Diseño: Torneos oficiales y clandestinos

Fecha: 2026-10-27
Estado: propuesta (pendiente de aprobación)

## Intención y porqué

Hoy la app maneja **un solo torneo a la vez**, creado y administrado solo por
admins. El usuario quiere abrir la organización de torneos a los jugadores
normales sin perder la seriedad del registro oficial. Para eso se separan los
torneos en dos tipos:

- **Oficiales**: los de siempre. Solo un admin los crea y administra. Son los
  únicos que cuentan para el cuadro de **Campeones del mes** y para el
  historial oficial.
- **Clandestinos**: torneos "por diversión". Cualquier usuario con sesión
  (admin o no — lo llamamos *Rol Amateur*) puede crearlos y administrarlos.
  No cuentan para campeones del mes, ni para el historial oficial, ni para
  ningún récord/estadística oficial del jugador.

El motor de torneos (suizo + corte a eliminatorias, emparejamientos, tabla,
rondas, resultados) es **idéntico** para ambos tipos. Lo único que cambia es
**quién puede administrar** y **si el resultado cuenta como oficial**.

## Objetivos

- Un torneo puede ser oficial o clandestino.
- Varios torneos activos a la vez: **un** oficial activo como máximo (como hoy),
  **varios** clandestinos activos sin límite.
- Un mismo jugador puede estar inscrito al mismo tiempo en el oficial y en
  varios clandestinos.
- Permisos:
  - Oficial: crear/administrar = solo `is_admin`.
  - Clandestino: crear/administrar = cualquier usuario con sesión que no sea
    invitado (`is_guest`), admins incluidos.
  - Inscribirse/salir: cualquier usuario con sesión, en cualquier torneo (como hoy).
- Navegación por **pestañas** en la sección Torneos: "Oficial" | "Clandestinos".
  La pestaña Oficial se ve igual que hoy. "Clandestinos" es una lista propia.
- Cada torneo tiene su **página propia** (tabla, rondas, resultados) a la que
  se entra tocándolo.
- Marcar los torneos existentes (agosto, septiembre, octubre, etc.) como
  **oficiales** — basta con el backfill, ya que hasta hoy todos los torneos lo eran.

## No-objetivos

- Los clandestinos **no** alimentan Campeones del mes (ese cuadro sigue siendo
  una lista curada manualmente por el admin — sin cambios en su lógica).
- Los clandestinos **no** aparecen en el historial oficial
  (`/tournament/history`) ni en ningún récord/estadística oficial del jugador.
- No se crea un "rol" nuevo en la base de datos. *Amateur* = cualquier usuario
  con sesión que no sea admin; se deriva de `is_admin`, no se guarda.
- No se añade borrado de torneos por UI (hoy no existe; fuera de alcance).

## Anti-objetivos (qué sería un fracaso aunque "funcione")

- Que un clandestino ensucie el récord oficial, el historial oficial o los
  campeones del mes.
- Que al refactorizar se rompa el flujo del torneo oficial que ya está en
  producción (inscripción → suizo → corte → eliminatorias → campeón).
- Que un no-admin pueda tocar un torneo oficial, o que un invitado efímero
  pueda crear torneos.

## Estado actual (lo que hay que tocar)

El motor resuelve "el torneo activo" de forma implícita con
`Tournament::where('status','!=','completed')->firstOrFail()` en casi todos los
métodos. Ese es el centro del refactor: pasar de "el torneo activo implícito" a
"**este** torneo por id".

Métodos afectados (todos en `App\Http\Controllers`):

- `TournamentController`: `store`, `update`, `removeEntry`, `join`, `leave`,
  `cut`, `show`, `history`, `historyShow`.
- `TournamentRoundController@store` (generar ronda).
- `TournamentMatchController@update` (ya resuelve el torneo vía
  `$match->round->tournament`; solo le falta el chequeo de permiso).
- `TournamentReportController@download` (hoy resuelve el activo implícito con
  `latest()->first()`; pasa a recibir el torneo por id).
- Rutas en `routes/web.php` (hoy singulares bajo `/tournament`, con las de
  administración bajo el middleware `admin`).

Props compartidas: `HandleInertiaRequests` ya expone `auth.user` (incluye
`is_admin`), así que el frontend puede decidir qué botones mostrar.

## Modelo de datos

Migración nueva: añadir a `tournaments` la columna

```php
$table->enum('type', ['official', 'clandestine'])->default('official')->after('name');
```

Backfill en la misma migración: `UPDATE tournaments SET type = 'official'` (todos
los existentes son oficiales). `created_by_user_id` ya existe y sigue registrando
al creador. `Tournament` agrega helpers `isOfficial()` / `isClandestine()`.

## Permisos: una Policy

Nueva `App\Policies\TournamentPolicy`:

- `manage(User $user, Tournament $t)`:
  - oficial → `$user->is_admin`
  - clandestino → `$user && ! $user->is_guest`
- `createOfficial(User $user)` → `$user->is_admin`
- `createClandestine(User $user)` → `! $user->is_guest`

Los controladores llaman `$this->authorize('manage', $tournament)` en
update/removeEntry/cut/rounds.store/matches.update, y `authorize('createOfficial'
|'createClandestine')` en los dos stores. Join/leave quedan solo detrás de
`auth` (cualquier usuario con sesión), como hoy.

Esto reemplaza el uso del middleware `admin` para las acciones de torneo (el
middleware `admin` se conserva para el resto: usuarios, etc.).

## Rutas (nueva forma, por-torneo)

Dentro del grupo `auth`:

```
GET    /tournament                         -> Oficial: torneo oficial activo (o último completado) inline, como hoy
GET    /tournament/clandestinos            -> Clandestinos: lista de torneos clandestinos
GET    /tournament/{tournament}            -> página propia de un torneo (oficial o clandestino)
POST   /tournament                         -> crear OFICIAL  (authorize createOfficial)
POST   /tournament/clandestinos            -> crear CLANDESTINO (authorize createClandestine)
PATCH  /tournament/{tournament}            -> editar (authorize manage)
DELETE /tournament/{tournament}/entries/{entry} -> quitar inscrito (authorize manage)
POST   /tournament/{tournament}/join       -> inscribirse (auth)
DELETE /tournament/{tournament}/leave      -> salir (auth)
POST   /tournament/{tournament}/rounds     -> generar ronda (authorize manage)
POST   /tournament/{tournament}/cut        -> corte a eliminatorias (authorize manage)
PATCH  /tournament/matches/{match}         -> reportar/corregir resultado (authorize manage via match->tournament)
POST   /tournament/{tournament}/report/pdf -> PDF del torneo (auth; throttle 5,1)
GET    /tournament/history                 -> historial OFICIAL (sin cambios de contenido)
GET    /tournament/history/{tournament}    -> detalle de oficial completado (HistoryShow, SIN cambios)
```

La ruta `GET /tournament/{tournament}` debe ir **después** de
`/tournament/clandestinos` y `/tournament/history` para no capturarlas.

### Concurrencia

- `createOfficial`: conserva el guard actual pero **scoped a oficiales**:
  abortar si existe un oficial activo (`type='official'` y `status!='completed'`).
- `createClandestine`: sin guard (varios permitidos).

## Pantallas

### Pestaña "Oficial" (`GET /tournament`)
Igual que hoy: resuelve el oficial activo (o el último completado) y renderiza
`Tournament/Show` con toda su lógica (inscripción, tabla, rondas, botones de
admin, confirmación de siguiente ronda ya existente). Arriba, una barra de
pestañas **Oficial | Clandestinos**.

### Pestaña "Clandestinos" (`GET /tournament/clandestinos`)
Nueva página `Tournament/Clandestine/Index.vue`: lista de torneos clandestinos
(activos primero, luego terminados recientes) como filas con nombre, creador,
nº de jugadores, badge de estado (Inscripción / En curso / Terminado) y acceso a
su página. Arriba, un botón **"+ Crear torneo clandestino"** visible para
cualquier usuario con sesión no-invitado. Misma barra de pestañas.

### Página de un torneo (`GET /tournament/{tournament}`)
Reusa `Tournament/Show` (el mismo componente) alimentado por el torneo dado en
vez del activo implícito. Los botones de administración (crear ronda, corte,
corregir, editar, quitar inscritos) se muestran según
`can_manage` (prop booleana calculada en backend con la Policy), no según
`is_admin`. Un badge "Oficial"/"Clandestino" en el encabezado.

### Navegación global
El layout autenticado ya tiene un enlace "Torneo". Se mantiene apuntando a
`/tournament`; las pestañas dentro de la sección cubren el cambio entre oficial
y clandestinos.

## Qué NO cambia

- Motor de emparejamiento/suizo/corte/eliminatorias (`App\Support\*`).
- Requisito de deck de torneo para inscribirse.
- Campeones del mes (`MonthlyChampionController`) — sigue siendo lista manual
  del admin.
- Historial oficial — sigue mostrando solo oficiales completados (ahora filtra
  explícitamente `type='official'`).
- Lógica de invitados (`endGuestSessions`) al completar un torneo.

## Pruebas (TDD)

Backend (PHPUnit, RefreshDatabase):
- Un no-admin puede crear un clandestino; no puede crear un oficial (403).
- Un admin puede crear un oficial y un clandestino.
- Un invitado (`is_guest`) no puede crear ninguno.
- Un no-admin puede generar ronda / corregir resultado / cortar en un
  clandestino, pero recibe 403 en un oficial.
- Puede haber varios clandestinos activos; solo un oficial activo (el segundo
  oficial aborta 422).
- El historial oficial no incluye clandestinos.
- Un jugador puede estar inscrito en el oficial y en un clandestino a la vez.
- Backfill: migración deja los torneos existentes como `official`.

Frontend (Vitest):
- La pestaña Clandestinos lista torneos y muestra "+ Crear torneo clandestino"
  a un usuario con sesión.
- En la página de un torneo, los botones de administración aparecen solo cuando
  `can_manage` es verdadero.

## Despliegue

Igual que siempre: build de assets commiteado, push a master, luego en el VPS
`git pull`, migrar (`php artisan migrate --force`), `config/route/view:cache` y
`systemctl restart php8.3-fpm` (OPcache con `validate_timestamps=0`).
La migración es aditiva (columna nueva + backfill), sin pérdida de datos.

## Decisiones tomadas por mí (marcar para revisión del usuario)

- **Invitados no crean/administran clandestinos** (solo participan): son
  usuarios efímeros, no tendría sentido que organicen. *Amateur* = usuario real
  con sesión, no invitado.
- **El historial oficial se mantiene solo-oficial**; los clandestinos
  terminados se ven desde su propia pestaña, no en el historial oficial.
- **Sin borrado de torneos por UI** (no existe hoy; fuera de alcance).
- **Cualquier amateur administra cualquier clandestino** (opción B elegida por
  el usuario), no solo el creador.
- **`endGuestSessions` se dispara solo al completar un OFICIAL.** Hoy, al
  terminar un torneo se borran las sesiones de los invitados inscritos. Con
  torneos simultáneos, un clandestino que termina desconectaría a invitados que
  siguen en el oficial. Por eso la limpieza queda ligada solo a oficiales; los
  clandestinos nunca cierran sesiones de nadie.
- **El reporte PDF pasa a ser por-torneo** (`/tournament/{tournament}/report/pdf`),
  porque con varios activos el "activo implícito" era ambiguo. El botón vive en
  la página de cada torneo. Disponible para cualquier usuario con sesión.
- **El detalle de oficial completado (`HistoryShow`) se conserva sin cambios.**
  La página unificada `/tournament/{tournament}` sirve para torneos en vivo
  (ambos tipos) y para el detalle de clandestinos; el historial oficial y su
  drill-down siguen idénticos a hoy.

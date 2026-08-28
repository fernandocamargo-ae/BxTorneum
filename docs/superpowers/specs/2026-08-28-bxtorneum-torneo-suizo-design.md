# BxTorneum — Torneo con emparejamiento suizo + eliminatorias

**Fecha:** 2026-08-28
**Estado:** Diseño aprobado, pendiente de plan de implementación
**Relacionado:** se apoya en el sistema de usuarios/decks existente
(`docs/superpowers/specs/2026-07-10-bxtorneum-perfiles-decks-design.md`), en particular en el
flag `decks.is_tournament_deck` ya construido. No toca `Team`/`Member`/`Beyblade` (equipos),
que quedan deshabilitados temporalmente por decisión de negocio ajena a esta iniciativa.

## Resumen

Hoy la app permite a un jugador crear su cuenta, armar decks y marcar uno como "deck de
torneo". Esta iniciativa agrega el **torneo en sí**: un administrador crea un torneo, los
jugadores con perfil se van uniendo (usando el deck que ya tienen marcado), y el sistema
corre el formato competitivo estándar de Beyblade X (el mismo que usan WBO y las tiendas
oficiales, análogo a lo que hace Konami Tournament Software para Yu-Gi-Oh):

1. **Fase suiza**: N rondas (configuradas por el admin al crear el torneo). Ronda 1 empareja
   al azar; las siguientes emparejan por récord similar, evitando repetir rival cuando se
   puede, con bye rotativo si el número de jugadores es impar.
2. **Corte a eliminatorias**: al terminar la fase suiza, el admin corta a los mejores N
   jugadores (configurado al crear el torneo) hacia un bracket de eliminación directa
   sembrado por standings.
3. **Eliminatorias**: solo avanzan los ganadores, hasta la final. Al resolverse la final, el
   torneo se cierra y queda un campeón.

Cada vez que se genera una ronda, cada jugador recibe un **correo** con el nickname de su
rival y el número de ronda. Solo puede existir **un torneo no completado a la vez**.

## Fuera de alcance (explícito)

- Reactivar o integrar el flujo de equipos (`Team`/`Member`/`Beyblade`).
- Marcador detallado por puntos Beyblade X (Spin/Over/Burst/Xtreme); solo se captura quién
  ganó cada duelo.
- Reporte de resultados por parte de los jugadores; solo el admin captura resultados y avanza
  rondas.
- Correos encolados/en background — se envían de forma síncrona (ver "Notificaciones").
- Que un jugador se pueda dar de baja ("drop") de un torneo en curso.
- Selección de deck distinta a la ya marcada como torneo en `/decks`.
- Varios torneos simultáneos o historial navegable de torneos pasados (el modelo lo permite a
  futuro, pero la UI de esta iniciativa solo muestra el torneo activo/más reciente).

## Modelo de datos

### `users` (se extiende)
- Nueva columna: `is_admin` (`boolean`, default `false`). Controla quién puede crear/administrar
  torneos. Se activa manualmente por seeder/tinker para el usuario del organizador — no hay UI
  para asignarlo en esta iniciativa.

### `tournaments`
- `id`, `name` (`string`, requerido), `status` (`enum: registration,swiss,elimination,completed`,
  default `registration`), `swiss_rounds` (`unsigned tinyint`, ≥1), `cut_size`
  (`unsigned tinyint`, potencia de 2: 2/4/8/16), `current_round` (`unsigned tinyint`, default 0),
  `created_by_user_id` (FK `users`), `champion_entry_id` (FK `tournament_entries`, nullable, se
  llena al completarse), timestamps.
- Regla de aplicación: no se puede crear un torneo si ya existe uno con `status != completed`.

### `tournament_entries`
- `id`, `tournament_id` (FK, `cascadeOnDelete`), `user_id` (FK `users`), `deck_id` (FK `decks` —
  snapshot del deck marcado como torneo al momento de unirse), timestamps.
- `unique(tournament_id, user_id)`.

### `tournament_rounds`
- `id`, `tournament_id` (FK, `cascadeOnDelete`), `number` (`unsigned tinyint`),
  `phase` (`enum: swiss,elimination`), timestamps.
- `unique(tournament_id, number)`.

### `tournament_matches`
- `id`, `tournament_round_id` (FK, `cascadeOnDelete`), `entry_one_id` (FK `tournament_entries`),
  `entry_two_id` (FK `tournament_entries`, nullable — null solo en un bye), `winner_entry_id`
  (FK `tournament_entries`, nullable hasta reportarse), `is_bye` (`boolean`, default `false`),
  timestamps.
- En un bye, `winner_entry_id` se llena automáticamente igual a `entry_one_id` al crear el
  match (no requiere reporte de resultado).

## Algoritmo de emparejamiento (`app/Support/TournamentPairing.php`)

Clase de soporte sin estado (funciones puras sobre colecciones), separada del controlador para
poder probarla de forma aislada:

- **`pairFirstRound(entries)`**: baraja las inscripciones y las empareja secuencialmente
  (1-2, 3-4, ...). Si el conteo es impar, una entrada aleatoria queda con bye.
- **`pairSwissRound(standings, previousMatchups)`**: recibe las standings ya ordenadas (ver
  "Orden final" más abajo) y empareja de forma voraz de arriba hacia abajo: por cada entrada
  sin pareja, busca la siguiente disponible en la lista que no haya jugado ya contra ella
  (`previousMatchups`); si no encuentra ninguna (grupo muy pequeño), permite el repetido como
  último recurso. Si el conteo es impar, el bye va a la entrada de menor posición en standings
  que no haya tenido bye todavía (si todas ya tuvieron uno, va a la última).
- **`seedEliminationBracket(standings, cutSize)`**: toma las primeras `cutSize` entradas de
  standings y arma el orden de bracket estándar (1° vs último, 2° vs penúltimo, etc.).
- **`advanceEliminationRound(previousRoundMatches)`**: toma los `winner_entry_id` de la ronda
  anterior en su mismo orden y los empareja consecutivos (ganador del match 1 vs ganador del
  match 2, y así sucesivamente).

### Cálculo de standings (`app/Support/TournamentStandings.php`)
Para cada `tournament_entry` con al menos un match jugado: `wins` (matches donde es
`winner_entry_id`, bye cuenta como victoria), `matches_played`, y `opponent_win_percentage`
(promedio de `wins/matches_played` de cada rival enfrentado, excluyendo byes propios y ajenos
del cálculo de rivales). Se calcula on-the-fly con una consulta por cada llamada — no se
denormaliza; el volumen (decenas de jugadores) lo hace trivial.

**Orden final:** `wins` desc. → `opponent_win_percentage` desc. → `tournament_entries.id` asc.
(orden de inscripción) como último desempate, para que el corte a eliminatorias sea siempre
determinístico y nunca dependa de azar en caso de empate total.

## Flujo de estados y rutas

Todas bajo el middleware `auth`; las de administración además requieren `is_admin` (nuevo
middleware `App\Http\Middleware\EnsureUserIsAdmin`, 403 si no cumple).

| Ruta | Método | Acción | Quién |
|---|---|---|---|
| `/tournament` | GET | `TournamentController@show` — arma la página según el estado actual | cualquier usuario logueado |
| `/tournament` | POST | `TournamentController@store` — crea el torneo (nombre, `swiss_rounds`, `cut_size`) | admin |
| `/tournament/join` | POST | `TournamentController@join` — inscribe al usuario autenticado con su deck de torneo actual | cualquier usuario logueado |
| `/tournament/rounds` | POST | `TournamentRoundController@store` — genera "la siguiente ronda" según la fase actual (ver abajo) | admin |
| `/tournament/cut` | POST | `TournamentController@cut` — cierra la fase suiza y arma la ronda 1 de eliminatorias | admin |
| `/tournament/matches/{match}` | PATCH | `TournamentMatchController@update` — reporta el ganador de un duelo | admin |

`TournamentRoundController@store` es un único endpoint que decide qué generar según
`tournament.status`:
- `registration` → cierra inscripción, pasa a `swiss`, genera ronda 1 con `pairFirstRound`.
- `swiss` y `current_round < swiss_rounds` → genera la siguiente ronda suiza con
  `pairSwissRound` (422 si aún hay resultados pendientes en la ronda actual).
- `swiss` y `current_round == swiss_rounds` → 422, debe usarse `/tournament/cut` en su lugar.
- `elimination` y quedan más de 2 entradas jugando → genera la siguiente ronda con
  `advanceEliminationRound` (422 si hay resultados pendientes).
- `elimination` y la ronda actual era la final → 422 (no hay siguiente ronda; el cierre ocurre
  al reportar el resultado de la final, ver abajo).

Al reportar el resultado de un match que es la final (2 entradas restantes en la última ronda
de eliminación), `TournamentMatchController@update` marca `tournament.status = completed` y
`champion_entry_id` en la misma transacción.

Después de crear los matches de cualquier ronda (suiza o eliminatoria), se despachan las
notificaciones por correo (ver siguiente sección) dentro de la misma request, antes de
responder.

## Notificaciones por correo

- `App\Notifications\PairedForRound` (canal `mail` únicamente, **sin** `ShouldQueue`: se envía
  de forma síncrona porque el proyecto no tiene un worker de colas corriendo en el hosting
  compartido actual).
- Contenido: "Te toca contra **{nickname del rival}** en la ronda {número}", con link a
  `route('tournament.show')`.
- Se dispara a ambos jugadores de cada match no-bye, inmediatamente después de crear los
  matches de la ronda. Cada envío va en su propio `try/catch`: si falla (SMTP caído, etc.) se
  registra en el log pero **no** revierte la creación de la ronda — el emparejamiento es lo
  importante, el correo es "best effort".
- Requiere que `MAIL_MAILER` esté configurado a un transporte real (SMTP) en producción; con
  `log` (valor actual de desarrollo) el contenido solo queda en el log, sin bloquear el flujo.

## Frontend

- `Pages/Tournament/Show.vue` — página única (`/tournament`) que renderiza según el estado
  recibido por props:
  - Sin torneo activo: admin ve formulario de creación; el resto ve mensaje "no hay torneo
    activo".
  - `registration`: info del torneo + lista de inscritos + botón "Unirme" (deshabilitado con
    hint a `/decks` si el usuario no tiene deck de torneo marcado, u oculto si ya está
    inscrito); admin ve botón "Cerrar inscripción y generar ronda 1".
  - `swiss` / `elimination`: tabla de standings (posición, nickname, W-L) — en fase de
    eliminación se muestra además como bracket; sección destacada "Tu duelo esta ronda: vs
    {nickname}" (o "Bye esta ronda" o "No clasificaste al corte" según aplique) para el
    usuario actual; admin ve la lista completa de matches de la ronda con selector de ganador
    por match y botón para generar la siguiente ronda (o "Cortar a eliminatorias" cuando
    corresponde).
  - `completed`: banner de campeón + standings/bracket final.
- `AppLayout.vue`: se agrega enlace "Torneo" al nav (junto a "Mis decks"/"Jugadores"), visible
  para cualquier usuario logueado.

## Reglas y manejo de errores

- Crear un torneo mientras existe otro no completado → 422.
- `cut_size` debe ser potencia de 2 (2/4/8/16) y no puede exceder el número de inscritos al
  momento del corte → 422 con mensaje si no alcanza.
- Unirse sin deck de torneo marcado, unirse dos veces, o unirse cuando `status != registration`
  → 422.
- Generar ronda/cortar/reportar resultado sin ser admin → 403.
- Generar la siguiente ronda con resultados pendientes en la ronda actual → 422.
- Reportar resultado de un match ya resuelto, o declarar ganador a alguien que no es
  `entry_one_id`/`entry_two_id` del match → 422.
- Fallo al enviar un correo de emparejamiento no revierte ni bloquea la generación de la ronda
  (ver Notificaciones).

## Pruebas

**`TournamentPairing` (unitarias, sin BD):**
- Ronda 1 con número par/impar de entradas: todos emparejados una sola vez, bye correcto si
  aplica.
- Rondas suizas siguientes: no repite rival cuando hay alternativa disponible; si es
  inevitable, empareja igual sin fallar; el bye rota entre quienes no lo han tenido.
- Siembra de bracket de eliminación: orden 1-vs-último correcto para distintos tamaños de
  corte (4, 8).
- Avance de ronda de eliminación: empareja ganadores consecutivos en el orden correcto.

**`TournamentStandings` (feature):**
- Victorias, partidos jugados y % de victorias de rivales calculados correctamente con datos
  de ejemplo, incluyendo byes.

**Feature (flujo completo, `tests/Feature`):**
- No-admin no puede crear torneo, generar ronda, cortar, ni reportar resultado (403 en cada
  endpoint).
- No se puede crear un segundo torneo mientras uno está activo.
- Un usuario sin deck de torneo marcado no puede unirse; con deck marcado, sí, y queda con el
  deck correcto en el `tournament_entry`.
- Simulación de un torneo pequeño de punta a punta (ej. 8 jugadores, 3 rondas suizas, corte a
  4): se generan las rondas, se reportan resultados, se corta a eliminatorias, se juegan las
  semifinales y la final, y al final `tournament.status === 'completed'` con
  `champion_entry_id` correcto.
- `Mail::fake()` — al generar una ronda, se manda `PairedForRound` a ambos jugadores de cada
  match no-bye, y no se manda a quien tiene bye.
- Generar la siguiente ronda con resultados pendientes devuelve 422 y no crea nada.

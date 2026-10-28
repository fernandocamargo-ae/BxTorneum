# Torneo por equipos — Diseño

## Intención (el porqué)

El registro de equipos ya existía en BxTorneum pero se ocultó (rutas comentadas en
`routes/web.php`; modelos y vistas intactos). Fernando lo quiere de vuelta **y** además
quiere un tipo de torneo nuevo donde los que compiten son **equipos**, no personas,
usando exactamente el mismo motor de emparejamiento (grupos suizos + bracket de
eliminación) y viéndose idéntico al torneo individual (la captura que compartió es la
vista actual `Tournament/Show.vue`).

La idea central: un equipo es simplemente otro "participante" del motor de torneos. No
hay que reinventar el emparejamiento; hay que hacer que una inscripción (`entry`) pueda
apuntar a un equipo en vez de a un usuario.

## Objetivos

1. **Restaurar el registro de equipos** tal como estaba: apartado "Equipos" en el menú,
   crear/editar equipo con sus integrantes (Capitán, Subcapitán, Oficial) y el deck de 3
   beys por integrante (9 beys = equipo completo). Nada de esa lógica cambia; solo se
   reactivan rutas y navegación, y se confirma con su suite de tests existente.
2. **Nuevo apartado "Torneo por equipos"**, separado del torneo individual, donde los
   participantes son equipos.
3. El torneo por equipos usa **el mismo motor** (suizo, grupos, corte, bracket,
   standings, reporte de resultado) y **se ve igual** que el individual.
4. El torneo por equipos se divide, igual que el individual, en **Oficial** (solo Admin
   crea/gestiona) y **Clandestino** (Amateur + Admin crean/gestionan).
5. El resultado de un match entre equipos se captura **igual que hoy**: se elige el
   equipo ganador (un marcador por match, sin duelos por integrante).

## No-objetivos

- No se tocan los duelos por integrante: un match de equipo lo decide el administrador
  marcando al equipo ganador, igual que un match individual hoy.
- No se cambia la **lógica** de emparejamiento (suizo/standings/corte/bracket); se
  reutiliza. Los únicos ajustes al motor son los puntos donde el código hoy asume que una
  inscripción tiene usuario (ver "Puntos donde el código asume usuario").
- No se modifica cómo se registran/gestionan los equipos (integrantes, roles, combos);
  solo se reactiva lo que ya existía.

## Anti-objetivos (qué sería un fracaso aunque "funcione")

- Que los torneos por equipos aparezcan en el cuadro de **Campeones del mes** o en el
  historial individual, ensuciando los registros individuales. El cuadro de campeones y
  el historial individual siguen siendo **solo** de torneos individuales oficiales.
- Duplicar el motor de torneos en un segundo juego de clases "para equipos". Si termina
  habiendo dos motores, el diseño falló.
- Que reactivar equipos rompa cualquier test existente de la feature de equipos.

## Restricciones

- Laravel 12 (PHP 8.3) + Inertia + Vue 3 `<script setup>`, MySQL en prod, assets Vite
  committeados en `public/build`.
- TDD en todo (RED→GREEN), suite PHPUnit + Vitest verde antes de dar por hecho.
- OPcache en prod requiere `systemctl restart php8.3-fpm` tras el deploy.
- Roles ya existentes: jugador / amateur / admin (owner-asignados). La autorización del
  torneo por equipos reutiliza la `TournamentPolicy` existente.

## Decisiones de diseño (el cómo ya decidido)

### Modelo de datos

- **`tournaments.format`**: nueva columna `enum('individual','teams')` con default
  `'individual'` y backfill a `'individual'` para lo existente. Combinada con la columna
  `type` (official/clandestine) ya presente, da las cuatro variantes:
  individual-oficial, individual-clandestino, equipos-oficial, equipos-clandestino.
- **`tournament_entries`**: hoy exige `user_id` y `deck_id` NOT NULL con
  `unique(tournament_id, user_id)`. Se vuelven **nullable** `user_id` y `deck_id`, se
  agrega `team_id` nullable (FK a `teams`), y se agrega `unique(tournament_id, team_id)`.
  Una entry individual tiene `user_id`+`deck_id`; una entry de equipo tiene `team_id`.
  (El `unique(tournament_id, user_id)` existente se mantiene; con `user_id` nullable los
  equipos no colisionan en él.)
- **La lógica de emparejamiento se reutiliza**: `TournamentPairing` y
  `TournamentStandings` operan sobre `entry_id`/`winner_entry_id` y son agnósticos a si la
  entry es persona o equipo. Los matches (`entry_one_id`, `entry_two_id`,
  `winner_entry_id`, `is_bye`) se reutilizan sin tocar.

### Puntos donde el código asume usuario (ajustes necesarios)

El código actual deref­erencia `entry->user` en tres lugares; con entries de equipo
`user_id` es NULL y truena. Se ajustan así (sin cambiar la lógica de emparejamiento):

- **`TournamentRoundFactory` (notificación al generar ronda):** hoy hace
  `$entry->user->notify(new PairedForRound(...))` y lee `$entry->user->nickname` para cada
  inscripción. Como un equipo no tiene cuenta a quién notificar, al generar rondas se
  **salta la notificación** de las inscripciones sin usuario (equipos). La generación del
  bracket/pairings en sí no cambia.
- **PDF de jugadores (`TournamentReportController`):** hoy lee `entry->user->name`,
  `->nickname` y `entry->deck`. En torneos por equipos ese reporte no aplica, así que el
  botón de exportar PDF **se oculta** en el formato equipos (un PDF por equipos sería una
  feature aparte, fuera de alcance). El controlador también rechaza la descarga si el
  torneo es por equipos.
- **Campeón en la vista:** hoy se arma con `champion?->user?->nickname` (NULL para un
  equipo). Para equipos se resuelve con el **nombre del equipo** campeón.

### Ciclo de vida del equipo inscrito

Un equipo inscrito queda referenciado por su entry, y los matches/standings/campeón
referencian esa entry. Para no corromper un bracket en curso o el historial:

- La FK `tournament_entries.team_id` usa **`restrict`** on-delete.
- Mientras un equipo tenga una inscripción en un torneo **no terminado**, se **bloquea**
  borrarlo y editarlo por debajo de 9 beys (su `TeamController@destroy`/`update` y el
  combo-lock validan esto). Completado el torneo, el equipo vuelve a ser editable/borrable.

### Inscripción de equipos (la única diferencia real de flujo)

Los equipos no son cuentas de usuario (sus integrantes son nombres de texto), así que un
equipo **no se une solo**. En la fase de registro de un torneo por equipos, quien lo
administra (Admin para oficial; Amateur/Admin para clandestino) **agrega equipos** desde
la lista de equipos registrados. Solo pueden agregarse equipos **completos** (9 beys),
igual que un jugador individual necesita un deck de torneo con al menos un combo.
Quitar un equipo en registro = borrar su entry (igual que `removeEntry` hoy).

### Vista y reporte

- Se reutiliza `Tournament/Show.vue` (la vista de la captura). Para entries de equipo, el
  "nickname" mostrado es el **nombre del equipo** y el avatar cae al círculo con la
  inicial (los equipos no tienen foto). Grupos suizos, bracket, standings y reporte de
  resultado se ven y funcionan igual.
- Reportar resultado = marcar equipo ganador, reusando `TournamentMatchController@update`
  (que ya setea `winner_entry_id`). El `endGuestSessions` ya está condicionado y no aplica
  a equipos.

### Navegación y permisos

- Menú: se restaura **"Equipos"** (registro) y se agrega **"Torneo por equipos"** como
  apartado aparte del "Torneo" individual. Dentro de "Torneo por equipos", pestañas
  **Oficial | Clandestino** (mismo patrón que el individual).
- Autorización: se reutiliza `TournamentPolicy`. `createOfficial`/`createClandestine` y
  `manage` ya dependen de `type`; el `format` no cambia quién puede qué (oficial = Admin,
  clandestino = Amateur+Admin), solo sobre qué formato.
- Igual que hoy, puede haber a la vez un torneo individual oficial activo y un torneo por
  equipos oficial activo (son formatos distintos; el candado de "ya hay un oficial activo"
  se evalúa por formato).

### Campeones e historial

- Los torneos por equipos **no** alimentan el cuadro de "Campeones del mes" (ese sigue
  siendo individual-oficial). Los torneos por equipos oficiales completados se consultan
  en su propio historial (mismo patrón que el historial individual, filtrado por
  `format='teams'`).

## Lo que queda a criterio del implementador

- La forma exacta de las rutas del torneo por equipos (controlador nuevo `format`-aware
  vs. métodos nuevos en `TournamentController`): elegir lo que menos duplique, dado que
  `show`/`store`/`clandestineIndex`/`history` ya ramifican por `type`.
- Detalles de UI del selector "agregar equipo" en registro (dropdown vs. buscador),
  siguiendo el estilo existente.
- Nombres internos de métodos/props siguiendo las convenciones ya presentes en el repo.

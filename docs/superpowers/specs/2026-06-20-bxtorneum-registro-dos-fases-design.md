# BxTorneum — Registro en dos fases + carga masiva (seeder)

**Fecha:** 2026-06-20
**Estado:** Diseño aprobado, pendiente de plan de implementación
**Relacionado:** extiende `2026-06-20-bxtorneum-registro-equipos-design.md`

## Resumen

Separar el registro de equipos en dos fases para soportar carga masiva de nombres y
llenado progresivo de combos:

- **Fase 1 — Nombres:** registrar un equipo con su nombre y los nombres de sus 3 miembros
  (capitán, subcaptián, oficial), sin combos. Un seeder precarga los equipos iniciales del
  torneo.
- **Fase 2 — Combos:** desde el detalle del equipo, registrar/editar los combos. Se permite
  **guardado parcial**: no hace falta tener los 9 combos para guardar.

Un equipo se considera **completo** cuando tiene 9 beyblades válidos (3 miembros × 3). El
estado se muestra como badge en el listado y el detalle.

Esto reemplaza la regla dura anterior (un equipo solo era guardable con los 9 combos
completos). Los combos pasan a ser opcionales en el alta y se completan después.

## Cambios sobre el diseño original

- El formulario de alta (`Teams/Create`) deja de incluir combos: solo nombre del equipo y
  nombres de los 3 miembros.
- `TeamRequest` deja de validar combos; valida solo nombres.
- Se añade un flujo aparte para combos (`TeamComboController` + `TeamComboRequest` +
  página `Teams/Combos`).
- No hay cambios de esquema en la base de datos.

## Modelo de datos

Sin cambios de esquema. Se apoya en el modelo existente:

- `teams (id, name)`
- `members (id, team_id, role, name)` — 3 por equipo, roles únicos.
- `beyblades (id, member_id, line, position)` — ahora de **0 a 3** por miembro.
- `parts`, `beyblade_part` — sin cambios.

**Completitud (derivada, sin columna nueva):**
- `Team::beybladesCount(): int` — total de beyblades del equipo (vía
  `beyblades` de sus miembros).
- `Team::isComplete(): bool` — `beybladesCount() === 9`.

## Reglas de validación

### Fase 1 — `TeamRequest` (nombres)

```
name              : required string max:255
members           : required array size:3
members.*.role    : required in(captain,subcaptain,official); distinct
members.*.name    : required string max:255
```

### Fase 2 — `TeamComboRequest` (combos, parcial)

Payload: `members` con (hasta) 3 `beyblades` por miembro, cada uno `{ line, parts }`. Reglas
en `withValidator()`:

- Un combo está **vacío** si todas sus piezas están en blanco → se ignora (no se guarda) y
  no genera error.
- Un combo está **lleno** si tiene al menos una pieza con valor → debe incluir **todas** las
  piezas obligatorias de su línea (`BeybladeLines::requiredSlotsFor`); si falta alguna,
  error en `members.{m}.beyblades.{b}.parts.{slot}`.
- `line` de cada combo presente debe ser válida (`BeybladeLines::lines()`).
- No se exige un número mínimo de combos; se guarda lo que haya.

## Persistencia (fase 2)

`TeamComboController::update` (transaccional):
1. Borra los beyblades existentes del equipo (`Member::beyblades()` en cascada borra pivotes).
2. Reinserta solo los combos **llenos**, asignando `position` 1..n por miembro en el orden
   recibido.
3. Cada pieza se resuelve con `Part::firstOrCreate(['type'=>slot,'name'=>name])` (catálogo
   híbrido), igual que el flujo original.

## Backend — controladores y rutas

- `TeamController` (resource existente): `store`/`update` ahora usan `TeamRequest` (solo
  nombres); `index`/`show`/`create`/`edit`/`destroy` se mantienen, ajustando los datos que
  pasan a las vistas (sin combos en create/edit).
- **Nuevo** `TeamComboController`:
  - `GET  /teams/{team}/combos` → `combos.edit` → `Inertia::render('Teams/Combos', [...])`
  - `PUT  /teams/{team}/combos` → `combos.update`
- `index` y `show` incluyen `is_complete` y `beyblades_count` en los datos del equipo.

## Frontend — páginas

- **`Teams/Index`** — listado con badge **"Completo"** (verde/cyan) o **"Incompleto n/9"**
  (naranja/magenta) por equipo, además del filtro por nombre existente.
- **`Teams/Create`** — formulario de **nombres**: nombre del equipo + 3 campos de nombre de
  miembro (capitán, subcaptián, oficial). Reemplaza el formulario actual de 9 combos.
- **`Teams/Edit`** — editar **nombres** (equipo + 3 miembros).
- **`Teams/Combos`** *(nueva)* — registrar/editar combos: reusa `MemberDeck` y
  `BeybladeForm`; precarga los combos existentes; permite dejar combos vacíos (guardado
  parcial). Botón "Guardar combos".
- **`Teams/Show`** — detalle: nombres + badge de completitud; los combos vacíos muestran
  "Pendiente"; botones **"Editar nombres"** (→ `Teams/Edit`) y **"Registrar/editar combos"**
  (→ `Teams/Combos`).

Los componentes `MemberDeck`, `BeybladeForm` y `PartAutocomplete` se reutilizan tal cual. En
`Teams/Combos` cada miembro arranca con 3 slots de combo precargados desde sus beyblades
existentes (o vacíos si no tiene).

## Seeder

`database/seeders/TeamSeeder.php` precarga los equipos iniciales (nombres, sin combos). Es
idempotente por nombre de equipo (`firstOrCreate` sobre `teams.name`; crea los 3 miembros
solo si el equipo es nuevo). Registrado en `DatabaseSeeder`. Se ejecuta con
`php artisan db:seed --class=TeamSeeder`.

Equipos iniciales:

| Equipo | Capitán | Subcapitán | Oficial |
|--------|---------|-----------|---------|
| Xplosivos | Ricardo | Cielo azul | Kristen |
| Kilos Mortales | Fernando Velasquez | Jonathan Paoli | Luis Rodrigo Guzman |
| Shadow break | Maui Perez | Xavi Monzón | José Ramos |
| Gan Gan Galaxy | James Bojorquez | Heythan | Don Mario |
| Dragon Knights | Yury | Guillermo | Derek |
| Bladers Fury | Steve Solorzano | Luis Rios | Roberto Lopez |
| Equipo Zooganico | Rafael Quevedo | Deniss Camey | Haydee Ramirez |
| Triple extinción | Dany Florian | Melman Camargo | Jonathan López Marroquín |
| The phantom thieves | Carlos Gonzalez | Linda choc | Haciel Garcia |
| Triada de Asgard | Pipo Díaz | Jossie Marroquín | Jorge Sagastume |
| Team Persona | Robin García | Fernando Camargo | Pana Poyo |
| Blazing Bahamuts | Dylan | Pablo Morales | Luis Mora |
| Sannins | Ricardo Sandoval | Andrés Lutin | Renato Arellano |

## Manejo de errores

- Validación en backend (Form Requests); errores inline bajo cada campo en Vue (igual que el
  flujo actual).
- Borrado de equipo con confirmación (sin cambios).
- Guardar combos parciales nunca falla por "faltan combos"; solo por combos a medias.

## Pruebas

- **Backend (PHPUnit):**
  - `TeamRequest` acepta alta solo-nombres y rechaza si falta un miembro/nombre.
  - `TeamComboController` guarda combos parciales (ej. solo capitán) y deja el equipo
    incompleto; rechaza un combo a medias (pieza obligatoria faltante).
  - `Team::isComplete()` true solo con 9 beyblades.
  - `TeamSeeder` crea 13 equipos × 3 miembros, 0 beyblades, y es idempotente.
- **Frontend (Vitest):**
  - `Teams/Create` renderiza 3 campos de nombre y postea nombres.
  - `Teams/Combos` precarga combos existentes y permite slots vacíos.
  - `Teams/Index` muestra el badge de completitud.

## Fuera de alcance

- Importación por archivo/CSV o pegado desde UI (la carga masiva se hace vía seeder).
- Enfrentamientos, brackets y resultados (siguen fuera de alcance).
- Autenticación.

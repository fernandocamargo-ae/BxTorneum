# BxTorneum — Registro de equipos y combos de Beyblade X

**Fecha:** 2026-06-20
**Estado:** Diseño aprobado, pendiente de plan de implementación

## Resumen

Aplicación web **pública** (sin login) para llevar el registro de equipos de un torneo de
Beyblade X y consultar sus combos. Cada equipo tiene 3 miembros con roles fijos
(capitán, subcapitán, oficial) y cada miembro registra un deck de 3 Beyblades. La app
permite crear, listar, ver en detalle, editar y borrar equipos. No incluye
enfrentamientos, brackets ni resultados (queda para un requerimiento posterior).

## Stack

- **Backend:** Laravel 12, MySQL (el `.env` actual apunta a SQLite; se cambiará a MySQL).
  El backend lo desarrolla el usuario (DDL pragmático). Este documento describe el modelo
  de datos como referencia compartida.
- **Frontend:** Inertia.js + Vue 3 + Tailwind CSS 4 (lo desarrolla el asistente).
- **Sin autenticación.** App pública: cualquiera puede registrar y consultar.

## Reglas de negocio (validación)

Reglas duras que el backend valida con Form Requests y el frontend refleja:

1. Un equipo tiene **exactamente 3 miembros**, uno por cada rol: `captain`, `subcaptain`,
   `official`. Los tres son obligatorios.
2. Cada miembro tiene **exactamente 3 Beyblades** (su deck). Los tres son obligatorios.
3. Cada Beyblade pertenece a una **línea** que determina qué piezas (slots) requiere.
   Todos los slots de la línea elegida son obligatorios, salvo el `ratchet` opcional en
   las líneas Infinity.
4. Un equipo solo es válido (guardable) cuando está completo: 3 miembros × 3 Beyblades = 9
   Beyblades, todos con sus piezas según su línea.

## Mapa línea → slots (fuente de verdad compartida)

Esta lógica se replica en JS (frontend, para mostrar campos dinámicos) y en PHP (backend,
para validar). Es el corazón del dominio.

| Línea          | Slots (piezas requeridas)                                          |
|----------------|-------------------------------------------------------------------|
| `bx`           | blade, ratchet, bit                                               |
| `ux`           | blade, ratchet, bit                                               |
| `bx_infinity`  | blade, **ratchet (opcional)**, bit                               |
| `ux_infinity`  | blade, **ratchet (opcional)**, bit                               |
| `cx`           | lock_chip, main_blade, assist_blade, ratchet, bit                |
| `cx_infinity`  | lock_chip, over_blade, metal_blade, assist_blade, ratchet, bit   |

En las líneas Infinity el usuario indica si el Beyblade lleva ratchet; si no, el slot
`ratchet` se omite.

## Modelo de datos

```
teams
  id            PK
  name          string
  timestamps

members
  id            PK
  team_id       FK -> teams (cascade on delete)
  role          enum(captain, subcaptain, official)
  name          string
  timestamps
  unique(team_id, role)   -- un rol por equipo

beyblades                  -- el "combo"; 3 por miembro = 1 deck
  id            PK
  member_id     FK -> members (cascade on delete)
  line          enum(bx, ux, bx_infinity, ux_infinity, cx, cx_infinity)
  position      tinyint (1..3, orden dentro del deck)
  timestamps
  unique(member_id, position)

parts                      -- catálogo híbrido (alimenta el autocompletado)
  id            PK
  type          enum(blade, ratchet, bit, lock_chip, main_blade,
                     assist_blade, over_blade, metal_blade)
  name          string
  timestamps
  unique(type, name)       -- evita duplicados; bolsa compartida por tipo

beyblade_part              -- qué piezas componen cada beyblade
  id            PK
  beyblade_id   FK -> beyblades (cascade on delete)
  part_id       FK -> parts
  slot          enum (mismos valores que parts.type)
  unique(beyblade_id, slot)
```

### Notas del modelo

- El catálogo de piezas es **una sola tabla `parts`** con columna `type`. Cada tipo es una
  "bolsa" compartida entre todas las líneas que lo usan (los ratchets y bits son
  universales; lock_chip y assist_blade se comparten entre CX y CX Infinity; etc.). La
  intercambiabilidad de piezas sale gratis de tener una bolsa por tipo.
- `beyblade_part` con columna `slot` permite que cada línea tenga campos distintos sin
  necesidad de tablas separadas por línea.
- **Autocompletado híbrido:** al guardar, cada pieza se resuelve con "buscar-o-crear"
  (`firstOrCreate`) en `parts` por `(type, name)`. Si el nombre ya existe se reutiliza; si
  no, se crea y queda disponible para futuras sugerencias.

## Frontend (Inertia + Vue)

### Páginas

- **`Teams/Index.vue`** — listado de equipos (cards o tabla), buscador por nombre, botón
  "Registrar equipo".
- **`Teams/Show.vue`** — detalle del equipo: los 3 miembros, cada uno con su deck de 3
  combos y el desglose de piezas. Botones editar/borrar (borrar con confirmación).
- **`Teams/Create.vue`** y **`Teams/Edit.vue`** — formulario completo: nombre del equipo +
  3 bloques de miembro (capitán, subcapitán, oficial), cada bloque con sus 3 combos.

### Componentes reutilizables

- **`MemberDeck.vue`** — un miembro (rol fijo + nombre) y sus 3 combos.
- **`BeybladeForm.vue`** — un combo individual: selector de **línea** + campos dinámicos
  generados según el mapa línea → slots. Para líneas Infinity incluye el toggle
  "¿lleva ratchet?".
- **`PartAutocomplete.vue`** — input con autocompletado híbrido: consulta sugerencias por
  `type`, muestra coincidencias existentes y permite escribir una pieza nueva. Es el núcleo
  de la UX de captura.

### Comunicación de datos

- Páginas vía Inertia: el backend devuelve `Inertia::render('Teams/...', [...])` y Vue
  recibe los datos como props.
- Acciones (crear/editar/borrar) vía formularios Inertia (`router.post/put/delete`); el
  backend valida con Form Requests y devuelve errores que Vue muestra inline bajo cada
  campo.
- **Endpoint de autocompletado:** `GET /parts/search?type={type}&q={query}` → JSON con
  sugerencias de piezas del tipo indicado. Es el único endpoint "tipo API" además de las
  páginas Inertia.

## Identidad visual (estética Beyblade X)

Inspirada en el arte oficial de la serie. Tema **oscuro por defecto**.

- **Base:** fondos casi negros / gris muy oscuro (`zinc-950`/`neutral-900`), superficies con
  sutil gradiente.
- **Acentos neón / holográficos:** azul eléctrico–cyan, magenta–rosa y naranja, usados en
  gradientes, bordes brillantes (glow) y estados activos. Evitar saturar: acentos sobre
  base oscura, no toda la pantalla.
- **Motivo gráfico:** la "X" como elemento decorativo (marcas de agua, separadores,
  esquinas de cards).
- **Tipografía:** titulares bold/condensados de alto impacto; cuerpo legible y neutro.
- **Sensación:** glossy, deportiva, competitiva, con tarjetas tipo "stat card" para los
  combos.
- Tailwind 4 con un set de tokens de color de marca (cyan/magenta/orange) y utilidades de
  glow/gradiente reutilizables.

## Manejo de errores

- Validación en el backend (Form Requests); errores mostrados inline bajo cada campo en
  Vue.
- Borrado de equipo con diálogo de confirmación.
- App server-rendered simple: no se requieren estados complejos de error de red.

## Fuera de alcance (futuros requerimientos)

- Enfrentamientos, brackets y resultados de torneo.
- Autenticación, usuarios y roles de acceso.
- Líneas de Beyblade fuera de Beyblade X (no aplica).
```

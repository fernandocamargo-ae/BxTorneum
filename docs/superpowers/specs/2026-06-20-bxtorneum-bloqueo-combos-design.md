# BxTorneum — Bloqueo de combos registrados (append-only)

**Fecha:** 2026-06-20
**Estado:** Diseño aprobado, pendiente de plan de implementación
**Relacionado:** extiende `2026-06-20-bxtorneum-registro-dos-fases-design.md`

## Resumen

Una vez registrado, un combo de un miembro **no se puede volver a editar ni borrar**. El
bloqueo es **por combo individual** (no por miembro): si Robin registra su combo 1, ese combo
queda bloqueado para siempre, pero Robin todavía puede registrar sus combos 2 y 3. Cuando un
miembro completa sus 3 combos queda totalmente bloqueado; cuando los 9 del equipo están
registrados, el equipo entero queda bloqueado.

El bloqueo es **total, sin excepción**: la única forma de empezar de cero es **eliminar el
equipo completo** (botón Eliminar existente) y volver a registrarlo.

## Modelo de bloqueo (sin cambios de esquema)

El bloqueo se **deriva de la existencia de la fila `beyblade`**. Un slot de combo que ya tiene
una fila `beyblade` guardada está bloqueado; un slot sin fila está vacío y es registrable. No
se añade columna de estado ni flag — "registrado" == "existe".

- `members` y `beyblades` no cambian de esquema.
- Cada miembro tiene de 0 a 3 beyblades (posiciones 1..3, únicas por miembro).
- Combos bloqueados de un miembro = sus beyblades existentes.
- Slots vacíos de un miembro = `3 - (cantidad de beyblades existentes)`.

## Cambio de comportamiento en el backend

El flujo actual de `TeamComboController::update` **borra todos los beyblades del miembro y los
reinserta**. Esto rompería el bloqueo y por tanto se reemplaza por un comportamiento
**append-only**:

1. Por cada miembro del payload, sus beyblades existentes **no se tocan** (ni update ni
   delete).
2. Se insertan únicamente los combos **nuevos** (no vacíos) en las posiciones libres
   siguientes: si el miembro ya tiene `k` beyblades, los nuevos se crean en
   `k+1, k+2, …` hasta un máximo de 3.
3. Defensa en profundidad: si `existentes + nuevos_no_vacíos > 3` para un miembro, la
   petición se rechaza con error de validación; nunca se modifica ni borra una fila
   existente.
4. Cada pieza de un combo nuevo se resuelve con `Part::firstOrCreate(['type'=>slot,
   'name'=>name])` (catálogo híbrido), igual que antes.
5. Toda la operación sigue siendo transaccional.

El borrado del equipo (`TeamController::destroy`) sigue igual y limpia todo en cascada.

## Validación — `TeamComboRequest`

Se mantiene la validación parcial existente (combo vacío se ignora; combo no vacío debe tener
todas las piezas obligatorias de su línea, `ratchet` opcional solo en Infinity) y se añade la
cota por miembro:

- Para cada miembro, sea `existentes` = beyblades ya guardados (leídos del equipo de la ruta)
  y `nuevos` = combos no vacíos del payload. Si `existentes + nuevos > 3`, error en
  `members.{m}.beyblades` ("Este miembro ya tiene todos sus combos registrados o se exceden
  los 3.").

La request tiene acceso al `Team` de la ruta (`$this->route('team')`) para contar los
beyblades existentes por miembro.

## Frontend — página de combos (`Teams/Combos.vue`)

La página deja de tratar los 3 slots de cada miembro como editables. Por cada miembro:

- **Combos bloqueados** (los beyblades existentes): se muestran como **tarjetas de
  solo-lectura** con un indicador 🔒 "Registrado" (mismo estilo que las tarjetas del detalle:
  línea + piezas por slot).
- **Slots vacíos** (`3 - existentes`): se renderizan como formularios `BeybladeForm`
  editables para registrar los combos nuevos.

Casos:
- Miembro con 1 combo registrado → 1 tarjeta bloqueada + 2 formularios.
- Miembro con 3 → 3 tarjetas bloqueadas, sin formularios.
- Equipo completo (9) → todo bloqueado; sin botón "Guardar" (la página funciona como vista
  de solo-lectura).

El formulario envía, por miembro, **solo** los combos nuevos de los slots vacíos
(`{ id, beyblades: [nuevos] }`); los combos bloqueados no se incluyen en el payload.

Implementación de componentes: la página separa, por miembro, los beyblades existentes
(read-only) de los slots editables. Se ajusta `MemberDeck` (hoy usado solo por esta página)
para recibir los combos bloqueados a mostrar y renderizar formularios únicamente para los
slots libres, o se construye la separación en la propia página; el plan fijará el detalle.
Las tarjetas de solo-lectura reutilizan el patrón visual del detalle (`Teams/Show.vue`).

## Copy

El botón del detalle "Registrar/editar combos" se renombra a **"Registrar combos"** (ya no se
edita lo registrado). El título de la página de combos pasa a "Registrar combos".

## Manejo de errores

- Validación en backend (`TeamComboRequest`); errores inline bajo cada formulario de combo
  nuevo.
- Intentos (vía payload manipulado) de modificar o exceder combos bloqueados se rechazan en
  el backend sin alterar datos existentes.
- Borrado de equipo con confirmación (sin cambios).

## Pruebas

**Backend (PHPUnit):**
- Registrar el combo 1 de un miembro lo crea y queda bloqueado.
- Un segundo PUT que envíe datos para un slot ya ocupado **no modifica** el combo existente.
- Registrar los combos 2 y 3 en slots vacíos del mismo miembro funciona (append).
- Enviar más combos de los que caben (`existentes + nuevos > 3`) se rechaza con error y no
  altera datos.
- Borrar el equipo limpia todos los beyblades (sin cambios respecto a hoy).

**Frontend (Vitest):**
- Un miembro con combos existentes muestra esas tarjetas en modo solo-lectura.
- Solo se renderizan formularios `BeybladeForm` para los slots vacíos (ej.: 1 existente → 2
  formularios).
- Un miembro completo no muestra formularios; un equipo completo no muestra botón Guardar.

## Fuera de alcance

- Edición o borrado de combos individuales (bloqueo total, sin excepción).
- Autenticación / identidad de quién registra (app pública; el bloqueo solo impide reeditar).
- Enfrentamientos, brackets, resultados.

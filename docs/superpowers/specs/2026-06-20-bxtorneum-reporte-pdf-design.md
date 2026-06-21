# BxTorneum — Reporte global en PDF (protegido por contraseña)

**Fecha:** 2026-06-20
**Estado:** Diseño aprobado, pendiente de plan de implementación
**Relacionado:** extiende el registro de equipos/combos existente.

## Resumen

Permitir exportar un **PDF global del torneo** con todos los equipos, sus 3 miembros
(capitán, subcapitán, oficial) y sus combos. La generación es server-side (descarga directa
de un clic) y está **protegida por una contraseña única** guardada en `.env`: solo quien la
conozca puede descargar el reporte. El PDF tiene un diseño **claro y print-friendly** con
acentos de la marca Beyblade X.

## Mecanismo de protección

- Una sola contraseña de administrador en `.env` como `REPORT_PASSWORD`, expuesta vía
  `config/report.php` (`config('report.password')`) para ser compatible con `config:cache`
  en producción.
- La descarga es `POST /report/pdf` (POST, no GET, para que la contraseña no quede en la URL
  ni en logs de acceso).
- El backend compara con `hash_equals(config('report.password'), $entrada)`:
  - Si `config('report.password')` está vacío/null → siempre **403** (no se puede descargar
    si no hay contraseña configurada).
  - Si no coincide → **403**, sin generar el PDF.
  - Si coincide → genera y devuelve el PDF.

## Backend

### Dependencia
- `barryvdh/laravel-dompdf` (composer).

### Configuración
- `config/report.php`:
  ```php
  return ['password' => env('REPORT_PASSWORD')];
  ```
- `.env.example`: añadir `REPORT_PASSWORD=` con comentario (rellenar en el servidor, no
  commitear el valor real).

### Ruta
- `POST /report/pdf` → `ReportController@download`, nombre `report.pdf`. Vive en el grupo web
  (CSRF activo).

### Controlador — `ReportController@download`
1. Validar que `password` viene en la petición (`required|string`).
2. `$expected = config('report.password');` Si `$expected` es vacío/null o
   `! hash_equals((string) $expected, (string) $request->input('password'))` →
   `abort(403, 'Contraseña incorrecta.')`.
3. Cargar `Team::with('members.beyblades.parts')->orderBy('name')->get()`.
4. Construir la estructura de presentación por equipo: nombre, completitud
   (`beybladesCount()`/`isComplete()`), y por miembro (ordenados captain → subcaptain →
   official) sus combos (`line` + `partsBySlot()`), reutilizando `BeybladeLines`
   (`SLOT_LABELS`/labels de línea — en PHP se usan los mismos mapas que ya existen en
   `App\Support\BeybladeLines`; las etiquetas legibles de slot/línea se definen como arrays
   en el controlador o en `BeybladeLines`).
5. `Pdf::loadView('reports.teams', [...])->download('reporte-bxtorneum.pdf')`.

### Vista — `resources/views/reports/teams.blade.php`
- Diseño **claro** (fondo blanco), CSS compatible con dompdf (layout con tablas, sin
  flexbox/grid; estilos inline o `<style>` simple).
- Encabezado: título "BEYBLADE X — Torneum", subtítulo "Reporte de equipos", y la fecha de
  generación (`now()->format('d/m/Y H:i')`).
- Por equipo: bloque con el nombre del equipo y un badge de completitud (texto
  "Completo" / "Incompleto n/9"); una tabla por miembro con su rol (Capitán/Subcapitán/
  Oficial), nombre, y sus combos (línea + lista de piezas slot→valor). Los miembros sin
  combos muestran "Pendiente".
- Acento de marca: una franja/encabezado con gradiente cyan→magenta (dompdf soporta
  `background` con gradiente limitado; si falla, color sólido cyan como fallback).

## Frontend

### Componente — `resources/js/Components/ReportExport.vue`
- Botón **"Exportar PDF"** ubicado en el encabezado de `Teams/Index.vue` (el "apartado" de
  reporte).
- Al pulsarlo abre un modal con un campo de **contraseña** y botones "Descargar" / "Cancelar".
- Al enviar:
  - `axios.post('/report/pdf', { password }, { responseType: 'blob' })`.
  - Si responde 200 → crear un `blob` URL y disparar la descarga del archivo
    `reporte-bxtorneum.pdf` (crear `<a>` temporal con `download`).
  - Si responde error (403) → mostrar "Contraseña incorrecta." inline; el modal permanece
    abierto.
  - Estado `processing` deshabilita el botón mientras genera.
- CSRF: axios ya envía el `X-XSRF-TOKEN` (configurado en `resources/js/bootstrap.js`), por lo
  que el POST en el grupo web pasa la verificación CSRF.

### Ubicación
- `Teams/Index.vue` monta `<ReportExport />` junto al título "Equipos" (a la par del buscador).

## Manejo de errores

- Contraseña incorrecta/ausente → 403; el frontend muestra el mensaje inline sin recargar.
- Sin `REPORT_PASSWORD` configurada → 403 siempre (no se filtra el reporte por descuido de
  configuración).
- Generación del PDF: si dompdf falla, error 500 estándar (no se intenta UI especial; caso
  raro).

## Pruebas

**Backend (PHPUnit):**
- `POST /report/pdf` con la contraseña correcta (config establecida en el test) → 200 y
  `Content-Type: application/pdf`.
- Con contraseña incorrecta → 403, cuerpo no es PDF.
- Sin `password` en la petición → 422 (falla la validación `required`).
- Con `config('report.password')` vacío y cualquier contraseña → 403.

**Frontend (Vitest):**
- El botón abre el modal con el campo de contraseña.
- Enviar con contraseña llama a `axios.post('/report/pdf', { password }, { responseType: 'blob' })`.
- Si axios rechaza (403), se muestra el texto "Contraseña incorrecta." y el modal sigue
  abierto.

## Fuera de alcance

- PDF por equipo individual (solo el reporte global).
- Sistema de login/usuarios (solo la contraseña única del export).
- Personalización de columnas/filtros del reporte (exporta todo el registro).
- Estética oscura del PDF (se eligió clara/print-friendly).

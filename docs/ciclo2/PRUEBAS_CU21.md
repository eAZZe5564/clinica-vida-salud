# CU21 — Pruebas e inventario

Campaña local del Prompt 14A. Rama `feat/ciclo2-cu21-reportes-citas`, desde
CU20 `866839ef96122a32426c0298fe8dc89fdc954d0c`. No hay commit ni push de CU21.

## Alcance y pruebas nuevas ejecutadas

Ruta `GET /reportes/citas`, solo administrador y recepcionista. Médico/paciente
403, sin sesión login, métodos de escritura 405 para autorizados. Reportes del
estado actual por inicio programado local: indicadores, estados, médicos,
especialidades y días. Todos usan el mismo conjunto de filtros/snapshot SQL.

| Suite ejecutada ahora | Aprobadas |
|---|---:|
| `php tests/regresion_cu21.php reportes_integration` | 138 |
| `php tests/regresion_cu21.php http_reportes` | 90 |
| `php tests/regresion_cu21.php reportes_browser` | 16 |
| **Total de pruebas nuevas** | **244** |

Integración: PDO real vigilado, tres SELECT preparados, consulta agregada sin
pacientes/historial/tabla puente y ejecución dentro de READ ONLY. Se verifican
los seis estados, suma exacta, subtotales de todas las columnas de cada tabla,
filtros simples/combinados, reprogramación con 23 eventos sin duplicación, médico
multiespecialidad, médico sin citas/inactivo, NULL históricos y períodos vacíos.
Se comparan cifras con COUNT directo y CU14/CU19/CU20 bajo filtros equivalentes.
Se prueban año/mes, límite 23:30 local frente a 00:00 siguiente, bisiesto,
años 0001/9999, tipos/IDs/fechas/estados manipulados y mes actual local.

HTTP: login real de actores ficticios, permisos/métodos antes de consulta,
tarjetas/tablas, filtros, límites, NULL, menús por rol, XSS en nombres de médico
y especialidad, mensajes 422 sin cifras parciales y ausencia de datos de pacientes,
motivos, documentos y credenciales. Las nueve tablas mantienen sus huellas
después de los logins, incluidas las consultas y los métodos rechazados.

Edge real: escritorio 1440, tablet 900 y celular 390, menú/panel, filtros GET,
tarjetas, subtotales de tablas, ceros y filtros inválidos; sin desbordamiento.
Capturas ficticias ignoradas en `tests/artifacts/cu21/`. El login modifica último
acceso de la cuenta ficticia: Edge compara las otras ocho tablas. HTTP compara
las nueve después del login. No se confunde autenticación con escritura de CU21.

Durante el desarrollo se ajustaron expectativas de fixtures (el médico también
tiene una cita de otro paciente), codificación HTTP de arrays no vacíos y el
argumento de entrada del runner. Las aprobaciones corresponden a ejecuciones
posteriores a esas correcciones; los intentos fallidos no se suman.

## Fixtures y preservación

Solo `clinica_vida_salud_dev`, PostgreSQL 17 local, con guardas de `Cu15Local.php`.
No se leen variables de Render ni se hacen conexiones externas. Se reutilizan
`HistorialPacientePrueba`, `AgendaPrueba` y sus auxiliares CU13–CU18 con IDs propios.
Se agregan un médico sin citas, una segunda asociación y dos citas de legado
para comprobar medianoche/cruce de año. Las transiciones CU18 del fixture usan
el reloj inyectado existente: no se modifica el reloj de HTTP ni producción.

Los registros ficticios se retiran por sus IDs. Las pruebas de reportes no cambian
citas ni agregan eventos. No se desactivan triggers, CHECK, GiST, autenticación,
sesiones ni CSRF. Se comparan filas y metadatos del esquema antes/después de la
campaña; las secuencias pueden avanzar por fixtures.

## Regresión y comprobaciones de cierre

`php tests/regresion_cu21.php` terminó correctamente: 29 entradas del runner,
incluida la campaña anidada de 26 suites del Ciclo 1. Se ejecutaron ahora las
48 suites de regresión funcional CU01–CU20 y las tres nuevas de CU21, además de
esquema y herramientas. No se reutilizan aprobaciones de CU20.

| Conjunto ejecutado ahora | Aprobadas |
|---|---:|
| CU01–CU14 y mejoras 6A: `regresion_cu15` (26 suites) | 1003 |
| CU15: integración 36, concurrencia 34, HTTP 35, Edge 16 | 121 |
| CU16: integración 53, HTTP 41, concurrencia 64, Edge 25 | 183 |
| CU17: integración 53, HTTP 43, concurrencia 86, Edge 29 | 211 |
| CU18: integración 74, HTTP 57, concurrencia 141, Edge 38 | 310 |
| CU19: integración 77, HTTP 101, Edge 21 | 199 |
| CU20: integración 76, HTTP 114, Edge 22 | 212 |
| **Regresión funcional CU01–CU20** | **2239** |
| Esquema: `esquema_cu15_validation`, READ ONLY | 93 |
| `render_sql_local`, READ ONLY local | 3 |
| `render_preparacion.php`, sin red | 24 |
| `render_preparacion.ps1`, sin red, ejecutada por separado | 34 |
| **Regresión, esquema y herramientas** | **2393** |
| Nuevas CU21: integración 138, HTTP 90, Edge 16 | 244 |
| **Total de esta campaña** | **2637** |

El runner PHP informa 2603; las 34 de PowerShell completan las 2637. Las primeras
ejecuciones de las suites nuevas no se suman otra vez. Ninguna suite final falló.
Sintaxis: 208 archivos PHP correctos y JavaScript correcto con `node --check`,
fuera del conteo de aserciones. La revisión visual cubrió las tres capturas.

Cierre de base: las nueve tablas conservan exactamente los conteos y huellas
de filas capturados antes de las pruebas; restricciones, índices y función del
trigger también coinciden. Verificadores finales READ ONLY: esquema 001–008,
nueve tablas/doce FK, cero conflictos de intervalos y cero duplicados CU18.
Migraciones y servicios CU11–CU20 permanecen idénticos a HEAD.

Comandos reproducibles:

```powershell
php tests/regresion_cu21.php
powershell -NoProfile -File tests/render_preparacion.ps1
php scripts/verificar-cu15-local.php
php scripts/inspeccionar-cu18-local.php
node --check tests/citas_browser.mjs
```

Las suites llamadas render son verificaciones locales/simuladas sin conexiones
externas; no invocan Inicializar, Administrador o migraciones. El runner guarda
diagnósticos/logs ignorados en `tests/artifacts/cu21/`; no deben publicarse.
No se construye Docker ni se prueba producción. Los informes anteriores conservan
sus conteos históricos; este documento registra las ejecuciones de CU21.

## Inventario exclusivo de CU21

**20 archivos: 13 nuevos y 7 modificados.** Los cinco cambios previos se excluyen.

| Estado | Archivo |
|---|---|
| Nuevo | `app/Controllers/ReportesCitasController.php` |
| Nuevo | `app/Repositories/ReporteCitaRepository.php` |
| Nuevo | `app/Services/ReporteCitaService.php` |
| Modificado | `app/Views/dashboard.php` |
| Modificado | `app/Views/layout.php` |
| Nuevo | `app/Views/reportes/citas.php` |
| Nuevo | `app/Views/reportes/error.php` |
| Nuevo | `docs/ciclo2/CU21.md` |
| Modificado | `docs/ciclo2/MAPA_DEL_CODIGO.md` |
| Nuevo | `docs/ciclo2/PRUEBAS_CU21.md` |
| Modificado | `docs/ciclo2/README.md` |
| Nuevo | `public/assets/css/reportes.css` |
| Modificado | `public/index.php` |
| Nuevo | `tests/ReportesPrueba.php` |
| Modificado | `tests/README.md` |
| Modificado | `tests/citas_browser.mjs` |
| Nuevo | `tests/http_reportes.php` |
| Nuevo | `tests/regresion_cu21.php` |
| Nuevo | `tests/reportes_browser.php` |
| Nuevo | `tests/reportes_integration.php` |

Excluidos y protegidos: `app/Config/Database.php`,
`app/Config/DatabaseDiagnostic.php`, `scripts/render-postgres.ps1`,
`tests/render_preparacion.ps1`, `tests/database_render_validation.php` y `.env`.
Capturas, sesiones, logs, temporales, respaldos y credenciales no se versionan.
No se cambian servicios CU11–CU20, migraciones 001–008, reglas o datos existentes.
No se implementa CU22. Definiciones, filtros, límites y UML: [CU21.md](CU21.md).

Cierre Git: inventario contrastado con el estado real (13 nuevos/7 modificados),
índice vacío y HEAD aún en CU20. Los cinco archivos previos y .env conservan
sus SHA256 iniciales exactos, sin publicar huellas privadas. No se detectaron
coincidencias de valores secretos de .env, claves privadas, tokens GitHub o URL
PostgreSQL con credenciales en los 20 archivos CU21. Formato y `git diff --check`
correctos; .env, sesiones, logs, capturas y temporales ignorados por Git.
Main conserva `1afbad7b4a68b01f9999f54c1c6d9fe8ce40bf37`. No se ejecutaron
git add, commit, push, merge, reset, clean o stash, ni se creó Pull Request.
No se consultó ni modificó Render, PostgreSQL externo u otros proyectos.
CU21 queda implementado y probado localmente, sin fallos conocidos pendientes.

# CU16 — Informe de implementación y pruebas

Fecha: 31/10/2026. Base exclusiva: `clinica_vida_salud_dev` local, PostgreSQL 17.10,
zona America/La_Paz. Rama: `feat/ciclo2-cu16-cancelar-citas`, creada desde el commit
CU15 `03136dde9011cfa559e0eeb732f59ac0c45f2448`. Sin commit, push, merge o PR de CU16.

## Resultado funcional

CU16 permite a administrador y recepcionista cancelar una cita programada por
GET/POST `/citas/{id}/cancelar`. GET muestra la confirmación sin escribir; POST
exige sesión, rol, CSRF, acción confirmar y referencia vigente del formulario.
PRG lleva a CU14 con un aviso de éxito que se consume una sola vez.

La cita conserva todos sus campos salvo estado, incluido código, paciente, médico,
especialidad, fecha y duración. Se registra un evento `cancelacion` con actor,
momento real y motivo administrativo opcional de hasta 250 caracteres. No se
reutiliza el motivo clínico de la reserva. Solo se usan los estados existentes
`programada`, `cancelada` y `atendida`; no se implementa inasistencia o CU17.

La ocupación CU12 y la exclusión GiST se limitan a programada. Una cancelada
no impide una nueva reserva CU13; sigue consultable en CU14 y CU15 la rechaza.
La cancelación no cambia horarios CU11 ni exige que los catálogos sigan activos.

## Migración autorizada

006 fue revisada contra `CU16.md`, autorizada expresamente y aplicada una sola vez
con `php scripts/aplicar-migracion-006.php --autorizado`. El procedimiento verifica
host local, nombre exacto de base, PostgreSQL 17 y contrato 005 antes de ALTER,
ejecuta 006 y vuelve a verificar antes de confirmar la misma transacción.
Un error provoca rollback, sin continuar parcialmente.

Solo se agregó `citas_historial.motivo_cancelacion VARCHAR(250) NULL`, se ampliaron
los CHECK de tipo/evento y se añadió el CHECK de motivo. No se alteraron migraciones
001–005, tablas originales, GiST, trigger, índices o referencias. Siguen existiendo
nueve tablas y doce claves foráneas.

El verificador compartido distingue estrictamente 001–004 (8/10), 001–005 (9/12)
y 001–006 (9/12 con columna y CHECK exactos). También verifica estados y duración.
Los esquemas anteriores se prueban con catálogos aislados en memoria y el mismo
SQL real, sin crear ni modificar físicamente otra base.

## Pruebas ejecutadas en esta entrega

Estos resultados se ejecutaron ahora con 006 instalada; no son cifras copiadas
de informes anteriores. Los intentos de diagnóstico intermedios no se suman dos veces.

| Comando / conjunto | Aprobadas |
|---|---:|
| `php tests/cancelacion_integration.php` | 53 |
| `php tests/http_cancelacion.php` | 41 |
| `php tests/cancelacion_concurrency.php` | 64 |
| `php tests/cancelacion_browser.php` | 25 |
| **CU16 funcional** | **183** |
| `php tests/regresion_cu15.php` — 26 suites CU01–CU14 y mejoras 6A | 1003 |
| `php tests/reprogramacion_integration.php` | 36 |
| `php tests/reprogramacion_concurrency.php` | 34 |
| `php tests/http_reprogramacion.php` | 35 |
| `php tests/reprogramacion_browser.php` | 16 |
| **Regresión funcional CU01–CU15** | **1124** |
| `php tests/esquema_cu15_validation.php` — READ ONLY, 001–004/005/006 | 67 |
| `php tests/render_sql_local.php` — READ ONLY local | 3 |
| `php tests/render_preparacion.php` — sin red | 24 |
| `powershell -NoProfile -File tests/render_preparacion.ps1` — sin red | 34 |
| **Total de comprobaciones finales** | **1435** |

Además: sintaxis correcta en 156 archivos PHP, `node --check tests/citas_browser.mjs`
y `git diff --check` sin errores. Son verificaciones adicionales, no incluidas en 1435.
El comando `php scripts/verificar-cu15-local.php` también confirmó nueve tablas,
doce FK y contrato completo. No se construyó Docker ni se probó Apache/Render.

Integración cubre identidad, horario, actor, TIMESTAMPTZ real, motivo opcional,
Unicode, CHECK reales rechazando eventos inválidos, roles, estados, rollback al
fallar UPDATE o INSERT, formularios viejos y relaciones entre CU11–CU15.
HTTP cubre login, ambos roles permitidos y ambos prohibidos, CSRF ausente/inválido/
malformado, XSS, parámetros manipulados, 404, 405, confirmación explícita y PRG.
No hay DELETE en el servicio o repositorio de cancelación; únicamente las pruebas
eliminan sus propios fixtures tras finalizar.

## Concurrencia real

Se ejecutaron ocho carreras con procesos PHP y conexiones PostgreSQL independientes.
En seis se observó espera real en `pg_stat_activity` con bloqueadores; los lectores
de las otras dos terminaron antes del commit, como corresponde a MVCC.

| Caso | Resultado |
|---|---|
| Cancelación → segunda cancelación | La segunda espera y recibe 409; un evento |
| Cancelación → reprogramación CU15 | CU15 espera y rechaza el estado cancelada |
| Reprogramación → cancelación con formulario anterior | CU16 espera y rechaza la revisión desactualizada; conserva el horario ganador |
| Cancelación sin commit + consulta CU12 | CU12 ve aún ocupado; después del commit ofrece el turno |
| Cancelación sin commit + reserva CU13 | CU13 rechaza ocupación visible; después del commit un reintento reserva correctamente |
| CU11 modifica otro día del mismo médico → cancelación | CU16 espera el bloqueo del médico y después cancela |
| Cancelación → CU11 desactiva el horario | CU11 espera; tras el commit puede desactivar sin dejar citas programadas fuera de cobertura |
| Reprogramación revertida → cancelación | CU16 espera el rollback y confirma solo la cancelación, sin evento falso de CU15 |

Se comprobaron estado final, identidad, evento único, ausencia de transacciones
abiertas y cero citas programadas superpuestas en las ocho carreras. El orden
de bloqueo es médico FOR SHARE → cita FOR UPDATE → usuario FOR SHARE.

## Navegador y datos

Edge real: escritorio 1440px y celular 390px, confirmación, volver sin cancelar,
motivo, cancelación real en cada tamaño, PRG, recarga sin duplicación y acciones
incompatibles ausentes. Sin desbordamiento horizontal. Se revisaron visualmente
las capturas de confirmación en ambos tamaños. Cuatro capturas ficticias en
`tests/artifacts/cu16/`, ignoradas por Git, usando perfil temporal separado.
Credenciales ficticias del navegador por stdin, nunca por argumentos.

Se compararon antes/después los conteos y huellas de las nueve tablas: todos
coincidieron; historial inicialmente vacío y vacío después de retirar fixtures.
Las restricciones de citas también coincidieron. Ningún registro previo se
modificó o eliminó. Las secuencias pueden avanzar por las pruebas, incluso tras
rollback. No se consultó PostgreSQL de Render ni otras bases.

## Inventario exclusivo de CU16

25 archivos: 13 nuevos y 12 modificados respecto del commit CU15. El inventario
histórico de CU15 permanece como registro de esa entrega, no como lista de CU16.

| Estado | Archivo |
|---|---|
| Nuevo | `app/Controllers/CancelacionesController.php` |
| Nuevo | `app/Services/CancelacionService.php` |
| Nuevo | `app/Views/citas/cancelar-error.php` |
| Nuevo | `app/Views/citas/cancelar.php` |
| Nuevo | `database/migrations/006_citas_cancelacion.sql` |
| Nuevo | `docs/ciclo2/CU16.md` |
| Nuevo | `docs/ciclo2/PRUEBAS_CU16.md` |
| Nuevo | `scripts/aplicar-migracion-006.php` |
| Nuevo | `tests/CancelacionPrueba.php` |
| Nuevo | `tests/cancelacion_browser.php` |
| Nuevo | `tests/cancelacion_concurrency.php` |
| Nuevo | `tests/cancelacion_integration.php` |
| Nuevo | `tests/http_cancelacion.php` |
| Modificado | `app/Repositories/CitaRepository.php` |
| Modificado | `app/Views/citas/index.php` |
| Modificado | `app/Views/citas/show.php` |
| Modificado | `docs/ciclo2/MAPA_DEL_CODIGO.md` |
| Modificado | `docs/ciclo2/README.md` |
| Modificado | `public/assets/css/app.css` |
| Modificado | `public/index.php` |
| Modificado | `scripts/render/verificar-instalacion.sql` |
| Modificado | `scripts/verificar-cu15-local.php` |
| Modificado | `tests/README.md` |
| Modificado | `tests/citas_browser.mjs` |
| Modificado | `tests/esquema_cu15_validation.php` |

## Archivos protegidos y pendientes

Los cinco archivos anteriores permanecen intactos, comprobados con SHA-256:
`app/Config/Database.php`, `app/Config/DatabaseDiagnostic.php`,
`scripts/render-postgres.ps1`, `tests/render_preparacion.ps1` y
`tests/database_render_validation.php`. No pertenecen a CU16. `.env` también
permanece intacto y excluido; no se publican hashes, credenciales o datos reales.
Capturas, sesiones, logs y temporales siguen ignorados. El índice permanece vacío.

No se ejecutaron git add/commit/push/merge, migraciones en Render o despliegues.
`main` y el commit CU15 no cambiaron. No se implementó CU17. Sin fallos funcionales
pendientes en las pruebas locales ejecutadas; guardar CU16 en Git y desplegar
quedan fuera de esta autorización y requieren instrucciones posteriores.

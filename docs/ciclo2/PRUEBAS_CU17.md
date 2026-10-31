# CU17 — Informe de implementación y pruebas

Fecha: 31/10/2026. PHP 8.4.22, PostgreSQL 17.10 local, America/La_Paz.
Base exclusiva: `clinica_vida_salud_dev`. Rama `feat/ciclo2-cu17-confirmar-citas`,
creada desde CU16 `431a861bb9b01401691f16303735b9d4fbe4a70a`, que incluye CU15
`03136dde9011cfa559e0eeb732f59ac0c45f2448`. Sin commit ni push de CU17.

## Implementación

Administrador y recepcionista pueden confirmar una cita programada mediante
GET/POST `/citas/{id}/confirmar`. GET solo muestra la revisión; POST exige sesión,
rol, CSRF, acción explícita y referencia vigente de horario/revisión del historial.
Se usa PRG al detalle CU14 con aviso consumido una sola vez. Se integró la acción
en listado y detalle, y se agregó el estado Confirmada al catálogo/filtro CU14.
No se confunde con `/citas/confirmacion/{id}`, recibo de programación de CU13.

El único UPDATE cambia estado a confirmada. Código, paciente, médico, especialidad,
fecha, duración, motivo, creador y demás datos permanecen iguales. El evento
confirmacion registra actor, `clock_timestamp()` TIMESTAMPTZ, programada → confirmada,
inicio anterior/nuevo iguales y motivo_cancelacion NULL. No hay INSERT/DELETE de
citas en CU17. Roles y transiciones se controlan en servidor, no solo en botones.

CU15/CU16 conservan su política de solo programada; confirmada, cancelada y atendida
son rechazadas. No se agrega estado de inasistencia ni se implementa CU18.

## Migración y ocupación

007 fue revisada contra `CU17.md`, autorizada expresamente y aplicada una sola vez
mediante `php scripts/aplicar-migracion-007.php --autorizado`. El asistente verificó
destino local/versión/006, ejecutó DDL y verificó 007 dentro de la misma transacción.
Cualquier fallo revierte todo; no hay aplicación automática ni inicialización Docker.

No crea tablas, columnas o FK. Conserva nueve tablas y doce referencias; amplía
CHECK de estado/historial, reconstruye GiST con el mismo intervalo UTC [inicio,fin)
para programada y confirmada, y reemplaza la función del trigger existente.
El bloqueo transaccional evita una ventana visible sin exclusión.

El trigger conserva fecha futura y catálogos activos para nuevas reservas, incluidas
inserciones directas confirmadas. La transición programada → confirmada que cambia
solo estado mantiene la reserva anterior y toma FOR SHARE del médico, sin imponer
anticipación o reactivación de catálogos. Las pruebas comprobaron esta última
propiedad con catálogos ficticios inactivos y el rechazo de nuevas reservas inválidas.
No se fabricó una programada pasada desactivando triggers para probar el paso del tiempo.

CU12 selecciona ocupaciones programada/confirmada; CU13 revalida con ese mismo
servicio dentro de su transacción y GiST arbitra conflictos. CU11 protege ambos
estados futuros frente a recortes/desactivación. Se actualizó también el conteo
diagnóstico de reservas futuras. Las canceladas siguen sin bloquear.

El verificador compartido distingue estrictamente 001–004 (8/10) y 001–005/006/007
(9/12) por objetos reales, CHECK, columnas, referencias, índices, GiST y trigger.
Para 007 verifica además la huella normalizada del código público de la función,
lenguaje, retorno trigger y ausencia de SECURITY DEFINER. Rechaza instalaciones
parciales, GiST anterior con estados nuevos o una función alterada.

## Pruebas ejecutadas en esta entrega

Todos los siguientes resultados se ejecutaron ahora con 007 instalada. No se
copian aprobaciones de informes anteriores ni se suman varias veces las pruebas
que se ampliaron durante el desarrollo.

| Comando / conjunto | Aprobadas |
|---|---:|
| `php tests/confirmacion_integration.php` | 53 |
| `php tests/http_confirmacion.php` | 43 |
| `php tests/confirmacion_concurrency.php` | 86 |
| `php tests/confirmacion_browser.php` | 29 |
| **CU17 funcional** | **211** |
| `php tests/regresion_cu15.php` — 26 suites CU01–CU14 y mejoras 6A | 1003 |
| `php tests/reprogramacion_integration.php` | 36 |
| `php tests/reprogramacion_concurrency.php` | 34 |
| `php tests/http_reprogramacion.php` | 35 |
| `php tests/reprogramacion_browser.php` | 16 |
| `php tests/cancelacion_integration.php` | 53 |
| `php tests/http_cancelacion.php` | 41 |
| `php tests/cancelacion_concurrency.php` | 64 |
| `php tests/cancelacion_browser.php` | 25 |
| **Regresión funcional CU01–CU16** | **1307** |
| `php tests/esquema_cu15_validation.php` — READ ONLY | 77 |
| `php tests/render_sql_local.php` — READ ONLY local | 3 |
| `php tests/render_preparacion.php` — sin red | 24 |
| `powershell -NoProfile -File tests/render_preparacion.ps1` — sin red | 34 |
| **Total de comprobaciones finales** | **1656** |

Adicionalmente: sintaxis correcta en 166 archivos PHP, JavaScript validado con
`node --check tests/citas_browser.mjs` y `git diff --check` sin errores.
`php scripts/verificar-cu15-local.php` confirmó el contrato 001–007 en READ ONLY.
Estas verificaciones adicionales no se suman al total. No se construyó Docker ni
se hicieron pruebas de Apache o Render.

Integración: identidad completa, evento/actor/momento, ambos roles permitidos,
roles prohibidos/inactivos, inexistente, estados incorrectos, segunda confirmación,
formulario viejo, fallo en UPDATE o INSERT con rollback, CHECK de historial reales,
errores 23P01 reales de GiST, CU11 y conservación de las reglas de CU15/CU16.
HTTP: autenticación, autorización GET/POST, CSRF, XSS, 404/405/409/419/422, PRG,
parámetros manipulados, confirmada en CU14 y ocupación CU12.

## Diez carreras reales

Procesos PHP y conexiones PostgreSQL independientes. En nueve carreras se observó
espera real con bloqueadores en `pg_stat_activity`; CU12 leyó sin esperar como
corresponde a MVCC en la décima.

| Carrera | Resultado |
|---|---|
| Confirmar → segunda confirmación | 409 y un solo evento |
| Confirmar → cancelar CU16 | CU16 espera y rechaza confirmada |
| Cancelar CU16 → confirmar | CU17 espera y rechaza cancelada |
| Confirmar → reprogramar CU15 | CU15 espera y rechaza confirmada |
| Reprogramar CU15 → confirmar con formulario anterior | CU17 rechaza la referencia vieja; conserva el horario ganador |
| Confirmar → inserción directa superpuesta | GiST espera y rechaza con 23P01; el estado confirmado sigue excluido |
| Confirmar → desactivar horario CU11 | CU11 espera y rechaza dejar confirmada sin cobertura |
| CU11 cambia otro día → confirmar | CU17 espera al médico y confirma conservando la cita |
| Confirmación revertida → confirmar | Solo queda el evento confirmado del segundo proceso |
| Confirmar sin commit + consulta CU12 | El turno se ve ocupado antes y después del commit |

Se comprobaron estado ganador, identidad, horario, único evento válido, ausencia
de transacciones abiertas y cero cruces entre programadas/confirmadas. Orden de
bloqueos del servicio: médico FOR SHARE → cita FOR UPDATE → usuario FOR SHARE.

## Interfaz y conservación

Edge real en 1440px y 390px: abrir confirmación, volver sin escribir, confirmar,
PRG, badge, refresh, acciones incompatibles ausentes, filtro CU14 y ocupación CU12.
Se revisaron visualmente las capturas de confirmación en ambos tamaños, sin
desbordamiento horizontal. Cuatro capturas ficticias en `tests/artifacts/cu17/`,
ignoradas por Git. Perfil temporal independiente y credenciales ficticias por stdin.

Los conteos y huellas de las nueve tablas coincidieron antes/después de 007 y al
final de todas las pruebas. Historial inicialmente vacío y vacío al retirar
fixtures; ninguna cita o registro previo se alteró o eliminó. Las secuencias pueden
avanzar con las pruebas incluso tras rollback. Migraciones 001–006 intactas.
No se desactivaron triggers, GiST, autenticación, sesiones, CSRF o TLS.

## Inventario exclusivo de CU17

35 archivos: 13 nuevos y 22 modificados respecto del commit CU16. Incluye la
actualización de documentación CU15/CU16 para reflejar 007 sin cambiar sus políticas.

| Estado | Archivo |
|---|---|
| Nuevo | `app/Controllers/ConfirmacionesController.php` |
| Nuevo | `app/Services/ConfirmacionService.php` |
| Nuevo | `app/Views/citas/confirmar-error.php` |
| Nuevo | `app/Views/citas/confirmar.php` |
| Nuevo | `database/migrations/007_citas_confirmacion.sql` |
| Nuevo | `docs/ciclo2/CU17.md` |
| Nuevo | `docs/ciclo2/PRUEBAS_CU17.md` |
| Nuevo | `scripts/aplicar-migracion-007.php` |
| Nuevo | `tests/ConfirmacionPrueba.php` |
| Nuevo | `tests/confirmacion_browser.php` |
| Nuevo | `tests/confirmacion_concurrency.php` |
| Nuevo | `tests/confirmacion_integration.php` |
| Nuevo | `tests/http_confirmacion.php` |
| Modificado | `app/Models/Cita.php` |
| Modificado | `app/Repositories/CitaRepository.php` |
| Modificado | `app/Repositories/DisponibilidadRepository.php` |
| Modificado | `app/Repositories/HorarioRepository.php` |
| Modificado | `app/Services/CancelacionService.php` |
| Modificado | `app/Services/HorarioService.php` |
| Modificado | `app/Services/ReprogramacionService.php` |
| Modificado | `app/Views/citas/index.php` |
| Modificado | `app/Views/citas/show.php` |
| Modificado | `app/Views/horarios/status.php` |
| Modificado | `docs/ciclo2/CU15.md` |
| Modificado | `docs/ciclo2/CU16.md` |
| Modificado | `docs/ciclo2/MAPA_DEL_CODIGO.md` |
| Modificado | `docs/ciclo2/README.md` |
| Modificado | `public/assets/css/app.css` |
| Modificado | `public/index.php` |
| Modificado | `scripts/inspeccionar-horarios.php` |
| Modificado | `scripts/render/verificar-instalacion.sql` |
| Modificado | `scripts/verificar-cu15-local.php` |
| Modificado | `tests/README.md` |
| Modificado | `tests/citas_browser.mjs` |
| Modificado | `tests/esquema_cu15_validation.php` |

## Entornos y archivos protegidos

Los cinco archivos anteriores permanecen intactos, comparados con SHA-256, y fuera
de este inventario: `app/Config/Database.php`, `app/Config/DatabaseDiagnostic.php`,
`scripts/render-postgres.ps1`, `tests/render_preparacion.ps1` y
`tests/database_render_validation.php`. `.env` también permanece intacto y excluido.
No se publican huellas privadas, contraseñas, URLs de conexión, sesiones o logs.

El índice permanece vacío. No se ejecutaron git add/commit/push/merge, no se tocó
`main`, Render, PostgreSQL de producción ni Panadería Santiago. No se implementó
CU18. No quedan fallos locales pendientes en las pruebas ejecutadas; guardar esta
entrega en Git y desplegarla requieren instrucciones posteriores.

# CU18 — Informe de implementación y pruebas

Fecha: 31/10/2026. PHP 8.4.22, PostgreSQL 17.10 local, America/La_Paz.
Destino exclusivo: `clinica_vida_salud_dev`.
Rama: `feat/ciclo2-cu18-asistencia-citas`, creada desde CU17
`078272a9f7f106dccb9149a8e76e6084d7caf764`, que contiene CU16 y CU15.
Sin commit, push, merge, Pull Request o despliegue. CU19 no implementado.

## Implementación y reglas

- Llegada: programada/confirmada → presente. Administrador y recepcionista.
- Finalización: presente → atendida, únicamente por el médico activo vinculado
  a esa cita y desde el inicio previsto. No existe excepción administrativa.
- Inasistencia: programada/confirmada → inasistencia, solo desde el fin del
  intervalo, sin llegada previa. Administrador y recepcionista.
- Cancelada, atendida e inasistencia son terminales para CU18. No se corrigen
  estados hacia atrás ni se infieren asistencias históricas sin evento.
- Llegar no finaliza la atención. No se inventan tolerancias, multas, ventanas de
  anticipación ni automatizaciones de inasistencia.
- Código, paciente, médico, especialidad, inicio, duración y demás campos
  permanecen iguales: CU18 actualiza únicamente estado y agrega historial.
- Paciente conserva sus permisos anteriores, sin acciones administrativas nuevas.
  El médico solo consulta/atiende su propia agenda. Administrador ve la traza
  completa; recepción y médico ven las marcas administrativas necesarias.
- GET/POST `/citas/{id}/llegada`, `/citas/{id}/atencion`,
  `/citas/{id}/inasistencia`. Sesión/rol en servidor, CSRF, escape HTML,
  parámetros PDO, confirmación explícita y referencia vigente de inicio/estado/revisión.
  GET no escribe; POST exitoso usa 303 al detalle CU14 y aviso de una sola lectura.
  No se aceptan actor, estado ni fecha enviados por el cliente.

El evento registra tipo, estados anterior/nuevo, el mismo inicio en ambos campos,
usuario responsable y `cambiado_en` TIMESTAMPTZ. Motivo_cancelacion es NULL;
no se guarda diagnóstico ni contenido clínico. La hora normal procede de
`clock_timestamp()`, representada en America/La_Paz.

Secuencia: médico FOR SHARE → cita FOR UPDATE → usuario FOR SHARE →
revalidación de permisos/vínculo/estado/tiempo/revisión → UPDATE + INSERT →
COMMIT. Transacción READ COMMITTED propia o savepoint compatible. Cualquier
fallo revierte estado e historial; no hay evento en rechazos o recargas.

CU15–CU17 mantienen su política de solo programada. CU12/CU13 y GiST conservan
programada, confirmada, presente, atendida e inasistencia; únicamente cancelada
libera el intervalo. CU11 mantiene cobertura de intervalos que aún no terminan,
incluso si la atención concluyó antes del fin reservado. No se permite usar CU18
para liberar anticipadamente un turno.

## Migración 008

Fue necesaria para los estados y contratos nuevos. Se revisó contra CU18.md,
se autorizó expresamente y se aplicó una sola vez mediante:

```powershell
php scripts/aplicar-migracion-008.php --autorizado
```

No repetir. El ejecutor confirmó host/servidor loopback, nombre real de base,
PostgreSQL 17, esquema 007 y ausencia de 008. Bajo bloqueo exclusivo comprobó
cero superposiciones históricas y cero duplicados incompatibles con el índice;
DDL y verificación posterior se confirmaron en una sola transacción, con rollback
ante error. No se aplicó ninguna otra migración en esta entrega.

008 no crea tablas, columnas ni FK: quedan nueve tablas y doce claves foráneas.
Amplía CHECK de estado y eventos, conserva la exclusión GiST UTC [inicio,fin)
para los cinco estados ocupantes, agrega CHECK temporal de atención/inasistencia
y un índice único parcial (cita_id,tipo) para llegada/atencion/inasistencia.
Conserva las dos referencias, índices anteriores, trigger y función de 007.
No reescribe citas/historial ni modifica migraciones 001–007.

El contrato compartido detecta estrictamente 001–004 (8/10) y 005/006/007/008
(9/12). Comprueba nombres, columnas, tipos, FK, estados/transiciones, GiST,
trigger/función y todos los índices de la versión; 008 exige además CHECK temporal
y tercer índice único parcial. Rechaza instalaciones parciales o debilitadas.
Los esquemas anteriores se representan con catálogos aislados en memoria;
no se crean bases alternativas ni se ejecuta DDL para esas pruebas.

## Pruebas ejecutadas con 008

Los resultados siguientes corresponden a esta entrega; no se copian aprobaciones
históricas ni se suman intentos repetidos. Todas las siguientes suites completaron
sin fallos pendientes con 008 instalada.

| Comando / conjunto | Aprobadas |
|---|---:|
| `php tests/asistencia_integration.php` | 74 |
| `php tests/http_asistencia.php` | 57 |
| `php tests/asistencia_concurrency.php` | 141 |
| `php tests/asistencia_browser.php` | 38 |
| **CU18 nuevo** | **310** |
| `php tests/regresion_cu15.php` — 26 suites CU01–CU14 y mejoras 6A | 1003 |
| `php tests/reprogramacion_integration.php` | 36 |
| `php tests/reprogramacion_concurrency.php` | 34 |
| `php tests/http_reprogramacion.php` | 35 |
| `php tests/reprogramacion_browser.php` | 16 |
| **CU15 regresión** | **121** |
| `php tests/cancelacion_integration.php` | 53 |
| `php tests/http_cancelacion.php` | 41 |
| `php tests/cancelacion_concurrency.php` | 64 |
| `php tests/cancelacion_browser.php` | 25 |
| **CU16 regresión** | **183** |
| `php tests/confirmacion_integration.php` | 53 |
| `php tests/http_confirmacion.php` | 43 |
| `php tests/confirmacion_concurrency.php` | 86 |
| `php tests/confirmacion_browser.php` | 29 |
| **CU17 regresión** | **211** |
| **Regresión funcional CU01–CU17** | **1518** |
| `php tests/esquema_cu15_validation.php` — READ ONLY | 93 |
| `php tests/render_sql_local.php` — READ ONLY local | 3 |
| `php tests/render_preparacion.php` — sin red | 24 |
| `powershell -NoProfile -File tests/render_preparacion.ps1` — sin red | 34 |
| **Total final, sin duplicar subtotales** | **1982** |

Además: sintaxis correcta en 178 archivos PHP, `node --check tests/citas_browser.mjs`
y `git diff --check` sin errores. No se suman estas revisiones al total.

Integración: todas las transiciones, límites temporales al microsegundo,
identidad completa, evento/actor/momento, ambos roles administrativos, permisos
prohibidos/inactivos, médico propio/ajeno, reintento, referencia antigua, error
en UPDATE/INSERT con rollback, protección CU11–CU17 y errores reales GiST 23P01,
CHECK 23514 e índice único 23505.

HTTP: sesión/roles GET y POST, CSRF ausente/inválido/malformado, XSS,
404/405/409/419/422, confirmación explícita, PRG, recarga sin duplicado,
parámetros manipulados, filtro Presente, marca de llegada y atención, acceso ajeno,
traza completa solo para administrador y conservación de identidad.

Edge real: escritorio 1440px y celular 390px, llegada, volver sin escribir,
confirmaciones diferenciadas, cambio al médico vinculado, finalización,
acciones terminales ausentes, recarga y traza administrativa. Revisión visual
del resultado móvil y pantallas de confirmación. Se corrigió un desbordamiento
de la tabla móvil y se agregó comprobación de límites reales de sus celdas,
además de etiquetas visibles. Nueve capturas ficticias en
`tests/artifacts/cu18/`, excluidas por Git.

Se actualizaron dos expectativas históricas justificadas: disponibilidad ahora
retiene el intervalo de atendida (7 turnos, antes 8) y las dos respuestas HTTP
de protección de horarios mantienen 422 pero usan el nuevo mensaje «intervalo
vigente». No se eliminaron aserciones. La regresión C1 se reanudó desde esa suite
tras corregir la expectativa textual; las nueve suites anteriores ya habían
aprobado ahora y no fueron afectadas por el ajuste. Un primer proceso Edge
interrumpido sin diagnóstico no se cuenta como aprobado; la ejecución completa
posterior pasó y se repitió tras corregir el diseño móvil.

## Reloj, fixtures y límites de las pruebas

Producción, HTTP y Edge usan el reloj real de PostgreSQL. El constructor del
servicio permite una Closure de reloj solo desde código de prueba, sin
parámetro HTTP ni variable de entorno que altere la hora. Integración y carreras
usan instantes simulados para el límite de 30 minutos y registran ese mismo
instante en sus eventos ficticios: no se presentan como fechas reales.

Llegada y finalización se probaron por HTTP/Edge con hora real. Para finalizar
sin esperar una cita de agenda, el helper inserta una reserva ficticia a dos
segundos del reloj PostgreSQL: omite únicamente la selección de bloques de
CU13, conserva duración, trigger, FK y GiST. La reserva normal CU13 conserva su
regresión completa. El éxito de inasistencia y el borde exacto se prueban por
servicio con reloj simulado, no mediante una espera real de treinta minutos en
HTTP/Edge. El rechazo prematuro sí se prueba por HTTP/Edge con reloj real.

No se desactivaron triggers, GiST, autenticación, sesiones o CSRF, ni se cambió
el reloj del sistema. Cada suite revierte o limpia exclusivamente sus propios
IDs ficticios. Las secuencias pueden avanzar aunque se reviertan los registros.
No se vacían tablas ni se modifica permanentemente información preexistente.
No se construyó Docker ni se comprobó Apache, Render o producción.

## Dieciséis carreras entre conexiones reales

| Carrera | Resultado |
|---|---|
| Llegada → segunda llegada | Rechazo y un solo evento |
| Atención → segunda atención | Rechazo y un único cierre |
| Inasistencia → segunda inasistencia | Rechazo y un único evento |
| Llegada → inasistencia | Rechaza presente |
| Atención → inasistencia | Ya presente al leer; rechaza sin esperar |
| Llegada → cancelar CU16 | CU16 rechaza presente |
| Reprogramar CU15 → llegada con formulario anterior | Rechaza referencia vieja y conserva horario ganador |
| Confirmar CU17 → llegada con formulario anterior | Rechaza referencia vieja; exige nueva revisión |
| Llegada → reservar CU13 mismo intervalo | Ya ocupado; rechazo sin esperar |
| Llegada → desactivar CU11 | CU11 conserva cobertura y revierte desactivación |
| Cancelar CU16 → llegada | Rechaza cancelada, sin falsa llegada |
| Llegada → reprogramar CU15 | CU15 rechaza presente |
| Llegada → confirmar CU17 | CU17 rechaza presente |
| Atención → INSERT superpuesto | GiST 23P01, intervalo no liberado |
| CU11 modifica otro día → llegada | Espera médico y registra tras releer |
| Llegada revertida → llegada | Segundo registra una llegada; no queda evento del rollback |

En catorce carreras se observó espera Lock y bloqueadores reales mediante
pg_stat_activity/pg_blocking_pids; las otras dos rechazan sin necesitar esperar.
Las conexiones son independientes, el reloj de negocio es simulado y los
bloqueos/commits/rollbacks son PostgreSQL reales.

## Inventario exclusivo de CU18

**39 archivos: 15 nuevos y 24 modificados.** Los documentos anteriores se ajustan
para señalar el esquema/ocupación actual sin reescribir sus informes históricos.
No se presupone el número de archivos de entregas anteriores.

| Estado | Archivo |
|---|---|
| Nuevo | `app/Controllers/AsistenciasController.php` |
| Modificado | `app/Controllers/CitasController.php` |
| Modificado | `app/Models/Cita.php` |
| Modificado | `app/Repositories/CitaRepository.php` |
| Modificado | `app/Repositories/DisponibilidadRepository.php` |
| Modificado | `app/Repositories/HorarioRepository.php` |
| Nuevo | `app/Services/AsistenciaService.php` |
| Modificado | `app/Services/HorarioService.php` |
| Nuevo | `app/Views/citas/asistencia-error.php` |
| Nuevo | `app/Views/citas/asistencia-resumen.php` |
| Nuevo | `app/Views/citas/asistencia.php` |
| Modificado | `app/Views/citas/index.php` |
| Modificado | `app/Views/citas/show.php` |
| Modificado | `app/Views/horarios/status.php` |
| Nuevo | `database/migrations/008_citas_asistencia.sql` |
| Modificado | `docs/ciclo2/CU15.md` |
| Modificado | `docs/ciclo2/CU16.md` |
| Modificado | `docs/ciclo2/CU17.md` |
| Nuevo | `docs/ciclo2/CU18.md` |
| Modificado | `docs/ciclo2/MAPA_DEL_CODIGO.md` |
| Nuevo | `docs/ciclo2/PRUEBAS_CU18.md` |
| Modificado | `docs/ciclo2/README.md` |
| Modificado | `public/assets/css/app.css` |
| Modificado | `public/index.php` |
| Nuevo | `scripts/aplicar-migracion-008.php` |
| Nuevo | `scripts/inspeccionar-cu18-local.php` |
| Modificado | `scripts/inspeccionar-horarios.php` |
| Modificado | `scripts/render/verificar-instalacion.sql` |
| Modificado | `scripts/verificar-cu15-local.php` |
| Nuevo | `tests/asistencia_browser.php` |
| Nuevo | `tests/asistencia_concurrency.php` |
| Nuevo | `tests/asistencia_integration.php` |
| Nuevo | `tests/AsistenciaPrueba.php` |
| Modificado | `tests/citas_browser.mjs` |
| Modificado | `tests/disponibilidad_integration.php` |
| Modificado | `tests/esquema_cu15_validation.php` |
| Nuevo | `tests/http_asistencia.php` |
| Modificado | `tests/http_horarios_integration.php` |
| Modificado | `tests/README.md` |

## Protección, estado de Git y comprobaciones de cierre

Se mantienen separados los cinco cambios anteriores, excluidos del inventario:

- `app/Config/Database.php`
- `app/Config/DatabaseDiagnostic.php`
- `scripts/render-postgres.ps1`
- `tests/render_preparacion.ps1`
- `tests/database_render_validation.php`

También se excluyen .env, credenciales, sesiones, logs, capturas y temporales.
Los artefactos de pruebas quedan ignorados. Comprobaciones finales ejecutadas:

- SHA256 de los cinco archivos anteriores y .env: idénticos a los del comienzo.
  No se publican sus huellas ni contenido privado.
- Conteos y huellas de todas las filas de las nueve tablas: idénticos al snapshot
  previo a la migración y pruebas. Citas/historial y demás datos existentes intactos;
  sin fixtures residuales. Las secuencias pueden haber avanzado.
- `php scripts/inspeccionar-cu18-local.php`: READ ONLY, nueve tablas/doce FK,
  cero conflictos de intervalos retenidos y cero eventos CU18 duplicados.
- `php scripts/verificar-cu15-local.php`: contrato 001–008 válido en READ ONLY.
- Migraciones 001–007 idénticas a HEAD. Solo 008 nueva, aplicada con autorización.
- Inventario contrastado contra Git: 39 archivos, sin faltantes ni adicionales.
- Sin coincidencias de credenciales privadas del .env local en los 39 archivos,
  ni patrones de claves privadas, tokens GitHub o URL PostgreSQL con credenciales.
  Las credenciales de fixtures son ficticias; no se copió información real a pruebas.
- `.env`, capturas y logs ignorados confirmados mediante `git check-ignore`.
- Índice vacío, sin git add, commit, push, merge o Pull Request. HEAD sigue en
  `078272a9f7f106dccb9149a8e76e6084d7caf764` (CU17); main permanece en
  `1afbad7b4a68b01f9999f54c1c6d9fe8ce40bf37`. Árbol de trabajo con los 39 archivos
  CU18 y los cinco cambios anteriores separados. No se tocó Render ni otra base.

CU18 está implementado y probado localmente, sin errores conocidos pendientes
dentro del alcance descrito. No se hicieron pruebas de despliegue o producción.
No se autoriza implícitamente ningún futuro commit/push: este trabajo termina
con archivos locales revisables, fuera del índice, en la rama CU18.
Diseño/UML: [CU18.md](CU18.md) y [mapa del código](MAPA_DEL_CODIGO.md).

# CU15 — Informe de implementación y pruebas

Fecha: 30/10/2026. Desarrollo exclusivamente local. PHP 8.4 / PostgreSQL 17.10.
Estado: CU15 implementado y probado. No se implementó CU16 ni se publicó esta entrega.

## Entrega

- GET y POST `/citas/{id}/reprogramar`.
- Roles administrador/recepción; controles en servidor y CSRF en todos los POST.
- Formulario responsivo, consulta CU12, revisión anterior/nuevo, confirmación y PRG hacia CU14.
- Mismo ID/código, paciente, médico, especialidad, duración, motivo y datos de creación.
- Solo se actualiza `citas.fecha_hora_inicio`. Solo estado programada es elegible.
- Historial de cambios con actor/fecha y revisión para rechazar formularios desactualizados.
- Transacción READ COMMITTED, bloqueos compatibles CU11/CU13, GiST y rollback.
- Exclusión de la propia cita en CU12 solo desde el servicio interno de CU15.

Reglas RN-R01–RN-R16, rutas y diseño: [CU15.md](CU15.md).
Archivos y relaciones para UML: [MAPA_DEL_CODIGO.md](MAPA_DEL_CODIGO.md).

## Pruebas funcionales ejecutadas en 8A (históricas)

| Comando | Comprobaciones aprobadas |
|---|---:|
| `php tests/reprogramacion_integration.php` | 36 |
| `php tests/reprogramacion_concurrency.php` | 34 |
| `php tests/http_reprogramacion.php` | 35 |
| `php tests/reprogramacion_browser.php` | 16 |
| **Total CU15** | **121** |

Integración: rechazo de inexistentes/canceladas/atendidas, fechas/horas inválidas/pasadas,
turno actual sin cambios, fuera de atención, descansos, ocupado/desactivado, médico y
especialidad inactivos, asociación retirada y rol no autorizado. Conservación de todos
los campos salvo inicio; identidad/código, historial/actor, CU12 y CU14 actualizados.
Los fallos de UPDATE y de inserción de historial se inyectan mediante un proxy PDO que
delega el resto a la conexión real: se comprueba rollback real de filas, sin crear
triggers de prueba ni modificar esquema. Formulario viejo rechazado incluso tras
ida y vuelta al horario inicial. Pruebas revertidas en transacción exterior.

HTTP: GET/POST sin sesión, médico/paciente 403, administrador/recepción autorizados,
CSRF ausente/inválido/array 419, XSS escapado, sin motivo clínico en formulario,
revisión sin escritura, PRG/confirmación y recarga sin repetir; conflicto después de
revisión conserva la cita, métodos/IDs inválidos, acción según rol/estado y actualización
CU12/CU14. Los campos de identidad proceden de la base, nunca del POST.

Edge real: escritorio 1440×1000 y móvil 390×844; selección del turno actual, aviso sin
cambios, turno nuevo, resumen y confirmación; recarga GET y otro cambio de fecha/hora
desde móvil. Verificación PostgreSQL: exactamente una cita, dos eventos confirmados
y campos restantes intactos. Se revisaron visualmente cuatro capturas con datos
ficticios, ignoradas en `tests/artifacts/cu15/`. Se ajustó espaciado y controles de
fecha antes de repetir y aprobar la verificación visual. No se afirma compatibilidad
probada en navegadores distintos de Edge.

## Concurrencia real

Seis carreras con procesos PHP y sesiones PostgreSQL independientes. Se observó el
bloqueo en `pg_stat_activity`, no se presentan reenvíos secuenciales como concurrencia.

1. CU15 gana el turno 10:00; CU13 espera y recibe SQLSTATE 23P01 / HTTP 409.
2. CU13 gana 10:00; CU15 recibe 23P01 y su cita queda íntegra en el horario original.
3. Dos reprogramaciones de la misma cita: una confirma y la otra rechaza su referencia obsoleta.
4. CU11 desactiva el horario destino primero: CU15 espera y rechaza tras revalidar.
5. CU15 confirma primero: CU11 espera y no puede dejar esa cita sin cobertura.
6. CU11 edita primero la cobertura: CU15 espera y rechaza el turno que ya no pertenece al horario.

En todos los casos: cero superposiciones, historial solo de operaciones confirmadas,
conexiones sin transacciones abiertas y conservación del horario de la cita perdedora.

## Regresión completa del Ciclo 1

`php tests/regresion_cu15.php`: 26 suites, **1003 comprobaciones aprobadas**.
No se ejecuta la suite histórica `auth_users_integration.php`, que exige usuarios
vacíos; se utiliza la regresión HTTP vigente, sin vaciar tablas.

| Suite | Aprobadas |
|---|---:|
| `pacientes_validation.php` | 15 |
| `pacientes_integration.php` | 17 |
| `http_pacientes_integration.php` | 65 |
| `http_auth_integration.php` | 34 |
| `medicos_validation.php` | 25 |
| `medicos_integration.php` | 40 |
| `http_medicos_integration.php` | 108 |
| `horarios_validation.php` | 17 |
| `horarios_integration.php` | 33 |
| `http_horarios_integration.php` | 65 |
| `horarios_concurrency.php` | 4 |
| `disponibilidad_integration.php` | 48 |
| `http_disponibilidad_integration.php` | 44 |
| `citas_integration.php` | 41 |
| `http_citas_integration.php` | 35 |
| `citas_concurrency.php` | 13 |
| `http_citas_formulario.php` | 37 |
| `citas_browser.php` | 15 |
| `consulta_citas_integration.php` | 55 |
| `http_consulta_citas.php` | 65 |
| `consulta_citas_browser.php` | 16 |
| `auditoria_ciclo1_http.php` | 46 |
| `mejoras_6a_validation.php` | 43 |
| `mejoras_6a_integration.php` | 46 |
| `http_mejoras_6a.php` | 62 |
| `mejoras_6a_browser.php` | 14 |

Total distinto ejecutado en 8A: **121 + 1003 = 1124** (no reejecutado en 8C).
No se suman repeticiones. Adicionalmente, las 44 comprobaciones del diagnóstico PDO
preexistente aprobaron; no se cuentan como CU15 ni como una nueva funcionalidad.

Una ejecución de médicos terminó sin diagnóstico y no se contó como aprobada;
al reanudar, la suite completa aprobó sus 108 aserciones. La auditoría 5A también
detectó que su simulación de producción intentaba usar PostgreSQL local sin TLS,
incompatible con el endurecimiento PDO previo a CU15. Se ajustó exclusivamente la
prueba: comprueba la cookie Secure en una ruta 404 que ejecuta bootstrap sin consultar
PostgreSQL. `HttpCatalogo` admite esa comprobación opcional y mantiene /login 200 por
defecto. Las 46 aserciones aprobaron; no se rebajó TLS ni se cambió autenticación.
No se han modificado documentos académicos del Ciclo 1.

Sintaxis: **144 PHP** comprobados; `node --check` correcto en el soporte de navegador
modificado y JS de citas. `git diff --check` sin errores.

## PostgreSQL y conservación

Migración `005_citas_historial.sql` aplicada con autorización expresa mediante
`scripts/aplicar-migracion-005.php`, solo en `clinica_vida_salud_dev` local y en
transacción. Nueve tablas tras incorporar historial; migraciones 001–004 intactas.
El ejecutor comprueba host local, base real y PostgreSQL 17 antes de escribir;
rechaza reejecución y nunca usa los asistentes de Render.

Inspección `php scripts/inspeccionar-cu15-local.php` en READ ONLY:
conteos y huellas de las ocho tablas originales idénticos al inicio, mismas columnas
y restricciones de citas, GiST conservada. Estado final:

| Tabla | Registros |
|---|---:|
| usuarios | 3 |
| pacientes | 3 |
| medicos | 2 |
| especialidades | 4 |
| medicos_especialidades | 3 |
| horarios_atencion | 2 |
| citas | 1 |
| roles | 4 |
| citas_historial | 0 |

Los fixtures CU15 eliminan solo su historial y sus IDs rastreados; integración usa rollback.
Las secuencias pueden avanzar por las pruebas, como en las suites del Ciclo 1; no se
reinician. No se editó .env ni se cambiaron contraseñas. No hubo operaciones en Render
ni otros proyectos/bases.

## Git y pendientes antes de publicar

Rama `feat/ciclo2-cu15-reprogramar-citas`, creada conservando los cambios previos.
HEAD sigue en `1afbad7`; sincronizado con origin/main en la comprobación final (0/0).
Sin archivos en el índice, commits, push ni despliegue.

Se conservan estos cinco archivos previos de diagnóstico Render/PDO, fuera del
alcance nuevo de CU15: `app/Config/Database.php`, `app/Config/DatabaseDiagnostic.php`,
`scripts/render-postgres.ps1`, `tests/render_preparacion.ps1` y
`tests/database_render_validation.php`. Revisarlos por separado antes de preparar
un futuro commit. No se sobrescribieron cambios del compañero.

Antes de publicar: revisión del equipo/PR, autorización para commit y push y plan
controlado de migración 005 en Render antes de desplegar código que dependa del historial.
En 8C se corrigió el SQL compartido para aceptar únicamente 8/10 sin historial o
9/12 con historial completo, sin modificar los asistentes protegidos. No usar Inicializar,
no borrar cuentas ni añadir migraciones al arranque Docker. Probar HTTPS/login,
cookies, roles y CU15 en el alojamiento tras la futura liberación autorizada.
La comprobación de dos estudiantes y la actualización académica UML quedan a cargo
de la revisión del equipo. No hay un bloqueo funcional de CU15 detectado en las pruebas.


## Cierre técnico 8C — resultados ejecutados ahora

Se completó la comprobación del esquema sin cambiar lógica de CU15, variables de
entorno, contraseñas o los cinco archivos protegidos. SQL compartido compatible con
001–004 y 001–005; el comando local y el inspector usan READ ONLY. El ejecutor 005
incorpora controles anteriores y posteriores, pero no se ejecutó ni se repitió la
migración. No se hicieron operaciones de escritura en PostgreSQL ni conexiones Render.

| Comando ejecutado en 8C | Aprobadas | Alcance |
|---|---:|---|
| `php tests/esquema_cu15_validation.php` | 53 | Esquema local real y catálogos aislados 8/10 y 9/12; toda la ejecución READ ONLY |
| `php tests/render_sql_local.php` | 3 | SQL real en transacciones READ ONLY, incluidas guardas de destino |
| `php tests/render_preparacion.php` | 24 | Guardas/CLI y PDO simulado, sin red |
| `powershell -NoProfile -File tests/render_preparacion.ps1` | 34 | Regresión de asistentes; entorno, Unicode y diagnósticos sin red |
| **Total ejecutado ahora** | **114** | No se suman repeticiones ni se mezcla con el total histórico de 8A |

Sintaxis correcta en los **146 archivos PHP** actuales y `git diff --check` sin errores.
El inventario se cotejó con Git: 33 rutas existentes, sin duplicados ni archivos
protegidos. La revisión no encontró claves privadas ni coincidencias con credenciales
locales largas en el conjunto propuesto; `.env` y artefactos siguen ignorados.

El comando `php scripts/verificar-cu15-local.php` confirmó realmente nueve tablas,
doce FK e historial válido en `clinica_vida_salud_dev`. El estado 8/10 se probó con
una copia del catálogo sin metadatos de 005, ejecutando el mismo bloque SQL de
validación. No se creó una segunda base física ni se ejecutó DDL para simular C1.
Se rechazaron conteos mezclados, tablas/columnas sustituidas, tipos y nulabilidad
incorrectos, relaciones hacia otra tabla/esquema, acciones FK alteradas, índices
ausentes/incorrectos, CHECK debilitados y pérdida de GiST/trigger/catálogos.

La inspección READ ONLY conserva las mismas filas/huellas: nueve tablas,
historial vacío y datos originales intactos. Los cinco archivos protegidos y `.env`
se cotejaron por SHA-256 antes/después y quedaron idénticos, sin imprimir los valores. No se ejecutaron
`git add`, `git commit`, `git push` ni `git merge`; el índice sigue vacío.

Comandos locales y contrato: [CU15.md](CU15.md#verificación-compatible-de-esquema-cierre-8c).
La migración 005 y los documentos académicos del Ciclo 1 permanecen sin cambios.
Las 1124 verificaciones funcionales son resultados de 8A: este cierre solo ejecutó
las pruebas de esquema y de herramientas pertinentes, sin suites que insertan/eliminan fixtures.

## Inventario de archivos de CU15

Conjunto exacto propuesto para el primer commit: **33 archivos: 21 nuevos y 12
modificados**. Se agregan al inventario original el comando local, sus pruebas y
el SQL compartido modificado. No incluir los cinco archivos previos, `.env`, logs,
sesiones, capturas ni temporales. Mensaje recomendado:
`feat: implementar CU15 de reprogramación de citas con historial`.

| Estado | Archivo |
|---|---|
| Modificado | `app/Repositories/CitaRepository.php` |
| Modificado | `app/Repositories/DisponibilidadRepository.php` |
| Modificado | `app/Services/DisponibilidadService.php` |
| Modificado | `app/Views/citas/index.php` |
| Modificado | `app/Views/citas/show.php` |
| Modificado | `public/assets/css/app.css` |
| Modificado | `public/index.php` |
| Modificado | `scripts/render/verificar-instalacion.sql` |
| Modificado | `tests/HttpCatalogo.php` |
| Modificado | `tests/README.md` |
| Modificado | `tests/auditoria_ciclo1_http.php` |
| Modificado | `tests/citas_browser.mjs` |
| Nuevo | `app/Controllers/ReprogramacionesController.php` |
| Nuevo | `app/Services/ReprogramacionService.php` |
| Nuevo | `app/Views/citas/reprogramar-error.php` |
| Nuevo | `app/Views/citas/reprogramar-revisar.php` |
| Nuevo | `app/Views/citas/reprogramar.php` |
| Nuevo | `database/migrations/005_citas_historial.sql` |
| Nuevo | `docs/ciclo2/CU15.md` |
| Nuevo | `docs/ciclo2/MAPA_DEL_CODIGO.md` |
| Nuevo | `docs/ciclo2/PRUEBAS_CU15.md` |
| Nuevo | `docs/ciclo2/README.md` |
| Nuevo | `scripts/aplicar-migracion-005.php` |
| Nuevo | `scripts/inspeccionar-cu15-local.php` |
| Nuevo | `scripts/verificar-cu15-local.php` |
| Nuevo | `tests/Cu15Local.php` |
| Nuevo | `tests/ReprogramacionPrueba.php` |
| Nuevo | `tests/esquema_cu15_validation.php` |
| Nuevo | `tests/http_reprogramacion.php` |
| Nuevo | `tests/regresion_cu15.php` |
| Nuevo | `tests/reprogramacion_browser.php` |
| Nuevo | `tests/reprogramacion_concurrency.php` |
| Nuevo | `tests/reprogramacion_integration.php` |

Los cinco archivos previos de diagnóstico Render/PDO se conservan y no se incluyen en este inventario.

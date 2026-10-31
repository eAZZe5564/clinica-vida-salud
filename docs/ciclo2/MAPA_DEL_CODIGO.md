# Mapa del código — Ciclo 2

Complementa [el mapa del Ciclo 1](../ciclo1/MAPA_DEL_CODIGO.md), que se conserva sin cambios.

| Archivo | Responsabilidad / UML |
|---|---|
| `app/Controllers/ReprogramacionesController.php` | Controlador CU15: autenticación/roles heredados, CSRF, formulario, revisión, confirmación y PRG |
| `app/Services/ReprogramacionService.php` | Caso de uso CU15: política, bloqueo del médico/cita, revalidación CU12, modificación y auditoría atómicas |
| `app/Repositories/CitaRepository.php` | Lectura/lock de cita, actualización exclusiva del inicio, escritura de historial y revisión del formulario |
| `app/Services/DisponibilidadService.php` | CU12 compartido: permite excluir internamente la propia cita de la ocupación |
| `app/Repositories/DisponibilidadRepository.php` | Consulta parametrizada de ocupaciones; exclusión opcional por ID solo desde servicios internos |
| `app/Views/citas/reprogramar*.php` | Formulario, revisión y errores CU15 sin información clínica innecesaria |
| `app/Views/citas/index.php`, `show.php` | Acción por rol/elegibilidad y confirmación de horario nuevo |
| `public/index.php` | GET/POST `/citas/{id}/reprogramar` y validación de ID/método |
| `public/assets/css/app.css` | Estilos de CU15 acotados a sus clases; controles táctiles y dos columnas en móvil |
| `database/migrations/005_citas_historial.sql` | Entidad de historial con relación N:1 a cita y usuario responsable |
| `scripts/aplicar-migracion-005.php` | Aplicación manual local autorizada, transaccional, sin reejecución automática |
| `scripts/inspeccionar-cu15-local.php`, `tests/Cu15Local.php` | Guarda de host/base/versión y revisión READ ONLY de esquema/huellas |
| `scripts/render/verificar-instalacion.sql` | Contrato compartido: detección estricta 001–004 (8/10), 001–005 o 001–006 (9/12), columnas, CHECK, referencias e índices; solo lectura |
| `scripts/verificar-cu15-local.php` | Entrada local protegida al contrato de esquema, REPEATABLE READ READ ONLY, sin migraciones |
| `tests/esquema_cu15_validation.php` | SQL real y catálogos aislados 001–004/005/006, rechazo de variantes parciales o debilitadas, sin DDL ni modificaciones de registros |
| `tests/ReprogramacionPrueba.php` | Fixtures locales y limpieza limitada a sus IDs; proxy para fallos de persistencia sin DDL |
| `tests/reprogramacion_integration.php` | Reglas, identidad, historial, fallos y rollback |
| `tests/reprogramacion_concurrency.php` | Seis carreras con procesos/sesiones PostgreSQL independientes |
| `tests/http_reprogramacion.php` | Autorización, CSRF, XSS, revisión, PRG y CU12/CU14 por HTTP |
| `tests/reprogramacion_browser.php`, `tests/citas_browser.mjs` | Edge real; recorrido CU15 con datos ficticios en escritorio/móvil |
| `tests/regresion_cu15.php` | Ejecución/reanudación de las 26 suites existentes del Ciclo 1 |

Para diagramas de secuencia: Actor → Router → ReprogramacionesController →
ReprogramacionService → CitaRepository / DisponibilidadService → PostgreSQL.
El repositorio usa la misma conexión PDO durante toda la transacción. CU11 coordina
mediante la misma fila de médico. La vista previa es informativa y no bloquea ni reserva.
`citas_historial` no es una segunda cita: registra un evento de una cita existente.

## CU16

| Archivo | Responsabilidad / UML |
|---|---|
| `app/Controllers/CancelacionesController.php` | Roles administrativos, CSRF, confirmación, errores y PRG |
| `app/Services/CancelacionService.php` | Estado permitido, motivo opcional, revisión del formulario, bloqueos y transacción |
| `app/Repositories/CitaRepository.php` | Actualización condicionada programada → cancelada e inserción del evento con actor |
| `app/Views/citas/cancelar.php`, `cancelar-error.php` | Confirmación explícita o rechazo; escape HTML y motivo administrativo breve |
| `app/Views/citas/index.php`, `show.php` | Acción Cancelar cita por rol/estado, confirmación tras PRG y consulta histórica |
| `public/index.php` | GET/POST `/citas/{id}/cancelar`; ID y método validados |
| `public/assets/css/app.css` | Formulario adaptable y botón de confirmación de cancelación |
| `database/migrations/006_citas_cancelacion.sql` | Amplía historial y CHECK; no cambia citas, GiST, tablas o FK originales |
| `scripts/aplicar-migracion-006.php` | Aplicación manual autorizada y transaccional, solo PostgreSQL 17 de desarrollo local |
| `tests/CancelacionPrueba.php` | Reutiliza fixtures de CU15, entradas de confirmación y lectura de eventos |
| `tests/cancelacion_integration.php` | Reglas, historial, CHECK reales, rollback y CU11–CU15 |
| `tests/cancelacion_concurrency.php` | Ocho carreras con conexiones independientes y bloqueos reales observados |
| `tests/http_cancelacion.php` | Roles, CSRF, XSS, PRG, identidad y CU12/CU14/CU15 por HTTP |
| `tests/cancelacion_browser.php`, `tests/citas_browser.mjs` | Edge real: confirmación y cancelación en escritorio y móvil |

Secuencia CU16: Actor → Router → CancelacionesController → CancelacionService →
CitaRepository → PostgreSQL. Médico FOR SHARE → cita FOR UPDATE → actor FOR SHARE;
estado e historial confirman o revierten juntos. CU12 solo libera la ocupación
después del commit. No hay rutas de asistencia ni reasignación de médico.

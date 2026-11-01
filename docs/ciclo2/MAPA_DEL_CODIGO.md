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
| `scripts/render/verificar-instalacion.sql` | Contratos estrictos 001–004 (8/10), 001–005/006/007/008 (9/12), columnas, CHECK, GiST, función del trigger, referencias e índices; solo lectura |
| `scripts/verificar-cu15-local.php` | Entrada local protegida al contrato de esquema, REPEATABLE READ READ ONLY, sin migraciones |
| `tests/esquema_cu15_validation.php` | SQL real y catálogos aislados 001–004/005/006/007/008, rechazo de variantes parciales o debilitadas, sin DDL ni modificaciones de registros |
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
después del commit. CU16 no implementa asistencia ni reasignación de médico.

## CU17

| Archivo | Responsabilidad / UML |
|---|---|
| `app/Controllers/ConfirmacionesController.php` | Roles, CSRF, pantalla explícita, errores y PRG; distinto del recibo de programación CU13 |
| `app/Services/ConfirmacionService.php` | Solo programada, referencia vigente, usuario activo, bloqueos y transacción estado/historial |
| `app/Repositories/CitaRepository.php` | UPDATE condicionado de estado e INSERT de confirmación sin motivo de cancelación |
| `app/Models/Cita.php` | Catálogo de estados y etiqueta Confirmada para CU14 |
| `app/Views/citas/confirmar.php`, `confirmar-error.php` | Confirmación y rechazo con escape HTML, sin datos clínicos innecesarios |
| `app/Views/citas/index.php`, `show.php` | Acción por rol/estado, badge Confirmada, detalle y aviso de éxito |
| `public/index.php` | GET/POST `/citas/{id}/confirmar`, sin colisión con recibo `/citas/confirmacion/{id}` |
| `public/assets/css/app.css` | Pantalla adaptable, badge Confirmada y acciones del detalle con ajuste de línea |
| `app/Repositories/DisponibilidadRepository.php` | CU12: programada y confirmada ocupan; CU13 revalida mediante esta misma consulta |
| `app/Repositories/HorarioRepository.php`, `app/Services/HorarioService.php`, `app/Views/horarios/status.php` | CU11 protege confirmadas futuras frente a recorte/desactivación y explica el rechazo |
| `app/Services/ReprogramacionService.php`, `CancelacionService.php` | Política de solo programada conservada; mensajes incluyen confirmadas |
| `scripts/inspeccionar-horarios.php` | Conteo diagnóstico de reservas futuras en ambos estados |
| `database/migrations/007_citas_confirmacion.sql` | CHECK de estado, GiST para ambos estados, historial y función del trigger; ninguna tabla nueva |
| `scripts/aplicar-migracion-007.php` | Solo local y autorización explícita; validación 006 → 007 en una transacción |
| `tests/ConfirmacionPrueba.php` | Composición de fixtures CU13–CU16 y guarda de esquema 007 |
| `tests/confirmacion_integration.php` | Identidad, historial, CHECK, trigger, GiST, rollback y CU11–CU16 |
| `tests/http_confirmacion.php` | Roles, CSRF, XSS, PRG, parámetros manipulados y consultas CU12/CU14 |
| `tests/confirmacion_concurrency.php` | Diez carreras, bloqueos reales y reserva GiST incompatible |
| `tests/confirmacion_browser.php`, `tests/citas_browser.mjs` | Edge real en escritorio/móvil; confirmación, filtro CU14 y ocupación CU12 |

UML: añadir ConfirmacionesController y ConfirmacionService; Cita conserva su
identidad y relaciones. CitasHistorial agrega el tipo confirmacion y la transición
programada → confirmada con inicios iguales y motivo NULL. No hay nuevas entidades.
Secuencia: médico FOR SHARE → cita FOR UPDATE → relectura de estado/revisión →
usuario FOR SHARE → UPDATE estado → INSERT historial → COMMIT → 303 al detalle.

## CU18

Las secciones anteriores describen cada entrega. Desde 008 la política compartida
de ocupación es programada/confirmada/presente/atendida/inasistencia; solo cancelada
libera. CU15–CU17 conservan sus transiciones y rechazan los nuevos estados.

| Archivo | Responsabilidad / UML |
|---|---|
| `app/Controllers/AsistenciasController.php` | Pantallas GET/POST, sesión, CSRF, confirmación explícita, errores y PRG |
| `app/Services/AsistenciaService.php` | Autorización por acción/vínculo, estado/tiempo/revisión, transacción y coordinación con CU11–CU17 |
| `app/Repositories/CitaRepository.php` | Consulta con médico vinculado, UPDATE condicionado, evento con actor/momento y consulta administrativa limitada |
| `app/Controllers/CitasController.php` | Integración CU14: acciones elegibles, marcas y trazabilidad solo tras verificar acceso a la cita |
| `app/Models/Cita.php` | Catálogo con Presente e Inasistencia |
| `app/Views/citas/asistencia.php`, `asistencia-error.php`, `asistencia-resumen.php` | Confirmación, rechazo, marcas y traza; llegada distinta de atención, sin texto clínico |
| `app/Views/citas/index.php`, `show.php`, `public/assets/css/app.css` | Acciones, estados, avisos y tabla adaptable con etiquetas en celular |
| `public/index.php` | GET/POST `/citas/{id}/llegada`, `/atencion`, `/inasistencia`; ID y método cerrados |
| `app/Repositories/DisponibilidadRepository.php` | CU12/CU13 conservan ocupación de los cinco estados |
| `app/Repositories/HorarioRepository.php`, `app/Services/HorarioService.php`, `app/Views/horarios/status.php` | CU11 protege la cobertura hasta el fin del intervalo, incluso atención completada antes de terminar |
| `scripts/inspeccionar-horarios.php` | Diagnóstico con la misma política de intervalos vigentes |
| `database/migrations/008_citas_asistencia.sql` | CHECK de estados/eventos/tiempos, ampliación GiST e índice único parcial; nueve tablas/doce FK |
| `scripts/aplicar-migracion-008.php` | Guarda local/versión/007, conflictos y DDL verificado en una sola transacción autorizada |
| `scripts/inspeccionar-cu18-local.php` | Inspección READ ONLY del contrato y conteos de superposiciones/duplicados |
| `tests/AsistenciaPrueba.php` | Composición de fixtures, reloj simulado por constructor y cita cercana al reloj real sin omitir restricciones |
| `tests/asistencia_integration.php`, `http_asistencia.php` | Reglas, identidad, autorización, rollback, GiST, CSRF/XSS y recorrido HTTP con reloj real |
| `tests/asistencia_concurrency.php` | Dieciséis carreras, conexiones independientes y comprobación de bloqueos/rollback |
| `tests/asistencia_browser.php`, `tests/citas_browser.mjs` | Edge real escritorio/móvil, llegada y finalización con médico propio; capturas ignoradas |
| `tests/disponibilidad_integration.php`, `http_horarios_integration.php` | Expectativa de atendida ocupada tras 008 y nuevo mensaje de cobertura, sin eliminar verificaciones |

Entidades: Cita conserva identidad y relaciones; CitasHistorial conserva su esquema
y agrega tipos llegada/atencion/inasistencia. No existe una entidad de atención
clínica ni un historial de diagnósticos. El estado presente es intermedio.

Secuencia: Actor → Router → AsistenciasController → AsistenciaService →
CitaRepository → PostgreSQL. Médico FOR SHARE → cita FOR UPDATE → actor FOR SHARE →
relectura de permiso/estado/hora/revisión → UPDATE estado + INSERT historial →
COMMIT → 303 al detalle. Cualquier error revierte ambas escrituras. El reloj por
defecto es PostgreSQL, no una entrada del navegador. Administrador/recepción
registran llegada o inasistencia; únicamente el médico propio finaliza.

# Mapa del código — Ciclo 2

## CU21 — Reportes administrativos

| Archivo | Responsabilidad |
|---|---|
| `app/Controllers/ReportesCitasController.php` | Sesión/roles, GET y errores seguros |
| `app/Services/ReporteCitaService.php` | Filtros CU14, límites UTC, permisos, agregados independientes de HTML |
| `app/Repositories/ReporteCitaRepository.php` | Dos catálogos y un GROUPING SETS sin multiplicación de citas |
| `app/Views/reportes/citas.php`, `error.php` | Tarjetas, cuatro tablas, filtros y mensajes escapados |
| `public/assets/css/reportes.css` | Tarjetas y tablas responsivas |
| `public/index.php`, `app/Views/layout.php`, `dashboard.php` | Ruta, CSS condicional y navegación por roles |
| `tests/ReportesPrueba.php` | Fixtures ficticios previos, médico multiespecialidad y bordes locales |
| `tests/reportes_integration.php`, `http_reportes.php` | READ ONLY, invariantes, coherencia, roles y XSS |
| `tests/reportes_browser.php`, `citas_browser.mjs` | Edge real escritorio/tablet/móvil |
| `tests/regresion_cu21.php` | Campaña local reproducible y artefactos ignorados |

Sin entidades nuevas: Cita N:1 Medico y Cita N:0..1 Especialidad. No consulta
pacientes ni eventos. Reglas, filtros y secuencia UML: [CU21.md](CU21.md).

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

## CU19

| Archivo | Responsabilidad / UML |
|---|---|
| `app/Controllers/AgendaController.php` | Autenticación/roles heredados, vistas GET, errores 403/404/422 y presentación |
| `app/Services/AgendaService.php` | Alcance por vínculo de médico, validación, límites locales/UTC, semana lunes–domingo, filtros, franjas y navegación |
| `app/Repositories/AgendaRepository.php` | Tres SELECT preparados por profesional: especialidades, horarios activos y citas acotadas al rango; sin datos clínicos |
| `app/Repositories/HorarioRepository.php` (reutilizado, sin editar) | Vínculo de usuario médico y opciones permitidas conforme al alcance de CU14 |
| `app/Views/agenda/index.php`, `error.php` | Vista diaria/semanal, errores controlados, estados con texto, huecos/fuera de atención y enlaces CU14 |
| `public/assets/css/agenda.css` | Siete columnas de escritorio, dos de tablet y una de móvil; clases exclusivas de agenda |
| `public/index.php` | Rutas GET `/agenda`, `/agenda/diaria`, `/agenda/semanal`; demás métodos autorizados: 405 |
| `app/Views/layout.php`, `dashboard.php` | Acceso por roles a agenda, CSS solo en sus pantallas; no cambia autenticación ni CSRF |
| `tests/AgendaPrueba.php` | Fixtures locales con estados CU13–CU18 reales, huellas y PDO vigilado de solo SELECT |
| `tests/agenda_integration.php` | READ ONLY, permisos, filtros, fechas/zonas/límites, consultas agrupadas y reflejo de transiciones existentes |
| `tests/http_agenda.php` | Rutas reales, IDOR, privacidad/XSS, formularios GET, filtros y conservación de las nueve tablas tras login |
| `tests/agenda_browser.php`, `tests/citas_browser.mjs` | Edge real escritorio/tablet/celular, navegación, filtros, CU14 y agenda médica propia |

No hay nuevas entidades ni migraciones. AgendaService proyecta Cita, Medico,
Especialidad y HorarioAtencion existentes. CitasHistorial no se modifica ni se
consulta para construir la agenda. Secuencia/UML y reglas: [CU19.md](CU19.md).
Los períodos sin cita describen la agenda; el cálculo de disponibilidad para
reservar continúa exclusivamente en CU12/CU13.

## CU20

| Archivo | Responsabilidad / UML |
|---|---|
| `app/Controllers/HistorialPacientesController.php` | Sesión/roles, GET de búsqueda, historial y eventos; errores controlados |
| `app/Services/HistorialPacienteService.php` | Paciente accesible, validación CU14, fechas locales/UTC, presentación administrativa y permisos de responsable |
| `app/Repositories/HistorialPacienteRepository.php` | SELECT de pacientes accesibles, citas/eventos paginados, conteo agrupado por página y catálogos por alcance |
| `app/Services/ConsultaCitaService.php` (reutilizado, sin editar) | Deriva alcance médico, permisos y validaciones de filtros existentes |
| `app/Models/BusquedaPaciente.php`, `Paciente.php`, `Cita.php` (reutilizados, sin editar) | Términos de búsqueda, códigos estables y seis estados existentes |
| `app/Views/historial/buscar.php`, `index.php`, `eventos.php`, `error.php` | Selección mínima, historial único descendente, auditoría cronológica y errores, con escape HTML |
| `public/assets/css/historial.css` | Filtros adaptables, tabla móvil etiquetada, eventos en tarjetas |
| `public/index.php` | GET `/historial-citas`, `/pacientes/{id}/historial-citas`, `/pacientes/{id}/historial-citas/{cita}/eventos` |
| `app/Views/pacientes/index.php`, `show.php` | Enlace CU20 desde CU04–CU06, sin ampliar permisos del CRUD |
| `app/Views/layout.php`, `dashboard.php` | Acceso administrativo/médico a la consulta; CSS solo en CU20 |
| `tests/HistorialPacientePrueba.php` | Composición de fixtures CU13–CU19, legado, cuenta de paciente vinculada y reprogramaciones reales |
| `tests/historial_paciente_integration.php` | READ ONLY, aislamiento, paginación, tipos de evento, identidad única, fechas y consistencia CU19 |
| `tests/http_historial_paciente.php` | Rutas reales, métodos, IDOR de paciente/cita/médico, XSS, mínimos datos y huellas posteriores al login |
| `tests/historial_paciente_browser.php`, `tests/citas_browser.mjs` | Edge real escritorio/tablet/celular, CU04, filtros, páginas, eventos y médico propio |

No hay entidad persistente ni auditoría nueva: Paciente 1:N Cita 1:N CitasHistorial;
Cita conserva sus relaciones con Medico/Especialidad; Usuario solo aporta el
responsable autorizado para administrador. No se implementa historia clínica.
Secuencia: actor → controlador → política CU14 → servicio CU20 → repositorio →
SELECT parametrizados con paciente/alcance/filtros/LIMIT → presentación.
CU14 sigue autorizando cualquier enlace de detalle. El rol Paciente permanece
denegado; propuesta de integración futura y reglas RN-H01–20: [CU20.md](CU20.md).

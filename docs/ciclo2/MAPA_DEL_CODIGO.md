# Mapa del código — Ciclo 2

## CU24 — Antecedentes clínicos y resultados de laboratorio

| Archivo | Responsabilidad |
|---|---|
| `app/Services/AccesoClinicoService.php`, `PerfilClinicoService.php` | Autorización y transacción compartidas CU23/CU24, misma política diaria y bloqueos |
| `app/Models/RegistroClinico.php` | Categorías, eventos y lista cerrada de tablas/campos |
| `app/Repositories/RegistroClinicoRepository.php` | Listados, fila actual y revisiones con SQL parametrizado |
| `app/Services/RegistroClinicoService.php` | Validación de fechas/textos/decimales, versión esperada y escritura/auditoría atómicas |
| `app/Controllers/RegistrosClinicosController.php`, `public/index.php` | Rutas privadas, métodos, CSRF y PRG |
| `app/Views/clinico/registros.php`, `registro-formulario.php`, `cabecera.php` | Listados, detalles, formularios y revisiones; navegación común con CU23 y CSS clínico existente |
| `database/migrations/010_antecedentes_laboratorio.sql` | Cuatro tablas/diez FK nuevas, aplicada una sola vez con autorización local; 16 tablas/30 FK |
| `scripts/aplicar-migracion-010.php`, `Cu24Esquema.php`, `cu24/*` | Asistente transaccional y contratos de metadatos públicos; preservación de las doce tablas anteriores |
| `scripts/verificar-cu24-local.php`, verificadores CU15/CU23 y SQL común | Contratos estrictos 8/10, 9/12, 12/20 y 16/30 en READ ONLY |
| `scripts/inspeccionar-cu24-local.php` | Solo inspección anterior a 010; rechaza ahora la base ya migrada |
| `tests/RegistroClinicoPrueba.php`, `registros_clinicos_*.php`, `http_registros_clinicos.php`, `esquema_cu24_validation.php` | Fixtures ficticios, integración, seguridad, privacidad, metadatos e integridad |
| `tests/registros_clinicos_concurrency.php`, `registros_clinicos_browser.mjs` | Procesos PDO independientes y Edge en tres tamaños sin capturas clínicas |
| `tests/regresion_cu24.php`, `regresion_cu23.php` | Campaña completa/anidada con argumentos aislados |
| `docs/ciclo2/CU24.md`, `PRUEBAS_CU24.md` | Diseño real, permisos, auditoría, resultados e inventario |

Las instantáneas históricas son campos tipados, no archivos de imagen ni adjuntos.
Sin cambios de main, Render, migraciones 001–009, cinco archivos protegidos o .env.

## CU23 — Perfil clínico y alergias, implementación local

| Archivo | Responsabilidad |
|---|---|
| `app/Models/PerfilClinico.php` | Enumeraciones clínicas sin valores médicos predeterminados |
| `app/Services/PerfilClinicoService.php`, `PerfilClinicoException.php` | Relación asistencial diaria, permisos por operación, validación, versiones, bloqueos y errores seguros |
| `app/Repositories/PerfilClinicoRepository.php` | SQL parametrizado explícito, perfiles, alergias y revisiones atómicas |
| `app/Controllers/PerfilesClinicosController.php`, `public/index.php` | Rutas privadas, roles, CSRF, métodos y POST-Redirect-GET |
| `app/Views/clinico/*`, `public/assets/css/clinico.css`, `app/Views/layout.php` | Formularios, información desconocida y revisiones responsivas, sin recursos externos |
| `AgendaController.php`, `AgendaRepository.php`, `app/Views/agenda/index.php` | Enlace para pacientes autorizados del día; sin mostrar datos clínicos en la agenda |
| `database/migrations/009_perfil_clinico_alergias.sql` | Perfil, alergias y revisiones; aplicada localmente tras autorización, 12 tablas/20 FK |
| `scripts/aplicar-migracion-009.php`, `Cu23Esquema.php` | Aplicación única y transaccional, guardas de destino y conservación de tablas previas |
| `scripts/cu23/catalogo.sql`, `scripts/render/verificar-instalacion.sql` | Catálogo clínico estricto y compatibilidad de contratos administrativos anteriores |
| `scripts/verificar-cu23-local.php`, `verificar-cu15-local.php` | Verificación READ ONLY del esquema completo |
| `scripts/inspeccionar-cu23-local.php` | Herramienta exclusivamente anterior a 009; ahora rechaza la base ya migrada |
| `tests/PerfilClinicoPrueba.php`, `perfil_clinico_*.php`, `http_perfil_clinico.php`, `esquema_cu23_validation.php` | Fixtures inventados y pruebas de integración, validación, integridad, roles, privacidad, esquema y concurrencia |
| `tests/perfil_clinico_browser.mjs`, `perfil_clinico_browser.php` | Edge real en tres tamaños sin capturas clínicas ni solicitudes a terceros |
| `tests/regresion_cu23.php` | Campaña reproducible, con opción de ejecutar solo CU23 |
| `docs/ciclo2/CU23.md`, `PRUEBAS_CU23.md` | Reglas, límites, resultados actuales e inventario exacto |

No se modifica Render ni main. Los cinco archivos anteriores y .env permanecen intactos.

## CU22 — Exportaciones PDF y CSV

| Archivo | Responsabilidad |
|---|---|
| `app/Exports/ReporteCitaDocumento.php` | Presentación de un resultado CU21, metadatos, filtros y filas CSV; sin SQL ni estadísticas nuevas |
| `app/Exports/ReporteCitaCsv.php` | BOM, punto y coma, CRLF, fputcsv y neutralización de fórmulas solo en texto |
| `app/Exports/ReporteCitaPdf.php`, `ReportePdfLimiteException.php` | Dompdf local, A4, recursos desactivados, temporales privados, páginas y límite de tamaño |
| `app/Views/reportes/pdf.php` | Plantilla PDF local, tablas y metadatos escapados |
| `app/Controllers/ReportesCitasController.php` | CU21 y CU22 llaman al mismo servicio; validación, respuestas y errores seguros |
| `app/Views/reportes/citas.php`, `public/assets/css/reportes.css`, `public/index.php` | Acciones responsivas y rutas GET autorizadas con los cinco filtros |
| `composer.json`, `composer.lock`, `Dockerfile`, `.dockerignore` | Dependencias reproducibles y build desde lock; sin vendor ni secretos locales |
| `tests/exportacion_*_validation.php`, `exportaciones_integration.php`, `http_exportacion_*.php` | Formatos, fórmulas, READ ONLY, agregados idénticos y HTTP real |
| `tests/exportaciones_browser.php`, `citas_browser.mjs`, `leer_pdf_cu22.py` | Descargas Edge, lector PDF real, Unicode, páginas y cifras |
| `tests/exportaciones_docker_validation.php`, `regresion_cu22.php` | Inspección estática Docker y campaña local reproducible |

CU21 permanece como única fuente de cálculos. No hay entidades o migraciones
nuevas. Diseño, seguridad y dependencias: [CU22.md](CU22.md).

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

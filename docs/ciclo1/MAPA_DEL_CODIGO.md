# Mapa del código — Ciclo 1

Auditoría final 5A: [REVISION_FINAL_CICLO1.md](REVISION_FINAL_CICLO1.md), con 838 comprobaciones distintas aprobadas y matriz consolidada de rutas, tablas y permisos. Preparación de infraestructura: [PREPARACION_DESPLIEGUE.md](PREPARACION_DESPLIEGUE.md). La prueba integral está en `tests/auditoria_ciclo1_http.php`.

CU01–CU14 están implementados, con migraciones 001–004 aplicadas localmente. La interfaz de CU13 y la consulta CU14 reutilizan el esquema existente. CU14 incluye listado y detalle con alcance por rol; la consulta dentro del detalle de pacientes también sigue siendo de lectura.

| Caso de uso | Archivos y módulos implementados |
|---|---|
| CU01: Iniciar sesión | `GET/POST /login`; `app/Controllers/AuthController.php`, `app/Services/AuthService.php`, `app/Repositories/UsuarioRepository.php`, `app/Models/Usuario.php`, `app/Views/auth/login.php`. |
| CU02: Cerrar sesión | `POST /logout`; `app/Controllers/AuthController.php`, `app/Middleware/AuthMiddleware.php`, formulario CSRF en `app/Views/layout.php`. |
| CU03: Administrar usuarios y roles | `GET/POST /admin/users`, `/admin/users/new`, `/admin/users/{id}/edit`, `/admin/users/{id}/status`; `app/Controllers/UsuariosController.php`, `app/Services/UsuarioService.php`, `app/Repositories/UsuarioRepository.php`, `app/Repositories/RolRepository.php`, `app/Views/admin/users/`. Primera cuenta: `scripts/crear-administrador.ps1` y `scripts/crear-administrador.php` (solo CLI). |
| CU04: Registrar paciente | `GET /pacientes/nuevo`, `POST /pacientes`; `PacientesController::formulario/guardar`, `PacienteService::validar/guardar`, `PacienteRepository::guardar`, vista `pacientes/form.php`. |
| CU05: Buscar y consultar pacientes | `GET /pacientes`, `GET /pacientes/{id}`; `PacientesController::listar/detalle`, `PacienteService::obtener`, `PacienteRepository::listar/buscar/citas`, vistas `pacientes/index.php` y `show.php`. |
| CU06: Modificar y desactivar pacientes | `GET/POST /pacientes/{id}/editar`, `GET/POST /pacientes/{id}/estado`; controlador, servicio y repositorio de pacientes, vistas `form.php/status.php`. |
| CU07: Registrar médico | `GET /medicos/nuevo`, `POST /medicos`; `MedicosController::formulario/guardar`, `MedicoService::validar/guardar`, `MedicoRepository::guardar/asignar`, `app/Views/medicos/form.php` y `seleccion.php`. |
| CU08: Consultar, modificar y desactivar médicos | `GET /medicos`, `GET /medicos/{id}`, `GET/POST /medicos/{id}/editar`, `GET/POST /medicos/{id}/estado`; controlador/servicio/repositorio de médicos, vistas `index/show/form.php` y `catalogo/estado.php`. |
| CU09: Gestionar especialidades médicas | `GET/POST /especialidades`, `GET /especialidades/nuevo`, `GET /especialidades/{id}`, `GET/POST /especialidades/{id}/editar`, `GET/POST /especialidades/{id}/estado`; `EspecialidadesController`, `EspecialidadService`, `EspecialidadRepository`, vistas `especialidades/index/form/show.php` y `catalogo/estado.php`. |
| CU10: Asignar especialidades a médicos | `GET/POST /medicos/{id}/especialidades` y selección en alta/edición; `MedicosController::asignaciones/asignar`, `MedicoService::asignar`, `MedicoRepository::asignar`, vistas `asignaciones.php/seleccion.php`, tabla `medicos_especialidades`. |
| CU11: Configurar horarios de atención | `GET/POST /horarios`, `GET /horarios/nuevo`, `GET /horarios/{id}`, `GET/POST /horarios/{id}/editar`, `GET/POST /horarios/{id}/estado`; `HorariosController`, `HorarioService`, `HorarioRepository`, `HorarioAtencion`, vistas `horarios/index/form/show/status/tabla.php`, rutas en `RutasHorarios`. Detalle médico integra la consulta semanal. |
| CU12: Consultar disponibilidad médica | `GET /disponibilidad`; `DisponibilidadController::index`, `DisponibilidadService::consultar/validarParametros`, `DisponibilidadRepository`, `HorarioService::tramosParaFecha`, `app/Views/disponibilidad/index.php`. Solo lectura, turnos libres y permisos por médico vinculado. |
| CU13: Programar cita médica | `GET /citas/nueva`, `POST /citas`, `GET /citas/confirmacion/{id}`; `CitasController`, `CitaService::crear/validar`, `CitaRepository`, `CitaException`, `Cita::codigo`; vistas `citas/form/revisar/campos/confirmacion.php`, JS `citas.js`. Revalida `DisponibilidadService` bajo FOR SHARE, usa GiST y especialidad de 004; recibo de sesión con enlaces a consulta CU14. |
| CU14: Consultar citas registradas | `GET /citas`, `GET /citas/{id}`; `CitasController::listar/detalle`, `ConsultaCitaService::validar/listar/detalle/opciones`, `CitaRepository::listar/detalle/especialidadesConsulta`, `Cita::ESTADOS`, vistas `citas/index.php` y `show.php`. Paginación de 20, filtros combinables, agenda propia del médico restringida en SQL. Guía: `implementacion/CONSULTA_CITAS.md`. |

## Componentes actuales

- `public/index.php`: controlador frontal CU01–CU14; delega catálogo a RutasCatalogo, horarios a RutasHorarios, disponibilidad a DisponibilidadController y reservas/consultas a CitasController. Las rutas literales CU13 preceden al detalle numérico CU14.
- `app/Config/bootstrap.php`: carga de entorno, autoload, cookies/sesión y cabeceras de seguridad.
- `app/Config/Database.php`: conexión PDO PostgreSQL parametrizada; verificada contra `clinica_vida_salud_dev`.
- `app/Controllers/HomeController.php`, `app/Views/dashboard.php` y `app/Views/home.php`: inicio público y panel autenticado.
- `app/Views/layout.php`: navegación por rol, mensajes y formulario de salida con token CSRF.
- `app/Middleware/AuthMiddleware.php`: vigencia de sesión, usuario activo, autorización por rol y protección CSRF.
- `public/assets/css/app.css` y `public/assets/js/app.js`: interfaz adaptativa de inicio, acceso y administración.
- `tests/auth_users_integration.php` y `tests/http_auth_integration.php`: pruebas con rollback o eliminación de datos sintéticos.
- `database/migrations/001_esquema_ciclo1.sql`: propuesta de modelo relacional.
- `database/migrations/002_pacientes_cu04_cu06.sql`: sexo, dirección y restricciones de pacientes, aplicada con autorización el 20/10/2026.
- `app/Models/Paciente.php`: código público derivado del id de PostgreSQL.
- `app/Services/PacienteValidacionException.php`: errores por campo.
- `tests/pacientes_validation.php`, `tests/pacientes_integration.php`, `tests/http_pacientes_integration.php`: pruebas de pacientes.
- `app/Config/RutasCatalogo.php`: despacha CU07–CU10 desde public/index.php.
- `app/Controllers/CatalogoController.php`: presentación, sesión y autorización común del nuevo catálogo.
- `app/Services/CatalogoTransaccion.php`, `CatalogoValidacion.php`, `CatalogoValidacionException.php`: transacciones, validación y errores de médicos/especialidades.
- `app/Models/Medico.php`: código MED derivado del id.
- `database/migrations/003_medicos_especialidades.sql`: correo de médicos e integridad normalizada; aplicada con autorización explícita el 20/10/2026.
- `tests/medicos_validation.php`, `tests/medicos_integration.php`, `tests/http_medicos_integration.php` y `tests/HttpCatalogo.php`: pruebas y soporte HTTP de CU07–CU10.
- `app/Config/RutasHorarios.php`, `app/Controllers/HorariosController.php`: rutas y permisos de CU11, incluido alcance por usuario médico vinculado.
- `app/Services/HorarioService.php`, `app/Repositories/HorarioRepository.php`, `app/Models/HorarioAtencion.php`: reglas, SQL, protección de citas, bloqueo, días y duración.
- `tests/horarios_validation.php`, `tests/horarios_integration.php`, `tests/http_horarios_integration.php`, `tests/horarios_concurrency.php`, `tests/HorariosFixture.php`: pruebas y datos ficticios de CU11.
- `app/Services/DisponibilidadService.php` y `app/Repositories/DisponibilidadRepository.php`: intervalos de CU12; no crea citas ni divulga pacientes.
- `tests/disponibilidad_integration.php`, `tests/http_disponibilidad_integration.php`: resultados exactos, casos especiales, permisos y privacidad de CU12.
- `database/migrations/004_citas_especialidad.sql`: referencia histórica a especialidad, aplicada con autorización explícita el 20/10/2026.
- `tests/CitasPrueba.php`, `tests/citas_integration.php`, `tests/http_citas_integration.php`, `tests/citas_concurrency.php`: persistencia, seguridad y tres carreras reales de CU13/CU11.
- `tests/ConsultaCitasPrueba.php`, `tests/consulta_citas_integration.php`, `tests/http_consulta_citas.php`, `tests/consulta_citas_browser.php`: fixtures propios, consulta, privacidad, IDOR, integración CU13 y revisión real de navegador. El runner Edge compartido es `tests/citas_browser.mjs`.

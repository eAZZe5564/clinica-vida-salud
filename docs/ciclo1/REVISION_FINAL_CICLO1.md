# Revisión final del Ciclo 1 — Prompt 5A

Fecha: 20/10/2026. Entrega prevista: 25/10/2026. Proyecto local Clínica Vida Salud, PHP 8.4.22, PostgreSQL 17.10, PDO, HTML/CSS/JavaScript. Base auditada: únicamente `clinica_vida_salud_dev`.

**Dictamen:** CU01–CU14 implementados e integrados para la revisión y preparación del repositorio. **838 comprobaciones distintas aprobadas**: 792 existentes y 46 nuevas de auditoría. El sistema funciona localmente; la publicación de un repositorio remoto, despliegue HTTPS, URL y QR siguen pendientes. No se hizo commit, push, despliegue, conexión externa, creación de bases ni cambio de esquema. La auditoría no sustituye una prueba de penetración ni una validación de producción.

## 1. Estructura y casos de uso

Arquitectura MVC coherente: public/index.php despacha solicitudes; controladores manejan HTTP y permisos; servicios validan reglas/transacciones; repositorios encapsulan SQL PDO; vistas escapan HTML. `app/Config` contiene entorno, PDO, sesión, helpers y despachadores de catálogo/horarios. `public/` contiene únicamente el controlador frontal y assets. SQL ordenado en database/migrations y seed separado; scripts administrativos en CLI; tests y docs fuera de la raíz pública. No hay framework, dependencia Composer, instalador web ni módulo del Ciclo 2.

| Caso | Estado y archivos principales |
|---|---|
| CU01 Iniciar sesión | Implementado: AuthController::formulario/iniciar, AuthService, UsuarioRepository, AuthMiddleware, Views/auth/login.php |
| CU02 Cerrar sesión | Implementado: AuthController::cerrar, AuthMiddleware::invalidarSesion, formulario en Views/layout.php |
| CU03 Usuarios y roles | Implementado: UsuariosController, UsuarioService, UsuarioRepository, RolRepository, Views/admin/users; primera cuenta en scripts/crear-administrador.ps1/.php |
| CU04 Registrar paciente | Implementado: PacientesController::formulario/guardar, PacienteService, PacienteRepository, Views/pacientes/form.php |
| CU05 Consultar pacientes | Implementado: PacientesController::listar/detalle, PacienteRepository::listar/buscar/citas, Views/pacientes/index.php/show.php |
| CU06 Modificar/desactivar pacientes | Implementado: PacientesController::guardar/confirmarEstado/cambiarEstado, PacienteService, Views/pacientes/form.php/status.php |
| CU07 Registrar médico | Implementado: MedicosController::formulario/guardar, MedicoService, MedicoRepository, Views/medicos/form.php/seleccion.php |
| CU08 Consultar/modificar/desactivar médico | Implementado: MedicosController, MedicoService, MedicoRepository, Views/medicos/index.php/show.php/form.php y catalogo/estado.php |
| CU09 Especialidades | Implementado: EspecialidadesController, EspecialidadService, EspecialidadRepository, Views/especialidades |
| CU10 Asignar especialidades | Implementado: MedicosController::asignaciones/asignar, MedicoService::asignar, MedicoRepository::asignar, Views/medicos/asignaciones.php/seleccion.php |
| CU11 Horarios | Implementado: RutasHorarios, HorariosController, HorarioService, HorarioRepository, HorarioAtencion, Views/horarios |
| CU12 Disponibilidad | Implementado: DisponibilidadController, DisponibilidadService/Repository, HorarioService::tramosParaFecha, Views/disponibilidad/index.php |
| CU13 Programar cita | Implementado: CitasController::formulario/crear/confirmacion, CitaService/Exception, CitaRepository, Cita, Views/citas/form.php/revisar.php/campos.php/confirmacion.php, citas.js |
| CU14 Consultar citas | Implementado: CitasController::listar/detalle, ConsultaCitaService, CitaRepository, Views/citas/index.php/show.php |

Los nombres de clases anteriores se ubican en sus carpetas app/Controllers, app/Services, app/Repositories y app/Models. Mapa ampliado: [MAPA_DEL_CODIGO.md](MAPA_DEL_CODIGO.md). Las guías por módulo están en [implementacion/README.md](implementacion/README.md). Los documentos de entregas previas conservan su contexto histórico; este informe refleja el cierre funcional actual.

## 2. Rutas HTTP principales

| Ruta | Métodos | Alcance |
|---|---|---|
| `/`, `/login` | GET; login también POST con CSRF | Público |
| `/dashboard`, `/logout` | GET / POST con CSRF | Sesión activa |
| `/admin/users`, `/admin/users/new`, `/admin/users/{id}/edit`, `/admin/users/{id}/status` | Listado GET, alta/edición POST, formularios GET, estado POST | Administrador |
| `/pacientes`, `/pacientes/nuevo`, `/pacientes/{id}`, `/pacientes/{id}/editar`, `/pacientes/{id}/estado` | Consulta/formularios GET, alta/cambios POST con CSRF | Administrador/recepción |
| `/medicos`, `/medicos/nuevo`, `/medicos/{id}`, `/medicos/{id}/editar`, `/medicos/{id}/estado` | Consulta GET, cambios POST con CSRF | Administración; recepción solo listado/detalle |
| `/medicos/{id}/especialidades` | GET/POST con CSRF para escritura | Administrador |
| `/especialidades`, `/especialidades/nuevo`, `/especialidades/{id}`, `/especialidades/{id}/editar`, `/especialidades/{id}/estado` | Consulta GET, cambios POST con CSRF | Administrador |
| `/horarios`, `/horarios/nuevo`, `/horarios/{id}`, `/horarios/{id}/editar`, `/horarios/{id}/estado` | Consulta GET, cambios POST con CSRF | Administrador configura; recepción consulta; médico consulta lo propio |
| `/disponibilidad` | GET | Administración/recepción; médico solo lo propio |
| `/citas/nueva` | GET | Administrador/recepción |
| `/citas` | GET listado CU14 / POST reserva CU13 con CSRF | Lectura autorizada; escritura administrativa |
| `/citas/confirmacion/{id}` | GET | Administrador/recepción; recibo restringido a sesión/creador |
| `/citas/{id}` | GET | Administración/recepción o médico asignado |

Las rutas literales nueva/confirmacion preceden al ID numérico; las pruebas de CU13/CU14 lo verifican. Los GET no ejecutan cambios clínicos; los cambios de estado de catálogos requieren POST y CSRF. No se implementan rutas para cancelar, reprogramar, marcar atendida, exportar ni consultar historia clínica.

## 3. Tablas y relaciones verificadas

| Tabla | Relaciones y reglas relevantes |
|---|---|
| roles | Nombres fijos administrador/recepcionista/medico/paciente |
| usuarios | FK rol_id → roles; correo único normalizado, hash, activo y marcas de tiempo |
| pacientes | usuario_id opcional y único → usuarios; CI único/normalizado; datos de recepción y desactivación lógica |
| medicos | usuario_id opcional y único → usuarios; registro profesional único normalizado; activo |
| especialidades | Nombre único normalizado; activo |
| medicos_especialidades | PK compuesta medico_id/especialidad_id; FK a ambos catálogos; relación muchos a muchos |
| horarios_atencion | FK medico_id; día ISO 1–7, inicio < fin, unicidad médico/día/inicio; servicio valida grilla y cruces |
| citas | FK paciente_id, medico_id, especialidad_id nullable y creada_por_usuario_id nullable; inicio/creación TIMESTAMPTZ, duración CHECK=30, estado CHECK; GiST impide superposición de programadas |

Se verificaron columnas de 002/003/004, índices normalizados, FK de especialidad, trigger de reserva y exclusión GiST. Los estados reales son programada/cancelada/atendida, aunque solo se crea programada en CU13 y no se exponen transiciones. La especialidad NULL histórica se presenta Sin registrar. Las bajas de catálogos son lógicas y las FK conservan relaciones.

Migraciones presentes y aplicadas: 001 esquema/roles estructurales/trigger/GiST; 002 pacientes; 003 médicos/especialidades; 004 especialidad de cita. El seed separado carga cuatro roles y tres especialidades, sin usuarios o contraseñas. No se volvió a ejecutar ninguno.

**Preservación:** al comenzar y terminar coinciden conteos y huellas agregadas del contenido de las ocho tablas. Registros finales: roles 4; usuarios 2; pacientes 3; médicos 2; especialidades 4; asociaciones médico/especialidad 3; horarios 2; citas 1. No se publican filas ni datos identificatorios como evidencia. Las secuencias pueden avanzar por pruebas, sin significar pérdida de registros.

## 4. Permisos de los cuatro roles

| Operación | Administrador | Recepcionista | Médico | Paciente |
|---|---|---|---|---|
| Login/logout/panel | Sí | Sí | Sí | Sí |
| Usuarios/roles | Gestiona | No | No | No |
| Pacientes | Gestiona | Gestiona | No | No |
| Médicos | Gestiona | Consulta | No catálogo general | No |
| Especialidades/asignaciones | Gestiona | No administración | No administración | No |
| Horarios | Configura/consulta | Consulta | Consulta propios | No |
| Disponibilidad | Consulta | Consulta | Propia | No |
| Programar cita/recibo | Sí | Sí | No | No |
| Listar/detallar citas | Todas | Todas administrativas | Solo asignadas | No |
| Motivo breve en CU14 | Sí | Sí | No | No |
| Cuenta creadora en CU14 | Sí | No | No | No |

Una cuenta médica sin vínculo no recibe agendas ajenas; se restringen consultas SQL y acceso por ID. El detalle médico no selecciona CI, contactos, dirección, motivo o datos de la cuenta creadora. Los controles del servidor no dependen de los menús. La política actual es por roles fijos, sin permisos granulares adicionales ni autogestión de paciente.

## 5. Pruebas ejecutadas y evidencia

| Suite | Aprobadas |
|---|---:|
| pacientes_validation / pacientes_integration / http_pacientes_integration | 15 / 17 / 65 |
| http_auth_integration | 34 |
| medicos_validation / medicos_integration / http_medicos_integration | 25 / 40 / 108 |
| horarios_validation / horarios_integration / http_horarios_integration / horarios_concurrency | 17 / 33 / 65 / 4 |
| disponibilidad_integration / http_disponibilidad_integration | 48 / 44 |
| citas_integration / http_citas_integration / citas_concurrency | 41 / 35 / 13 |
| http_citas_formulario / citas_browser | 37 / 15 |
| consulta_citas_integration / http_consulta_citas / consulta_citas_browser | 55 / 65 / 16 |
| **Batería existente completa** | **792** |
| **auditoria_ciclo1_http (nueva)** | **46** |
| **Total de comprobaciones distintas** | **838** |

La batería existente se ejecutó al comenzar y se repitió después de las correcciones; no se duplican sus aserciones en el total. La nueva suite verifica cookies/cabeceras, raíz pública, login y renovación de sesión, identidad/navegación de CU03, mensajes malformados, creación HTTP de paciente/especialidad/médico, asignación, horario, disponibilidad, revisión/reserva/recibo, desaparición exacta del turno, listado/detalle CU14 y bloqueo de ocho rutas tras logout. También simula APP_ENV=production en otro proceso local y comprueba la cookie Secure sin editar .env ni conectar a otra base.

Las pruebas de integración usan rollback; las HTTP y de concurrencia retiran exclusivamente sus fixtures. Se mantuvieron las carreras reales de doble reserva y los dos órdenes de reserva/cambio de horario. La suite histórica auth_users_integration.php exige usuarios vacíos: no se ejecutó ni se vaciaron tablas; la autenticación se cubre con http_auth_integration y el nuevo recorrido.

Sintaxis: **116 archivos PHP** y tres scripts JS/MJS correctos. Las suites Edge reales se ejecutaron en escritorio y móvil: 15 comprobaciones del formulario CU13 y 16 de CU14, incluidas dentro de las 792. Capturas ficticias en tests/artifacts/citas y tests/artifacts/consulta-citas, ignoradas por Git. No se inventaron capturas, diagramas, resultados ni pruebas en otro navegador/proveedor.

## 6. Seguridad y problemas corregidos

| Hallazgo comprobado | Gravedad | Evidencia previa y corrección |
|---|---|---|
| `mensaje` como arreglo provocaba 500 en el layout | Baja: robustez/disponibilidad de esa solicitud | GET /login?mensaje[]=prueba pasó de 500 a 200; se valida que la clave del mensaje sea string antes de buscarla |
| CU03 confundía la cuenta editada con el actor de navegación, o perdía el menú autenticado | Baja: presentación e identidad de sesión | Listado/alta no ofrecían salida ni enlaces administrativos; edición tomaba correo/rol de la cuenta objetivo. UsuariosController pasa usuarioSesion y layout lo prioriza; backend conserva permisos del actor |
| README incompleto para reproducir instalación y con referencias de etapas anteriores | Riesgo operativo medio | Se consolidaron 001→002→003→004→seed, mbstring, versiones validadas, copia de .env sin sobrescritura, primera cuenta y alcance actual; no se ensayó creación de una base |

Controles revisados: password_hash/password_verify (hash irreversible, no cifrado reversible); consultas preparadas y fragmentos SQL cerrados del servidor; sesiones estrictas, HttpOnly/SameSite, Secure en producción y renovación de ID; CSRF aleatorio comparado con hash_equals; autorización por rol revalidada; e() para HTML; alcance propio SQL/IDOR; GiST y bloqueos; errores sin SQL o secretos; raíz web limitada a public. La prueba de exposición devuelve 404 para .env, .git/config, app, migraciones, scripts, tests y ruta de traversal ensayada.

No se encontró un secreto real en los archivos elegibles para Git: se buscó coincidencia con la contraseña local sin imprimirla y patrones de claves privadas/tokens/URLs con contraseña, y se revisaron correos y seed. Las contraseñas ficticias de tests no son cuentas predeterminadas de aplicación. El hash fijo de comparación de AuthService solo sirve para verificación de tiempo de credenciales inexistentes; no es un usuario ni una credencial operativa. Esta revisión no garantiza detectar todos los formatos posibles de secreto.

## 7. Reproducibilidad y límites

README contiene requisitos PHP/PDO_PGSQL/mbstring/sesiones, PostgreSQL/btree_gist, PATH, php.ini, creación de una base nueva autorizada, cuatro migraciones en orden, seed, .env, asistente privado de administrador, arranque y URLs. Los comandos SQL ahora piden contraseña interactivamente, sin incorporarla a archivos o argumentos. No volver a aplicarlos a la base actual. No hay registro automático de migraciones: el operador debe saber cuáles se aplicaron.

La instalación desde cero está **revisada documentalmente, no ejecutada**. No se creó una base aislada porque requeriría autorización adicional y no era necesaria para preservar/verificar la instalación existente. Tampoco se probó otro sistema operativo o hosting. Las pruebas Edge necesitan Node con WebSocket nativo y Edge Windows; esas herramientas son de pruebas, no dependencias para ejecutar la aplicación.

## 8. Preparación para GitHub

Estado observado: rama **master**, sin commits, sin remotos, índice vacío y archivos del proyecto todavía untracked. Se ejecutaron git status, git branch --show-current, git remote -v, git status --ignored y listado de archivos elegibles; no se añadió nada al índice. `.env` y tests/artifacts están ignorados. No hay sesiones, logs privados, dumps ni claves privadas elegibles detectados. Migraciones 001–004, seed, scripts CLI, .env.example sin contraseña y documentación sí están disponibles para versionar.

Recomendación concreta para la siguiente etapa autorizada: revisar el inventario y diff de archivos, acordar rama principal (por ejemplo main), crear el remoto con la visibilidad acordada, preparar el primer commit local, volver a revisar el índice por secretos y luego publicar. No añadir dump ni datos personales. El repositorio debe contener código y catálogos seguros, no el contenido de desarrollo. Mensaje propuesto:

```text
feat: implementar CU01–CU14 de Clínica Vida Salud (Ciclo 1)
```

No se ejecutaron esos cambios de Git ni se creó el repositorio remoto.

## 9. Preparación para despliegue

Informe específico: [PREPARACION_DESPLIEGUE.md](PREPARACION_DESPLIEGUE.md). Se necesita un destino que ejecute PHP 8.4 con extensiones, PostgreSQL 17/btree_gist, servidor HTTP de producción con raíz public, enrutamiento al index, entorno privado, HTTPS, sesiones privadas, TLS de PostgreSQL verificado y cuenta de aplicación con permisos acotados. `php -S` no debe usarse para exposición pública. El DSN no define una política TLS y no consume DATABASE_URL; se explica cómo evaluar PGSSLMODE/PGSSLROOTCERT y cuándo sería necesaria una adaptación.

Pendientes antes de abrir acceso público: configurar/verificar infraestructura, límites de intentos de login en servidor/proxy (la aplicación no los implementa), migraciones y primera cuenta del destino, respaldo y prueba funcional/concurrente de ese despliegue. Son requisitos pendientes de producción, no vulnerabilidades demostradas de un proveedor actualmente configurado. No se configuró Render ni otro servicio.

## 10. Riesgos y pendientes antes del 25 de octubre

| Pendiente | Impacto y acción |
|---|---|
| Repositorio GitHub todavía no publicado | La exigencia de repositorio remoto sigue pendiente; revisar y publicar en la siguiente etapa autorizada |
| Alojamiento, URL HTTPS y QR | Riesgo alto para la entrega si se exige acceso público: todavía no existen como entregables; elegir destino compatible y luego generar/probar QR |
| Seguridad/configuración del destino | No exponer el servidor de desarrollo ni trasladar credenciales locales; validar TLS/HTTPS/sesiones, permisos y límites de login antes de abrir acceso |
| Instalación nueva no ensayada | Riesgo medio de reproducibilidad; la secuencia está documentada, pero su ensayo requiere una base aislada autorizada o la instalación destino autorizada |
| Documentación académica y UML | Pendiente con el usuario; este informe aporta clases, rutas, relaciones y evidencia real, sin presentarlo como documentación académica definitiva |
| Ensayo conjunto de presentación | Preparar datos ficticios consentidos, revisar cuentas/horarios/fechas y recorrido en los dispositivos reales; no dejar fixtures de auditoría permanentes |

No se detectaron bloqueos funcionales locales de CU01–CU14 en las ejecuciones finales. Estar listo para preparar GitHub no equivale a tener ya lista una publicación pública. Esta auditoría termina aquí, sin avanzar al Ciclo 2.

## 11. Archivos de esta auditoría y ejecución local

Código corregido: app/Controllers/UsuariosController.php y app/Views/layout.php. Pruebas: nuevo tests/auditoria_ciclo1_http.php y apoyo tests/HttpCatalogo.php (cabeceras y entorno opcional del proceso, manteniendo el comportamiento previo por defecto). Documentación: README.md; tests/README.md; docs/ciclo1/MAPA_DEL_CODIGO.md; DECISIONES_TECNICAS.md y analisis/DECISIONES_TECNICAS.md; implementacion/README.md y AUTENTICACION_USUARIOS.md; pruebas/README.md; nuevos REVISION_FINAL_CICLO1.md y PREPARACION_DESPLIEGUE.md. .env, .env.example, .gitignore y migraciones no se modificaron.

Con el PHP/PostgreSQL locales configurados, desde PowerShell:

```powershell
Set-Location 'C:\Users\vacaa\Desktop\clinica-vida-salud'
php -S 127.0.0.1:8000 -t public
```

Si ya está iniciado, conservarlo y abrir http://127.0.0.1:8000/login. Panel `/dashboard`, disponibilidad `/disponibilidad`, reserva `/citas/nueva`, consulta `/citas`. Prueba nueva: `php tests/auditoria_ciclo1_http.php`; batería completa en tests/README.md. El servidor existente del puerto 8000 se conserva; se cierran solo los servidores temporales propios.

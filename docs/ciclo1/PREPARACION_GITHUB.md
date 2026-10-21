# Preparación del primer commit — Prompt 5B

Proyecto: Clínica Vida Salud, independiente de Panadería Santiago. Preparación original: main, archivos y guías sin cambiar CU01–CU14. Actualización: el usuario autorizó el primer commit local y eligió un repositorio público; no autorizó push ni definió la URL remota. El resultado de la auditoría 5A de 838 comprobaciones se mantiene como evidencia previa.

## Estado preparado

- Rama principal local: **main**, renombrada desde master, conservando los archivos.
- Primer commit local autorizado; sin origin configurado. La URL real de GitHub aún debe proporcionarla el usuario. La visibilidad elegida es **pública** para consulta del código mediante URL/QR.
- Identidad existente: **eAZZe5564**, con correo noreply de su cuenta GitHub. Se conserva la configuración encontrada; no se inventaron datos ni se modificó la configuración global.
- Mensaje propuesto: `feat: implementar CU01-CU14 de Clínica Vida Salud (Ciclo 1)`.
- Autorización del primer commit recibida según la etapa C. El primer push requiere otra autorización según la etapa D y continúa pendiente.

Las referencias internas que puedan mantener las herramientas locales no son la rama del proyecto ni su primer commit. La publicación prevista será únicamente de main, nunca mediante push --mirror o publicación indiscriminada de referencias.

## Archivos que se preparan

Código MVC completo en app; punto de entrada y assets en public; migraciones 001–004 y seed de roles/especialidades; scripts CLI de instalación/inspección; pruebas y fixtures ficticios; documentación, README, .gitignore y .env.example sin contraseña. Los .gitkeep existentes se conservan: son marcadores vacíos sin datos sensibles y no impiden reproducir el proyecto.

## Exclusiones y revisión

.env y sus variantes privadas, capturas/artefactos de pruebas, logs, sesiones, temporales, respaldos y claves privadas permanecen excluidos. Se conserva la excepción pública .env.example. La configuración privada .vscode/settings.json queda ignorada; solo se mantiene la posible lista compartible de extensiones del editor. No se borran esos archivos del disco.

La revisión de secretos se realiza sobre el contenido exacto del índice: coincidencia con credenciales locales sin imprimirlas, patrones de tokens/claves/URLs con contraseña, archivos inesperados, binarios y correos/datos que requieran revisión. Los tests usan datos y contraseñas ficticios; el seed no crea cuentas. No se exporta ni consulta el contenido de la base para incluirlo en Git.

La guía de [trabajo entre dos integrantes](GITHUB_TRABAJO_EN_EQUIPO.md) explica creación del remoto vacío y público, invitación del compañero, acceso de la ingeniera al código, clonación/instalación, ramas, PR, actualización segura de main y comandos futuros de conexión/publicación con la URL real.

## Revisión del despliegue

[PREPARACION_DESPLIEGUE.md](PREPARACION_DESPLIEGUE.md) contiene PHP 8.4 y PostgreSQL 17, extensiones y requisitos de infraestructura. .env.example incorpora únicamente comentarios opcionales PGSSLMODE y PGSSLROOTCERT para TLS remoto; no se activan ni cambian la configuración local. No hay proveedor elegido, producción configurada, migraciones externas o secretos en GitHub.


## Resultado de la revisión del índice

Preparados **155 archivos como altas** para el primer commit, sin cambios funcionales. Distribución comprobada:

| Grupo | Archivos |
|---|---:|
| Raíz (.gitignore, .env.example, README.md) | 3 |
| app | 85 |
| database (cuatro migraciones y seed) | 5 |
| docs | 20 |
| public | 5 |
| scripts | 8 |
| tests | 29 |
| **Total** | **155** |

Se inspeccionaron los blobs exactos del índice, no solo las copias de trabajo: **sin secretos reales detectados, archivos privados indebidos, binarios o correos pendientes de revisión**. git diff --cached --check no reportó errores. El resultado expresa el alcance de la revisión realizada, no una garantía de detección de cualquier secreto posible.

Los ocho archivos presentes que quedan ignorados son .env y siete capturas bajo tests/artifacts. También se verifican reglas para sesiones, logs, temporales, respaldos, claves y configuración local del editor, aunque no haya archivos actuales de todos esos tipos. Las migraciones/seed y .env.example sí quedan incluidos.

La comparación de huellas de archivos contra el inicio de 5B confirma: creadas las dos guías de GitHub; modificados únicamente .gitignore, .env.example, README y PREPARACION_DESPLIEGUE. Las guías se actualizaron para reflejar la autorización del commit y la visibilidad pública elegida. No se eliminaron archivos ni se modificaron PHP, HTML de vistas, CSS, JS, SQL, scripts o pruebas. Las 838 comprobaciones corresponden a la auditoría 5A, no se ejecutan nuevamente para este commit documental. La comprobación final de privacidad no exporta ni modifica PostgreSQL. Commit local **autorizado**, sin origin y sin push.

## Inventario exacto para el primer commit

Esta lista procede de git diff --cached --name-only e incluye este documento:

```text
.env.example
.gitignore
README.md
app/Config/Database.php
app/Config/Env.php
app/Config/RutasCatalogo.php
app/Config/RutasHorarios.php
app/Config/bootstrap.php
app/Config/helpers.php
app/Controllers/AuthController.php
app/Controllers/CatalogoController.php
app/Controllers/CitasController.php
app/Controllers/DisponibilidadController.php
app/Controllers/EspecialidadesController.php
app/Controllers/HomeController.php
app/Controllers/HorariosController.php
app/Controllers/MedicosController.php
app/Controllers/PacientesController.php
app/Controllers/UsuariosController.php
app/Middleware/.gitkeep
app/Middleware/AuthMiddleware.php
app/Models/.gitkeep
app/Models/Cita.php
app/Models/HorarioAtencion.php
app/Models/Medico.php
app/Models/Paciente.php
app/Models/Usuario.php
app/Repositories/.gitkeep
app/Repositories/CitaRepository.php
app/Repositories/DisponibilidadRepository.php
app/Repositories/EspecialidadRepository.php
app/Repositories/HorarioRepository.php
app/Repositories/MedicoRepository.php
app/Repositories/PacienteRepository.php
app/Repositories/RolRepository.php
app/Repositories/UsuarioRepository.php
app/Services/.gitkeep
app/Services/AuthService.php
app/Services/CatalogoTransaccion.php
app/Services/CatalogoValidacion.php
app/Services/CatalogoValidacionException.php
app/Services/CitaException.php
app/Services/CitaService.php
app/Services/ConsultaCitaService.php
app/Services/DisponibilidadService.php
app/Services/EspecialidadService.php
app/Services/HorarioService.php
app/Services/MedicoService.php
app/Services/PacienteService.php
app/Services/PacienteValidacionException.php
app/Services/UsuarioService.php
app/Views/admin/users/form.php
app/Views/admin/users/index.php
app/Views/auth/login.php
app/Views/catalogo/estado.php
app/Views/catalogo/paginacion.php
app/Views/citas/campos.php
app/Views/citas/confirmacion.php
app/Views/citas/form.php
app/Views/citas/index.php
app/Views/citas/revisar.php
app/Views/citas/show.php
app/Views/dashboard.php
app/Views/disponibilidad/index.php
app/Views/errors/403.php
app/Views/errors/404.php
app/Views/errors/405.php
app/Views/errors/419.php
app/Views/errors/500.php
app/Views/especialidades/form.php
app/Views/especialidades/index.php
app/Views/especialidades/show.php
app/Views/home.php
app/Views/horarios/form.php
app/Views/horarios/index.php
app/Views/horarios/show.php
app/Views/horarios/status.php
app/Views/horarios/tabla.php
app/Views/layout.php
app/Views/medicos/asignaciones.php
app/Views/medicos/form.php
app/Views/medicos/index.php
app/Views/medicos/seleccion.php
app/Views/medicos/show.php
app/Views/pacientes/form.php
app/Views/pacientes/index.php
app/Views/pacientes/show.php
app/Views/pacientes/status.php
database/migrations/001_esquema_ciclo1.sql
database/migrations/002_pacientes_cu04_cu06.sql
database/migrations/003_medicos_especialidades.sql
database/migrations/004_citas_especialidad.sql
database/seeds/001_datos_base.sql
docs/ciclo1/DECISIONES_TECNICAS.md
docs/ciclo1/GITHUB_TRABAJO_EN_EQUIPO.md
docs/ciclo1/MAPA_DEL_CODIGO.md
docs/ciclo1/PLANTILLA_DOCUMENTACION.md
docs/ciclo1/PREPARACION_DESPLIEGUE.md
docs/ciclo1/PREPARACION_GITHUB.md
docs/ciclo1/REVISION_FINAL_CICLO1.md
docs/ciclo1/analisis/DECISIONES_TECNICAS.md
docs/ciclo1/casos_de_uso/README.md
docs/ciclo1/diseno/README.md
docs/ciclo1/implementacion/AUTENTICACION_USUARIOS.md
docs/ciclo1/implementacion/CONSULTA_CITAS.md
docs/ciclo1/implementacion/DISPONIBILIDAD_MEDICA.md
docs/ciclo1/implementacion/HORARIOS_MEDICOS.md
docs/ciclo1/implementacion/MEDICOS_ESPECIALIDADES.md
docs/ciclo1/implementacion/PACIENTES.md
docs/ciclo1/implementacion/PROGRAMACION_CITAS.md
docs/ciclo1/implementacion/README.md
docs/ciclo1/pruebas/README.md
docs/ciclo1/requisitos/README.md
public/assets/css/app.css
public/assets/img/.gitkeep
public/assets/js/app.js
public/assets/js/citas.js
public/index.php
scripts/aplicar-migracion-citas.php
scripts/aplicar-migracion-medicos.php
scripts/aplicar-migracion-pacientes.php
scripts/crear-administrador.php
scripts/crear-administrador.ps1
scripts/inspeccionar-horarios.php
scripts/inspeccionar-medicos.php
scripts/inspeccionar-pacientes.php
tests/CitasPrueba.php
tests/ConsultaCitasPrueba.php
tests/HorariosFixture.php
tests/HttpCatalogo.php
tests/README.md
tests/auditoria_ciclo1_http.php
tests/auth_users_integration.php
tests/citas_browser.mjs
tests/citas_browser.php
tests/citas_concurrency.php
tests/citas_integration.php
tests/consulta_citas_browser.php
tests/consulta_citas_integration.php
tests/disponibilidad_integration.php
tests/horarios_concurrency.php
tests/horarios_integration.php
tests/horarios_validation.php
tests/http_auth_integration.php
tests/http_citas_formulario.php
tests/http_citas_integration.php
tests/http_consulta_citas.php
tests/http_disponibilidad_integration.php
tests/http_horarios_integration.php
tests/http_medicos_integration.php
tests/http_pacientes_integration.php
tests/medicos_integration.php
tests/medicos_validation.php
tests/pacientes_integration.php
tests/pacientes_validation.php
```

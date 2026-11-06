# Inventario exacto: main → CU26

Revisión: 2026-11-06. Solo diferencias entre commits; no incluye los cinco cambios anteriores ni los documentos nuevos de preparación.

- Base main local/remota: `1afbad7b4a68b01f9999f54c1c6d9fe8ce40bf37`.
- Candidato: `23b1175fe865021a102a890c0f5b267eabad5588`.
- Resultado: **245 archivos; 216 A (nuevos), 29 M (modificados), cero eliminados**.
- Ancestría lineal acumulativa comprobada; este inventario no autoriza merge ni push.

## Destino de los cambios

| Grupo | Cantidad | Destino |
|---|---:|---|
| app / public | 88 / 9 | 97 archivos de aplicación/estáticos que cambiarían dentro de la imagen |
| Dockerfile / .dockerignore / composer.json / composer.lock | 4 | Construcción; vendor generado desde lock |
| database/migrations | 6 | SQL 005–010, ejecución manual autorizada fuera de la imagen |
| tests / docs / scripts / README | 89 / 26 / 22 / 1 | 138 archivos de soporte en Git, excluidos del runtime por contexto/COPY |

Los archivos Docker/Apache restantes ya existentes se conservan aunque no aparezcan en este diff.
No construir desde un árbol con cambios privados: usar el commit exacto exportado o checkout limpio.

## Archivos

| Estado | Ruta |
|---|---|
| M | `.dockerignore` |
| M | `Dockerfile` |
| M | `README.md` |
| A | `app/Config/RutasPortal.php` |
| A | `app/Controllers/AgendaController.php` |
| A | `app/Controllers/AsistenciasController.php` |
| A | `app/Controllers/CancelacionesController.php` |
| M | `app/Controllers/CitasController.php` |
| A | `app/Controllers/ConfirmacionesController.php` |
| A | `app/Controllers/HistorialPacientesController.php` |
| A | `app/Controllers/PerfilesClinicosController.php` |
| A | `app/Controllers/PortalPacienteController.php` |
| A | `app/Controllers/RegistrosClinicosController.php` |
| A | `app/Controllers/ReportesCitasController.php` |
| A | `app/Controllers/ReprogramacionesController.php` |
| A | `app/Controllers/VinculacionesController.php` |
| A | `app/Exports/ReporteCitaCsv.php` |
| A | `app/Exports/ReporteCitaDocumento.php` |
| A | `app/Exports/ReporteCitaPdf.php` |
| A | `app/Exports/ReportePdfLimiteException.php` |
| M | `app/Models/Cita.php` |
| A | `app/Models/PerfilClinico.php` |
| A | `app/Models/RegistroClinico.php` |
| A | `app/Repositories/AgendaRepository.php` |
| M | `app/Repositories/CitaRepository.php` |
| M | `app/Repositories/DisponibilidadRepository.php` |
| A | `app/Repositories/HistorialPacienteRepository.php` |
| M | `app/Repositories/HorarioRepository.php` |
| A | `app/Repositories/PerfilClinicoRepository.php` |
| A | `app/Repositories/PortalPacienteRepository.php` |
| A | `app/Repositories/RegistroClinicoRepository.php` |
| A | `app/Repositories/ReporteCitaRepository.php` |
| A | `app/Services/AccesoCitaPaciente.php` |
| A | `app/Services/AccesoClinicoService.php` |
| A | `app/Services/AgendaService.php` |
| A | `app/Services/AsistenciaService.php` |
| A | `app/Services/CancelacionService.php` |
| A | `app/Services/CatalogoVoz.php` |
| M | `app/Services/CitaService.php` |
| A | `app/Services/ConfirmacionService.php` |
| M | `app/Services/ConsultaCitaService.php` |
| M | `app/Services/DisponibilidadService.php` |
| A | `app/Services/FiltrosCita.php` |
| A | `app/Services/HistorialPacienteService.php` |
| M | `app/Services/HorarioService.php` |
| A | `app/Services/PerfilClinicoException.php` |
| A | `app/Services/PerfilClinicoService.php` |
| A | `app/Services/PortalPacienteService.php` |
| A | `app/Services/RegistroClinicoService.php` |
| A | `app/Services/ReporteCitaService.php` |
| A | `app/Services/ReprogramacionService.php` |
| A | `app/Services/VinculacionPacienteService.php` |
| A | `app/Views/agenda/error.php` |
| A | `app/Views/agenda/index.php` |
| A | `app/Views/citas/asistencia-error.php` |
| A | `app/Views/citas/asistencia-resumen.php` |
| A | `app/Views/citas/asistencia.php` |
| A | `app/Views/citas/cancelar-error.php` |
| A | `app/Views/citas/cancelar.php` |
| A | `app/Views/citas/confirmar-error.php` |
| A | `app/Views/citas/confirmar.php` |
| M | `app/Views/citas/index.php` |
| A | `app/Views/citas/reprogramar-error.php` |
| A | `app/Views/citas/reprogramar-revisar.php` |
| A | `app/Views/citas/reprogramar.php` |
| M | `app/Views/citas/show.php` |
| A | `app/Views/clinico/alergias.php` |
| A | `app/Views/clinico/cabecera.php` |
| A | `app/Views/clinico/error.php` |
| A | `app/Views/clinico/historial.php` |
| A | `app/Views/clinico/perfil.php` |
| A | `app/Views/clinico/registro-formulario.php` |
| A | `app/Views/clinico/registros.php` |
| M | `app/Views/dashboard.php` |
| A | `app/Views/historial/buscar.php` |
| A | `app/Views/historial/error.php` |
| A | `app/Views/historial/eventos.php` |
| A | `app/Views/historial/index.php` |
| M | `app/Views/horarios/status.php` |
| M | `app/Views/layout.php` |
| M | `app/Views/pacientes/index.php` |
| M | `app/Views/pacientes/show.php` |
| A | `app/Views/portal/detalle.php` |
| A | `app/Views/portal/error.php` |
| A | `app/Views/portal/formulario.php` |
| A | `app/Views/portal/index.php` |
| A | `app/Views/portal/vincular.php` |
| A | `app/Views/reportes/citas.php` |
| A | `app/Views/reportes/error.php` |
| A | `app/Views/reportes/pdf.php` |
| A | `app/Views/voz/panel.php` |
| A | `composer.json` |
| A | `composer.lock` |
| A | `database/migrations/005_citas_historial.sql` |
| A | `database/migrations/006_citas_cancelacion.sql` |
| A | `database/migrations/007_citas_confirmacion.sql` |
| A | `database/migrations/008_citas_asistencia.sql` |
| A | `database/migrations/009_perfil_clinico_alergias.sql` |
| A | `database/migrations/010_antecedentes_laboratorio.sql` |
| A | `docs/ciclo2/CU15.md` |
| A | `docs/ciclo2/CU16.md` |
| A | `docs/ciclo2/CU17.md` |
| A | `docs/ciclo2/CU18.md` |
| A | `docs/ciclo2/CU19.md` |
| A | `docs/ciclo2/CU20.md` |
| A | `docs/ciclo2/CU21.md` |
| A | `docs/ciclo2/CU22.md` |
| A | `docs/ciclo2/CU23.md` |
| A | `docs/ciclo2/CU24.md` |
| A | `docs/ciclo2/CU25.md` |
| A | `docs/ciclo2/CU26.md` |
| A | `docs/ciclo2/MAPA_DEL_CODIGO.md` |
| A | `docs/ciclo2/PRUEBAS_CU15.md` |
| A | `docs/ciclo2/PRUEBAS_CU16.md` |
| A | `docs/ciclo2/PRUEBAS_CU17.md` |
| A | `docs/ciclo2/PRUEBAS_CU18.md` |
| A | `docs/ciclo2/PRUEBAS_CU19.md` |
| A | `docs/ciclo2/PRUEBAS_CU20.md` |
| A | `docs/ciclo2/PRUEBAS_CU21.md` |
| A | `docs/ciclo2/PRUEBAS_CU22.md` |
| A | `docs/ciclo2/PRUEBAS_CU23.md` |
| A | `docs/ciclo2/PRUEBAS_CU24.md` |
| A | `docs/ciclo2/PRUEBAS_CU25.md` |
| A | `docs/ciclo2/PRUEBAS_CU26.md` |
| A | `docs/ciclo2/README.md` |
| A | `public/assets/css/agenda.css` |
| M | `public/assets/css/app.css` |
| A | `public/assets/css/clinico.css` |
| A | `public/assets/css/historial.css` |
| A | `public/assets/css/portal.css` |
| A | `public/assets/css/reportes.css` |
| A | `public/assets/css/voz.css` |
| A | `public/assets/js/comandos-voz.mjs` |
| M | `public/index.php` |
| A | `scripts/Cu23Esquema.php` |
| A | `scripts/Cu24Esquema.php` |
| A | `scripts/aplicar-migracion-005.php` |
| A | `scripts/aplicar-migracion-006.php` |
| A | `scripts/aplicar-migracion-007.php` |
| A | `scripts/aplicar-migracion-008.php` |
| A | `scripts/aplicar-migracion-009.php` |
| A | `scripts/aplicar-migracion-010.php` |
| A | `scripts/cu23/catalogo.sql` |
| A | `scripts/cu24/catalogo.sql` |
| A | `scripts/cu24/contrato.json` |
| A | `scripts/cu24/propuesta.json` |
| A | `scripts/inspeccionar-cu15-local.php` |
| A | `scripts/inspeccionar-cu18-local.php` |
| A | `scripts/inspeccionar-cu23-local.php` |
| A | `scripts/inspeccionar-cu24-local.php` |
| M | `scripts/inspeccionar-horarios.php` |
| M | `scripts/render/verificar-instalacion.sql` |
| A | `scripts/verificar-cu15-local.php` |
| A | `scripts/verificar-cu23-local.php` |
| A | `scripts/verificar-cu24-local.php` |
| A | `scripts/verificar-cu25-local.php` |
| A | `tests/AgendaPrueba.php` |
| A | `tests/AsistenciaPrueba.php` |
| A | `tests/CancelacionPrueba.php` |
| A | `tests/ConfirmacionPrueba.php` |
| A | `tests/Cu15Local.php` |
| A | `tests/Cu25Ejecutar.php` |
| A | `tests/Cu26Ejecutar.php` |
| A | `tests/HistorialPacientePrueba.php` |
| M | `tests/HttpCatalogo.php` |
| A | `tests/PerfilClinicoPrueba.php` |
| A | `tests/PortalPacientePrueba.php` |
| M | `tests/README.md` |
| A | `tests/RegistroClinicoPrueba.php` |
| A | `tests/ReportesPrueba.php` |
| A | `tests/ReprogramacionPrueba.php` |
| A | `tests/agenda_browser.php` |
| A | `tests/agenda_integration.php` |
| A | `tests/asistencia_browser.php` |
| A | `tests/asistencia_concurrency.php` |
| A | `tests/asistencia_integration.php` |
| M | `tests/auditoria_ciclo1_http.php` |
| A | `tests/cancelacion_browser.php` |
| A | `tests/cancelacion_concurrency.php` |
| A | `tests/cancelacion_integration.php` |
| M | `tests/citas_browser.mjs` |
| A | `tests/comandos_voz_browser.mjs` |
| A | `tests/comandos_voz_browser.php` |
| A | `tests/comandos_voz_validation.mjs` |
| A | `tests/comandos_voz_validation.php` |
| A | `tests/confirmacion_browser.php` |
| A | `tests/confirmacion_concurrency.php` |
| A | `tests/confirmacion_integration.php` |
| M | `tests/disponibilidad_integration.php` |
| A | `tests/esquema_cu15_validation.php` |
| A | `tests/esquema_cu23_validation.php` |
| A | `tests/esquema_cu24_validation.php` |
| A | `tests/exportacion_csv_validation.php` |
| A | `tests/exportacion_pdf_validation.php` |
| A | `tests/exportaciones_browser.php` |
| A | `tests/exportaciones_docker_validation.php` |
| A | `tests/exportaciones_integration.php` |
| A | `tests/historial_paciente_browser.php` |
| A | `tests/historial_paciente_integration.php` |
| A | `tests/http_agenda.php` |
| A | `tests/http_asistencia.php` |
| A | `tests/http_cancelacion.php` |
| A | `tests/http_comandos_voz.php` |
| A | `tests/http_confirmacion.php` |
| A | `tests/http_exportacion_csv.php` |
| A | `tests/http_exportacion_pdf.php` |
| A | `tests/http_historial_paciente.php` |
| M | `tests/http_horarios_integration.php` |
| A | `tests/http_perfil_clinico.php` |
| A | `tests/http_portal_paciente.php` |
| A | `tests/http_registros_clinicos.php` |
| A | `tests/http_reportes.php` |
| A | `tests/http_reprogramacion.php` |
| A | `tests/leer_pdf_cu22.py` |
| A | `tests/perfil_clinico_browser.mjs` |
| A | `tests/perfil_clinico_browser.php` |
| A | `tests/perfil_clinico_concurrency.php` |
| A | `tests/perfil_clinico_integration.php` |
| A | `tests/perfil_clinico_integridad.php` |
| A | `tests/perfil_clinico_privacidad.php` |
| A | `tests/perfil_clinico_validation.php` |
| A | `tests/portal_paciente_browser.mjs` |
| A | `tests/portal_paciente_browser.php` |
| A | `tests/portal_paciente_concurrency.php` |
| A | `tests/portal_paciente_integration.php` |
| A | `tests/portal_paciente_privacidad.php` |
| A | `tests/registros_clinicos_browser.mjs` |
| A | `tests/registros_clinicos_browser.php` |
| A | `tests/registros_clinicos_concurrency.php` |
| A | `tests/registros_clinicos_integration.php` |
| A | `tests/registros_clinicos_integridad.php` |
| A | `tests/registros_clinicos_privacidad.php` |
| A | `tests/registros_clinicos_validation.php` |
| A | `tests/regresion_cu15.php` |
| A | `tests/regresion_cu21.php` |
| A | `tests/regresion_cu22.php` |
| A | `tests/regresion_cu23.php` |
| A | `tests/regresion_cu24.php` |
| A | `tests/regresion_cu25.php` |
| A | `tests/regresion_cu26.php` |
| A | `tests/reportes_browser.php` |
| A | `tests/reportes_integration.php` |
| A | `tests/reprogramacion_browser.php` |
| A | `tests/reprogramacion_concurrency.php` |
| A | `tests/reprogramacion_integration.php` |

## Huellas de archivos inmutables revisados

SHA-256 de los archivos de SQL/build/Composer que deben conservarse. No se publica ninguna huella ni contenido de .env, contraseñas o datos de PostgreSQL.

| Archivo | SHA-256 |
|---|---|
| `composer.json` | `43c6805c10646a9f2643058a80899237a6f28c36087e2dacb6ff83aa4355d0db` |
| `composer.lock` | `7ede570468717648ed45bea224de41739f3ae0e8cc3a72b8778f2d2ee6e6cd16` |
| `Dockerfile` | `53c6f14f242afa09ae208915219d9ea2ad49630e29bb87d438b2032f599530ae` |
| `.dockerignore` | `be1ad94a87c5054c3e296eda828f5623f4f6bad9e21129d56fd1438d9dbea149` |
| `database/migrations/001_esquema_ciclo1.sql` | `cece1b5801b3b91ae2f9e0567e2e67ad4d3267676529a86b28c2ea736f923fbf` |
| `database/migrations/002_pacientes_cu04_cu06.sql` | `a2c9b9d9cbbcf80cbd88dc8bf194d2482bba17f15fb3d8e2989a44b86301ddc1` |
| `database/migrations/003_medicos_especialidades.sql` | `8e24340f0bbe79674de51873f423cb6587fdcd94b7c4acf546f184842e6b850c` |
| `database/migrations/004_citas_especialidad.sql` | `93550b0ad721ea35620a28331fc9c468e607b2acc297c8754fb3fe722dbfce30` |
| `database/migrations/005_citas_historial.sql` | `65fb5838c4dd591c47afc0905497f86a6be2052c4e027cea003876022d9a8459` |
| `database/migrations/006_citas_cancelacion.sql` | `3c388a1e8904fac765c46d28dafe82185cb5a2ab2784633a65c1056d59c292f3` |
| `database/migrations/007_citas_confirmacion.sql` | `d68484e2e4a0e2f91be6c02123ab7fb7acfc2cc5e089e6f94a1d11482a9cbd70` |
| `database/migrations/008_citas_asistencia.sql` | `380f1351464a59da13f4a1bcd22dec1f1319ec4ea54f489470dac73be0109fec` |
| `database/migrations/009_perfil_clinico_alergias.sql` | `65bf0780f7a492b1bca0197891d6f44c9b687b15941ad30c91c766e47bdbcfc0` |
| `database/migrations/010_antecedentes_laboratorio.sql` | `91769e9985d88b21b8ce4093c4e315a42b172989b897ec44f2b2f8361a64f64a` |

## Exclusiones expresas

- Los cambios de trabajo pendientes en app/Config/Database.php, app/Config/DatabaseDiagnostic.php, scripts/render-postgres.ps1, tests/render_preparacion.ps1 y tests/database_render_validation.php.
- .env, vendor local, sesiones, logs, temporales, respaldos, capturas privadas, información clínica real y audio/transcripciones.
- Los cinco Markdown de docs/despliegue son preparación local posterior al candidato y no forman parte de este diff de 245 archivos.
- El workflow autorizado en 20B también es posterior al candidato; no cambia este inventario de código. El commit de preparación contiene solo cinco documentos y ese YAML.

Una ruta protegida puede existir ya versionada sin cambios entre main y CU26; lo excluido son sus modificaciones locales posteriores. No sustituir la versión del candidato por la pendiente.

## Comprobación

```powershell
git diff --name-status 1afbad7b4a68b01f9999f54c1c6d9fe8ce40bf37...23b1175fe865021a102a890c0f5b267eabad5588
git diff --stat 1afbad7b4a68b01f9999f54c1c6d9fe8ce40bf37...23b1175fe865021a102a890c0f5b267eabad5588
```

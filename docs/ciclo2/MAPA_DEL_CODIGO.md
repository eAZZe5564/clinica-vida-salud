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
| `scripts/render/verificar-instalacion.sql` | Contrato compartido: detección real 001–004 (8/10) o 001–005 (9/12), tablas, columnas, referencias e índices; solo lectura |
| `scripts/verificar-cu15-local.php` | Entrada local protegida al contrato de esquema, REPEATABLE READ READ ONLY, sin migraciones |
| `tests/esquema_cu15_validation.php` | 53 comprobaciones de SQL real y catálogos aislados C1/C2, sin DDL ni modificaciones de registros |
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

No hay rutas de cancelación, asistencia ni reasignación de médico en esta entrega.

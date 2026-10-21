# Gestión de pacientes — CU04–CU06

Implementado el 20/10/2026 con MVC, PDO, sesiones, CSRF y layout existentes. No se desarrollaron CU07–CU14 ni Ciclo 2. Solo se consultan citas en el detalle del paciente.

## Rutas y módulos

| Caso | Rutas | Métodos y vistas |
|---|---|---|
| CU04 | `GET /pacientes/nuevo`, `POST /pacientes` | `PacientesController::formulario/guardar`, `PacienteService::validar/guardar`, `PacienteRepository::guardar`, `form.php` |
| CU05 | `GET /pacientes`, `GET /pacientes/{id}` | controlador `listar/detalle`, servicio `obtener`, repositorio `listar/buscar/citas`, `index.php/show.php` |
| CU06 | `GET/POST /pacientes/{id}/editar`, `GET/POST /pacientes/{id}/estado` | controlador `formulario/guardar/confirmarEstado/cambiarEstado`, servicio y repositorio `guardar/cambiarEstado`, `form.php/status.php` |

Las clases están bajo sus carpetas habituales de `app/`; las vistas en `app/Views/pacientes/`. Modificar reglas en `PacienteService`, SQL en `PacienteRepository` y presentación en las vistas. `PacienteValidacionException` transporta errores por campo: el controlador responde 422 y conserva entradas; los fallos internos usan el error 500 genérico existente. Los métodos HTTP no admitidos devuelven 405 y los pacientes inexistentes, 404.

## Campos y reglas

- Código público: `Paciente::codigo(id)`, formato `PAC-000001`. Usa la identidad generada por PostgreSQL, nunca cuenta registros; puede tener saltos y crecer en longitud.
- Nombres/apellidos obligatorios, máximo 100 caracteres; no se admiten espacios como único contenido.
- CI obligatorio conforme al esquema actual, texto de hasta 40 caracteres. Elimina espacios y unifica mayúsculas, conservando ceros y signos: ` 001 ab ` → `001AB`. Servicio comprueba duplicados y el índice único protege ante concurrencia.
- Nacimiento obligatorio en formulario/servicio, fecha real YYYY-MM-DD, desde año 0001 hasta hoy en America/La_Paz.
- Sexo: femenino, masculino o no especificado; debe seleccionarse para alta/edición. Los registros anteriores pueden mantener NULL hasta editarse.
- Teléfono opcional (30), correo opcional válido (254), dirección opcional (250). Vacíos opcionales se guardan como NULL; rechaza tipos inesperados, caracteres de control y longitudes excesivas.
- Alta activa por defecto. Desactivación y reactivación requieren confirmación en pantalla y POST con CSRF. Conservan id, usuario_id y citas, sin modificar sus estados. El trigger existente impide nuevas citas para inactivos.
- No crea cuentas de acceso ni modifica vínculos usuario_id. Los identificadores proceden de la ruta y no de campos editables.
- Búsqueda por CI, nombres y apellidos, con comodines escapados y páginas de 20. Citas también limitadas a 20 por página; fechas con hora de La Paz.
- Salidas escapadas mediante e(); HTML5 y JavaScript complementan la validación PHP, sin decidir permisos ni unicidad.

## Permisos comprobados

| Operación | Administrador | Recepcionista | Médico | Paciente |
|---|---|---|---|---|
| Registrar, listar, buscar, consultar detalle/citas | Sí | Sí | No | No |
| Editar, desactivar, reactivar | Sí | Sí | No | No |

AuthMiddleware::exigirRoles se ejecuta en el constructor del controlador. Reutiliza la revalidación del usuario activo y el vencimiento de sesión. Todas las escrituras son POST con CSRF; GET de estado solo confirma. No existe DELETE.

## Migración

`database/migrations/002_pacientes_cu04_cu06.sql` se aplicó con autorización explícita el 20/10/2026, solo en la base local clinica_vida_salud_dev (127.0.0.1). Agrega sexo/dirección, restricciones de sexo y nombres/CI no vacíos y un índice único de CI normalizado. No reescribe datos y conserva las relaciones y el UNIQUE original.

El ejecutor `scripts/aplicar-migracion-pacientes.php` usa transacción y espera de bloqueo de 5 segundos. Ante incompatibilidades revierte. No repetir localmente: ya fue aplicada y no es idempotente. Para otra instalación aplicar 001/seed y posteriormente 002 con autorización. El script `scripts/inspeccionar-pacientes.php` consulta columnas/restricciones y cantidad de cuentas sin secretos.

## Archivos creados

- `app/Controllers/PacientesController.php`
- `app/Models/Paciente.php`
- `app/Repositories/PacienteRepository.php`
- `app/Services/PacienteService.php`
- `app/Services/PacienteValidacionException.php`
- `app/Views/pacientes/index.php`
- `app/Views/pacientes/form.php`
- `app/Views/pacientes/show.php`
- `app/Views/pacientes/status.php`
- `database/migrations/002_pacientes_cu04_cu06.sql`
- `scripts/aplicar-migracion-pacientes.php`
- `scripts/inspeccionar-pacientes.php`
- `tests/pacientes_validation.php`
- `tests/pacientes_integration.php`
- `tests/http_pacientes_integration.php`
- `docs/ciclo1/implementacion/PACIENTES.md`

## Archivos modificados

- `.env.example`: corrige clave APP_NAME mal escrita; no toca secretos locales.
- `app/Middleware/AuthMiddleware.php`
- `app/Views/layout.php`
- `app/Views/dashboard.php`
- `public/index.php`
- `public/assets/css/app.css`
- `public/assets/js/app.js`
- `README.md`
- `tests/README.md`
- `docs/ciclo1/MAPA_DEL_CODIGO.md`
- `docs/ciclo1/implementacion/README.md`
- `docs/ciclo1/pruebas/README.md`

No se hicieron commits ni push. Resultados y límites de verificación en el registro de pruebas.

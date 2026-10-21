# Médicos y especialidades — CU07–CU10

Entrega del Prompt 3A, 20/10/2026. Mantiene PHP/PDO, las sesiones y permisos existentes y el layout de pacientes. No implementa horarios, disponibilidad ni programación/consulta general de citas (CU11–CU14). La lectura de citas que ya existía en pacientes se conserva.

## Estado de instalación

La migración `003_medicos_especialidades.sql` se aplicó en una transacción con autorización explícita del usuario el 20/10/2026, únicamente en clinica_vida_salud_dev local. La inspección previa encontró cero duplicados normalizados y cero datos incompatibles con sus restricciones. Se conservaron las tres especialidades base y la cuenta previa, sin reemplazar datos. No repetir la migración: no es idempotente. En otra instalación, aplicar después de 001/002 y previa autorización. Las suites de integración/HTTP requieren 003 y devuelven código 2 si falta.

## Rutas y responsabilidades

| CU | Rutas HTTP | Controlador, servicio, repositorio y vistas |
|---|---|---|
| CU07 | `GET /medicos/nuevo`, `POST /medicos` | `MedicosController::formulario/guardar`, `MedicoService::validar/guardar`, `MedicoRepository::guardar/asignar`, `medicos/form.php` y `seleccion.php` |
| CU08 | `GET /medicos`, `GET /medicos/{id}`, `GET/POST /medicos/{id}/editar`, `GET/POST /medicos/{id}/estado` | controlador `listar/detalle/formulario/guardar/estado/cambiarEstado`, servicio `obtener/guardar/cambiarEstado`, repositorio `listar/obtener/especialidades/guardar/cambiarEstado`, vistas `index/show/form.php`, `catalogo/estado.php` |
| CU09 | `GET /especialidades`, `GET /especialidades/nuevo`, `POST /especialidades`, `GET /especialidades/{id}`, `GET/POST /especialidades/{id}/editar`, `GET/POST /especialidades/{id}/estado` | `EspecialidadesController`, `EspecialidadService`, `EspecialidadRepository`, vistas `especialidades/index/form/show.php`, `catalogo/estado.php` |
| CU10 | `GET/POST /medicos/{id}/especialidades` y selección en alta/edición | controlador `asignaciones/asignar`, servicio `asignar/validarAsignaciones`, repositorio `asignar/especialidades`, vistas `asignaciones.php/seleccion.php` |

`app/Config/RutasCatalogo.php` despacha estas rutas desde public/index.php y distingue 404/405. `CatalogoController` comparte únicamente render, lectura de entradas y comprobaciones de permisos de este módulo; utiliza AuthMiddleware y la conexión PDO existentes. No introduce otra autenticación. La lógica de negocio está en servicios y las consultas en repositorios.

## Datos y validaciones

Utiliza las tablas `medicos`, `especialidades`, `medicos_especialidades`; solo consulta `usuarios/roles` para asociación opcional. No modifica usuarios ni pacientes. `citas` referencia médico/paciente, no la tabla de asociaciones; retirar una especialidad de un médico no rompe esas claves foráneas ni elimina citas.

- Código `MED-000001` derivado del id generado por PostgreSQL. No usa conteos y admite saltos y números de más de seis dígitos.
- Nombres y apellidos obligatorios, hasta 100 caracteres e incluyendo al menos una letra; UTF-8 válido, sin caracteres de control ni textos vacíos de espacios.
- Registro profesional obligatorio, máximo 60 caracteres. Quita espacios y convierte a mayúsculas conservando ceros; admite letras, números, punto, guion y barra. Servicio e índice único comprueban unicidad normalizada.
- Teléfono opcional hasta 30 caracteres y entre 7 y 15 dígitos; admite + inicial, espacios, paréntesis y guiones. Correo opcional de hasta 254 caracteres con formato válido. Vacíos se guardan como NULL.
- Especialidad: nombre obligatorio de hasta 100 caracteres; colapsa espacios y conserva su presentación. La comparación única usa lower y espacios normalizados, conservando acentos. Descripción opcional de hasta 250 caracteres.
- Altas activas por defecto. Estado solo cambia mediante confirmación y POST protegido. No existe eliminación física por HTTP.
- Listados de 20 resultados por página con búsqueda y enlaces que conservan filtros. Médicos se buscan por nombre, apellido y registro; filtran por especialidad y activo/inactivo. Se muestran especialidades inactivas asociadas, identificadas como tales.
- Errores por campo mediante CatalogoValidacionException y HTTP 422, conservando entradas. HTML escapado con e(), PDO parametrizado, validación JavaScript auxiliar y reglas definitivas en PHP.

## Asociaciones y concurrencia

Un médico activo debe conservar al menos una especialidad activa en alta, edición, asignaciones y reactivación. Puede conservar una especialidad inactiva ya vinculada, pero no añadirla como nueva. Un médico inactivo puede quedar sin especialidades; antes de reactivarlo se requiere una activa. Desactivar una especialidad se bloquea si deja algún médico activo sin alternativa. Primero asignar otra especialidad activa o desactivar el médico.

CatalogoTransaccion combina transacción/savepoint y bloqueo consultivo transaccional compartido entre cambios de médicos, especialidades y asociaciones. Evita carreras entre desactivar una especialidad y asignarla o retirar otra. El savepoint revierte cambios compuestos y permite continuar suites dentro de una transacción externa. Los índices únicos garantizan unicidad incluso para escrituras que no usan los servicios. La regla de al menos una especialidad corresponde a los servicios: escrituras SQL externas no deben saltárselos.

La PK (medico_id,especialidad_id) impide duplicados. La entrada también rechaza IDs repetidos/inválidos. Se conserva asignada_en de las asociaciones mantenidas; solo se insertan nuevas y se retiran las que dejaron de seleccionarse. La retirada no elimina especialidades ni altera citas. Desactivar médicos/especialidades conserva sus asociaciones y las citas; el trigger existente impide reservar para un médico inactivo.

## Cuenta médica opcional

El administrador puede seleccionar una cuenta existente activa de rol medico y no asociada a otro médico (hasta 200 opciones ordenadas por correo). El backend revalida rol/estado/disponibilidad, bloquea la fila y usa el mismo bloqueo administrativo de cuentas durante el guardado; UNIQUE(usuario_id) garantiza una sola asociación. No se crean cuentas. Elegir «Sin cuenta vinculada» retira únicamente el vínculo y conserva la cuenta. Cambiar estado del perfil no cambia el de la cuenta de acceso.

La administración de usuarios conserva su comportamiento: si posteriormente cambia el rol o se desactiva la cuenta, el middleware aplica el estado/rol actual y no concede permisos médicos por el perfil. Al editar el médico, una cuenta que ya no es elegible debe sustituirse o desvincularse; se muestra la indicación. El vínculo por sí solo no implementa agenda personal ni funciones del Ciclo 2.

## Permisos

| Acción | Administrador | Recepcionista | Médico | Paciente |
|---|---|---|---|---|
| Listar/buscar/filtrar médicos y consultar detalle/contacto profesional | Sí | Sí | No | No |
| Crear/editar médicos, cambiar estado y asignar especialidades | Sí | No | No | No |
| Gestión del catálogo de especialidades | Sí | No | No | No |

Recepción ve nombres de especialidades desde médicos y el filtro; no puede entrar al catálogo administrativo. Los formularios GET y todos los POST administrativos comprueban rol. Escrituras: POST y CSRF; GET de estado solo confirma. Sin sesión se redirige a login; rol indebido devuelve 403.

## Archivos creados

- `app/Config/RutasCatalogo.php`
- `app/Controllers/CatalogoController.php`
- `app/Controllers/MedicosController.php`
- `app/Controllers/EspecialidadesController.php`
- `app/Models/Medico.php`
- `app/Repositories/MedicoRepository.php`
- `app/Repositories/EspecialidadRepository.php`
- `app/Services/MedicoService.php`
- `app/Services/EspecialidadService.php`
- `app/Services/CatalogoTransaccion.php`
- `app/Services/CatalogoValidacion.php`
- `app/Services/CatalogoValidacionException.php`
- `app/Views/medicos/index.php`
- `app/Views/medicos/form.php`
- `app/Views/medicos/show.php`
- `app/Views/medicos/asignaciones.php`
- `app/Views/medicos/seleccion.php`
- `app/Views/especialidades/index.php`
- `app/Views/especialidades/form.php`
- `app/Views/especialidades/show.php`
- `app/Views/catalogo/estado.php`
- `app/Views/catalogo/paginacion.php`
- `database/migrations/003_medicos_especialidades.sql`
- `scripts/aplicar-migracion-medicos.php`
- `scripts/inspeccionar-medicos.php`
- `tests/medicos_validation.php`
- `tests/medicos_integration.php`
- `tests/HttpCatalogo.php`
- `tests/http_medicos_integration.php`
- `docs/ciclo1/implementacion/MEDICOS_ESPECIALIDADES.md`

## Archivos modificados

- `public/index.php`
- `app/Views/layout.php`
- `app/Views/dashboard.php`
- `public/assets/css/app.css`
- `public/assets/js/app.js`
- `README.md`
- `tests/README.md`
- `docs/ciclo1/MAPA_DEL_CODIGO.md`
- `docs/ciclo1/implementacion/README.md`
- `docs/ciclo1/pruebas/README.md`
- `docs/ciclo1/analisis/DECISIONES_TECNICAS.md`

## Arranque y revisión

Ejecutar `php -S 127.0.0.1:8000 -t public`, entrar en http://127.0.0.1:8000/login con la cuenta administradora local y abrir Especialidades y Médicos. Registrar un médico ficticio con una o varias especialidades activas, buscarlo, editar/asignar y confirmar cambios de estado. La recepcionista solo consulta Médicos. Las credenciales se escriben únicamente en el navegador.

Antes de Prompt 3B: revisar visualmente escritorio/móvil y acordar horarios. No se simula agenda, disponibilidad ni citas. Registro de pruebas en `docs/ciclo1/pruebas/README.md`. Sin commits ni push.

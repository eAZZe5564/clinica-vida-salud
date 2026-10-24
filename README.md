# Sistema Web de Gestión de Citas Médicas — Clínica Vida Salud

Aplicación académica PHP/PostgreSQL para gestionar Clínica Vida Salud por etapas. Implementa CU01–CU14: autenticación, usuarios, pacientes, médicos, especialidades, horarios, disponibilidad, reserva y consulta de citas. Las migraciones 001–004 están aplicadas localmente; CU14 funciona sin migraciones adicionales. El alcance corresponde al Ciclo 1.

## Tecnologías

- PHP 8.4 con PDO, `pdo_pgsql`, `mbstring` y sesiones habilitadas.
- PostgreSQL 17 con la extensión `btree_gist` (requerida por la migración 001).
- HTML5, CSS3 y JavaScript sin frameworks ni dependencias externas.
- Git para el control de versiones.

## Requisitos previos

1. PHP 8.4 disponible en `PATH` con `pdo_pgsql` y `mbstring` habilitados.
2. PostgreSQL local y su cliente `psql`.
3. Git.
4. Composer no es necesario: no se agregaron dependencias externas.

En el entorno local se encontró PHP 8.4.22, `pdo_pgsql` habilitado (libpq 16.14), `psql` 17.10 y Git 2.41.0. Composer no se encontró. PostgreSQL local y PDO se conectan a `clinica_vida_salud_dev`.

## Instalar en Windows

1. Abre esta carpeta en VS Code y verifica `php -v`, `php --ini`, `php -m` y `psql --version`. Utiliza PHP 8.4 y PostgreSQL 17; otras versiones no forman parte del entorno validado.
2. Si PHP no está instalado, instala una distribución de PHP 8.4 para Windows adecuada a tu arquitectura y agrega su carpeta al `PATH`. En el `php.ini` activo, habilita `extension=pdo_pgsql` y `extension=mbstring`; confirma con `php --ri pdo_pgsql` y `php --ri mbstring`. `mbstring` se utiliza para validar y normalizar texto. Las sesiones deben estar habilitadas.
3. Instala PostgreSQL 17 localmente solo si aún no está disponible, incluye las herramientas de línea de comandos y agrega su carpeta `bin` al `PATH`. El usuario de instalación necesita permiso para crear la base e instalar `btree_gist`. No se requiere Composer.
4. En una computadora nueva, copia `.env.example` solo si todavía no existe `.env`, con `if (-not (Test-Path -LiteralPath .env)) { Copy-Item -LiteralPath .env.example -Destination .env }`. Ajusta localmente DB_HOST, DB_PORT, DB_NAME, DB_USER y DB_PASSWORD. Conserva APP_ENV=local y APP_TIMEZONE=America/La_Paz. No compartas ni subas `.env`; no hay contraseña predeterminada.
5. La carpeta `public/` es la raíz pública: no sirvas la raíz del proyecto.

## PostgreSQL local

La base local `clinica_vida_salud_dev` ya existe con el esquema aplicado: `usuarios` tiene `id`, `rol_id`, `correo`, `hash_contrasena`, `activo`, marcas de tiempo y último acceso. CU01–CU03 utilizan las ocho tablas y los cuatro roles existentes; no se necesita una migración adicional. Los scripts de la instalación original están en [`database/migrations/001_esquema_ciclo1.sql`](./database/migrations/001_esquema_ciclo1.sql) y [`database/seeds/001_datos_base.sql`](./database/seeds/001_datos_base.sql).

Para reproducir la instalación en otra computadora, verifica primero que el nombre de la base no exista y autoriza su creación. Nunca borres ni reemplaces una base existente. **Estos comandos son exclusivamente para una instalación nueva autorizada; no ejecutarlos sobre la base actual.** Desde la raíz del proyecto, con permisos suficientes y la contraseña PostgreSQL solicitada de forma interactiva por `-W`, ejecutar en este orden (detenerse si cualquier comando falla):

```powershell
createdb -h 127.0.0.1 -p 5432 -U postgres -W -O postgres clinica_vida_salud_dev
psql -X -W --single-transaction -v ON_ERROR_STOP=1 -h 127.0.0.1 -p 5432 -U postgres -d clinica_vida_salud_dev -f database\migrations\001_esquema_ciclo1.sql
psql -X -W --single-transaction -v ON_ERROR_STOP=1 -h 127.0.0.1 -p 5432 -U postgres -d clinica_vida_salud_dev -f database\migrations\002_pacientes_cu04_cu06.sql
psql -X -W --single-transaction -v ON_ERROR_STOP=1 -h 127.0.0.1 -p 5432 -U postgres -d clinica_vida_salud_dev -f database\migrations\003_medicos_especialidades.sql
psql -X -W --single-transaction -v ON_ERROR_STOP=1 -h 127.0.0.1 -p 5432 -U postgres -d clinica_vida_salud_dev -f database\migrations\004_citas_especialidad.sql
psql -X -W --single-transaction -v ON_ERROR_STOP=1 -h 127.0.0.1 -p 5432 -U postgres -d clinica_vida_salud_dev -f database\seeds\001_datos_base.sql
```

El seed solo carga cuatro roles y tres especialidades; no crea usuarios, pacientes, médicos, horarios ni citas. Las migraciones no son reejecutables: comprobar qué está aplicado antes de continuar. Los ejecutores `scripts/aplicar-migracion-*.php` están restringidos a la base local de desarrollo; para otra instalación se usa la secuencia SQL revisada. No vuelvas a ejecutar `createdb` ni migraciones en esta computadora. Las credenciales se configuran en `.env`, excluido de Git. La auditoría revisó esta secuencia, pero no creó una base aislada ni ensayó una instalación desde cero.

## Configuración y arranque

La conexión PDO usa consultas preparadas nativas. `.env` no debe publicarse. Las contraseñas de usuarios se procesan con `password_hash()`/`password_verify()`; el hash nunca se muestra en las vistas.

Inicia el servidor integrado desde la raíz del proyecto:

```powershell
php -S 127.0.0.1:8000 -t public
```

Abre <http://127.0.0.1:8000>. Si ese puerto está ocupado por otro proyecto, utiliza `php -S 127.0.0.1:8001 -t public` y abre <http://127.0.0.1:8001>. Mantén abierta la terminal mientras uses el servidor local. En producción debe usarse HTTPS y un servidor configurado para exponer únicamente `public/`; en `APP_ENV=production`, la cookie de sesión exige `Secure`.

## Primera cuenta administradora

El seed no incluye cuentas de demostración ni contraseñas predeterminadas. La base de desarrollo actual ya tiene cuentas y las pruebas las conservan. No repitas la inicialización si ya puedes ingresar. En una instalación nueva, después de las cuatro migraciones y el seed, autoriza la primera cuenta e inicia el asistente desde PowerShell en la raíz del proyecto:

```powershell
.\scripts\crear-administrador.ps1
```

El asistente pide el correo y recibe la contraseña mediante `Read-Host -AsSecureString`; la contraseña no se muestra ni forma parte de los argumentos del proceso. La cuenta solo se puede inicializar cuando `usuarios` está vacía. El servicio guarda únicamente el hash de la contraseña. No existe un instalador público.

Al entrar por primera vez, abre `/login` e ingresa el mismo correo y contraseña localmente; no los pegues en el chat ni los guardes en Git. Las contraseñas nuevas o cambiadas deben tener al menos 8 caracteres, una mayúscula, una minúscula, un número y un símbolo especial. Los espacios no cuentan como símbolos. Se admiten letras/dígitos/símbolos Unicode y se mantiene un máximo de 72 bytes para evitar truncamiento de bcrypt. Las contraseñas existentes siguen permitidas al iniciar sesión; editar dejando la contraseña vacía conserva su hash.

## Mejoras 6A del Ciclo 1

El buscador de pacientes admite varios términos en cualquier orden: por ejemplo, nombre + apellido, apellido + CI o los tres juntos. Todos deben coincidir con el mismo paciente, aunque aparezcan en campos distintos. Admite coincidencias parciales, ignora mayúsculas y tildes de vocales, normaliza espacios y conserva filtros y paginación. Límite: 100 caracteres; ñ y n permanecen distintas.

Las altas y cambios de contraseña se validan en PHP mediante una política central de cinco condiciones, también usada por el administrador inicial. Los mensajes indican qué falta sin reflejar la contraseña. El login de cuentas anteriores y la edición con contraseña vacía conservan su comportamiento.

Verificación del 23/10/2026: **165 comprobaciones nuevas y 838 de regresión aprobadas (1003 distintas)**, sintaxis PHP/JS correcta y pruebas reales de escritorio/móvil en Edge. Detalle, inventario de archivos y comprobación manual: [MEJORAS_6A.md](docs/ciclo1/implementacion/MEJORAS_6A.md). No se modificó el esquema PostgreSQL ni se realizó commit o push de esta entrega.

## Rutas implementadas

| Ruta | Método | Acceso |
|---|---|---|
| `/` | GET | Público; página inicial |
| `/login` | GET, POST | Público; autenticación POST protegida por CSRF |
| `/logout` | POST | Sesión autenticada; token CSRF obligatorio |
| `/dashboard` | GET | Cualquier usuario activo autenticado |
| `/admin/users` | GET, POST | Administrador |
| `/admin/users/new` | GET | Administrador |
| `/admin/users/{id}/edit` | GET, POST | Administrador |
| `/admin/users/{id}/status` | POST | Administrador |

La interfaz de administración permite buscar por correo o rol, crear cuentas, cambiar correo/rol y contraseña, y activar/desactivar. La eliminación física y la edición de permisos granulares no están disponibles. Los roles son los cuatro valores fijos de la tabla existente.

## Matriz básica de permisos

| Operación | Administrador | Recepcionista | Médico | Paciente |
|---|---:|---:|---:|---:|
| Iniciar/cerrar sesión | Sí | Sí | Sí | Sí |
| Consultar panel de inicio | Sí | Sí | Sí | Sí |
| Listar/buscar usuarios | Sí | No | No | No |
| Crear usuarios y asignar uno de los roles fijos | Sí | No | No | No |
| Modificar cuentas, activar/desactivar | Sí* | No | No | No |

`*` El servidor bloquea la auto-desactivación y los cambios que dejarían al sistema sin un administrador activo. El médico vinculado consulta sus horarios, disponibilidad y citas propias. Administrador y recepción programan y consultan citas; paciente no accede al listado administrativo.

## Gestión de pacientes — CU04–CU06

Administrador y recepcionista pueden registrar, buscar, consultar, editar, desactivar y reactivar pacientes. Médico y paciente reciben 403. No se crean cuentas de acceso automáticamente.

| Ruta | Método | Función |
|---|---|---|
| `/pacientes` | GET / POST | Listar y buscar / registrar |
| `/pacientes/nuevo` | GET | Formulario de registro |
| `/pacientes/{id}` | GET | Detalle y citas de solo lectura |
| `/pacientes/{id}/editar` | GET / POST | Formulario / guardar edición |
| `/pacientes/{id}/estado` | GET / POST | Confirmar / desactivar o reactivar |

CI, nombres, apellidos, nacimiento y selección de sexo son obligatorios. Teléfono, correo y dirección son opcionales. CI se trata como texto, conserva ceros y se normaliza quitando espacios y convirtiendo a mayúsculas. El código `PAC-000001` deriva del id generado por PostgreSQL y admite saltos. El listado y las citas se paginan a 20 resultados. La desactivación conserva datos y citas; GET solo presenta confirmación, POST con CSRF cambia el estado.

La migración `database/migrations/002_pacientes_cu04_cu06.sql` fue aplicada localmente con autorización el 20/10/2026. Agrega sexo/dirección e integridad de nombres/CI y unicidad normalizada del documento. **No volver a aplicarla en esta computadora.** En una instalación nueva, seguir la secuencia completa de migraciones indicada arriba. No se recreó la base ni se ejecutaron seeders en esta entrega.

Detalle de campos, validaciones, permisos, responsabilidades y lista exacta de archivos: [implementación de pacientes](docs/ciclo1/implementacion/PACIENTES.md).

## Médicos y especialidades — CU07–CU10

El módulo está implementado y probado. **La migración 003 se aplicó con autorización explícita el 20/10/2026.** No repetir migraciones ni ejecutar seeders sobre la instalación actual.

| Ruta | Método | Función |
|---|---|---|
| `/medicos` | GET / POST | Listar, buscar y filtrar / registrar |
| `/medicos/nuevo` | GET | Formulario de registro |
| `/medicos/{id}` | GET | Detalle y especialidades |
| `/medicos/{id}/editar` | GET / POST | Editar datos y especialidades |
| `/medicos/{id}/estado` | GET / POST | Confirmar / cambiar estado |
| `/medicos/{id}/especialidades` | GET / POST | Consultar / guardar asignaciones |
| `/especialidades` | GET / POST | Listar y buscar / registrar |
| `/especialidades/nuevo` | GET | Formulario de registro |
| `/especialidades/{id}` | GET | Detalle |
| `/especialidades/{id}/editar` | GET / POST | Editar |
| `/especialidades/{id}/estado` | GET / POST | Confirmar / cambiar estado |

Administrador: todas las operaciones. Recepcionista: solo listado, búsqueda/filtros y detalle de médicos; sin acceso al catálogo administrativo. Médico/Paciente: sin acceso general. Escrituras solo POST con CSRF. Se conservan relaciones y citas al desactivar.

Registro profesional obligatorio, único y normalizado como texto, conservando ceros; nombres/apellidos obligatorios, teléfono y correo opcionales validados. Código `MED-000001` generado a partir de la identidad PostgreSQL. Nombre de especialidad único sin distinguir mayúsculas o espacios repetidos. Un médico activo necesita al menos una especialidad activa. La cuenta de acceso es opcional y solo se vincula si existe, está activa, tiene rol Médico y está disponible; no se crean cuentas automáticamente.

Migración aplicada: `database/migrations/003_medicos_especialidades.sql`. Agrega correo_contacto a medicos, restricciones contra vacíos e índices únicos normalizados para registro profesional y nombre de especialidad. La inspección encontró cero incompatibilidades y se conservaron las tres especialidades base. En otra instalación, después de 001/002 y previa autorización, aplicar una sola vez con `php scripts\aplicar-migracion-medicos.php`. El ejecutor verifica base local y usa transacción. Detalles, lista exacta de archivos y reglas: [MEDICOS_ESPECIALIDADES.md](docs/ciclo1/implementacion/MEDICOS_ESPECIALIDADES.md).

Para probar: iniciar PHP con el comando habitual, entrar como administrador en <http://127.0.0.1:8000/login> y abrir <http://127.0.0.1:8000/medicos> o <http://127.0.0.1:8000/especialidades>. Recepción consulta Médicos y Horarios. La programación implementada se encuentra en `/citas/nueva`.

## Horarios médicos — CU11

Acceso: <http://127.0.0.1:8000/horarios>. El detalle de cada médico incluye una sección paginada de sus horarios semanales. Los listados consultan PostgreSQL; no hay datos simulados en la interfaz.

| Ruta | Método | Función |
|---|---|---|
| `/horarios` | GET / POST | Listar y filtrar por médico/día / registrar |
| `/horarios/nuevo` | GET | Formulario de registro |
| `/horarios/{id}` | GET | Detalle |
| `/horarios/{id}/editar` | GET / POST | Formulario / guardar edición |
| `/horarios/{id}/estado` | GET / POST | Confirmar / desactivar o reactivar |

Administrador: todas las operaciones. Recepción: consulta. Médico vinculado: solo sus propios horarios; sin vínculo se muestra un mensaje sin horarios ajenos. Paciente: acceso denegado. Escrituras POST con CSRF y permisos comprobados en backend.

Días ISO lunes=1 a domingo=7, presentados en español. Horas de 24 horas en tramos de 30 minutos, sin cruzar medianoche. Se permiten 08:00–12:00 y 12:00–16:00; se rechaza 11:30–15:30 si se cruza con el primero. Médicos distintos pueden coincidir. Un médico inactivo no admite nuevos horarios activos ni reactivaciones. Las citas futuras programadas quedan protegidas: un cambio que las deje sin cobertura se revierte con mensaje, sin cancelarlas ni reprogramarlas.

La zona de la clínica es **America/La_Paz**, confirmada explícitamente antes de actualizar APP_TIMEZONE local y el ejemplo. No se reescribieron fechas ni se cambió la zona de PostgreSQL. CU11 no necesitó una migración adicional; 004 corresponde posteriormente a CU13. Transacciones con bloqueo de la fila del médico serializan cambios y revalidan superposiciones. El UNIQUE original también cubre horarios inactivos con igual médico/día/inicio: editar o reactivar el registro existente. El médico del horario no se cambia al editar.

`HorarioService::tramosParaFecha(medico, 'YYYY-MM-DD')` devuelve tramos activos del día para preparar CU12/CU13; no calcula turnos libres ni reserva. Inventario exacto, reglas y protocolo futuro de bloqueo: [HORARIOS_MEDICOS.md](docs/ciclo1/implementacion/HORARIOS_MEDICOS.md).

## Disponibilidad médica — CU12

Ruta autenticada de solo lectura: **GET /disponibilidad**, con medico_id, fecha YYYY-MM-DD y especialidad_id opcional. Ejemplo de forma de URL: `/disponibilidad?medico_id=1&fecha=2026-11-16`; debe corresponder a un médico activo y autorizado existente. CU12 consulta disponibilidad; la reserva corresponde a CU13.

Administrador y recepción consultan todos los médicos activos. Un médico vinculado solo consulta lo propio; sin vínculo no recibe catálogos ajenos. Paciente no accede. El formulario permite filtrar por especialidad activa, elegir médico/fecha y consultar turnos libres. El filtro se actualiza con «Filtrar médicos»; no usa JavaScript asíncrono.

Se reutiliza HorarioService::tramosParaFecha y la duración de 30 minutos. El cálculo genera turnos completos dentro de cada tramo activo, respeta descansos/cierre y descarta inicios ya transcurridos o iguales al momento actual. Usa America/La_Paz para hoy, límites del día y presentación; permite hoy y fechas futuras válidas, sin límite arbitrario de anticipación.

Las citas **programada** bloquean intervalos, coherente con GiST; **cancelada** y **atendida** no son reservas activas bajo esta política. Se detecta cualquier superposición real, incluso citas que comienzan antes del día consultado. Las consultas SQL no seleccionan nombres, identificadores ni motivos de pacientes.

La disponibilidad es informativa y puede cambiar. CU13 vuelve a comprobarla dentro de su transacción y bloquea el médico antes de leerla, conservando GiST como defensa ante carreras de reserva. **CU12 no crea ni modifica citas.** No se necesitó migración ni cambio de configuración. Detalles, archivos exactos y pruebas: [DISPONIBILIDAD_MEDICA.md](docs/ciclo1/implementacion/DISPONIBILIDAD_MEDICA.md).

Para verificar: entrar como administrador o recepción en <http://127.0.0.1:8000/login>, abrir <http://127.0.0.1:8000/disponibilidad>, elegir especialidad/médico y fecha. Si todavía no tiene horarios, registrarlos primero en CU11. La interfaz no se llena con reservas simuladas.

## Programación de citas — CU13

El servicio guarda reservas reales, con estado programada y duración fija de 30 minutos. **POST /citas** acepta paciente_id, medico_id, especialidad_id, fecha YYYY-MM-DD, hora_inicio HH:mm, motivo opcional y _csrf. Requiere sesión de administrador o recepcionista; médico/paciente reciben 403. El contrato JSON de 4B.1 se conserva (201/422/409); el formulario HTML utiliza revisión previa, POST y redirección a confirmación. GET nunca crea reservas; el listado general se consulta mediante CU14.

Abrir **GET /citas/nueva** desde «Programar cita». Pasos: buscar/seleccionar paciente activo; seleccionar especialidad y médico activos; elegir fecha y turno; revisar el resumen y confirmar. Los selectores se actualizan mediante POST de lectura con CSRF, conservando el motivo fuera de la URL. Con JavaScript se actualizan al cambiar y sin JavaScript se usa «Actualizar opciones y turnos». PHP reutiliza CU12: no se calculan turnos en el navegador.

La confirmación es **GET /citas/confirmacion/{id}**, limitada a recibos de las últimas 20 reservas HTML del actor en su sesión. Muestra código, paciente, médico, especialidad, fecha, horario y estado, sin motivo clínico. Recargar no repite el POST. Un 409 refresca los turnos y conserva los datos válidos para elegir otro; no reintenta automáticamente. El botón final se bloquea mientras se procesa, manteniendo GiST como defensa definitiva.

El código `CM-000001` deriva del id generado por PostgreSQL, con saltos permitidos. Se persisten paciente, médico, especialidad elegida, inicio, duración, motivo opcional, cuenta que registra y fecha de creación. No se aceptan estado, duración, hora final ni actor arbitrarios desde el navegador. Se valida paciente/médico activos y especialidad activa vinculada; se vuelve a consultar CU12 dentro de la transacción, con el mismo PDO.

La reserva adquiere **FOR SHARE del médico antes de consultar horarios**, compatible con FOR UPDATE de CU11. Usa READ COMMITTED y conserva la exclusión GiST: dos lectores pueden competir, pero solo una reserva incompatible confirma. El perdedor recibe conflicto comprensible, incluyendo SQLSTATE 23P01 traducido sin exponer SQL. No se agregaron restricciones por paciente/día ni se implementaron cancelación, reprogramación o asistencia.

Migración 004 aplicada con autorización explícita: `database/migrations/004_citas_especialidad.sql`, añade especialidad_id y FK a especialidades sin reescribir datos ni cambiar GiST/triggers. NULL conserva compatibilidad histórica; cada reserva nueva del servicio exige especialidad. **No repetir localmente.** En otra instalación requiere aplicar las migraciones previas y autorización antes de ejecutar `php scripts\aplicar-migracion-citas.php`.

Pruebas de backend: `php tests\citas_integration.php`, `php tests\http_citas_integration.php`, `php tests\citas_concurrency.php`. Las tres carreras reales verifican doble reserva y los dos órdenes de competencia con CU11. No se dejaron citas ficticias permanentes. Inventario exacto, modelo, errores y protocolo: [PROGRAMACION_CITAS.md](docs/ciclo1/implementacion/PROGRAMACION_CITAS.md).

## Organización del código

| Ubicación | Responsabilidad |
|---|---|
| `app/Config` | Carga de entorno, arranque de sesión, PDO y utilidades CSRF/HTML. |
| `app/Controllers` | Solicitudes HTTP y presentación de CU01–CU14. |
| `app/Models` | Usuario, códigos de pacientes/médicos/citas y convenciones de horarios. |
| `app/Repositories` | Consultas PDO para usuarios, roles, pacientes, médicos, especialidades, horarios y citas. |
| `app/Services` | Validaciones, autenticación, reglas de catálogos/horarios, disponibilidad, reserva y consulta de citas. |
| `app/Middleware` | Sesión, roles autorizados y validación CSRF. |
| `app/Views` | Acceso, panel, módulos de CU03–CU14 y errores. |
| `public` | Única raíz pública; contiene el controlador frontal y assets. |
| `database/migrations` | Cambios reproducibles y ordenados del esquema SQL. |
| `database/seeds` | Datos de catálogo seguros e idempotentes. |
| `tests` | Pruebas de integración de servicios y rutas HTTP. |
| `docs/ciclo1` | Documentación académica y mapa de casos de uso. |

## GitHub para dos integrantes

La rama local principal es **main**. El primer commit local fue autorizado con el mensaje `feat: implementar CU01-CU14 de Clínica Vida Salud (Ciclo 1)`. Todavía no hay origin ni URL remota definida; el primer push queda pendiente de autorización. Este proyecto es independiente de Panadería Santiago.

Por decisión del usuario, el repositorio de GitHub será **público** para que la ingeniera y los compañeros consulten el código mediante su URL o QR. Crearlo vacío con el nombre `clinica-vida-salud`, sin inicializar README/.gitignore/licencia. Invitar al compañero mediante Settings → Collaborators → Add people para que pueda contribuir. La URL del código y la de la aplicación desplegada son distintas; GitHub Pages no ejecuta el backend PHP/PostgreSQL.

Usar ramas por tarea y Pull Requests revisados por el compañero antes de integrar en main. No compartir .env ni el contenido real de PostgreSQL. Guía completa con instalación para el segundo integrante, migraciones, ramas, commits, PR y actualización segura: [GITHUB_TRABAJO_EN_EQUIPO.md](docs/ciclo1/GITHUB_TRABAJO_EN_EQUIPO.md). Inspección del primer commit: [PREPARACION_GITHUB.md](docs/ciclo1/PREPARACION_GITHUB.md).

## Pruebas

Ejecuta desde PowerShell:

```powershell
php tests\pacientes_validation.php
php tests\pacientes_integration.php
php tests\http_pacientes_integration.php
php tests\http_auth_integration.php
php tests\medicos_validation.php
php tests\medicos_integration.php
php tests\http_medicos_integration.php
php tests\horarios_validation.php
php tests\horarios_integration.php
php tests\http_horarios_integration.php
php tests\horarios_concurrency.php
php tests\disponibilidad_integration.php
php tests\http_disponibilidad_integration.php
php tests\citas_integration.php
php tests\http_citas_integration.php
php tests\citas_concurrency.php
php tests\http_citas_formulario.php
php tests\citas_browser.php
php tests\consulta_citas_integration.php
php tests\http_consulta_citas.php
php tests\consulta_citas_browser.php
php tests\auditoria_ciclo1_http.php
php tests\mejoras_6a_validation.php
php tests\mejoras_6a_integration.php
php tests\http_mejoras_6a.php
php tests\mejoras_6a_browser.php
```

Las validaciones no necesitan conexión. Integración de pacientes usa una transacción revertida; las suites HTTP crean registros ficticios y los eliminan en `finally`. Las secuencias pueden avanzar al probar. Las pruebas de pacientes requieren migración 002. Resultados reales y límites en [`docs/ciclo1/pruebas/README.md`](./docs/ciclo1/pruebas/README.md). La suite histórica `auth_users_integration.php` requiere usuarios vacíos; no vacíes tablas para ejecutarla con la cuenta actual.

## Próximo paso

Realizar con los dos integrantes la revisión funcional final de CU01–CU14 y preparar la entrega académica, los diagramas UML y la revisión de GitHub. La integración y publicación requieren la revisión del equipo; no se realizan commits ni push automáticamente.

## Consultar citas — CU14

Abrir <http://127.0.0.1:8000/citas> después de iniciar sesión. Administrador y recepción consultan todas las citas; el médico vinculado solo su agenda, con restricciones también al abrir el detalle por ID. Una cuenta médica sin vínculo recibe un mensaje sin datos de otras agendas. Paciente recibe 403.

| Ruta | Método | Finalidad |
|---|---|---|
| `/citas` | GET | Listado con filtros y páginas de 20 citas |
| `/citas/{id}` | GET | Detalle de una cita dentro del alcance del actor |
| `/citas/nueva` | GET | Formulario CU13, administrador/recepción |
| `/citas` | POST | Reserva CU13, sesión y CSRF |
| `/citas/confirmacion/{id}` | GET | Recibo CU13 limitado a la sesión y creador |

Filtros combinables: fecha exacta, desde/hasta inclusivos, médico, especialidad, estado y código completo CM. Período: todas las fechas, próximas o pasadas. Todas/próximas se ordenan por inicio ascendente; pasadas por inicio descendente, con ID como desempate. Los filtros se conservan al paginar y regresar desde el detalle. Fecha exacta y rango se intersectan. Horas y fecha de registro en America/La_Paz.

Ejemplo: <http://127.0.0.1:8000/citas?desde=2026-10-20&hasta=2026-10-25&estado=programada>. Usar el código obtenido en el recibo para una búsqueda precisa. El detalle muestra motivo breve solo a administrador/recepción y cuenta creadora solo al administrador; el médico ve nombre del paciente y datos básicos de la cita.

Guía del código, inventario de archivos y pruebas: [CONSULTA_CITAS.md](docs/ciclo1/implementacion/CONSULTA_CITAS.md). CU13 continúa usando su servicio transaccional original y CU12 mantiene ocupado el turno reservado. CU14 solo consulta registros y no modifica su estado.

Verificación final de CU14: **136 comprobaciones nuevas y 656 de regresión, 792 aprobadas**. Sintaxis de 115 archivos PHP y scripts JavaScript correcta; revisión real en Edge de escritorio/móvil. PostgreSQL conserva los conteos presentes al iniciar 4C, incluida una cita previa, sin migraciones adicionales ni fixtures permanentes. Detalle en [registro de pruebas](docs/ciclo1/pruebas/README.md).

## Auditoría final 5A — 20/10/2026

CU01–CU14 integrados: **838 comprobaciones distintas aprobadas**, correspondientes a las 792 de regresión y 46 de auditoría. Se verificó por HTTP el recorrido administrador → paciente → especialidad/médico → horario → disponibilidad → reserva → turno ocupado → consulta CU14 → logout y bloqueo privado. Se corrigieron el contexto de navegación de CU03 y un 500 ante mensajes enviados como arreglo. Sintaxis de 116 archivos PHP y tres scripts JS/MJS correcta. Las ocho tablas conservan sus conteos y huellas de contenido; los fixtures se retiraron.

Informe con matriz de casos de uso, rutas, relaciones, permisos, seguridad y archivos: [REVISION_FINAL_CICLO1.md](docs/ciclo1/REVISION_FINAL_CICLO1.md). Requisitos de infraestructura y cambios condicionados al destino: [PREPARACION_DESPLIEGUE.md](docs/ciclo1/PREPARACION_DESPLIEGUE.md).

En la auditoría 5A, Git se encontraba en master, sin commits ni remoto; la preparación posterior 5B utiliza main y el mensaje indicado en la sección GitHub. `.env` y artefactos permanecen ignorados. No se ejecutó commit, push ni despliegue. Pendientes para el 25/10: revisión conjunta, documentación académica/UML, repositorio remoto, alojamiento compatible PHP/PostgreSQL, URL HTTPS y QR. La instalación desde cero se revisó documentalmente, sin crear otra base; el despliegue real sigue sin probarse.

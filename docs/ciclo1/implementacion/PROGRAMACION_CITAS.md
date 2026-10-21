# CU13 — Programación de citas (Prompts 4B.1 y 4B.2)

Implementado el 20/10/2026, únicamente Ciclo 1. El backend persiste reservas, revalida disponibilidad y protege concurrencia. La interfaz 4B.2 añade formulario, revisión previa y recibo por PRG reutilizando el mismo servicio. CU14 se completó posteriormente en [CONSULTA_CITAS.md](CONSULTA_CITAS.md); las referencias a CU14 pendiente más abajo describen el estado histórico de 4B.1/4B.2.

## Archivos

Creados:

- `app/Models/Cita.php`: código público estable derivado del id.
- `app/Repositories/CitaRepository.php`: consultas parametrizadas y bloqueos de registros de reserva; INSERT con lista explícita de columnas.
- `app/Services/CitaService.php`: reglas, transacción, revalidación de CU12 y creación atómica.
- `app/Services/CitaException.php`: errores públicos controlados y traducción segura de SQLSTATE.
- `app/Controllers/CitasController.php`: endpoint POST autenticado/CSRF, respuestas JSON, sin formularios ni transiciones.
- `database/migrations/004_citas_especialidad.sql`.
- `scripts/aplicar-migracion-citas.php`.
- `tests/CitasPrueba.php`: composición de fixtures y servicios existentes.
- `tests/citas_integration.php`.
- `tests/http_citas_integration.php`.
- `tests/citas_concurrency.php`.
- `docs/ciclo1/implementacion/PROGRAMACION_CITAS.md`.

Modificados:

- `public/index.php`: despacho de POST /citas y métodos no admitidos.
- `app/Services/CatalogoValidacion.php`: únicamente comentario para reflejar su reutilización de validación de texto; no cambia reglas previas.
- `README.md`.
- `tests/README.md`.
- `docs/ciclo1/MAPA_DEL_CODIGO.md`.
- `docs/ciclo1/DECISIONES_TECNICAS.md`.
- `docs/ciclo1/analisis/DECISIONES_TECNICAS.md`.
- `docs/ciclo1/implementacion/README.md`.
- `docs/ciclo1/pruebas/README.md`.

## Modelo y migración 004

Se inspeccionaron las migraciones 001–003 y el esquema real antes de desarrollar. La tabla citas no tenía especialidad_id; derivarla de la relación actual del médico perdería la especialidad concreta elegida y cambiaría el significado histórico al modificar asociaciones. La migración 004 añade **solo citas.especialidad_id BIGINT** y FK a especialidades con ON DELETE RESTRICT. Se aplicó con autorización explícita del usuario, dentro de una transacción en clinica_vida_salud_dev local; había cero citas antes de aplicarla. No reemplaza datos, no cambia GiST/triggers y no ejecuta seeders. El script verifica host/base y limita la espera de bloqueo a 5 segundos. No repetir: no es idempotente.

La columna admite NULL para compatibilidad histórica y los fixtures previos del esquema 001; **el servicio exige una especialidad válida para toda nueva reserva**. No se infiere ni rellena una especialidad histórica. Se referencia el catálogo, no la tabla puente: retirar una asociación no borra citas. Las reglas de pertenencia/estado se validan bajo bloqueo en el servicio. El código y final son derivados, sin columnas adicionales ni COUNT.

| Dato | Persistencia |
|---|---|
| Código CM-000001 | Cita::codigo(id); id GENERATED ALWAYS AS IDENTITY, sin truncar valores mayores ni prometer continuidad |
| Paciente y médico | paciente_id, medico_id, FK existentes |
| Especialidad elegida | especialidad_id, FK nueva |
| Fecha/hora inicial | fecha_hora_inicio TIMESTAMPTZ, construida en America/La_Paz |
| Duración/final | duracion_minutos=30, final calculado como inicio + duración |
| Motivo opcional | motivo, máximo 500 caracteres, NULL si vacío |
| Estado inicial | programada, fijado en backend y permitido por CHECK |
| Cuenta que registra | creada_por_usuario_id, de la sesión y revalidada en el servicio |
| Creación | creada_en, valor por defecto de PostgreSQL |

Se ignoran campos enviados para cambiar id, código, hora final, duración, estado o creador. Fecha de creación proviene de PostgreSQL. No hay restricciones adicionales de paciente/día ni exclusión por paciente: el mismo paciente puede reservar con médicos diferentes en intervalos coincidentes, conforme al alcance solicitado.

## API y permisos

**POST /citas**, con formulario application/x-www-form-urlencoded y sesión válida. Campos: _csrf, paciente_id, medico_id, especialidad_id, fecha (YYYY-MM-DD), hora_inicio (HH:mm) y motivo opcional. El contrato JSON original se conserva. El formulario HTML añade _formato=html y accion para actualizar/revisar/confirmar, según el flujo descrito abajo. Desde CU14, GET /citas consulta reservas sin crearlas; otros métodos reciben 405 con Allow: GET, POST para usuarios autorizados.

Administrador y recepcionista pueden programar. Médico y paciente reciben 403 antes de procesar el cuerpo. Sin sesión o después de logout se aplica la redirección de autenticación existente; CSRF ausente, inválido o de tipo inesperado devuelve 419 con la página existente. El servicio también revalida actor activo y rol administrativo/recepción, por lo que no depende solo de ocultar controles ni del middleware.

Éxito 201 JSON: `{cita: {id, codigo, inicio, fin, estado, creada_en}}`, sin motivo ni nombres/datos del paciente. Errores del servicio JSON `{errores: {campo: mensaje}}`, 422 para validación, 409 para conflicto y 403 para falta de autorización. Los errores de sesión/CSRF conservan las respuestas previas de la aplicación; 4B.2 deberá manejar esas respuestas además del JSON. No hay un endpoint nuevo de obtención de tokens: se reutiliza el token de sesión de los formularios/layout existentes.

## Reglas RN-C01–RN-C15

- IDs positivos y tipos válidos. Paciente/médico deben existir y estar activos. Especialidad obligatoria, existente, activa y vinculada al médico.
- Fecha real YYYY-MM-DD, no pasada según la clínica. Hora HH:mm con minutos 00/30, sin segundos. Inicio estrictamente futuro; el trigger de PostgreSQL también comprueba el instante actual al insertar.
- Se reutiliza DisponibilidadService dentro de la transacción: solo acepta un inicio que siga presente entre sus turnos libres. Esto verifica tramo activo completo, grilla y cierre, descansos, médico activo, duración 30 y ausencia de superposición con programadas.
- Las canceladas no bloquean según la política existente de CU12/GiST; no se agrega un flujo para cancelarlas. Atendida no se convierte en programada ni se modifica desde el endpoint.
- Una consulta previa de CU12 no se considera garantía. Si el horario fue editado/desactivado o el turno se ocupó, se rechaza el guardado.
- Motivo opcional, texto UTF-8 de hasta 500 caracteres, normalizando espacios y rechazando tipos/caracteres de control. No se reproduce en errores ni logs.
- No se admiten escrituras parciales: fallar en cualquier paso revierte la operación. Las secuencias pueden avanzar aunque se revierta; los saltos de código son normales y no significan citas perdidas.

## Transacción y orden de bloqueos

La entrada reutilizable es `CitaService::crear(array $entrada, int $actorId): array`. El actorId es un valor de contexto del servidor, no un campo POST. El servicio realiza validación estructural sin escrituras, inicia una transacción READ COMMITTED READ WRITE y crea un savepoint. Si ya existe una transacción externa, exige escritura READ COMMITTED y no la confirma ni la termina: usa rollback al savepoint para revertir su propia operación. Una transacción REPEATABLE READ antigua no sirve como validación final de reserva.

Orden de adquisición: **médico FOR SHARE → especialidad FOR SHARE → asociación FOR SHARE → paciente FOR SHARE → actor FOR SHARE**. Se mantienen los bloqueos hasta el commit de la operación o del llamador externo. No se toma un nuevo bloqueo consultivo ni se mezcla un orden de advisory locks contrario al catálogo. El trigger existente valida médico antes que paciente, compatible con este orden.

El bloqueo SHARE del médico se obtiene **antes** de leer los horarios y consultar CU12, en la misma conexión PDO. CU11 toma FOR UPDATE sobre ese médico: no puede modificar sus tramos durante una reserva. Si CU11 confirmó primero, la reserva espera y vuelve a leer los horarios actualizados; si confirmó primero la reserva, CU11 espera y después su protección de citas impide invalidarla. Los bloqueos de especialidad/asociación/paciente/actor mantienen sus estados y pertenencia mientras se guarda la cita.

Dos reservas usan SHARE compatibles: ambas podrían ver libre el mismo intervalo. El INSERT conserva la exclusión GiST `citas_medico_sin_superposicion` del médico y rango temporal semiabierto [inicio,fin), únicamente para programada. La segunda inserción espera la resolución de la primera; si esta confirma, PostgreSQL genera **23P01**. El servicio revierte y devuelve 409 con «El turno ya no está disponible. Consulta otro horario». No se reintenta automáticamente ni se deja una transacción abierta. Las reservas consecutivas no se superponen.

## Errores de integridad

CitaException traduce 23P01/23505 a conflicto, 23503 a referencias no válidas y 23514/23502 a condiciones de datos no cumplidas. 40P01, 40001 y 55P03 indican que hubo un cambio concurrente y debe consultarse nuevamente; no se reintenta ciegamente. Otros errores PDO devuelven indisponibilidad temporal 503. No se incluye el mensaje SQL original, consultas, credenciales ni datos de pacientes. El SQLSTATE se conserva solo como metadato interno para las pruebas/diagnóstico, no se publica por el controlador.

## Pruebas reales

- Integración: cita válida, campos persistidos, código único, estado/duración/actor confiables, entradas malformadas y pacientes/médicos/especialidades inválidos, pasado, descansos/fuera de grilla, duplicados, consecutivas y otro médico, horarios editados/desactivados, CU11 protegiendo una reserva, CU12 actualizado y rollback. Todo en transacción revertida.
- HTTP: sesiones de cuatro roles, permisos, CSRF, GET sin escritura, 201/409/422, persistencia exacta, privacidad, métodos rechazados y logout. Fixtures temporales rastreados por ID y eliminados en finally; no se dejan citas permanentes ni se alteran registros previos.
- Concurrencia real: dos procesos PHP y dos sesiones PostgreSQL. En la primera carrera, el padre guarda una cita sin confirmar; el hijo obtiene SHARE compatible, revalida sin ver la cita no confirmada e intenta insertar. Se observa wait_event_type=Lock y wait_event=transactionid en pg_stat_activity; tras el primer commit, el hijo recibe 23P01 real traducido a 409. Solo queda una cita y ambas conexiones quedan sin transacción. También se ejecutan las dos carreras contra CU11 descritas arriba. Fixtures eliminados al terminar.

Resultados numéricos y regresión en `docs/ciclo1/pruebas/README.md`. En 4B.1 no había interfaz ni prueba visual; las pruebas reales de navegador de 4B.2 se describen abajo.

Resultado final: integración **41**, HTTP **35**, concurrencia **13**: **89 comprobaciones nuevas**, más **515 de regresión**, total **604 aprobadas**. Sintaxis de **102 archivos PHP** y JavaScript correctas. La base terminó con los mismos conteos previos: dos usuarios, un paciente, un médico, tres especialidades, un horario y cero citas. No quedaron reservas ficticias permanentes.

## Interfaz 4B.2 — rutas, flujo y seguridad

| Ruta | Método | Finalidad |
|---|---|---|
| `/citas/nueva` | GET | Formulario administrativo, administrador/recepción |
| `/citas` | POST | Contrato JSON existente o flujo HTML, siempre CSRF |
| `/citas/confirmacion/{id}` | GET | Recibo autenticado de una reserva creada en la sesión por ese actor |

El formulario tiene cuatro grupos: paciente; especialidad/médico; fecha/turno; motivo/revisión. La búsqueda reutiliza PacienteRepository::listar con un filtro opcional soloActivos, falso por defecto para conservar CU04–CU06. Resultados de hasta 20 por página por nombre, apellido o CI. La selección muestra código PAC y nombre; un paciente seleccionado puede conservarse aunque no esté en la página actual, siempre que siga activo. Inactivos no se ofrecen ni se aceptan al reservar.

Especialidades y médicos provienen de DisponibilidadRepository, activos y asociados. Los cambios de paciente/especialidad/médico/fecha envían el formulario al servidor para actualizar opciones y tramos mediante CU12; JS no fabrica horarios ni hace SQL. Sin JS se usa «Actualizar opciones y turnos». El POST de actualización exige CSRF pero no guarda citas; conserva motivo y selección en el cuerpo, fuera de la URL. Se distinguen médico sin horarios registrados, día sin atención, turnos ocupados/transcurridos, fecha pasada y falta de médicos/campos.

El botón Revisar reserva envía accion=previsualizar. El servidor valida entradas con CitaService::validar, comprueba paciente/selección y disponibilidad actual, y muestra un resumen de paciente, médico, especialidad, fecha y hora. Todavía no hay escritura. Volver y corregir conserva los campos mediante otro POST, incluido el motivo. Confirmar envía accion=confirmar al servicio original, que revalida todo dentro de la transacción de 4B.1. Esta revisión previa no garantiza el turno.

Éxito HTML: respuesta 303 a `/citas/confirmacion/{id}`. Recargar ese GET no crea otra cita. La sesión guarda solo los IDs de los últimos 20 recibos HTML; además, la consulta de confirmación exige creada_por_usuario_id igual al actor. Otra sesión, incluso de otro administrador, no puede consultar ese recibo. Médico/paciente no pueden acceder a ninguna pantalla administrativa de reserva. No es un listado ni una búsqueda general de CU14. La confirmación muestra código, paciente, médico, especialidad, fecha, inicio/final y estado, sin motivo clínico.

Conflicto 409: vuelve al formulario con aviso, datos válidos y motivo conservados, pero recalcula los turnos y elimina el ocupado de las opciones. No elige ni reintenta otro turno. Errores de negocio 422 se muestran de forma comprensible; se conservan las pantallas comunes 419/403 para CSRF/permisos. Un permiso revocado durante la reserva tampoco devuelve selectores con datos. Fallos inesperados siguen el manejo seguro existente sin SQL visible.

`citas.js` gestiona la actualización por cambio y los avisos de procesamiento. En el formulario final bloquea envíos adicionales y deshabilita el botón; pageshow lo restablece al volver desde caché de navegación. Esto complementa, sin modificar, CitaService y GiST. Todas las salidas y campos ocultos usan e(). No se agregó biblioteca, migración ni endpoint asíncrono público.

### Archivos de 4B.2

Creados: `app/Views/citas/form.php`, `revisar.php`, `campos.php`, `confirmacion.php`; `public/assets/js/citas.js`; `tests/http_citas_formulario.php`; `tests/citas_browser.php`; `tests/citas_browser.mjs`.

Modificados: `app/Controllers/CitasController.php`; `app/Repositories/PacienteRepository.php`; `app/Repositories/CitaRepository.php` (solo lectura restringida de recibo); `public/index.php`; `app/Views/layout.php`; `app/Views/dashboard.php`; `public/assets/css/app.css`; `.gitignore`; `README.md`; `tests/README.md`; `docs/ciclo1/MAPA_DEL_CODIGO.md`; `docs/ciclo1/implementacion/README.md`; este documento y `docs/ciclo1/pruebas/README.md`. No se cambió la lógica de CitaService ni las restricciones PostgreSQL.

### Verificación real y uso

Pruebas nuevas: 37 HTTP y 15 en Edge headless real (Node 24 con DevTools/WebSocket nativo, sin librerías). Se completó login, formulario con actualización real de selectores, ocho turnos de CU12, revisión, doble intento final y recarga de recibo. La base tuvo una sola reserva ficticia en ese flujo. Se capturaron e inspeccionaron formulario de escritorio (1440 px), móvil (390 px) y confirmación móvil; ambos tamaños sin desbordamiento horizontal y turnos en dos columnas en móvil. Capturas con datos ficticios en tests/artifacts/citas, ignoradas por Git. Perfil de navegador temporal separado del perfil del usuario, con limpieza verificada. No se usaron credenciales reales ni se imprimieron contraseñas de prueba.

Las 604 comprobaciones previas continúan aprobadas, incluidas las tres carreras reales de reservas/CU11. Las suites limpian únicamente sus fixtures y no dejan citas permanentes. Las secuencias pueden avanzar. Se mantiene el servidor existente del puerto 8000.

Resultado final de 4B.2: **52 comprobaciones nuevas + 604 de regresión = 656 aprobadas**. Sintaxis de **108 archivos PHP** y los scripts JavaScript correcta. Conteos finales iguales a los iniciales: dos usuarios, un paciente, un médico, tres especialidades, un horario y cero citas. No se cambió el esquema ni la configuración local.

Para usar: si el servidor no está iniciado, desde la raíz ejecutar `php -S 127.0.0.1:8000 -t public`. Entrar como administrador o recepción y abrir http://127.0.0.1:8000/citas/nueva. Buscar/seleccionar paciente activo, elegir especialidad y médico, seleccionar fecha con atención y turno, escribir motivo opcional, pulsar Revisar reserva y Confirmar y registrar cita. El recibo muestra el código y permite Programar otra cita. No se dejaron turnos/citas simulados para llenar la aplicación; si no hay atención ese día, revisar los horarios de CU11.

CU13 queda completo. Antes de CU14 solo resta la revisión del flujo con el equipo y acordar el alcance de consulta general; no se implementó automáticamente. Sin commits, push ni publicación.

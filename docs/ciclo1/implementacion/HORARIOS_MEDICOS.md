# CU11 — Configurar horarios de atención médica

Entrega Prompt 3B del 20/10/2026, únicamente Ciclo 1. Administra tramos semanales reales de PostgreSQL. No calcula turnos libres ni implementa disponibilidad, programación o consulta general de citas (CU12–CU14), ni funciones del Ciclo 2.

## Esquema y zona horaria

Reutiliza `horarios_atencion`: id, medico_id, dia_semana, hora_inicio, hora_fin, activo y creado_en. Mantiene la FK a medicos, CHECK de días 1–7, CHECK inicio < fin y UNIQUE(medico_id,dia_semana,hora_inicio), incluidos registros inactivos. No inventa columnas de duración o actualización: la duración de 30 minutos coincide con CHECK citas_duracion_ciclo1, en migración 001. HorarioAtencion contiene la constante compartida del módulo.

No se necesita ni se aplicó migración 004. La estrategia de concurrencia usa la fila existente de medicos como punto de bloqueo, con transacción y savepoint. Ningún esquema ni dato anterior fue reemplazado. La inspección encontró inicialmente cero horarios, cero superposiciones, cero horas fuera de grilla y cero citas futuras; había dos usuarios, un paciente, un médico y tres especialidades, que se conservan.

El usuario confirmó explícitamente America/La_Paz antes de modificar APP_TIMEZONE en `.env` local y `.env.example`. No se cambió la zona del servidor PostgreSQL ni se reescribieron fechas. Las horas semanales TIME sin zona son horas de pared de la clínica. Las citas TIMESTAMPTZ se convierten a la zona de la clínica para comprobar día y cobertura. Los días siguen ISO: lunes=1, martes=2, miércoles=3, jueves=4, viernes=5, sábado=6 y domingo=7, equivalente a PHP format('N') y PostgreSQL extract(isodow).

## Rutas y permisos

| Ruta | Método | Acceso y finalidad |
|---|---|---|
| `/horarios` | GET | Administrador/recepción: listado paginado y filtros; Médico: solo su registro vinculado |
| `/horarios/nuevo` | GET | Administrador; formulario, acepta `?medico=id` como preselección |
| `/horarios` | POST | Administrador; registrar activo |
| `/horarios/{id}` | GET | Administrador/recepción; Médico únicamente si el horario es propio |
| `/horarios/{id}/editar` | GET / POST | Administrador; formulario / guardar |
| `/horarios/{id}/estado` | GET / POST | Administrador; confirmar / desactivar o reactivar |

Filtros GET `medico`, `dia` (ISO) y `pagina`. Orden médico/día/inicio/id; páginas de 20 y estados visibles. El detalle de médico incluye su sección de horarios, paginada mediante horarios_pagina. Se muestran nombre/especialidades, día, tramo 24 horas, duración y estado. Las vistas reutilizan layout, CSS y tabla responsive existentes.

| Operación | Administrador | Recepcionista | Médico | Paciente |
|---|---|---|---|---|
| Consulta de horarios | Todos | Todos | Solo propios con usuario_id vinculado | No |
| Registrar/editar/cambiar estado | Sí | No | No | No |

El médico no puede forzar otro filtro ni entrar a un detalle ajeno por URL: responde 403. Sin vínculo, el listado presenta un mensaje y no consulta el catálogo general; un detalle directo se deniega. Las autorizaciones usan AuthMiddleware y el usuario/rol revalidados de PostgreSQL. Paciente recibe 403 y sin sesión se redirige a login. Formularios administrativos GET también comprueban rol. Todas las escrituras requieren POST y CSRF; no existe DELETE. Salidas escapadas con e(), entradas tipadas y PDO parametrizado.

## Reglas RN-H01–RN-H12

- El médico debe existir. Solo se crean horarios activos para médicos activos. Un horario inactivo puede editarse conservando su estado; reactivarlo exige médico activo y todas las validaciones.
- Horas obligatorias HH:mm de 00:00 a 23:30, minutos 00 o 30. Fin estrictamente posterior a inicio; al menos 30 minutos y sin cruzar medianoche. Se rechazan segundos y días fuera de 1–7.
- Intervalos semiabiertos [inicio,fin): hay solapamiento si existente.inicio < nuevo.fin y existente.fin > nuevo.inicio, para el mismo médico/día y solo activos, excluyendo el id editado. Los consecutivos no chocan; médicos distintos pueden coincidir.
- Ejemplos válidos: lunes 08:00–12:00 y 12:00–16:00 del mismo médico; 08:00–12:00 para otro médico. Inválidos: 11:30–15:30 si ya existe 08:00–12:00; 12:00–08:00; 08:00–08:00; 08:15–12:00; día 8.
- El médico del horario es inmutable en edición. Para otro médico se crea un registro separado. No se aceptan identificadores internos arbitrarios ni estado/duración enviados para cambiar el alta por defecto.
- El UNIQUE original también abarca inactivos: no se crea otra fila con el mismo médico/día/inicio. El error indica editar/reactivar la existente. No se borra ese registro ni se reemplaza su identidad.
- Desactivar conserva id y creado_en. Sus tramos quedan excluidos del servicio de horarios activos. No altera citas ni el estado del médico.
- Tras editar un horario activo o desactivarlo, dentro de la transacción se comprueba que las citas futuras programadas de los días afectados sigan cubiertas por un tramo activo, con hora inicial alineada a 30 minutos desde el inicio del tramo y fin dentro del mismo día/tramo. Se considera el día de la clínica, no el día UTC. Si falla, rollback y HTTP 422 con mensaje; no se cancela ni reprograma.
- La política es conservadora: una cita futura ya sin cobertura en uno de los días afectados también bloquea el cambio. No se ignoran inconsistencias previas ni se intenta corregirlas automáticamente. Citas pasadas, canceladas o atendidas no bloquean esta comprobación.
- HTML5/JavaScript complementan PHP con mensajes junto a campos; el servidor vuelve a verificar todo. El estado usa pantalla de confirmación previa.

## Transacciones, concurrencia y futuras reservas

HorarioService inicia una transacción (o savepoint si ya hay una), bloquea medicos con SELECT FOR UPDATE, vuelve a leer el horario al editar y comprueba solapamientos antes de guardar. Todas las altas, ediciones y cambios de estado del mismo médico compiten por esa fila. READ COMMITTED permite que quien esperaba lea el primer commit antes de decidir. Errores de UNIQUE se traducen a validación comprensible, sin detalles internos; el savepoint permite continuar pruebas en una transacción externa.

El trigger existente de citas utiliza FOR SHARE sobre medicos: una reserva que ya obtuvo ese bloqueo obliga al cambio de horario a esperar y, después, a comprobar la cita confirmada. Para CU13, el futuro servicio deberá iniciar su transacción, bloquear el médico con FOR SHARE **antes de leer/validar horarios** y mantenerlo hasta insertar la cita y confirmar. No basta con comprobar horarios fuera de la transacción y esperar al trigger al final. No se implementó esa reserva en CU11.

La garantía contra solapamientos de horarios corresponde al protocolo de servicios, no a una nueva exclusión SQL. Cualquier futuro escritor de horarios debe usar HorarioService o respetar el mismo bloqueo y validaciones. SQL manual que ignore el servicio puede saltarse la regla de solapamiento; los CHECK/UNIQUE originales siguen vigentes. No se ha alterado el esquema sin autorización.

## Interfaz para CU12/CU13

`HorarioService::tramosParaFecha(int $medico, string $fecha): array` recibe YYYY-MM-DD, valida la fecha con zona de la clínica y convierte a ISO N. Devuelve filas activas del día ordenadas por inicio, o [] si el médico está inactivo/no tiene tramos; un médico inexistente provoca OutOfBoundsException. Excluye horarios inactivos y no calcula huecos libres, no consulta ocupación ni crea reservas. No es una ruta pública: el futuro controlador deberá aplicar permisos y el servicio de reserva el protocolo transaccional descrito arriba.

## Archivos creados

- `app/Config/RutasHorarios.php`: despacho de rutas CU11, métodos y errores 404/405.
- `app/Controllers/HorariosController.php`: HTTP, permisos y alcance por médico vinculado.
- `app/Models/HorarioAtencion.php`: días ISO, duración y zona configurada.
- `app/Repositories/HorarioRepository.php`: SQL de horarios, filtros, bloqueo, superposición y cobertura de citas.
- `app/Services/HorarioService.php`: validaciones, transacciones y reglas RN-H.
- `app/Views/horarios/index.php`
- `app/Views/horarios/form.php`
- `app/Views/horarios/show.php`
- `app/Views/horarios/status.php`
- `app/Views/horarios/tabla.php`
- `scripts/inspeccionar-horarios.php`: inspección de solo lectura y conteos sin secretos.
- `tests/HorariosFixture.php`: creación/limpieza rastreada de datos ficticios.
- `tests/horarios_validation.php`
- `tests/horarios_integration.php`
- `tests/http_horarios_integration.php`
- `tests/horarios_concurrency.php`
- `docs/ciclo1/implementacion/HORARIOS_MEDICOS.md`

## Archivos modificados

- `.env` local (solo APP_TIMEZONE, con confirmación; sigue ignorado por Git).
- `.env.example` (zona horaria confirmada, sin secretos).
- `app/Controllers/CatalogoController.php` (lista opcional de roles de consulta; preserva los valores anteriores).
- `app/Controllers/MedicosController.php` (consulta paginada de horarios en detalle).
- `app/Views/medicos/show.php`
- `app/Views/layout.php`
- `app/Views/dashboard.php`
- `public/index.php`
- `public/assets/js/app.js`
- `README.md`
- `tests/README.md`
- `tests/http_auth_integration.php` (arranque directo del proceso PHP para cerrar correctamente el servidor de pruebas en Windows).
- `docs/ciclo1/MAPA_DEL_CODIGO.md`
- `docs/ciclo1/implementacion/README.md`
- `docs/ciclo1/pruebas/README.md`
- `docs/ciclo1/analisis/DECISIONES_TECNICAS.md` (ruta técnica ya existente; no se duplica el documento).

## Pruebas reales y arranque

Ejecutadas: 17 validaciones sin conexión; 33 comprobaciones de integración revertidas; 65 HTTP con servidor PHP real; 4 de concurrencia con dos procesos PHP y dos sesiones PostgreSQL. La prueba de concurrencia deja la primera alta sin commit, inicia la segunda, comprueba wait_event_type=Lock en pg_stat_activity y confirma la primera. La segunda continúa, rechaza la superposición y queda un solo horario activo. Los datos ficticios se limpian al terminar, conservando los previos; las secuencias pueden avanzar.

Regresión CU01–CU10: 304 comprobaciones aprobadas en las siete suites existentes. Total CU11 + regresión: 423 comprobaciones aprobadas. Sintaxis: 86 archivos PHP y JavaScript correctos. El ejecutor histórico de pruebas HTTP se ajustó para lanzar directamente PHP: el servidor temporal ya no queda abierto por cerrar solamente cmd.exe en Windows. No se modificó la autenticación de la aplicación. Detalles en `docs/ciclo1/pruebas/README.md`. No se efectuaron capturas ni verificación visual en navegador; queda pendiente escritorio/móvil y ejecución de JavaScript en navegador.

Desde la raíz: `php -S 127.0.0.1:8000 -t public`, entrar por http://127.0.0.1:8000/login y abrir http://127.0.0.1:8000/horarios. Administrador configura; recepción consulta; un médico vinculado consulta lo propio. El módulo parte de datos reales y no simula horarios.

Antes de CU12–CU14: revisar interfaz visual, confirmar los flujos con el equipo y aplicar el protocolo de bloqueo en el futuro servicio de reservas. No se avanzó automáticamente. Sin commits ni push.

# Decisiones técnicas iniciales

## Cierre de auditoría 5A — 20/10/2026

Se conserva MVC y el esquema 001–004; no se agregan funciones del Ciclo 2. Correcciones demostradas: layout valida la clave GET mensaje antes de consultar el catálogo (el tipo array producía 500) y CU03 separa usuarioSesion, identidad autenticada, de usuario, cuenta objetivo de edición. La autorización del servidor sigue usando el actor original. Prueba nueva de 46 comprobaciones y batería previa de 792 aprobadas; conteos y huellas de contenido de ocho tablas conservados. Los cambios solo afectan presentación/robustez y soporte de pruebas; CitaService, bloqueos y GiST permanecen intactos.

La instalación se documenta con PHP 8.4, PDO_PGSQL, mbstring, sesiones y PostgreSQL 17/btree_gist, aplicando 001→002→003→004→seed únicamente sobre una base nueva autorizada. No se creó ni recreó una base para auditar. Preparación de GitHub sin añadir al índice, commit, remoto ni push; revisión de secretos y artefactos sobre los archivos elegibles.

El despliegue público requiere servidor PHP de producción con raíz public, HTTPS, entorno privado, sesiones privadas, política TLS verificada de PostgreSQL y permisos de base acotados. El código no impone sslmode ni interpreta DATABASE_URL; libpq permite PGSSLMODE/PGSSLROOTCERT sin modificar el DSN, sujeto a verificación del alojamiento. No se anticipa contenedor, proveedor ni cambio de conexión sin destino elegido. URL/QR y validación del despliegue siguen pendientes. Informe: `../REVISION_FINAL_CICLO1.md`; requisitos: `../PREPARACION_DESPLIEGUE.md`. Las secciones siguientes conservan la evolución histórica.

## Citas de 30 minutos y concurrencia

El esquema fija citas en intervalos de 30 minutos (`duracion_minutos` con una restricción `CHECK`). Es una decisión inicial para simplificar la disponibilidad del Ciclo 1; debe confirmarse con los requisitos de la clínica antes de ofrecer reservas reales.

La restricción de exclusión GiST sobre el médico y el rango `[inicio, fin)` impide que dos citas programadas del mismo médico se solapen, incluso entre transacciones concurrentes. `btree_gist` proporciona igualdad GiST para el identificador del médico. Los intervalos semiabiertos permiten que una cita empiece exactamente cuando termina la anterior. Si PostgreSQL rechaza una inserción por la restricción, la aplicación debe responder como conflicto de disponibilidad y no reintentar sin volver a consultar.

El trigger valida en el servidor que una cita programada sea futura y que médico y paciente estén activos; bloquea esas filas con `FOR SHARE` durante la transacción para coordinarse con cambios concurrentes de estado. Las claves foráneas conservan integridad referencial. La validación de que la cita cae dentro de un horario activo corresponde también al servicio de disponibilidad del servidor: el flujo de programación aún no se implementa. Ese servicio deberá validar el día y el intervalo horario y crear la cita dentro de una transacción; la restricción de exclusión queda como última defensa contra carreras de reserva.

Las desactivaciones son lógicas (`activo = FALSE`), y las relaciones históricas usan `ON DELETE RESTRICT`; por ello, desactivar no borra pacientes, médicos ni citas. No se incluyen todavía reglas de reprogramación, cancelación operativa o asistencia.

## Acceso y datos de prueba

La base de datos no contiene cuentas de demostración ni contraseñas predeterminadas. Los seeds solo agregan roles y especialidades de catálogo de forma idempotente. La autenticación implementada guarda hashes con `password_hash()` y verifica con `password_verify()`.

La migración requiere que el usuario PostgreSQL pueda instalar `btree_gist`. Si ese privilegio no está disponible, detenerse y solicitar su habilitación; no quitar la restricción de exclusión como atajo.

## Médicos y especialidades — Prompt 3A

La migración 003 agrega solo correo_contacto a medicos y restricciones/índices normalizados de registro profesional y nombre de especialidad. Se aplicó con autorización específica del usuario el 20/10/2026; no cambia los datos base.

Código MED derivado de la identidad PostgreSQL, sin conteos. Registro profesional como texto, sin espacios y en mayúsculas, preservando ceros. Nombre de especialidad colapsa espacios y compara con lower sin quitar acentos. La unicidad está respaldada por índices únicos de PostgreSQL.

Un médico activo necesita al menos una especialidad activa. La desactivación de la última especialidad útil se rechaza hasta asignar otra o desactivar el médico. Especialidades inactivas existentes se conservan visibles; no se admiten para nuevas asignaciones. Los cambios compuestos se ejecutan mediante CatalogoTransaccion con savepoint y un bloqueo consultivo compartido por los servicios. No se borran médicos/especialidades ni se alteran citas. Retirar la fila de asociación es seguro: citas referencia al médico y no a esa fila.

La vinculación opcional utiliza usuario_id existente, cuenta activa de rol medico y UNIQUE del vínculo, comprobados en backend con bloqueo de cuenta. No crea cuentas ni concede permisos por tener un perfil médico. La administración de usuarios puede seguir cambiando estado/rol: el middleware siempre usa el rol/estado actual y el formulario médico exige corregir un vínculo que ya no sea elegible.

Administrador gestiona el módulo; recepcionista consulta médicos y su filtro de especialidades, sin acceso al catálogo administrativo. Médico y paciente no acceden a administración general. No se implementan CU11–CU14 ni Ciclo 2. Detalles: `docs/ciclo1/implementacion/MEDICOS_ESPECIALIDADES.md`.

## Horarios — Prompt 3B, CU11

Se reutiliza horarios_atencion sin migración 004: días ISO 1–7 y horas TIME de la clínica, tramos en minutos 00/30, fin posterior al inicio, sin medianoche. La duración coincide con CHECK de citas de 30 minutos. Se conserva el UNIQUE médico/día/inicio, también para inactivos; se edita o reactiva el registro en vez de duplicarlo. El médico del horario es inmutable en edición.

La zona America/La_Paz se confirmó expresamente antes de actualizar APP_TIMEZONE en .env local y .env.example. No se cambiaron zonas de PostgreSQL ni fechas existentes. Las citas se convierten desde TIMESTAMPTZ para comprobar el día/hora local; el horario semanal no es UTC.

Superposición: intervalos [inicio,fin), comparando inicio<fin_existente y fin>inicio_existente, mismo médico/día, solo activos y excluyendo id editado. Se bloquea la fila medicos mediante FOR UPDATE dentro de una transacción antes de comprobar/guardar. Los cambios concurrentes del mismo médico se serializan; las reglas del servicio cubren alta/edición/reactivación. No se añadió una exclusión de base, por lo que los futuros escritores deben pasar por HorarioService o respetar el protocolo.

Tras editar/desactivar se comprueba cobertura de todas las citas futuras programadas de los días afectados con el estado propuesto; si alguna queda fuera de tramo o grilla, se revierte sin cancelar/reprogramar. El bloqueo se coordina con FOR SHARE del trigger de citas. Para CU13 será obligatorio adquirir ese FOR SHARE antes de consultar/validar horarios y mantenerlo hasta confirmar la reserva. CU11 solo expone tramosParaFecha, sin implementar disponibilidad ni reserva.

Administrador configura; recepción consulta; médico solo consulta sus horarios mediante usuario_id, y sin vínculo no recibe datos ajenos; paciente no accede. Documentación detallada: `docs/ciclo1/implementacion/HORARIOS_MEDICOS.md`. Las menciones a CU11 pendiente en las entregas anteriores son históricas; CU12–CU14 siguen pendientes.

## Disponibilidad — Prompt 4A, CU12

Se reutiliza HorarioService::tramosParaFecha y la duración fija de 30 minutos. El nuevo DisponibilidadService mantiene el cálculo fuera de HTML/controladores; los selectores de especialidad activa y médico activo se validan también en backend. Hoy y fechas futuras válidas están permitidos, sin un límite de anticipación añadido. Inicio <= ahora no se ofrece. Se mantiene America/La_Paz sin cambios de configuración.

Solo programada ocupa disponibilidad, coherente con el predicado de la exclusión GiST. Cancelada y atendida no son reservas activas. El servicio no corrige estados, no crea citas y no muestra datos privados de pacientes: el repositorio de ocupaciones selecciona únicamente fecha_hora_inicio/duracion_minutos. La búsqueda por intersección con el día local incluye reservas iniciadas antes de ese día. Candidatos dentro de cada tramo, sin sobrepasar cierre ni cruzar descansos, se descartan si se cruzan con una programada usando intervalos [inicio,fin).

Una consulta autónoma usa una instantánea REPEATABLE READ READ ONLY para que horarios/ocupación sean coherentes. Si el llamador tiene una transacción, el servicio la conserva. Esta consulta no garantiza un turno futuro. CU13 deberá usar una transacción READ COMMITTED, bloquear medicos FOR SHARE antes de leer/revalidar disponibilidad y mantener el bloqueo hasta insertar/confirmar, respetando GiST ante otra reserva concurrente. No reutilizar una instantánea antigua como garantía de reserva. CU11 continúa usando FOR UPDATE del médico.

GET /disponibilidad es autenticado y no realiza escrituras. Administrador/recepción consultan todos los activos, médico vinculado solo su registro y especialidades, sin vínculo no recibe datos ajenos, paciente no accede. No se añadió API JSON ni lógica asíncrona innecesaria. No hay migración para CU12 ni cambios de CU13/CU14 o Ciclo 2. Detalles y ejemplos en `implementacion/DISPONIBILIDAD_MEDICA.md`.

## Reservas — Prompt 4B.1, backend CU13

La reserva persiste la especialidad elegida mediante citas.especialidad_id, añadida por migración 004 con autorización explícita, sin modificar datos ni GiST/triggers. Nullable para compatibilidad histórica, obligatoria para nuevas reservas del servicio. FK al catálogo con borrado restringido, no a la tabla puente: retirar una asociación no altera la especialidad histórica. Código CM derivado de identidad; final calculado desde duración fija de 30 minutos, sin nuevas columnas derivadas ni conteos.

CitaService abre READ COMMITTED READ WRITE y adquiere FOR SHARE del médico antes de consultar CU12, compatible con FOR UPDATE de CU11. Orden: médico, especialidad, asociación, paciente y actor; todos se revalidan bajo bloqueo. No se agregan advisory locks ni un orden contrario al catálogo. CU12 se ejecuta con el mismo PDO y la transacción existente, sin confirmar una transacción ajena. Si el llamador tiene una transacción, debe ser escritura READ COMMITTED; el servicio usa un savepoint y revierte solo su operación. La transacción externa queda bajo responsabilidad del llamador.

Dos reservas pueden adquirir SHARE simultáneamente y ver el mismo turno libre. La exclusión GiST decide atómicamente: la segunda inserción espera la primera y recibe 23P01 si la primera confirma. Se traduce a 409 sin SQL ni datos privados y se revierte. No se reintenta automáticamente. Se ejecutó la carrera real, observando espera transactionid, una sola cita y ausencia de transacciones abiertas. También se probaron ambas carreras reserva/cambio de horario de CU11.

POST /citas usa sesión, roles administrador/recepcionista y CSRF; responde 201 JSON o errores de negocio controlados. Estado programada, duración, final y creador no son confiados al navegador. No se añadieron restricciones por paciente/día ni flujos de transición. La interfaz pertenece a 4B.2 y CU14/Ciclo 2 siguen pendientes. Documentación: `implementacion/PROGRAMACION_CITAS.md`.

## Formulario CU13 y consulta CU14 — Prompts 4B.2/4C

CU13 se completó con formulario, revisión sin escritura y confirmación PRG. CU14 agrega GET /citas y GET /citas/{id}; las rutas literales de CU13 se resuelven antes del ID numérico. La consulta se separa en ConsultaCitaService, reutilizando CitaRepository y la relación medicos.usuario_id de HorarioRepository. CitaService, sus bloqueos y la exclusión GiST permanecen intactos.

Administrador/recepción consultan todas las citas. Médico vinculado solo las propias: WHERE c.medico_id=:propio en listado y detalle, además de validar el filtro. Un médico sin vínculo usa alcance 0, jamás NULL administrativo. IDs ajenos e inexistentes responden igual con 404. Paciente recibe 403. El constructor ampliado del controlador no autoriza reservas médicas: crear/formulario/recibo comprueban explícitamente los roles administrativos.

Privacidad: listado sin motivo ni creador; detalle con motivo solo para administrador/recepción y correo del creador solo para administrador. El detalle médico selecciona únicamente nombre del paciente y datos básicos de la cita, incluida fecha de registro. Las columnas restringidas se excluyen de SQL, no solo de la vista. Todas las salidas usan e(). Esta política conservadora no concede acceso a historial clínico ni a contactos/CI del paciente.

Filtros preparados por fecha exacta, rango inclusivo, médico, especialidad, estado real y código CM completo; período todas/próximas/pasadas. Todas y próximas por inicio/ID ascendente; pasadas descendente. Fecha y rango se intersectan. Paginación de 20 con COUNT y JOIN, sin N+1, conservando filtros en páginas y regreso. No se admite un ORDER BY libre del cliente.

Los límites de fechas se construyen en America/La_Paz y se comparan como instantes [inicio, día siguiente), sin DATE() sobre la columna indexada. Inicio y creación TIMESTAMPTZ se convierten una vez para mostrar fecha/horas locales; el final se deriva de duración. Se incluyen catálogos inactivos y especialidades históricas aunque se retire su asociación; un especialidad_id histórico NULL se muestra Sin registrar, sin inferencias ni backfill.

Se inspeccionaron PK, índices de paciente/fecha y médico/fecha y GiST existentes. CU14 no requiere migración, índice adicional, cambios de zona de PostgreSQL ni reescritura de datos. Detalles, inventario, pruebas y guía de equipo en `implementacion/CONSULTA_CITAS.md`. Las menciones anteriores a casos pendientes documentan etapas históricas; CU01–CU14 quedan implementados y el Ciclo 2 permanece fuera del alcance.

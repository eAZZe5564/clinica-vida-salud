# CU12 — Consultar disponibilidad médica

Prompt 4A, 20/10/2026. Consulta real de PostgreSQL, de solo lectura. No implementa CU13, CU14 ni Ciclo 2. No se crearon migraciones, no se cambiaron configuraciones persistentes y no quedan citas ficticias permanentes.

## Archivos y responsabilidades

Creados:

- `app/Controllers/DisponibilidadController.php`: sesión/roles existentes, validación de parámetros GET, alcance del médico vinculado, filtros y presentación.
- `app/Services/DisponibilidadService.php`: validación, fecha/reloj de clínica, lectura coherente y cálculo único reutilizable de intervalos.
- `app/Repositories/DisponibilidadRepository.php`: médicos/especialidades activos, pertenencia y consulta parametrizada de intervalos ocupados, sin datos de pacientes.
- `app/Views/disponibilidad/index.php`: especialidad opcional, médico, fecha, mensajes por campo y turnos libres, sin acciones de reserva.
- `tests/disponibilidad_integration.php`: escenarios de cálculo y validación con transacción revertida y reloj controlado.
- `tests/http_disponibilidad_integration.php`: resultados HTML exactos, filtros, permisos y privacidad con servidor PHP real y limpieza final de fixtures.
- `docs/ciclo1/implementacion/DISPONIBILIDAD_MEDICA.md`.
- `docs/ciclo1/DECISIONES_TECNICAS.md`: enlace al registro técnico consolidado existente en analisis.

Modificados:

- `public/index.php`: ruta autenticada de solo GET.
- `app/Views/layout.php`: navegación por rol.
- `app/Views/dashboard.php`: acceso a consulta.
- `public/assets/css/app.css`: formulario y cuadrícula adaptable de turnos.
- `README.md`.
- `tests/README.md`.
- `docs/ciclo1/MAPA_DEL_CODIGO.md`.
- `docs/ciclo1/implementacion/README.md`.
- `docs/ciclo1/analisis/DECISIONES_TECNICAS.md`.
- `docs/ciclo1/pruebas/README.md`.

No se modificaron autenticación, CSRF, esquema, estados, restricciones ni funciones de CU01–CU11. No se cambió JavaScript: se usan formularios GET con recarga normal, HTML5 y mensajes del servidor. No hay estado de carga asíncrono que simular.

## Ruta y formulario

`GET /disponibilidad`, por ejemplo `/disponibilidad?medico_id=1&fecha=2026-11-16`. El ejemplo solo funciona si ese médico existe/está activo y el usuario está autorizado. Parámetros: medico_id, fecha YYYY-MM-DD y especialidad_id opcional. No se agregó una API JSON ni endpoints públicos. POST/PUT/DELETE devuelven 405 para usuarios autorizados; sin sesión redirige a login y rol prohibido recibe 403. No necesita CSRF para esta lectura: no realiza escrituras.

La página inicial presenta un formulario. «Filtrar médicos» envía accion=filtrar y restringe las opciones a la especialidad activa seleccionada sin exigir fecha/médico; evita conservar una selección anterior incompatible. «Consultar disponibilidad» envía accion=consultar y valida todos los parámetros. La URL directa con médico/fecha funciona sin accion. La especialidad es opcional; sin filtro se ofrecen médicos activos autorizados. Si se proporciona, se exige que esté activa y que el médico pertenezca a ella. No se agregaron límites de anticipación: se admiten hoy y fechas futuras válidas, sin un máximo arbitrario de días.

## Tablas y política de estados

Reutiliza medicos, especialidades, medicos_especialidades, horarios_atencion y citas. La cuenta médica se identifica por medicos.usuario_id mediante HorarioRepository, igual que en CU11. La configuración oficial sigue APP_TIMEZONE=America/La_Paz, ya confirmada en la etapa anterior. Las migraciones 001–003 y la estructura real se inspeccionaron antes de programar.

Estados reales de citas: programada, cancelada y atendida. **Solo programada ocupa disponibilidad**, conforme al predicado de la exclusión GiST actual. Cancelada no es una reserva activa; atendida representa una cita ya completada y tampoco bloquea nuevos intervalos bajo esta política. Una atendida fechada accidentalmente en el futuro no se convierte en programada desde CU12. El módulo no corrige ni cambia estados existentes. Las citas de fechas/horas transcurridas quedan fuera por el corte temporal. Esta decisión se prueba y se documenta para mantener coherencia con PostgreSQL.

La consulta de ocupaciones selecciona únicamente fecha_hora_inicio y duracion_minutos; no consulta ni devuelve paciente_id, nombres de pacientes, motivo, datos clínicos o correos de pacientes. Solo se publican datos del profesional autorizado y los intervalos libres.

## Algoritmo y RN-D01–RN-D17

1. Validar IDs positivos y tipos, fecha real YYYY-MM-DD y fecha no anterior a hoy en la zona clínica. Rechazar médicos inexistentes/inactivos, especialidad inválida/inactiva y pertenencias incorrectas. El controlador verifica además el alcance por usuario antes de cargar opciones/resultados ajenos.
2. Obtener tramos mediante **HorarioService::tramosParaFecha**, sin duplicar la lógica semanal de CU11. PHP format('N') conserva ISO lunes=1 a domingo=7. Solo devuelve horarios y médicos activos.
3. Construir límites del día local [00:00,00:00 del día siguiente), con America/La_Paz. Consultar programadas del médico cuya fecha de inicio sea anterior al fin del día y cuyo fin sea posterior al inicio del día. Esto incluye una cita que empieza el día anterior y acaba dentro del día consultado. Se pasan instantes con desplazamiento explícito a PostgreSQL.
4. En cada tramo generar intervalos consecutivos de **HorarioAtencion::DURACION_MINUTOS (30)**, avanzando desde su inicio, solo mientras el final no exceda su cierre. Cada tramo se calcula por separado: no aparecen turnos durante descansos ni turnos que crucen el cierre. Datos heredados fuera de la grilla válida de CU11 se omiten, sin reescribirlos ni ofrecerlos como válidos.
5. Descartar inicio <= momento actual. Hoy puede consultarse, pero no se ofrecen turnos ya comenzados ni exactamente iguales al instante de cálculo, coherente con el trigger que exige fecha futura al reservar. El reloj se captura una vez para el cálculo; las pruebas pueden inyectarlo sin alterar el reloj del sistema.
6. Descartar cualquier candidato que se superponga con una programada: candidato.inicio < ocupada.fin y candidato.fin > ocupada.inicio. Intervalos semiabiertos [inicio,fin) permiten adyacencias y detectan citas desalineadas con la grilla que cruzan dos candidatos. Las citas de otros médicos no intervienen.
7. Ordenar por inicio y deduplicar. Devolver médico, fecha, zona, duración, turnos {inicio,fin} ISO 8601 con desplazamiento y bandera sin_atencion. No hay datos privados de reservas ni botones para guardarlas.

Ejemplo real de prueba: 08:00–12:00 produce ocho candidatos. Una programada 09:00–09:30 deja siete, conservando 08:30 y 09:30. Si ocupa 09:15–09:45, se excluyen tanto 09:00 como 09:30. Dos tramos 08:00–12:00 y 14:00–16:00 no generan turnos entre 12:00 y 14:00.

Sin horarios/día sin atención se muestra «El médico no tiene atención activa para ese día». Con tramos pero sin candidatos libres/futuros se muestra «No quedan turnos disponibles para la fecha consultada». La disponibilidad puede cambiar entre consultas, por ejemplo al cancelarse una reserva. No se cachean resultados ni se supone que una consulta asegura el turno.

## Consistencia, servicio reutilizable y CU13

La entrada principal es `DisponibilidadService::consultar(int $medico, string $fecha, ?int $especialidad=null): array`. `validarParametros(array)` valida entradas HTTP y `validarEspecialidad` permite filtrar el selector. El servicio es independiente de HTML. En consultas autónomas abre una transacción **REPEATABLE READ READ ONLY**, usando la misma conexión PDO de los repositorios y de HorarioService, para que los tramos y ocupaciones pertenezcan a una instantánea coherente. Confirma o revierte solo la transacción que él abrió. No bloquea el médico para una consulta informativa.

Si el llamador ya tiene una transacción, el servicio la conserva y no la confirma, revierte ni cambia su aislamiento. CU13 deberá crear su propia transacción READ COMMITTED, bloquear el médico **FOR SHARE antes de consultar horarios/disponibilidad**, comprobar médico/especialidad/paciente y que el intervalo solicitado siga libre, y recién entonces insertar. Mantendrá el bloqueo hasta commit y manejará la exclusión GiST frente a reservas concurrentes. Si usa REPEATABLE READ y ya obtuvo una instantánea anterior, no debe tratarla como validación actual para reservar. CU11 usa FOR UPDATE del mismo médico, coordinando cambios de horario. Este protocolo queda documentado; no se implementó la reserva en CU12.

## Permisos comprobados

| Rol | Alcance |
|---|---|
| Administrador | Todos los médicos activos, con filtro opcional de especialidad |
| Recepcionista | Igual consulta para gestión futura de citas |
| Médico vinculado | Solo su registro; selectores también limitados a sus especialidades activas |
| Médico sin vínculo | Mensaje sin catálogos ajenos; una consulta por ID se deniega |
| Paciente | Sin acceso al módulo administrativo |

Se reutilizan CatalogoController y AuthMiddleware con las sesiones/roles revalidados de la base. Los GET forzados hacia otro médico reciben 403. IDs, arrays y fechas inesperados producen errores 422 comprensibles, sin excepciones SQL en la vista. Salidas HTML escapadas con e().

## Pruebas y verificación manual

Pruebas nuevas: disponibilidad_integration.php, 48 comprobaciones con rollback; http_disponibilidad_integration.php, 44 comprobaciones con servidor PHP real y eliminación final de sus fixtures rastreados por ID. La primera usa reloj controlado para hoy/horas vencidas y reservas ficticias que son futuras también para el trigger real. La suite HTTP usa el reloj real y fechas futuras calculadas. Se verifican listas exactas de turnos y privacidad; no solo HTTP 200. Ninguna consulta de producción inserta citas. Los escenarios de prueba no dejan citas permanentes; pueden avanzar secuencias.

Una primera ejecución HTTP detectó una colisión entre el nombre de variable del formulario y el argumento de render existente; se corrigió en la vista/controlador y se volvió a ejecutar toda la suite. El registro de pruebas contiene el resultado final y regresión, sin contar intentos repetidos como nuevas comprobaciones. No se hicieron pruebas visuales ni capturas: queda pendiente comprobar escritorio/móvil y HTML5 en navegador.

Resultado final: **92 comprobaciones nuevas + 423 de regresión CU01–CU11 = 515 aprobadas**. Sintaxis de 92 archivos PHP y JavaScript correctas. Los conteos finales coinciden con los iniciales: dos usuarios, un paciente, un médico, tres especialidades, cero horarios y cero citas. La pantalla mostrará los horarios reales que se configuren; no se dejaron datos ficticios para aparentar resultados.

Desde la raíz: `php -S 127.0.0.1:8000 -t public`. Entrar en http://127.0.0.1:8000/login con una cuenta autorizada y abrir http://127.0.0.1:8000/disponibilidad. Elegir especialidad (opcional), pulsar Filtrar médicos, seleccionar médico/fecha y consultar. Si el médico todavía no tiene horarios, configurar primero sus tramos mediante CU11. Los resultados salen de los datos locales; no se dejaron reservas ficticias para llenar la pantalla. No se hicieron commits, push ni despliegues.

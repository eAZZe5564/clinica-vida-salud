# CU14 — Consultar citas médicas registradas

Entrega Prompt 4C, 20/10/2026. CU14 consulta PostgreSQL local `clinica_vida_salud_dev`. El alcance es de lectura: no hay transiciones de estado, reprogramación, asistencia, historial clínico, exportación ni reportes del Ciclo 2.

## Inspección y esquema utilizado

Se revisaron las migraciones 001–004, tabla y restricciones reales de citas, índices, CU12/CU13, catálogos, middleware, vinculación usuario/médico y pruebas anteriores. Los estados de `citas_estado_valido` son `programada`, `cancelada`, `atendida`; `Cita::ESTADOS` mantiene exactamente esa lista y sus etiquetas. La especialidad guardada en 004 es nullable para citas históricas. Una cita sin especialidad muestra «Sin registrar»: no se infiere a partir del catálogo actual.

Se conservaron los índices existentes: PK de citas, `(paciente_id, fecha_hora_inicio DESC)`, `(medico_id, fecha_hora_inicio)` y exclusión GiST de programadas. No se agregó índice ni migración. Para el volumen local no hay evidencia de necesitar otro; una evaluación con volumen real y planes de ejecución debe preceder cualquier propuesta futura.

## Rutas y recorrido

| Ruta | Método | Operación |
|---|---|---|
| `/citas` | GET | Listado con filtros y paginación |
| `/citas/{id}` | GET | Detalle dentro del alcance autorizado |
| `/citas/nueva` | GET | Formulario administrativo CU13 existente |
| `/citas` | POST | Reserva CU13, JSON o formulario HTML, con CSRF |
| `/citas/confirmacion/{id}` | GET | Recibo CU13 de esa sesión y actor |

El controlador frontal resuelve primero las rutas literales `nueva` y `confirmacion`, después `/citas` y finalmente el detalle numérico. Los IDs desbordados devuelven 404. El detalle acepta solo GET y devuelve 405 para escritura. La consulta y el recibo tienen permisos diferentes: abrir un detalle autorizado no concede acceso al recibo de otra sesión.

Menú: «Citas médicas» para administrador/recepción y «Mis citas» para médico. El panel incluye la consulta y conserva «Programar cita» para los dos roles administrativos. El recibo permite abrir el detalle o el listado.

## Filtros y orden

| Parámetro GET | Interpretación |
|---|---|
| `fecha` | Día local exacto, AAAA-MM-DD |
| `desde`, `hasta` | Rango local inclusivo; se permite solo uno de los límites |
| `medico_id` | Identificador positivo; el médico no puede elegir otra agenda |
| `especialidad_id` | Especialidad almacenada en la cita |
| `estado` | Uno de los tres estados reales del esquema |
| `codigo` | Código completo CM derivado de ID, por ejemplo CM-000001; acepta minúsculas |
| `periodo` | `todas` por defecto, `proximas`, `historicas` |
| `pagina` | Entero positivo, 20 filas por página |

Todos se combinan por AND. Fecha exacta y rango se intersectan: si no coinciden, se obtiene lista vacía. Las fechas inexistentes, tipos array, caracteres de control, códigos/IDs desbordados y rangos invertidos se rechazan con 422, sin ejecutar el listado inválido ni mostrar SQL.

Todas/próximas: inicio ascendente, ID ascendente como desempate. Históricas: inicio descendente, ID descendente. Próximas se define por inicio >= ahora e históricas por inicio < ahora, independientemente del estado; «cancelada» no significa necesariamente «pasada». El selector y los rangos permiten localizar ambos períodos. No se acepta un campo arbitrario de ordenamiento.

Listado con COUNT y un SELECT paginado con JOIN a paciente/médico y LEFT JOIN a especialidad, sin una consulta adicional por fila. La página se ajusta al rango existente antes de calcular OFFSET, incluso si se solicita PHP_INT_MAX. Los enlaces conservan todos los filtros y la página; el detalle construye el regreso a `/citas` desde parámetros validados, nunca desde una URL externa. El formulario de nueva búsqueda vuelve a página 1. «Limpiar filtros» abre `/citas`.

Los selectores administrativos incluyen catálogos inactivos para consultar historia. Los médicos solo reciben su registro y especialidades propias actuales o históricas; retirar una asociación no oculta la especialidad registrada en citas anteriores.

## Seguridad y datos por rol

| Rol | Listado/detalle | Motivo breve | Cuenta que registró |
|---|---|---|---|
| Administrador | Todas las citas | Sí | Sí, correo |
| Recepcionista | Todas las citas administrativas | Sí | No |
| Médico vinculado | Solo citas de su registro médico | No | No |
| Médico sin vínculo | Mensaje sin agenda; detalle 404 | No | No |
| Paciente | 403 | No | No |

El motivo se trata como información administrativa de programación y se reserva a administrador/recepción. El médico recibe únicamente el nombre del paciente, profesionales/especialidad y datos básicos de la cita, incluida su fecha de registro. No se seleccionan CI, contactos, dirección, antecedentes, alergias ni contraseñas. El listado no selecciona motivos ni datos del creador para ningún rol. El detalle médico tampoco los selecciona: la restricción no depende solo de ocultar HTML. No se muestran enlaces a la administración de pacientes desde esa vista médica.

`AuthMiddleware` revalida sesión, rol y usuario activo. `ConsultaCitaService` recibe el actor del servidor y obtiene el vínculo por `medicos.usuario_id`; no acepta el alcance desde GET. NULL significa consulta administrativa; 0 significa médico sin vínculo y produce SQL sin coincidencias. El repositorio agrega `c.medico_id=:propio` tanto en listado como en detalle. Un código ajeno no amplía el alcance. Un ID ajeno y uno inexistente devuelven la misma página 404.

Ampliar el constructor de CitasController para lectura médica se acompaña de una comprobación explícita administrador/recepción en crear, formulario y confirmación. Se mantienen CSRF, permisos y las condiciones de recibo existentes. Todas las salidas pasan por `e()`; los valores de filtros son parámetros PDO y los únicos fragmentos de orden/proyección provienen de decisiones cerradas del servidor.

## Fechas y relación con CU12/CU13

La clínica mantiene `APP_TIMEZONE=America/La_Paz`. Los límites de un día se construyen en esa zona y se convierten a instantes con offset para comparar `fecha_hora_inicio >= inicio` y `< comienzo del día siguiente`. No se aplica DATE() sobre la columna indexada ni se cambia la zona del servidor PostgreSQL. Así se incluyen correctamente citas locales a las 23:30 que ya pertenecen al siguiente día UTC.

Los TIMESTAMPTZ de inicio y creación se convierten una vez al presentarlos. Se muestran fecha d/m/Y, horas H:i y registro d/m/Y H:i. La hora final se calcula con `duracion_minutos`; no se agrega una columna derivada. Si el intervalo termina a medianoche, su final se muestra 00:00 del día siguiente.

`CitaService` no se modificó: conserva READ COMMITTED, bloqueos, savepoints y GiST. Una reserva hecha desde CU13 aparece inmediatamente en el listado y detalle CU14 con su código, paciente, médico, especialidad, fecha, horas y estado programada. CU12 sigue descartando ese turno. Las pruebas incluyen una reserva realizada por el formulario HTML con revisión y PRG, además de reservas mediante el servicio.

## Guía de archivos para los dos integrantes

| Capa / tarea | Archivos |
|---|---|
| Entrada HTTP y autorización | `public/index.php`, `app/Controllers/CitasController.php` |
| Validación, alcance y presentación local | **Nuevo** `app/Services/ConsultaCitaService.php` |
| JOIN, filtros SQL, paginación y proyección | `app/Repositories/CitaRepository.php` |
| Código/estados permitidos | `app/Models/Cita.php` |
| Pantallas CU14 | **Nuevas** `app/Views/citas/index.php`, `app/Views/citas/show.php` |
| Menú, panel y enlace desde recibo | `app/Views/layout.php`, `app/Views/dashboard.php`, `app/Views/citas/confirmacion.php` |
| Tabla, filtros, estados y móvil | `public/assets/css/app.css` |
| Fixtures y pruebas nuevas | `tests/ConsultaCitasPrueba.php`, `tests/consulta_citas_integration.php`, `tests/http_consulta_citas.php`, `tests/consulta_citas_browser.php` |
| Pruebas existentes ajustadas | `tests/http_citas_integration.php` (GET /citas ahora 200, conserva comprobación de no escritura), `tests/citas_browser.mjs` (modo CU14 sin duplicar cliente DevTools) |

Documentación actualizada: `README.md`, `tests/README.md`, `docs/ciclo1/MAPA_DEL_CODIGO.md`, `docs/ciclo1/DECISIONES_TECNICAS.md`, `docs/ciclo1/analisis/DECISIONES_TECNICAS.md`, `docs/ciclo1/implementacion/README.md`, `docs/ciclo1/implementacion/PROGRAMACION_CITAS.md`, este nuevo documento y `docs/ciclo1/pruebas/README.md`.

Para revisar en equipo: un integrante puede seguir ruta → controlador → servicio y comprobar la política de acceso; el otro puede revisar repositorio → vistas → pruebas. Ambos deben recorrer el formulario CU13 → recibo → consulta CU14 y verificar el turno ocupado en CU12.

## Inicio y revisión manual

Desde la raíz, si no está iniciado el servidor:

```powershell
php -S 127.0.0.1:8000 -t public
```

Entrar con una cuenta existente en `http://127.0.0.1:8000/login` y abrir `http://127.0.0.1:8000/citas`. Ejemplo: `/citas?desde=2026-10-20&hasta=2026-10-25&estado=programada`. Puede mostrar lista vacía si no existen coincidencias: no se insertaron citas demostrativas permanentes. Para probar un caso real, reservar desde `/citas/nueva` usando registros autorizados de la clínica y buscar el código del recibo.

La documentación académica final y los UML se prepararán posteriormente con el usuario. GitHub queda para revisión e integración final del equipo, sin commits, push ni publicación automática.

## Resultados reales de pruebas

| Suite nueva | Comprobaciones aprobadas |
|---|---:|
| `php tests/consulta_citas_integration.php` | 55 |
| `php tests/http_consulta_citas.php` | 65 |
| `php tests/consulta_citas_browser.php` | 16 |
| Total CU14 | **136** |

La integración verifica filtros, límites de fecha local/UTC, código, catálogos históricos, alcance médico y columnas restringidas desde el servicio/repositorio, en una transacción revertida. HTTP verifica además respuestas reales, XSS, paginación y retorno, autenticación de cinco cuentas ficticias (incluido médico sin vínculo), rutas anteriores y el flujo de formulario CU13 → CU14 → CU12. Las consultas por sí mismas conservan el conteo de citas; la única reserva adicional de ese flujo se elimina junto con sus fixtures.

Edge headless real: escritorio 1440 × 1000 y móvil 390 × 844, con revisión visual de listado y detalle en ambos tamaños. Se comprobaron filtros mediante el formulario real, código/fecha/horas completos en escritorio, tarjetas móviles, ausencia de desbordamiento, retorno al listado, motivo escapado y restricción médica por URL. Capturas ficticias en `tests/artifacts/consulta-citas/{listado-escritorio,listado-movil,detalle-escritorio,detalle-movil}.png`, ignoradas por Git. El cliente DevTools se comparte con CU13; el modo original de reserva también pasó. No se afirma validación de todos los navegadores ni auditoría completa de accesibilidad.

Regresión CU01–CU13 ejecutada: **656 comprobaciones aprobadas**, incluidas las tres carreras reales de reserva/CU11, 37 del formulario y 15 del navegador original CU13. Total acumulado: **136 + 656 = 792**. Sintaxis de **115 archivos PHP** y de `app.js`, `citas.js`, `citas_browser.mjs` correcta. Las ejecuciones repetidas no se suman como pruebas nuevas. La suite histórica de primera cuenta `auth_users_integration.php` exige usuarios vacíos; se conserva la regresión HTTP de autenticación, sin vaciar tablas.

CU01–CU14 están implementados dentro del Ciclo 1 y listos para comenzar su integración y revisión final del equipo. Quedan la validación manual conjunta, documentación académica/UML y preparación/revisión del repositorio para GitHub. No se detectaron bloqueos funcionales en las ejecuciones finales. No se realizaron commits ni push.

PostgreSQL **17.10**, únicamente `clinica_vida_salud_dev`. Conteos al comenzar y terminar 4C: **2 usuarios, 3 pacientes, 2 médicos, 4 especialidades, 2 horarios y 1 cita**. Estos eran los registros presentes al iniciar esta tarea, aunque los informes históricos tuvieran menos registros; se conservaron. Las pruebas retiraron sus propios fixtures y no dejaron citas demostrativas. Las secuencias pueden avanzar por inserciones de prueba/rollback. Se conserva America/La_Paz y el servidor existente de 127.0.0.1:8000. `.env` y capturas siguen ignorados por Git.

# Pruebas

Preparación Render 7A: `php tests/render_preparacion.php` (24 guardas/CLI sin red), `powershell -NoProfile -File tests/render_preparacion.ps1` (16 de URL, aislamiento de proceso, stdin Unicode y errores sin secretos), `php tests/render_sql_local.php` (3 comprobaciones SQL reales en READ ONLY con rollback; nunca migra). `render_proceso_probe.php` solo apoya el test de subprocesos y usa datos ficticios. Docker no está disponible; no se afirma una construcción o prueba de Apache. Guía: [DESPLIEGUE_RENDER.md](../docs/ciclo1/DESPLIEGUE_RENDER.md).

Desde la raíz del proyecto:

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

Validaciones: sin conexión a PostgreSQL. Integración de pacientes: transacción revertida. Suites HTTP: servidor local y registros ficticios eliminados en finally. Requieren configuración local y migración 002 para pacientes. Las secuencias pueden avanzar aunque se revierta una transacción.

La suite histórica `auth_users_integration.php` presupone usuarios vacíos para comprobar la primera cuenta y el último administrador. No vaciar tablas ni ejecutarla con cuentas reales existentes. La regresión actual usa `http_auth_integration.php`.

Resultados: `docs/ciclo1/pruebas/README.md`.

Mejoras 6A: las cuatro suites `mejoras_6a_validation.php`, `mejoras_6a_integration.php`, `http_mejoras_6a.php` y `mejoras_6a_browser.php` aprobaron 43, 46, 62 y 14 comprobaciones respectivamente (165 nuevas; 1003 con la regresión de 838). La primera no conecta a PostgreSQL; la segunda revierte su transacción; HTTP/CLI y Edge retiran solo sus fixtures. El navegador requiere Edge y Node con WebSocket nativo, usa perfil temporal y credenciales ficticias por stdin, y deja cuatro capturas ignoradas en `tests/artifacts/mejoras-6a/`. Se probaron el rechazo de contraseña por el CLI inicial y su guarda de base con usuarios; no se vaciaron tablas para probar la inicialización completa. Las contraseñas ficticias de suites previas se adaptaron a la política nueva sin eliminar aserciones. Detalle: [MEJORAS_6A.md](../docs/ciclo1/implementacion/MEJORAS_6A.md).

Auditoría 5A: `auditoria_ciclo1_http.php` agrega 46 comprobaciones al total previo de 792 (838 distintas). Verifica por HTTP un único recorrido desde login hasta creación de paciente/especialidad/médico, asignación, horario, disponibilidad, revisión/reserva/recibo y consulta CU14, seguido de logout y bloqueo de rutas privadas. También comprueba raíz pública, cookies/cabeceras, renovación de sesión, mensajes malformados e identidad de navegación de CU03. Simula APP_ENV=production en otro proceso de pruebas para comprobar Secure, sin modificar .env, conectarse a bases externas o desplegar. Usa fixtures ficticios propios y limpieza en finally. HttpCatalogo devuelve cabeceras y acepta un entorno opcional del proceso; su uso previo sin argumentos sigue igual. El informe consolidado es `docs/ciclo1/REVISION_FINAL_CICLO1.md`.

Médicos: validaciones sin conexión, integración dentro de transacción revertida, HTTP con prefijo aleatorio y limpieza de sus propios registros. Las dos últimas requieren migración 003 autorizada y aplicada; devuelven código 2 si falta. `HttpCatalogo.php` es el cliente/servidor de apoyo de la suite HTTP. No crear bases ni vaciar tablas para probar.

Horarios: validaciones sin conexión; integración con rollback; HTTP con limpieza por IDs de fixtures; concurrencia con dos procesos PHP, bloqueo real observado en pg_stat_activity y limpieza final. Reutiliza las migraciones 001–003 y no modifica esquema. HorariosFixture.php solo crea datos ficticios y conserva los previos. La zona confirmada para pruebas de cobertura local es America/La_Paz. La prueba de concurrencia requiere poder consultar las sesiones del mismo usuario PostgreSQL; no se instalaron extensiones ni se crearon bases para probar.

Los servidores de pruebas se lanzan mediante argumentos de proceso directo para que proc_terminate cierre PHP en Windows; http_auth_integration.php se ajustó a este mecanismo sin cambiar sus aserciones ni la aplicación.

Disponibilidad: integración con transacción revertida y reloj inyectado para comprobar hoy/horas pasadas sin alterar el reloj del sistema; HTTP con consultas reales y listas exactas de turnos. Se usan HorariosFixture y HttpCatalogo existentes. Las reservas HTTP son ficticias y temporales, eliminadas en finally por sus IDs de fixture; no queda ninguna cita permanente. No modifica esquema ni requiere migración adicional. La política de ocupación usa programada, igual que la exclusión GiST; se prueban cancelada y atendida como no activas.

Reservas CU13 backend: requiere migración 004 previamente autorizada/aplicada. CitasPrueba compone los fixtures/servicios; integración revierte sus registros. HTTP y concurrencia limpian exclusivamente los IDs ficticios creados. Concurrencia ejecuta tres carreras reales con dos procesos/sesiones: doble reserva con 23P01 comprobado, CU11 desactiva antes y CU11 intenta desactivar después de una reserva. No se crean bases ni se tocan datos previos. Las secuencias pueden avanzar tras rollback.

Interfaz CU13: http_citas_formulario.php verifica HTML real, revisión sin escritura, PRG, recibo restringido, refresh sin duplicados, conflictos con datos conservados, permisos y manipulación. citas_browser.php ejecuta Edge headless real mediante Node y el protocolo DevTools, sin bibliotecas instaladas. Requiere Edge en su ruta Windows habitual y Node con WebSocket nativo (probado con Node 24). El perfil es temporal y separado; no usa el perfil del usuario. Credenciales ficticias viajan por stdin, no por argumentos ni logs. Capturas en tests/artifacts/citas, ignoradas por Git. Ambas suites eliminan únicamente sus fixtures al terminar; pueden avanzar secuencias.

Consulta CU14: ConsultaCitasPrueba compone los fixtures anteriores, crea dos agendas y citas históricas/futuras ficticias. consulta_citas_integration.php ejecuta rollback; http_consulta_citas.php verifica filtros, paginación/retorno, todos los roles, IDOR, XSS, lectura sin escrituras y una reserva real por el formulario CU13 que aparece en CU14 y bloquea CU12. consulta_citas_browser.php usa el modo de consulta de citas_browser.mjs para Edge real de escritorio/móvil, filtros, retorno, privacidad y acceso por URL. Las dos últimas limpian únicamente sus IDs de prueba en finally. Capturas en tests/artifacts/consulta-citas, ignoradas. No requiere migración adicional. En http_citas_integration.php se actualizó la expectativa GET /citas de 405 a 200 conforme al nuevo contrato CU14, conservando la comprobación de que GET no escribe.

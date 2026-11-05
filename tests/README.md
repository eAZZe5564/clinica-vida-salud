# Pruebas

CU23: PostgreSQL local clinica_vida_salud_dev con 001–009 ya aplicado y dependencias
CU22 instaladas. No repetir migraciones para ejecutar pruebas.
`php tests/regresion_cu23.php` ejecuta CU01–CU22 y las ocho suites nuevas;
`php tests/regresion_cu23.php --solo-cu23` ejecuta únicamente CU23. También acepta
el nombre de una suite CU23 para investigar un fallo. Complementa la campaña
completa con el lector PDF Python y PowerShell sin red indicados en CU22.
`php scripts/verificar-cu23-local.php` valida el contrato 12/20 en READ ONLY.
Las pruebas usan pacientes/cuentas inventados, limpian sus IDs, no desactivan
restricciones y no acceden a Render. Edge comprueba escritorio/tablet/celular
sin generar capturas clínicas; los logs solo contienen etiquetas y diagnósticos
sanitizados y quedan ignorados. Resultados e inventario:
[PRUEBAS_CU23.md](../docs/ciclo2/PRUEBAS_CU23.md).

CU22, solo `clinica_vida_salud_dev` con 001–008 y dependencias instaladas desde
composer.lock: `php tests/regresion_cu22.php` ejecuta nuevamente CU01–CU21 y las
suites nuevas de CSV/PDF, HTTP, integración READ ONLY, Edge y Docker estático.
Después ejecuta `python tests/leer_pdf_cu22.py` con pypdf para lectura real de los
PDF ficticios y `powershell -NoProfile -File tests/render_preparacion.ps1` sin red.
Pypdf es solo herramienta de pruebas; no se necesita en la aplicación/Docker.
En este entorno ya está disponible; no se instaló ninguna dependencia Python.
No se declara probado Microsoft Excel ni un contenedor Docker si no se ejecutan.
Capturas y PDFs ficticios de revisión quedan ignorados en tests/artifacts/cu22;
los temporales del exportador y las descargas GUID se retiran automáticamente.
Resultados, límites e inventario: [PRUEBAS_CU22.md](../docs/ciclo2/PRUEBAS_CU22.md).

CU21, solo `clinica_vida_salud_dev`, con esquema 001–008 existente:
`php tests/regresion_cu21.php` ejecuta CU01–CU20, esquema, herramientas locales
y las nuevas suites `reportes_integration`, `http_reportes`, `reportes_browser`.
Una suite específica: `php tests/regresion_cu21.php reportes_integration`.
Complemento sin red: `powershell -NoProfile -File tests/render_preparacion.ps1`.
No se aplican migraciones. Fixtures ficticios y limpieza por IDs existentes;
reportes reales en READ ONLY, invariantes, límites locales, roles, XSS y comparación
CU14/CU19/CU20. Edge real 1440/900/390; capturas/logs ignorados en artifacts/cu21.
Los logins cambian último acceso de cuentas ficticias; HTTP compara las nueve
tablas después del login y Edge las ocho de dominio. Inventario/resultados:
[PRUEBAS_CU21.md](../docs/ciclo2/PRUEBAS_CU21.md).

CU20 (solo `clinica_vida_salud_dev`, requiere esquema 001–008 ya instalado, sin
migraciones nuevas): `php tests/historial_paciente_integration.php`,
`php tests/http_historial_paciente.php`, `php tests/historial_paciente_browser.php`.
Integración ejecuta el historial/eventos en READ ONLY con PDO vigilado y compara
las nueve tablas; crea únicamente fixtures antes de esas lecturas. HTTP compara
huellas tras login, porque autenticar registra acceso. Edge cambia de cuenta y
compara las otras ocho tablas, sin confundir esa escritura de autenticación con
CU20. Reutilizan fixtures CU13–CU19, conservan sus IDs y limpian solo esos registros.
Se prueba una ficha de paciente vinculada que sigue recibiendo 403: no existe aún
lectura personal CU14 y no se implementa un portal o autogestión CU25.
Capturas ficticias ignoradas en `tests/artifacts/cu20/`, perfil Edge temporal y
credenciales ficticias por stdin. Diseño, límites, resultados actuales e inventario:
[PRUEBAS_CU20.md](../docs/ciclo2/PRUEBAS_CU20.md).

CU19 (solo `clinica_vida_salud_dev`, esquema 001–008 ya instalado, sin migración nueva):
`php tests/agenda_integration.php`, `php tests/http_agenda.php`,
`php tests/agenda_browser.php`. Integración comprueba SELECT reales en transacción
READ ONLY, consultas agrupadas, permisos, filtros, bordes locales de día/semana,
cambios CU13–CU18 y huellas de las nueve tablas. HTTP toma su huella después de
login (autenticación registra acceso) y demuestra que consultar o intentar métodos
de escritura desde CU19 no altera ninguna tabla. Edge usa un perfil temporal y
credenciales ficticias por stdin; captura escritorio 1440px, tablet 900px y móvil
390px en `tests/artifacts/cu19/`, ignorado. Su comparación de dominio excluye
usuarios porque cambia de cuenta durante el recorrido; el login existente registra
último acceso. Todos los fixtures se retiran exclusivamente por sus propios IDs.
El informe actual, comandos de regresión CU01–CU18 e inventario están en
[PRUEBAS_CU19.md](../docs/ciclo2/PRUEBAS_CU19.md). No ejecutar pruebas contra Render.

CU18 (solo `clinica_vida_salud_dev`, requiere 008 ya autorizada/aplicada):
`php tests/asistencia_integration.php`, `php tests/http_asistencia.php`,
`php tests/asistencia_concurrency.php`, `php tests/asistencia_browser.php`.
No volver a aplicar migraciones para ejecutarlas. Comprueban llegada distinta de
atención, no-show desde el fin del intervalo, roles/vínculo, historial atómico,
reintentos, GiST y coordinación con CU11–CU17. Integración y carreras inyectan un
reloj de negocio únicamente por constructor; HTTP/Edge usan el reloj real de
PostgreSQL para llegada/atención y rechazo de inasistencia prematura. La
inasistencia válida se comprueba con fixtures y reloj simulado, no por HTTP tras
una espera de 30 minutos. No se desactivan restricciones ni triggers.
Capturas ficticias en `tests/artifacts/cu18/`, ignoradas. `php scripts/inspeccionar-cu18-local.php`
y `php scripts/verificar-cu15-local.php` verifican READ ONLY; el contrato compartido
distingue 001–004/005/006/007/008 y rechaza instalaciones parciales.
Resultados e inventario actual: [PRUEBAS_CU18.md](../docs/ciclo2/PRUEBAS_CU18.md).
Las cifras de las secciones siguientes son históricas de cada entrega.

CU17 (solo `clinica_vida_salud_dev`, requiere 007 autorizada y aplicada):
`php tests/confirmacion_integration.php`, `php tests/http_confirmacion.php`,
`php tests/confirmacion_concurrency.php`, `php tests/confirmacion_browser.php`.
Comprueban confirmación, historial atómico, GiST, ocupación CU12/CU13 y política
sin cambios de CU15/CU16. Usan datos ficticios con rollback/limpieza de IDs propios.
Edge genera cuatro capturas ignoradas en `tests/artifacts/cu17/`. El verificador
compartido y `php tests/esquema_cu15_validation.php` admiten estrictamente
001–004/005/006/007, incluyendo la función del trigger de 007. Resultados actuales
e inventario: [PRUEBAS_CU17.md](../docs/ciclo2/PRUEBAS_CU17.md).

CU16 (solo `clinica_vida_salud_dev`, requiere 006 autorizada/aplicada):
`php tests/cancelacion_integration.php`, `php tests/http_cancelacion.php`,
`php tests/cancelacion_concurrency.php` y `php tests/cancelacion_browser.php`.
Se reutilizan fixtures CU13/CU15 con rollback o limpieza por IDs propios.
El navegador usa Edge real y genera cuatro capturas ignoradas en
`tests/artifacts/cu16/`; no publica contraseñas ni usa el perfil personal.
`php tests/esquema_cu15_validation.php` comprueba ahora también 006 y compatibilidad
estricta con 001–004 y 001–005 mediante catálogos aislados, todo en READ ONLY.
Resultados actuales e inventario: [PRUEBAS_CU16.md](../docs/ciclo2/PRUEBAS_CU16.md).

Cierre CU15 8C: `php tests/esquema_cu15_validation.php` (53) ejecuta el verificador
SQL compartido en READ ONLY sobre desarrollo local con 005 y catálogos aislados
8/10 y 9/12. No crea ni elimina objetos o registros. `php scripts/verificar-cu15-local.php`
verifica cualquiera de los dos esquemas reales sin migrar. Regresión de herramientas:
`php tests/render_sql_local.php` (3, READ ONLY), `php tests/render_preparacion.php`
(24, sin red) y `powershell -NoProfile -File tests/render_preparacion.ps1` (34, sin red).
Son 114 comprobaciones ejecutadas en 8C; las 1124 funcionales de 8A son resultados
históricos, no una regresión funcional reejecutada en este cierre.

CU15 (Ciclo 2, solo local): `php tests/reprogramacion_integration.php` (36),
`php tests/reprogramacion_concurrency.php` (34), `php tests/http_reprogramacion.php` (35)
y `php tests/reprogramacion_browser.php` (16). Requieren migración 005 previamente
autorizada y aplicada. `php tests/regresion_cu15.php` ejecuta las 26 suites históricas
(1003 comprobaciones); admite el nombre de una suite para reanudar desde ella si las
anteriores aprobaron y no cambió el código que cubren. Detalle y límites:
[informe CU15](../docs/ciclo2/PRUEBAS_CU15.md). Nunca apuntar las pruebas a Render.

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

Disponibilidad: integración con transacción revertida y reloj inyectado para comprobar hoy/horas pasadas sin alterar el reloj del sistema; HTTP con consultas reales y listas exactas de turnos. Se usan HorariosFixture y HttpCatalogo existentes. Las reservas HTTP son ficticias y temporales, eliminadas en finally por sus IDs de fixture; no queda ninguna cita permanente. No modifica esquema. Tras 008 la política compartida con GiST conserva programada, confirmada, presente, atendida e inasistencia; cancelada libera. La expectativa histórica de atendida se actualizó a ocupada, conservando el número de aserciones.

Reservas CU13 backend: requiere migración 004 previamente autorizada/aplicada. CitasPrueba compone los fixtures/servicios; integración revierte sus registros. HTTP y concurrencia limpian exclusivamente los IDs ficticios creados. Concurrencia ejecuta tres carreras reales con dos procesos/sesiones: doble reserva con 23P01 comprobado, CU11 desactiva antes y CU11 intenta desactivar después de una reserva. No se crean bases ni se tocan datos previos. Las secuencias pueden avanzar tras rollback.

Interfaz CU13: http_citas_formulario.php verifica HTML real, revisión sin escritura, PRG, recibo restringido, refresh sin duplicados, conflictos con datos conservados, permisos y manipulación. citas_browser.php ejecuta Edge headless real mediante Node y el protocolo DevTools, sin bibliotecas instaladas. Requiere Edge en su ruta Windows habitual y Node con WebSocket nativo (probado con Node 24). El perfil es temporal y separado; no usa el perfil del usuario. Credenciales ficticias viajan por stdin, no por argumentos ni logs. Capturas en tests/artifacts/citas, ignoradas por Git. Ambas suites eliminan únicamente sus fixtures al terminar; pueden avanzar secuencias.

Consulta CU14: ConsultaCitasPrueba compone los fixtures anteriores, crea dos agendas y citas históricas/futuras ficticias. consulta_citas_integration.php ejecuta rollback; http_consulta_citas.php verifica filtros, paginación/retorno, todos los roles, IDOR, XSS, lectura sin escrituras y una reserva real por el formulario CU13 que aparece en CU14 y bloquea CU12. consulta_citas_browser.php usa el modo de consulta de citas_browser.mjs para Edge real de escritorio/móvil, filtros, retorno, privacidad y acceso por URL. Las dos últimas limpian únicamente sus IDs de prueba en finally. Capturas en tests/artifacts/consulta-citas, ignoradas. No requiere migración adicional. En http_citas_integration.php se actualizó la expectativa GET /citas de 405 a 200 conforme al nuevo contrato CU14, conservando la comprobación de que GET no escribe.

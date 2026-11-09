# Validación Docker del mantenimiento — 20I

Se mantienen dos ramas independientes: chore/puente-mantenimiento-ciclo1 y
chore/ciclo2-mantenimiento-validado. No se publica main ni se despliega Render.
El resultado real se consulta en Actions para el SHA exacto de cada ejecución;
este documento no presume que un build aún no ejecutado haya aprobado.

## Fuentes

El puente parte de 1afbad7b4a68b01f9999f54c1c6d9fe8ce40bf37 y solo incorpora
cuatro cambios de mantenimiento: app/Config/Mantenimiento.php,
app/Config/mantenimiento-http.php, public/index.php (una inclusión antes del
bootstrap) y docker/apache/clinica.conf (auto_prepend_file). Conserva Dockerfile,
scripts y migraciones 001–004 del Ciclo 1; sin Dompdf ni módulos del Ciclo 2.

CI real demostró además que Apache transformaba http_response_code(419) en HTTP
500, aunque la página de rechazo CSRF era correcta. Se corrige una única línea
en app/Middleware/AuthMiddleware.php para enviar explícitamente HTTP/1.1 419
Page Expired. Es el quinto cambio de runtime, sin alterar validación ni permisos.
El contrato de fuentes comprueba esa sustitución exacta en ambas versiones.

La versión completa parte de la rama de preparación en
a9883b0ca6488e0ce2aa63db44ebefcc5d2e77ef, con CU26
23b1175fe865021a102a890c0f5b267eabad5588 como ancestro. Añade los mismos cuatro
cambios de mantenimiento, la corrección HTTP 419, las pruebas/documentación 20G
y el harness de esta entrega.
Mantiene el Dockerfile y composer.lock originales de CU26, con Dompdf v3.1.6.

## Ejecución aislada

El workflow de cada rama usa su GITHUB_SHA. El workflow completo fija además el
SHA del puente ya publicado y valida ambos juntos. tests/docker/validar.py exige
el contrato de fuentes contra las bases anteriores y exporta commits con
git archive; nunca construye desde el workspace sucio de la laptop.

Los archivos tests/docker/validar.py, http.php, runtime.php y database_fixture.php
son comunes a ambas ramas. Solo CU26 incorpora tests/docker/pdf_csv.php.
Las pruebas quedan fuera de las imágenes por .dockerignore. El workflow usa
contents: read, runner Linux, checkout por SHA y ningún secreto de Render,
servicio PostgreSQL, registro de imágenes o artefacto clínico.

Se verifican Apache/mod_php, PHP 8.4 y extensiones, entrypoint/PID1 Apache,
health 200 sin nuevas sesiones, cierre 503 y recursos privados denegados.
Matriz HTTP: anónimo y cuatro roles ficticios, GET/POST/PUT/PATCH/DELETE/HEAD/OPTIONS,
rutas internas, formularios AJAX, reportes, rutas clínicas y portal. Incluye PHP
directo sintético montado solo durante la prueba, claves falsas y rutas alternas.

Los contenedores usan network none sin publicar puertos, DB_HOST=127.0.0.1 y
DB_PORT=1; solo hay loopback. No existe una base asociada y no se ejecuta SQL.
Se comprueba mantenimiento on, ausencia de variable en producción (cerrado por
defecto), error de escritura de configuración (cerrado), reinicio y coexistencia.

Primero se prueba cada imagen con su conexión PDO original. Después se montan
datos ficticios únicamente en otro contenedor, sobre la misma imagen intacta,
para probar login, dashboard, logout, permisos y CSRF con mantenimiento off y
acceso privado controlado. Ese montaje sustituye Database.php solo en el
contenedor de pruebas; no modifica ningún archivo protegido ni la imagen.
No se cambia el auto_prepend real de Apache. Las cookies Secure se conservan:
el cliente ficticio las envía explícitamente por loopback.

En CU26 se verifican Composer y las cinco versiones del lock y se generan
PDF/CSV reales desde CLI como www-data con datos ficticios y caracteres españoles.
No se presenta esta prueba CLI como descarga HTTP autenticada ni como regresión
funcional completa CU01–CU26. No se ha probado micrófono real.

## Publicación futura

Ambas versiones cierran con APP_MAINTENANCE=on. Sin hash de acceso privado,
ninguna cabecera permite pasar al login o a las rutas de negocio. Dejar
APP_MAINTENANCE_TEST_HASH ausente durante respaldo y migraciones; habilitarlo
solo después de COMMIT para aceptación controlada, conservando login/roles/CSRF.
Las claves se manejan en memoria y mediante HTTPS; nunca en Git ni en URLs.

Con autorización posterior, seleccionar el SHA exacto validado mediante Manual
Deploy → Deploy a specific commit, con Auto-Deploy Off y health check /health.
No usar /login como health check cerrado. No reactivar Auto-Deploy para validar.

Render mantiene temporalmente la instancia anterior durante la transición.
Antes de migrar es obligatorio confirmar el puente Live, su SHA, el cierre de
rutas y el fin de la instancia antigua y de sus solicitudes/transacciones.
Esperar el margen de drenaje/apagado configurado y comprobar que no hay
despliegues antiguos pendientes ni otros clientes escritores. Un 503 aislado o
Auto-Deploy Off no prueban por sí solos el cierre. Si no se verifica, no migrar.
Referencia: https://render.com/docs/deploys#zero-downtime-deploys

No volver a código sin guard después de iniciar migraciones; regresar al código
anterior no revierte el esquema. Mantener cerrado ante cualquier fallo.
Las etiquetas base Docker son mutables: registrar ID/digest de las imágenes
ensayadas; Render puede reconstruirlas y deberá confirmar el mismo contrato.

No se han cambiado main, Render, PostgreSQL, .env ni los cinco archivos privados.

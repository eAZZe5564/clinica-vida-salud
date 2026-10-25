# Clínica Vida Salud en Render: guía de preparación y despliegue manual

Preparación 7A, 25/10/2026. Repositorio: <https://github.com/eAZZe5564/clinica-vida-salud>, rama `main`. Esta entrega prepara el código; no crea recursos, ejecuta migraciones remotas ni crea administradores remotos. Utiliza una **cuenta nueva de Render**, exclusivamente para la clínica. No reutilices recursos de Panadería Santiago ni exportes la base local.

## 1. Qué se preparó

- `Dockerfile`: imagen oficial `php:8.4-apache-bookworm`, con `pdo_pgsql` y `mbstring`; instala `libpq-dev`, `libonig-dev` y CA públicas. JSON, PDO, sesiones y funciones de contraseñas ya forman parte del runtime. No requiere Composer.
- `docker/apache/clinica.conf`: raíz `/var/www/clinica/public`, sin índices ni `.htaccess`, archivos ocultos denegados y `FallbackResource /index.php` para MVC. CSS/JS existentes se sirven directamente; la URI y el método llegan al controlador frontal. El resto del proyecto queda fuera de la raíz pública.
- `docker/apache/ports.conf` y `docker/start.sh`: Apache escucha `0.0.0.0:$PORT`, predeterminado `10000`; valida puerto 1024–65535, producción, zona America/La_Paz, variables de conexión y exigencia TLS. No usa `php -S`, ni ejecuta migraciones o crea cuentas al arrancar.
- `docker/php-production.ini`: errores ocultos, logs internos, sesiones privadas en `/var/lib/clinica/sessions` (propiedad de `www-data`, modo 0700).
- `.dockerignore`: contexto limitado a aplicación, recursos públicos y configuración Docker. No entran `.env`, Git, editor, pruebas, capturas, scripts de instalación, SQL, respaldos, sesiones, logs ni claves. Los scripts y SQL siguen en Git para ejecutarlos desde tu computadora.
- `scripts/render-postgres.ps1`: asistente de consola con cuatro acciones; `scripts/RenderDestino.php` y `scripts/render-administrador.php` completan las guardas del administrador y reutilizan `UsuarioService`.

Referencias técnicas: [imagen PHP oficial](https://github.com/docker-library/docs/blob/master/php/README.md), [FallbackResource de Apache](https://httpd.apache.org/docs/2.4/mod/mod_dir.html#fallbackresource).

## 2. Antes de empezar

En PowerShell, desde la raíz del repositorio, comprueba `git status`, `php -v`, `php --ri pdo_pgsql`, `php --ri mbstring` y `psql --version`. Necesitas PHP 8.4 y cliente PostgreSQL 17. Usa Windows PowerShell 5.1 o posterior; los scripts nuevos conservan UTF-8 con BOM para esa versión y envían contraseñas Unicode como UTF-8 sin BOM por stdin.

No cambies `.env`: continúa apuntando a `clinica_vida_salud_dev`. Los nuevos asistentes no lo leen ni modifican y no utilizan los ejecutores antiguos `aplicar-migracion-*.php`, limitados al desarrollo local.

No pegues secretos en comandos, chat, capturas o archivos del repositorio. No uses transcripciones/grabaciones del terminal al inicializar. La URL externa y la clave administrativa se solicitan con `Read-Host -AsSecureString`; la contraseña PostgreSQL solo se pasa al entorno del subproceso y la contraseña administrativa por stdin. No se generan archivos temporales con secretos ni se cambian las variables del proceso padre.

## 3. Crear tú mismo PostgreSQL 17 Free

1. Abre <https://dashboard.render.com> con la cuenta NUEVA y confirma visualmente que estás en su workspace.
2. Selecciona **New → Postgres**. Puedes llamar al recurso `clinica-vida-salud-db`; es un nombre propuesto, no un recurso ya creado.
3. En **Database**, establece `clinica_vida_salud_render`. Es la base nueva recomendada; el asistente admite `clinica_vida_salud` o ese prefijo con sufijos en minúsculas, pero rechaza expresamente `_dev`. No uses un nombre de otro proyecto.
4. Elige explícitamente **PostgreSQL 17**, plan **Free**, y una región que luego usarás también para el Web Service. No aceptes otra versión por defecto.
5. Crea la base y espera **Available**. Guarda privadamente host, puerto, base, usuario y contraseña. En **Info/Connect**, distingue **External Database URL** de **Internal Database URL**.
6. Para usar psql desde Windows, permite tu IP pública actual en la configuración de acceso externo. La cuenta/workspace no se puede comprobar por SQL: confírmala en el Dashboard antes de copiar la conexión.

Render permite elegir la versión y usa la red privada para servicios de la misma región. La conexión interna admite TLS con certificado autofirmado; la externa usa un nombre público con certificado gestionado. [Creación, conexiones y acceso externo](https://render.com/docs/postgresql-creating-connecting).

`btree_gist` está soportada en PostgreSQL 17 de Render. La migración 001 contiene `CREATE EXTENSION IF NOT EXISTS btree_gist`; no hay que quitar las restricciones GiST ni modificarla. [Extensiones de Render](https://render.com/docs/postgresql-extensions).

## 4. CA pública para verificar la conexión externa

El asistente exige `sslmode=verify-full`: cifra y valida la CA y el nombre completo del servidor. No pases la URL con contraseña a `psql` como argumento. Las opciones TLS de la URL no rebajan el modo que impone el asistente.

Si no tienes una CA pública confiable en PEM, puedes obtener el bundle público de Mozilla distribuido por curl, fuera del repositorio:

```powershell
$caRender = Join-Path $env:USERPROFILE 'clinica-render-ca.crt'
Invoke-WebRequest -UseBasicParsing -Uri 'https://curl.se/ca/cacert.pem' -OutFile $caRender
```

Este archivo contiene certificados públicos, no credenciales. Mantén actualizado su origen y no desactives la validación si aparece un error: revisa CA, reloj y host externo real. [Bundle de curl](https://curl.se/docs/caextract.html), [verificación TLS de libpq 17](https://www.postgresql.org/docs/17/libpq-ssl.html).

## 5. Probar destino y comprobar base vacía

```powershell
.\scripts\render-postgres.ps1 -Accion Comprobar -CaCertificado $caRender
```

El asistente solicita, por separado, host EXTERNO y nombre de base; después pide la URL EXTERNA como entrada oculta. Copia esos datos de la base nueva de la clínica. No utilices la URL interna desde Windows.

Las guardas rechazan localhost/IP, nombres ajenos a la clínica, `_dev`, hosts que no sean externos de Render, discrepancias entre URL y confirmación, puerto distinto de 5432 o CA ausente. Al conectar comprueban nombre real, usuario, versión 17 y TLS. `Comprobar` exige cero relaciones de usuario (tablas, secuencias y vistas, fuera de los esquemas del sistema). No publica ni consulta filas de pacientes.

Si falla, no continúes: revisa que copiaste la base correcta, acceso por IP, credenciales, versión y certificado. El asistente oculta los diagnósticos nativos para evitar revelar secretos. Si ya instalaste el esquema, usa `Verificar`, no `Comprobar`.

## 6. Inicializar una sola vez

Solo cuando la acción anterior confirme una base NUEVA y VACÍA:

```powershell
.\scripts\render-postgres.ps1 -Accion Inicializar -CaCertificado $caRender
```

Confirma escribiendo exactamente la frase que incluye base y host. Esta acción usa `psql -X -w --single-transaction -v ON_ERROR_STOP=1`: ignora `psqlrc`, no pide otra clave, detiene el primer error y revierte toda la instalación si falla. Nunca utiliza la URL secreta como argumento. Se ejecutan estos archivos, en este orden, dentro de la misma transacción:

1. `scripts/render/comprobar-destino.sql`.
2. `scripts/render/exigir-base-vacia.sql` (también obtiene un bloqueo transaccional para serializar este instalador).
3. `database/migrations/001_esquema_ciclo1.sql`.
4. `database/migrations/002_pacientes_cu04_cu06.sql`.
5. `database/migrations/003_medicos_especialidades.sql`.
6. `database/migrations/004_citas_especialidad.sql`.
7. `database/seeds/001_datos_base.sql`.
8. `scripts/render/verificar-instalacion.sql`.

El seed añade cuatro roles y tres especialidades, sin usuarios ni datos médicos. Aunque el seed original permite reejecución, el asistente solo lo incluye en la instalación vacía y rechaza una segunda inicialización. No añade tablas de control ni cambia migraciones. [Opciones transaccionales de psql](https://www.postgresql.org/docs/17/app-psql.html).

Después:

```powershell
.\scripts\render-postgres.ps1 -Accion Verificar -CaCertificado $caRender
```

Verifica las ocho tablas, diez claves foráneas, columnas de 002–004, extensión, exclusión GiST, trigger de citas y catálogos. Muestra nombres de objetos y cantidad de cuentas, sin datos personales. No ejecuta escrituras de aplicación.

## 7. Crear el primer administrador desde tu computadora

Render Free no ofrece Shell; utiliza la consola local contra la conexión externa, después de las migraciones y catálogos:

```powershell
.\scripts\render-postgres.ps1 -Accion Administrador -CaCertificado $caRender
```

Se repiten las comprobaciones de destino y esquema antes de pedir confirmación, correo y contraseña. PHP vuelve a validar identidad, versión y TLS antes de escribir. `UsuarioService::crearAdministradorInicial()` mantiene el bloqueo transaccional de usuarios y exige cero cuentas. Solo crea el primer administrador, con `password_hash()` y la política existente: mínimo 8 caracteres, mayúscula, minúscula, número y símbolo; máximo 72 bytes; espacios no cuentan como símbolo.

No se cambia ninguna cuenta previa, no hay clave predeterminada ni endpoint público de instalación. Si ya hay usuarios, el proceso se niega a crear otra cuenta inicial; no borres usuarios para saltar esa guarda. Guarda tu contraseña en un gestor privado. No ejecutes el asistente local antiguo para este destino ni cambies `.env` para apuntarlo a Render.

## 8. Crear tú mismo el Web Service Docker

1. **New → Web Service**, conecta GitHub y selecciona únicamente `eAZZe5564/clinica-vida-salud`, rama `main`.
2. Usa un nombre propio de la clínica y la **misma región** que PostgreSQL. Selecciona runtime **Docker**, plan **Free** y raíz del repositorio (Root Directory vacío).
3. Dockerfile Path: `./Dockerfile`; Docker Build Context: `.` si aparece ese campo. No configures comandos de build/start para PHP ni `php -S`; usa el CMD de la imagen, sin Docker Command de reemplazo.
4. Antes de crear, añade las variables del apartado siguiente. Health Check Path: `/login`.
5. Crea el servicio y observa Build/Deploy Logs. Comprueba que construye extensiones y termina con Apache en ejecución. No compartas logs si contienen datos privados.

Render espera HTTP en `0.0.0.0` y ofrece `PORT` con valor habitual 10000. La imagen usa esa variable tanto en Listen como en VirtualHost. [Web Services](https://render.com/docs/web-services).

### Variables EXACTAS del Dashboard

No subas `.env`; carga estas variables en **Environment** del Web Service:

| Variable | Valor o procedencia | Necesidad / privacidad |
|---|---|---|
| `APP_ENV` | `production` | Obligatoria; pública; el arranque la exige y bootstrap fuerza Secure |
| `APP_TIMEZONE` | `America/La_Paz` | Obligatoria para la clínica; pública |
| `DB_HOST` | Host de **Internal Database URL** de la base nueva | Obligatoria; configuración privada |
| `DB_PORT` | `5432` | Opcional por defecto del código; configurar expresamente; no secreta |
| `DB_NAME` | Nombre real de la nueva base, recomendado `clinica_vida_salud_render` | Obligatoria; configuración privada |
| `DB_USER` | Usuario de esa misma base | Obligatoria; configuración privada |
| `DB_PASSWORD` | Contraseña de esa base, desde Dashboard | Obligatoria y **secreta**; no capturar ni compartir |
| `PGSSLMODE` | `require` para conexión INTERNA | Obligatoria para este contenedor; exige TLS sin caída a texto plano |
| `APP_NAME` | `Clínica Vida Salud` | Opcional; ese es el valor por defecto; pública |
| `APP_DEBUG` | `false` | Opcional/recomendada; actualmente no activa depuración; errores siempre ocultos |
| `PORT` | La proporciona Render; imagen predetermina `10000` | Automática; no hace falta añadirla manualmente |
| `PGSSLROOTCERT` | No definir para la conexión interna | Opcional de libpq; solo para otro destino TLS verificado con CA montada |

`DATABASE_URL` **no se interpreta**. No añadas `APP_URL`, `APP_KEY` o variables de otra aplicación: no existen en este proyecto. Extrae host/base/usuario/contraseña de la misma conexión interna, o de los campos correspondientes del Dashboard. `Env::load` respeta el entorno del proceso y `.env` no entra en la imagen.

El certificado interno Render es autofirmado: `verify-full` externo no se debe copiar al host interno. Aquí `require` cifra en la red privada; Windows usa `verify-full` y CA con el FQDN externo. No uses `disable`, `allow` o `prefer` para conseguir que conecte.

## 9. HTTPS, sesiones y comprobación de la aplicación

Render asigna la URL HTTPS real al servicio y redirige HTTP a HTTPS; no hay una URL creada por esta entrega. [TLS gestionado](https://render.com/docs/tls).

Abre esa URL en una ventana privada, añade `/login` e inicia sesión con tu administrador nuevo. En las herramientas del navegador verifica cookie `PHPSESSID` con **Secure, HttpOnly, SameSite=Lax**, y CSS/JS con estado 200 a través de HTTPS. Las rutas de assets y redirecciones son relativas a la raíz, sin hosts locales incrustados.

El código no confía en `X-Forwarded-Proto` enviado por el visitante: `APP_ENV=production` fuerza Secure sin depender de él. Se mantienen regeneración de sesión, expiración, CSRF, roles, política de contraseñas, escapes HTML, PDO parametrizado y transacciones de citas. No se cambió esa lógica.

Comprueba `/pacientes`, `/medicos`, `/horarios`, `/disponibilidad` y `/citas` tras autenticarte. Revisa permisos con cuentas autorizadas; usa solo datos ficticios para la exposición. Las rutas `/.env`, `/app/Config/Database.php`, `/scripts/render-administrador.php`, `/database/migrations/001_esquema_ciclo1.sql` y `/.git/config` deben devolver 403/404 sin contenido interno. Verifica cierre de sesión y denegación posterior. No ejecutes la batería de desarrollo apuntándola a Render.

Para el QR, copia la URL HTTPS real que funcione desde una ventana privada y desde otro dispositivo. El QR de la aplicación debe apuntar a esa URL; el enlace de GitHub permite consultar código, no ejecutar PHP. No incluyas credenciales en el QR.

## 10. Diagnóstico y límites

| Síntoma | Qué revisar |
|---|---|
| Falla compilación Docker | Runtime Docker, ruta/contexto, etiqueta PHP oficial, red de apt y compilación de `pdo_pgsql`/`mbstring` |
| No detecta puerto | `PORT` numérico, Listen `0.0.0.0`, CMD sin reemplazo, logs de arranque |
| Error 500/health check | `DB_*` de la misma base, esquema/seed aplicados, puerto 5432, usuario/clave correctos |
| Error de TLS | Host interno + `require` en servicio; host externo + CA/`verify-full` desde Windows; nunca rebajar protección |
| Login vuelve al formulario o CSRF falla | HTTPS, cookies habilitadas, sesión nueva tras reinicio, directorio escribible y hora/zona correctas |
| CSS/JS 404 o rutas MVC 404 | Raíz `public/`, FallbackResource, dominio raíz y archivos del commit correcto |
| Se niega Inicializar/Administrador | Destino distinto, objetos existentes o cuentas previas: no forzar ni borrar datos |

Free suspende el servicio tras 15 minutos sin tráfico y puede tardar alrededor de un minuto en reactivarlo. El sistema de archivos es efímero: sesiones se pierden al reiniciar/desplegar/suspender; tendrás que volver a ingresar. No guarda datos médicos en archivos, pero no ofrece almacenamiento de sesiones compartido. PostgreSQL Free expira a los 30 días y no incluye las funciones de respaldo de los planes pagados. Planifica la entrega y conserva solo demostración ficticia; para continuidad evalúa otro plan. [Límites Free](https://render.com/docs/free).

El limitador de intentos de login mencionado en la preparación 5A sigue pendiente para una operación real con datos sensibles; no se añadió como funcionalidad en esta entrega. No se preparó escalado, correo, recuperación de contraseñas ni Ciclo 2.

## 11. Qué se pudo comprobar aquí

Docker no está disponible (tampoco en su ruta habitual de Windows). Se realizó revisión estática de imagen, paquetes, raíz pública, enrutamiento, exclusiones, puerto y arranque. No se construyó ni ejecutó Apache en contenedor y no se probó el proxy/certificado real de Render.

Pruebas específicas de destino y consola: `php tests/render_preparacion.php` (24), `powershell -NoProfile -File tests/render_preparacion.ps1` (16) y `php tests/render_sql_local.php` (3). Las dos primeras no abren conexiones; usan datos ficticios y una respuesta PDO simulada para las guardas. La tercera ejecuta únicamente SQL de comprobación contra desarrollo, en transacciones READ ONLY terminadas en rollback; comprueba que el destino local y una base no vacía son rechazados. No ejecuta migraciones ni seed.

La regresión volvió a aprobar **1003 comprobaciones** (838 de CU01–CU14 y 165 de 6A); con las 43 nuevas suman **1046 distintas**. Sintaxis correcta en 128 PHP, cuatro JS/MJS, dos PowerShell y un sh. Los conteos y huellas de las ocho tablas de desarrollo quedaron idénticos; los fixtures se retiraron/revirtieron y las secuencias pueden avanzar. No se alteró el esquema local ni .env.

La instalación completa y la primera alta sobre la base nueva son **pendientes de ejecución manual autorizada en el destino**, no resultados inventados. Los comandos y las guardas están preparados y probados hasta donde permite este entorno. Resultados y límites: [registro de pruebas](pruebas/README.md).

Si después tienes Docker disponible, puedes validar sin credenciales:

```powershell
docker build --pull -t clinica-render:local .
docker run --rm --entrypoint php clinica-render:local -v
docker run --rm --entrypoint php clinica-render:local -m
docker run --rm --entrypoint apache2ctl -e PORT=10000 clinica-render:local -t
docker run --rm --entrypoint /bin/sh clinica-render:local -n /usr/local/bin/clinica-start
```

Después prueba HTTP con una configuración privada de una base de pruebas independiente y producción/HTTPS adecuados; no incrustes contraseñas en `docker run`. Un chequeo de sintaxis no sustituye probar rutas y sesiones con Apache real. No des por desplegado el proyecto hasta terminar las verificaciones del apartado 9.

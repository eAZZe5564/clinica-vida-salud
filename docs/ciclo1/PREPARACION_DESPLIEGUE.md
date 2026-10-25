# Preparación para despliegue público — Ciclo 1

Actualización 7A, 25/10/2026: se eligió una cuenta NUEVA de Render para la clínica. Ya están preparados Docker/Apache y los asistentes privados de instalación/administrador. Guía actual: [DESPLIEGUE_RENDER.md](DESPLIEGUE_RENDER.md). No se crearon recursos ni se ejecutaron operaciones remotas. Las referencias siguientes a proveedor no elegido describen la auditoría histórica 5A/5B.

Actualización 5B: requisitos revisados al preparar GitHub. .env.example incluye PGSSLMODE y PGSSLROOTCERT como ejemplos **comentados**, sin secretos ni valores activados para la base local. La guía de [GitHub y equipo](GITHUB_TRABAJO_EN_EQUIPO.md) distingue repositorio, URL de despliegue y QR. No se eligió proveedor ni se configuró producción.

Informe de auditoría 5A, 20/10/2026. No constituye un despliegue realizado. No se seleccionó ni configuró proveedor, no se accedió a bases externas y no se ejecutaron migraciones de producción. El sistema está validado localmente; faltan verificar las condiciones del alojamiento y obtener una URL pública HTTPS y su QR.

## Requisitos que debe cumplir el alojamiento

| Componente | Requisito para esta aplicación |
|---|---|
| Runtime | PHP 8.4 con PDO_PGSQL, mbstring y sesiones; JSON y funciones de contraseñas disponibles |
| Servidor HTTP | Apache con PHP o Nginx/Apache con PHP-FPM; raíz documental en `public/` |
| Rutas | Los recursos que no sean archivos públicos deben llegar a `public/index.php`, conservando URI y método |
| PostgreSQL | Versión 17, integridad FK/CHECK, exclusión GiST y extensión `btree_gist` permitida |
| Red | Conexión privada o TLS verificado a PostgreSQL; nunca exponer credenciales en código/URL del navegador |
| HTTPS | Certificado válido y redirección HTTP → HTTPS en servidor/proxy |
| Variables | Inyección privada del entorno APP_* y DB_*; América/La_Paz mediante `APP_TIMEZONE=America/La_Paz` |
| Sesiones | Almacenamiento privado y escribible fuera de `public/`; con varias instancias, almacenamiento compartido o afinidad y pruebas específicas |
| Operación | Acceso privado para migraciones y primera cuenta, logs protegidos, respaldo y recuperación |

No basta con que un proveedor permita subir un repositorio: debe ejecutar PHP y ofrecer acceso a PostgreSQL. Si solo admite PHP mediante un contenedor, habrá que preparar y validar su imagen, extensiones, servidor web y arranque cuando se elija ese destino. Esta auditoría no creó Dockerfile, configuración de Render ni archivos de despliegue específicos.

El servidor integrado `php -S` se utiliza aquí para desarrollo y pruebas locales; el manual de PHP indica que no debe exponerse a una red pública. [Servidor integrado de PHP](https://www.php.net/manual/en/features.commandline.webserver.php).

## Variables y comportamiento comprobado

`Env::load` lee `.env` fuera de `public/` y respeta variables ya definidas por el proceso. `Database::connection` utiliza DB_HOST, DB_PORT, DB_NAME, DB_USER y DB_PASSWORD, con consultas preparadas nativas y errores PDO por excepción. No interpreta automáticamente `DATABASE_URL`: si el proveedor solo entrega esa URL, habrá que descomponerla en variables privadas o adaptar y probar el lector en una tarea posterior.

Configurar expresamente `APP_ENV=production`, `APP_DEBUG=false`, `APP_TIMEZONE=America/La_Paz` y APP_NAME. APP_DEBUG no activa depuración en el código actual: los errores se ocultan mediante bootstrap. El modo production fuerza Secure en la cookie, incluso detrás de un proxy. Una prueba de servidor local con APP_ENV inyectada al proceso confirmó Secure; no equivale a haber probado un certificado HTTPS, proxy o proveedor real.

Las cookies usan HttpOnly y SameSite=Lax; se impone modo estricto, solo cookies y se renueva el ID al iniciar sesión. El middleware limita inactividad a 30 minutos y duración absoluta a 8 horas, y revalida cuenta/rol. El alojamiento debe preservar estas condiciones y no servir la aplicación mediante HTTP en producción. [Configuración segura de sesiones PHP](https://www.php.net/manual/en/session.security.ini.php).

## PostgreSQL y TLS

El DSN actual no fija `sslmode` ni una CA: eso no demuestra que una conexión remota sea segura. Para un servidor remoto, definir una política TLS y verificarla en el alojamiento antes de publicar. libpq permite `PGSSLMODE` y `PGSSLROOTCERT` cuando el código no especifica esos parámetros. Preferir `verify-full` con CA válida y nombre de servidor coincidente. `require` cifra, pero no aporta por sí solo la misma verificación de identidad que verify-full; el modo elegido depende de la configuración verificada del servidor.

Estas variables pueden inyectarse al proceso sin modificar Database.php. Si el alojamiento no las propaga o exige otra forma de conexión, se necesitará una adaptación explícita del DSN y sus pruebas. No se modificó la conexión local para anticipar una plataforma todavía no elegida. Fuentes: [PDO_PGSQL y sslmode](https://www.php.net/manual/en/ref.pdo-pgsql.connection.php), [variables libpq](https://www.postgresql.org/docs/17/libpq-envars.html), [verificación TLS PostgreSQL](https://www.postgresql.org/docs/17/libpq-ssl.html).

Separar la cuenta de instalación de una cuenta de aplicación con permisos necesarios sobre tablas y secuencias, sin superusuario ni permisos generales de esquema. `.env.example` usa postgres como ejemplo de desarrollo; no es una política de credenciales de producción. No se crearon usuarios PostgreSQL ni se cambiaron permisos en esta auditoría.

## Esquema y primer acceso

Para una base nueva autorizada, aplicar una sola vez 001 → 002 → 003 → 004 → seed, usando transacciones y deteniéndose ante errores. No repetir migraciones aplicadas; no hay tabla de historial ni ejecutor automático general de migraciones. Los ejecutores PHP existentes limitan deliberadamente su uso a 127.0.0.1/clinica_vida_salud_dev y no deben reutilizarse a ciegas para producción.

La extensión btree_gist debe estar disponible y el instalador debe poder crearla; es una extensión trusted, instalable con CREATE en la base según la documentación. No quitar GiST si el proveedor la restringe: elegir un alojamiento compatible o gestionar su habilitación. [btree_gist en PostgreSQL 17](https://www.postgresql.org/docs/17/btree-gist.html).

El seed contiene cuatro roles y tres especialidades, sin cuentas ni contraseñas predeterminadas. Crear la primera cuenta por CLI privado únicamente con usuarios vacíos, después del seed, mediante el asistente seguro documentado en README. Un servidor sin PowerShell requiere un canal privado compatible con el CLI PHP y su entrada por stdin; no publicar un instalador web ni pasar la contraseña como argumento. No subir un dump de la base local ni registros reales de pacientes al repositorio o a la demostración pública.

## Cambios indispensables antes de habilitar la URL

1. Elegir alojamiento que cumpla PHP/extensiones/PostgreSQL/btree_gist y configurar su servidor con raíz `public/` y enrutamiento al controlador frontal. No servir app/, database/, docs/, scripts/, tests/, .git ni .env.
2. Configurar entorno privado, HTTPS y APP_ENV=production; verificar cookies y recursos estáticos en el dominio definitivo. Las rutas y assets parten de `/`, por lo que se espera alojamiento en la raíz del dominio, no en un subdirectorio sin adaptación.
3. Preparar conexión PostgreSQL verificada, cuenta de aplicación con permisos acotados y proceso separado de migraciones/primera cuenta, con respaldo. Aplicar únicamente con autorización sobre la base destino.
4. Asegurar almacenamiento de sesiones y logs fuera de public; configurar límites de solicitudes al login en servidor/proxy. Actualmente la aplicación no implementa limitación de intentos, por lo que debe resolverse antes de una exposición abierta.
5. Probar en el destino login/logout, cuatro roles, registro y consulta de citas y concurrencia. La batería actual solo usa desarrollo local; nunca apuntarla directamente a producción. Crear datos de demostración ficticios únicamente con consentimiento.
6. Obtener URL HTTPS estable y luego generar/verificar el QR en los dispositivos de la presentación. No existe todavía URL o QR publicado como resultado de 5A.

No se ha demostrado que haga falta cambiar la lógica de CU01–CU14 para un servidor PHP/PostgreSQL compatible. Sí hacen falta las configuraciones de infraestructura anteriores. Las adaptaciones de DATABASE_URL, contenedor, proxy, subdirectorio o sesiones múltiples son condicionales al destino; no se implementaron anticipadamente.

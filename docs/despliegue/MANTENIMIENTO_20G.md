# Mantenimiento temporal — preparación local 20G

Fecha: 2026-11-09. Solo implementación y pruebas locales. **No autoriza commit,
push, merge, despliegue, cambios en Render ni migraciones.** No requiere programas
nuevos, recursos de pago ni cambios en Windows.

## Mecanismo

`public/index.php` ejecuta el guard antes del bootstrap, las sesiones, las rutas,
los controladores y PDO. Apache añade el mismo guard como `auto_prepend_file` a
todo PHP público. `require_once` evita duplicarlo. La configuración corresponde
a la imagen existente `php:8.4-apache-bookworm` con mod_php; debe probarse de nuevo
en el contenedor real. [Directiva PHP](https://www.php.net/manual/en/ini.core.php#ini.auto-prepend-file).

El guard devuelve HTTP **503**, `Retry-After: 120`, `Cache-Control: no-store` y una
página genérica sin datos internos. Cierra todas las rutas de negocio y todos sus
métodos, incluyendo GET, POST, PUT, PATCH, DELETE, HEAD y OPTIONS, con o sin sesión.
No autentica, consulta PostgreSQL ni crea sesiones al rechazar una solicitud.
Una sesión válida anterior tampoco abre el sistema durante el cierre.

Solo GET/HEAD de la ruta exacta `/health` devuelve 200 y `{"status":"ok"}`. No
comprueba PostgreSQL ni anuncia que la aplicación esté lista para los usuarios.
Otros métodos de `/health` devuelven 503 durante el cierre (405 al estar abierto).
CSS/JS/imágenes públicos siguen disponibles: no contienen datos privados.
Los controladores, configuración, sesiones, vendor y SQL permanecen fuera de
`DocumentRoot public/`, con acceso denegado por Apache. No se deben colocar
adjuntos clínicos ni archivos sensibles dentro de `public/`.

Las ejecuciones PHP CLI no son interceptadas. Los asistentes de migración
conservan sus comprobaciones de destino, TLS, transacción y autorización. No se
creó una ruta HTTP para ejecutar migraciones ni una excepción basada en URL/IP.

## Controles de entorno propuestos, todavía sin configurar

| Variable | Comportamiento |
|---|---|
| `APP_MAINTENANCE` | `on` cierra; `off` abre. También admite `0`/`false` para abrir. Un valor desconocido conserva el cierre. |
| `APP_ENV=production` | Si falta `APP_MAINTENANCE` o está vacía, cierra por defecto. El arranque Docker existente exige este entorno. |
| `APP_ENV=local` | Sin variable de mantenimiento, conserva el funcionamiento local habitual. |
| `APP_MAINTENANCE_TEST_HASH` | Opcional: SHA-256 de una clave aleatoria de 32 bytes, expresada en 64 caracteres hexadecimales minúsculos. Sin hash válido no existe acceso privado de pruebas. |

No se modificó `.env` ni se guardó ningún valor secreto. Las variables nuevas se
documentan aquí; todavía no se han incorporado a Render ni a `.env.example`.

Para validar la aplicación mientras está cerrada, una petición puede enviar
`X-Clinica-Maintenance-Key` con la clave privada correspondiente al hash. Esto
solo permite continuar hasta la aplicación: **siguen siendo obligatorios login,
sesión, permisos, vinculación del paciente y CSRF**. No se establece una cookie
de excepción ni se acepta una clave en query string o formulario.

La clave se generará y manejará en memoria mediante una consola privada, previa
autorización. Debe enviarse únicamente al dominio HTTPS oficial en cabeceras;
nunca por URL, comandos visibles, repositorio, capturas, logs o chat. Las pruebas
consola deberán conservar las cookies y los tokens CSRF normales y no reenviar
la cabecera a otros dominios. El log Apache actual no registra cabeceras ni query
strings. Conocer la clave permite alcanzar el login; no concede ningún rol.

Durante el cierre previo, el respaldo y las migraciones, dejar
`APP_MAINTENANCE_TEST_HASH` sin configurar: así ninguna cabecera permite entrar
a la aplicación. Habilitar el hash solo después del COMMIT satisfactorio para
la aceptación controlada de la versión completa; mantener mantenimiento on.

## Versión puente y versión completa

La base del puente probada es `1afbad7b4a68b01f9999f54c1c6d9fe8ce40bf37`
(Ciclo 1). Se añaden exclusivamente estos cuatro cambios de runtime:

1. `app/Config/Mantenimiento.php`.
2. `app/Config/mantenimiento-http.php`.
3. La inclusión del guard antes del bootstrap en `public/index.php`.
4. La directiva `php_admin_value auto_prepend_file` en `docker/apache/clinica.conf`.

No se reemplaza su `index.php` por el de CU26, no se incorpora código CU15–CU26
al puente y no se le añade ninguna dependencia. El guard no depende del esquema:
no requiere las migraciones 005–010 para cerrar, responder a `/health` o abrir
el comportamiento anterior. La prueba HTTP construye esta combinación exacta
en una exportación temporal aislada y la elimina al finalizar.

La versión completa se probó sobre
`23b1175fe865021a102a890c0f5b267eabad5588` más los mismos cuatro cambios. Ambas
versiones deben desplegarse con `APP_MAINTENANCE=on` y `/health` como health check.
El cierre no depende de un archivo del disco efímero, de la sesión o de la base;
por eso persiste entre instancias y despliegues que conserven estas variables.
La falta de la variable en producción también conserva el cierre.

Estos dos candidatos aún no tienen un nuevo hash de commit. El CU26 original
queda intacto, pero **no debe publicarse solo**, porque carece del guard. No se
crearon ramas, commits ni worktrees. La futura rama puente se preparará en un
checkout limpio, sin los cinco cambios locales protegidos.

## Pruebas ejecutadas ahora

Desde la raíz, con PHP 8.4, Git y `tar` ya disponibles:

```powershell
php tests/mantenimiento_validation.php
php tests/mantenimiento_http.php
```

El segundo script exporta ambos commits desde Git, superpone los cuatro cambios
y arranca servidores PHP en puertos loopback temporales. Usa un doble PDO sin
conexión, cuatro usuarios ficticios en memoria y los controladores reales de
login, dashboard, logout y autorización. No copia `.env`, vendor ni los cambios
pendientes; elimina variables de conexión heredadas. No crea bases, registros,
backups ni fixtures PostgreSQL. Al terminar cierra los servidores y elimina sus
copias de código, sesiones ficticias y logs temporales.

| Validación actual | Resultado |
|---|---|
| Estados, valores inválidos, producción cerrada por defecto, clave/hash, rutas alternativas y CLI | 217 comprobaciones aprobadas |
| Puente Ciclo 1, HTTP real con PDO en memoria | 8.161 comprobaciones aprobadas |
| CU26 más guard, HTTP real con PDO en memoria | 8.161 comprobaciones aprobadas |
| Coexistencia de ambas versiones cerradas, alternando solicitudes POST | 20 comprobaciones aprobadas |
| Total HTTP | **16.342 comprobaciones aprobadas** |

La matriz HTTP usa 45 rutas, siete métodos y cinco estados de acceso (anónimo y
cuatro roles autenticados). Cada respuesta se comprueba en varias propiedades;
estas cifras son **aserciones**, no miles de casos de uso distintos. Incluye
login, administración, citas y cambios de estado, agenda, historial, reportes,
PDF/CSV, perfiles y registros clínicos, portal, logout, PHP directo sintético,
rutas privadas, rutas codificadas y `index.php` con PATH_INFO. También prueba:

- Formularios AJAX con sesión y CSRF válidos bloqueados antes de PDO.
- Ninguna llamada adicional a la factoría de conexión durante la matriz cerrada.
- Respuestas sin datos de usuario, cookies de sesión nuevas ni contenido del PHP directo.
- Login/dashboard/logout normales para los cuatro roles con mantenimiento apagado.
- Acceso privado de prueba: anónimo redirigido a login, rol insuficiente 403 y CSRF inválido 419.
- Salud 200 sin base; HEAD sin cuerpo; clave incorrecta rechazada; recursos públicos disponibles.
- Front controller cerrado en producción incluso sin auto_prepend_file.

No se repitió la campaña funcional CU01–CU26 ni se probaron escrituras reales:
las actualizaciones de login se simulan en memoria. No se probaron micrófono
real, Render ni Apache dentro de Docker en esta entrega. Las verificaciones
del contenedor anteriores pertenecen al candidato CU26 sin mantenimiento.

## Riesgo del cambio de instancias en Render

Render conserva la instancia antigua mientras arranca la nueva. Su secuencia
documentada espera 60 segundos después del cambio de tráfico para enviar
SIGTERM; después aplica el plazo de apagado configurado. Un despliegue fallido
puede conservar el código anterior. [Secuencia oficial](https://render.com/docs/deploys#zero-downtime-deploys).

Por tanto, un build exitoso, Auto-Deploy Off o un 503 aislado **no prueban el
cierre**. El primer despliegue puente ocurre todavía con el esquema Ciclo 1.
Solo después de confirmar que todas las instancias antiguas terminaron y no
quedan escritores se permite comenzar a migrar. Si esto no puede comprobarse,
hay un bloqueo para producción.

Usar `/login` como health check del puente fallaría por su 503 y podría dejar
activo el código antiguo. Hay que coordinar `/health` con el primer puente y
confirmar el resultado en Events; cambiar la ruta antes puede hacer que la
instancia antigua parezca insana. No se asume que guardar una configuración no
despliega o no reinicia. [Health checks oficiales](https://render.com/docs/health-checks).

## Secuencia futura mínima, pendiente de autorizaciones

1. Revisar y guardar por separado el puente y el candidato CU26 con el guard.
   Construir y probar **ambas imágenes reales** en CI desde commits limpios:
   `apache2ctl -t`, PHP directo, rutas y métodos cerrados, `/health` 200, CLI
   disponible, modo abierto, sin conexión a producción. El workflow anterior
   exporta un SHA fijo y no valida automáticamente estas modificaciones.
2. En Render, confirmar servicio/plan/URL; desactivar Auto-Deploy y comprobar que
   no haya despliegues antiguos pendientes o en ejecución. No tocar PostgreSQL.
3. Seleccionar la futura rama puente como Branch del servicio antes de cualquier
   cambio que pueda reconstruir desde la rama vinculada. Configurar
   `APP_MAINTENANCE=on`, conservar `APP_ENV=production` y coordinar Health Check
   Path `/health`. No configurar el hash de acceso de pruebas. Desplegar
   explícitamente el commit puente aprobado. El puente
   sin variable de mantenimiento también está cerrado por defecto.
4. Confirmar en Events el SHA puente y el despliegue Live, salud 200 y 503
   repetidos en todas las rutas de negocio, incluyendo sesiones anteriores y
   POST. Comprobar el apagado de instancias antiguas y sus solicitudes en curso;
   esperar además el margen documentado (60 segundos más el shutdown delay real,
   no solo el cambio a Live). Confirmar por consulta de solo lectura que no
   quedan sesiones/transacciones escritoras de la aplicación y confirmar que no
   hay otros clientes que escriban. No inferir ausencia de escritores solo de
   una captura de `pg_stat_activity`. Si faltan evidencias, detenerse.
5. Repetir preflight de producción: destino/TLS/versión, esquema Ciclo 1,
   conflictos GiST, restricciones y comparación privada con el respaldo. Si
   cambiaron datos o secuencias, obtener y verificar un respaldo final con nueva
   autorización; no sobrescribir el existente. Mantener el cierre y evitar
   clientes CLI que escriban fuera del procedimiento autorizado.
6. Con autorización específica y conexión verificada, ejecutar 005–010 en una
   transacción, validar 16 tablas/30 FK, GiST e integridad antes de COMMIT.
   Cualquier fallo exige ROLLBACK completo. No incluirlo en el arranque Docker.
7. Tras éxito confirmado, actualizar `main` por fast-forward al nuevo commit
   completo **con guard**, preservando el historial. Revisar otra vez secretos e
   índice; excluir los cinco archivos pendientes y documentos privados.
8. Solo ahora volver a seleccionar Branch `main`, conservando mantenimiento on,
   `/health` y Auto-Deploy Off. Desplegar manualmente el SHA completo aprobado.
   Cualquier reconstrucción desde la rama vinculada será ahora código protegido.
   No desplegar ni recuperar un commit antiguo sin guard.
9. Después del COMMIT y despliegue completo, configurar el hash de pruebas y
   aplicar esa configuración manteniendo cierre público on. Validar mediante la cabecera privada y
   autenticación normal los cuatro roles, citas, PDF/CSV, clínica y portal.
   Usar únicamente identidades ficticias y escrituras expresamente autorizadas;
   nunca demostrar con datos potencialmente reales. Verificar públicamente que
   sin cabecera siguen bloqueadas todas las lecturas/escrituras de negocio.
10. Solo con aceptación y autorización de reapertura, establecer mantenimiento
    off y desplegar aplicando esa configuración. Comprobar login y navegación
    normales, salud y SHA final; mantener Auto-Deploy Off hasta nueva decisión.

No usar únicamente Restart para incorporar cambios pendientes de entorno: Render
documenta que reinicia con la configuración de la instancia actual.
[Reinicio y despliegue](https://render.com/docs/deploys#restarting-a-service).

Antes del COMMIT PostgreSQL, un fallo se revierte con ROLLBACK. Después, volver
al código anterior no revierte el esquema: mantener el cierre y decidir entre
corrección compatible o recuperación autorizada. No abrir el puente Ciclo 1
sobre el esquema nuevo; sus reglas de citas no conocen los estados del Ciclo 2.
Tampoco recuperar código sin guard tras iniciar las migraciones. Las dos
versiones protegidas permanecen cerradas ante un retorno entre ellas mientras
la configuración conserve mantenimiento on.

## Inventario y estado

Ocho archivos propios de 20G: dos modificados y seis nuevos. Sin staging.

| Archivo | Cambio |
|---|---|
| `public/index.php` | Modificado: guard antes del bootstrap |
| `docker/apache/clinica.conf` | Modificado: auto_prepend para PHP público |
| `app/Config/Mantenimiento.php` | Nuevo: decisión y respuesta HTTP |
| `app/Config/mantenimiento-http.php` | Nuevo: entrada compartida del guard |
| `tests/mantenimiento_validation.php` | Nuevo: pruebas de lógica/configuración/CLI |
| `tests/mantenimiento_http.php` | Nuevo: pruebas HTTP de las dos versiones |
| `tests/fixtures/mantenimiento_prepend.php` | Nuevo: PDO exclusivamente ficticio |
| `docs/despliegue/MANTENIMIENTO_20G.md` | Nuevo: procedimiento y evidencia |

Los cinco archivos previos protegidos, `.env`, Dockerfile, Composer, migraciones
001–010 y respaldo existente se preservan. Los tests no necesitan ni ejecutan
los archivos protegidos. GitHub, `main`, Auto-Deploy, Render y PostgreSQL no se
modificaron. El candidato CU26 conserva su hash y el índice permanece vacío.

**Resultado:** mecanismo local aprobado. Producción sigue pendiente de nueva
CI Docker, autorización y comprobación efectiva del puente/fin de escritores.
Sin esas barreras no se recomienda comenzar las migraciones remotas.

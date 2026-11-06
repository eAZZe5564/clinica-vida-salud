# Validación Docker en GitHub Actions — implementación 20B

Fecha: 2026-11-06. El usuario autorizó crear, revisar, confirmar y publicar el
workflow exclusivamente en `chore/preparar-despliegue-ciclo2`. No autorizó
producción, respaldos, migraciones ni despliegues. El resultado real se consulta
en la ejecución de Actions posterior al push; este documento no anticipa éxito.

## Inventario autorizado: seis archivos

- Los cinco documentos de `docs/despliegue/` preparados en 20A.
- [`.github/workflows/validar-docker-ciclo2.yml`](../../.github/workflows/validar-docker-ciclo2.yml).

Las pruebas PHP están embebidas en el YAML. No se crea el helper propuesto en
20A ni otros scripts: se conserva el inventario de seis archivos solicitado.
No cambia código funcional, Dockerfile, Composer ni SQL. Los cinco cambios
locales anteriores y .env quedan excluidos e intactos.

## Eventos, permisos y acción oficial

| Control | Implementación |
|---|---|
| Evento único | push a chore/preparar-despliegue-ciclo2 |
| Otros eventos | Ninguno; sin workflow_dispatch, PR, workflow_run o schedule |
| Job | ubuntu-24.04, máximo25 minutos, filtro adicional de repositorio/rama/evento |
| Permisos | contents:read; sin permisos de escritura, packages, deployments ni id-token |
| Checkout | actions/checkout **v7.0.1**, SHA completo **3d3c42e5aac5ba805825da76410c181273ba90b1** |
| Credencial de checkout | Token automático de GitHub de solo lectura; persist-credentials:false; no PAT ni credenciales de nube/Render |
| Código construido | Exportación Git del candidato CU26 **23b1175fe865021a102a890c0f5b267eabad5588** |
| Imagen | Local y efímera en el runner; no docker login/push ni registros externos |
| Artefactos | No se publican imagen, archivos PDF/CSV, logs privados ni datos clínicos |
| Red del runtime | network none; sin PostgreSQL, Render ni servicios externos |

La etiqueta/SHA se verificó contra el repositorio y release oficiales antes de
crear el workflow. Se revisaron los inputs y runtime Node24 de la acción.
[Release oficial de checkout](https://github.com/actions/checkout/releases/tag/v7.0.1).
No se cambia la configuración de Actions: la consulta anónima de permisos devolvió
401; solo una ejecución iniciada permitirá confirmar que Actions está habilitado
para este evento. La consulta pública de ejecuciones estaba disponible, sin runs
anteriores al preparar el workflow.

## Comandos y comprobaciones efectivas del workflow

1. Checkout del commit que dispara el workflow, sin persistir credenciales;
   comprobar su SHA, la ancestría de CU26 y exportar solo el árbol del candidato
   a RUNNER_TEMP. Así no se copia el árbol local con archivos protegidos sucios.
2. `docker build --pull --progress=plain --tag "$IMAGEN" "$FUENTE"` con el
   Dockerfile, composer.json y composer.lock reales, sin modificarlos. Registrar
   el ID de imagen. Las etiquetas base son mutables: el lock fija paquetes PHP,
   no garantiza reproducción binaria de todo el sistema operativo.
3. Dentro de la imagen comprobar PHP8.4, dom/mbstring/iconv/PDO/pdo_pgsql/session/
   json/openssl y driver PDO pgsql. Cargar vendor/autoload.php y comparar las
   cinco versiones instaladas con el lock: Dompdf v3.1.6, php-font-lib1.0.2,
   php-svg-lib1.0.2, html5 2.12.0 y php-css-parser v9.5.0.
4. Rechazar .env, cambios privados no versionados, SQL/asistentes/pruebas y
   archivos de claves/respaldos en el runtime. Inspeccionar el inicio para
   detectar invocaciones inesperadas; no ejecutar ninguna migración.
5. Ejecutar Composer --version, validate --strict, check-platform-reqs --no-dev,
   install --dry-run desde el lock y audit --locked. El build ya realiza el
   install real; el dry-run confirma que no necesita resolver versiones nuevas.
   No se actualizan paquetes ni se ignoran fallos de seguridad. Solo audit
   necesita red de consulta de avisos; las demás pruebas runtime están aisladas.
6. Ejecutar apache2 -v y apache2ctl -t con PORT10000.
7. Como www-data, generar PDF y CSV **reales por CLI**, usando exportadores del
   candidato y datos inventados en memoria, sin conexión a BD. Validar PDF
   %PDF-/%%EOF, CSV UTF-8 BOM/texto español y permisos de sesiones/temporales.
   No conservar ni publicar esos archivos.
8. Iniciar el entrypoint y CMD originales con network none. start.sh exige DB_*
   no vacíos incluso para servir un CSS: se usan nombre/usuario inexistentes,
   loopback puerto1 y valor aleatorio efímero sin cuenta PostgreSQL asociada.
   No son credenciales reales; el valor se enmascara y no queda en archivos.
   APP_ENV sigue production y PGSSLMODE verify-full; no se rebajan controles.
9. Desde el contenedor hacer HTTP real a `/assets/css/app.css` existente por
   loopback: exigir200, text/css y bytes idénticos al CSS exportado. Comprobar
   PID1 apache2 y proceso en ejecución. No crear una ruta de salud nueva.
10. Retirar únicamente el contenedor efímero del runner al terminar, también
    si falla. No hay reintentos automáticos del workflow; el bucle acotado HTTP
    es solo espera de disponibilidad del mismo proceso.

## Revisión local antes de publicar

Validar sintaxis YAML y estructura/eventos/permisos, comandos Bash y los tres
bloques PHP; no ejecutar el workflow localmente, porque no hay Docker instalado.
Ejecutado antes del commit: parser PyYAML6.0.3 temporal fuera del repositorio,
sin claves duplicadas y con estructura/permisos/evento correctos; bash -n en los
nueve bloques shell y php -l en los tres bloques PHP, todos sin errores.
Revisar índice exacto de seis archivos, diff --cached --check, ausencia de secretos,
identidad de autor ya configurada, candidato/ancestría, main local/remota y
SHA-256 de archivos protegidos/build/Composer/migraciones. No leer valores de .env.

Commit autorizado:
`chore: preparar despliegue ciclo2 y validacion Docker en CI`.
Push exclusivo a `origin/chore/preparar-despliegue-ciclo2`, sin force, PR o merge.
Después consultar el run correspondiente al SHA nuevo. Si falla el build,
recoger el error concreto y detener, sin modificar Dockerfile u otros archivos
ni hacer rerun. No confundir las comprobaciones locales con el resultado de CI.

## Límites que permanecen pendientes

- No existe PostgreSQL en este job. No se prueban /login, autenticación, PDO/TLS
  frente a un servidor, esquema, sesiones HTTP autenticadas ni invariancia de
  una BD ante reinicios. La inspección del inicio no sustituye ese ensayo.
- La prueba HTTP del CSS confirma arranque/Apache/ruta estática, no el MVC entero.
- PDF/CSV CLI reales no prueban descarga HTTP, cabeceras autenticadas, apariencia
  completa del PDF ni comparación de cifras con datos de CU21 en una BD.
- La integración con PostgreSQL17 **sintético** y TLS, si se desea, necesita una
  propuesta/autorización posterior. Este job no ejecuta SQL ni usa un dump real.
- El micrófono real de Edge sigue pendiente; las pruebas anteriores fueron
  simuladas. CU25 conserva recibo temporal sin auditoría persistente y selector
  administrativo limitado a100 cuentas.
- Actions verde no autoriza desplegar Render. Continúan los gates de backup,
  restauración privada, ensayo de migraciones, mantenimiento, main y despliegue
  documentados en [el plan](PLAN_RENDER_CICLO2.md).

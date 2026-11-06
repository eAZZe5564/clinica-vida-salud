# Plan de despliegue controlado del Ciclo 2

Fecha de revisión: 2026-11-06. **Preparación; no autoriza publicar ni acceder a producción.**

Actualización 20B: el usuario autorizó publicar únicamente documentación y CI en
la rama de preparación. Se creó el workflow de build/humo sin PostgreSQL descrito
en [su documentación](PROPUESTA_GITHUB_ACTIONS_DOCKER.md). La tabla siguiente
conserva la evidencia de 20A; el resultado del contenedor se consulta en Actions
después del push y no se presume exitoso aquí. Producción sigue fuera de alcance.

## Candidato y evidencia actual

| Elemento | Resultado comprobado en esta fase |
|---|---|
| Repositorio | https://github.com/eAZZe5564/clinica-vida-salud |
| Rama acumulativa | `feat/ciclo2-cu26-comandos-voz` |
| Candidato CU26 | `23b1175fe865021a102a890c0f5b267eabad5588` |
| Rama de preparación creada desde el candidato | `chore/preparar-despliegue-ciclo2`; sin commit ni push |
| Rama CU26 local/remota | Mismo SHA; cero commits de diferencia al revisar referencias |
| `main`, `origin/main` y referencia de GitHub | `1afbad7b4a68b01f9999f54c1c6d9fe8ce40bf37` |
| Diferencia main → CU26 | **245 archivos: 216 nuevos, 29 modificados; ninguno eliminado** |
| Ancestría | Verificados los commits de CU15–CU26 y el commit inicial del Ciclo 1 |
| Índice | Vacío; no se prepararon archivos para commit |
| Esquema local | Verificador CU25 de solo lectura: **16 tablas, 30 FK**, contrato 001–010 y vínculo único |
| PHP local | 8.4.22; dom, mbstring, iconv, PDO y pdo_pgsql disponibles |
| Sintaxis del código candidato | 293 archivos PHP y 2 JavaScript sin errores |
| Herramientas PostgreSQL locales | `pg_dump` y `pg_restore` 17.10 |
| Docker | No instalado/disponible; imagen **no construida ni ejecutada** |
| Composer en esta fase | Lock inspeccionado estáticamente; comando composer no disponible en PATH. No se repitieron validate/audit/check-platform-reqs; quedan como barreras de CI |
| Producción | Sin acceso al servicio, al Dashboard ni a la base de Render |

El [inventario exacto](INVENTARIO_MAIN_CU26.md) corresponde a los dos commits
anteriores, no a las modificaciones locales pendientes ni a estos documentos.
GitHub es público intencionalmente. No se suben respaldos, fixtures persistentes
ni evidencias clínicas al repositorio.

### Ancestría verificada

| Entrega | Commit |
|---|---|
| Ciclo 1 inicial | `810d660` |
| CU15 | `03136dde9011cfa559e0eeb732f59ac0c45f2448` |
| CU16 | `431a861bb9b01401691f16303735b9d4fbe4a70a` |
| CU17 | `078272a9f7f106dccb9149a8e76e6084d7caf764` |
| CU18 | `171c61936765367c1320c3ef6d1a5bab4eb8172f` |
| CU19 | `09de4d9bf1a7aea0cd4b1ef5561201b949e7f921` |
| CU20 | `866839ef96122a32426c0298fe8dc89fdc954d0c` |
| CU21 | `3415743bf7b9a1f8edf3b5e791520dbfec4df586` |
| CU22 | `58ab18767a3dc128c03b0fa6c5bf8695318e2cb4` |
| CU23 | `f3d275c3da6125438f9f577b699fe2c2d5b3bb78` |
| CU24 | `13d204d10632eb20d3707379b1f65cbe7aa0a026` |
| CU25 | `8e3fd8f38b6dfbbf706031c01c70aee677e64f94` |
| CU26 | `23b1175fe865021a102a890c0f5b267eabad5588` |

Comandos de lectura para repetir las verificaciones desde PowerShell:

```powershell
git status --short --branch
git branch --show-current
git rev-parse HEAD
git log -1 --oneline
git rev-list --left-right --count feat/ciclo2-cu26-comandos-voz...origin/feat/ciclo2-cu26-comandos-voz
git ls-remote --heads origin main feat/ciclo2-cu26-comandos-voz
git diff --name-status main...feat/ciclo2-cu26-comandos-voz
git diff --stat main...feat/ciclo2-cu26-comandos-voz
git diff --cached --name-only
php scripts/verificar-cu25-local.php
```

El último comando acepta exclusivamente loopback y `clinica_vida_salud_dev`;
abre una transacción READ ONLY y la revierte. No sirve como ejecutor remoto.
Si las referencias cambian, detener y recalcular el candidato y el inventario.
Las campañas históricas de 5.137 comprobaciones y la voz simulada no se
repitieron en esta fase. No se ejecutaron suites que crean fixtures.

Revisión actual de seguridad del árbol comprometido: 397 archivos; ningún .env,
vendor local, dump ni directorio de artefactos privados versionado. La búsqueda
de patrones de claves privadas/tokens/URL con credenciales no encontró secretos
evidentes. La única coincidencia de URL corresponde al fixture explícito
`dpg-fixture` en una prueba versionada, no a una conexión real. Esta revisión
no sustituye la inspección del índice antes de una publicación futura.
Los seis bloques PowerShell de la documentación pasaron el analizador sintáctico
sin ejecutarse. El ejemplo PHP de humo pasó php -l. El YAML propuesto en 20A se
revisó manualmente; 20B incorpora un parser YAML temporal y la validación del
workflow efectivo antes del commit.

## Preservación y procedencia del código

Continúan pendientes y excluidos los cinco archivos anteriores:

- `app/Config/Database.php` (modificado).
- `app/Config/DatabaseDiagnostic.php` (sin seguimiento).
- `scripts/render-postgres.ps1` (modificado).
- `tests/render_preparacion.ps1` (modificado).
- `tests/database_render_validation.php` (sin seguimiento).

Se comparan sus SHA-256 antes/después, junto con `.env`, Docker, Composer y las
migraciones 001–010. No guardar huellas ni contenido de `.env` en documentación.
Para auditar estos componentes se usa la versión de HEAD cuando hay cambios
pendientes. **El diagnóstico PDO local pendiente no forma parte del candidato.**

No construir la imagen desde este árbol sucio: `.dockerignore` permite
`app/**`, por lo que podría copiar el diagnóstico sin seguimiento. Construir
desde un checkout limpio de GitHub o una exportación del commit exacto, sin
revertir, borrar o guardar en stash los cambios privados del usuario.

## Auditoría estática del contenedor

| Área | Hallazgo y condición de aceptación |
|---|---|
| Bases | `composer:2.11.1` y `php:8.4-apache-bookworm`; etiquetas mutables. Registrar digest/ID de la imagen real. No prometer reproducción binaria por usar un lock de PHP |
| Extensiones | Instala `pdo_pgsql` y `mbstring`; exige `dom`, `mbstring`, `iconv` antes de Composer. dom/iconv dependen de la base y deben comprobarse en el build real; no basta su presencia en Windows |
| Dependencias | `composer install --no-dev --prefer-dist --no-interaction --no-progress --no-scripts --no-plugins --optimize-autoloader`, después `composer check-platform-reqs --no-dev` |
| Bloqueo | Dompdf **v3.1.6**, php-font-lib **1.0.2**, php-svg-lib **1.0.2**, masterminds/html5 **2.12.0**, php-css-parser **v9.5.0**; sin cambiar el lock |
| Composer raíz | `^3.1.6`, PHP `~8.4.0`, plataforma 8.4.0, licencia proprietary, plugins deshabilitados |
| Vendor | Se genera en `/var/www/clinica/vendor` durante el build y permanece en la imagen final. No se copia vendor local |
| Código y estáticos | Se copian app/public y configuraciones seleccionadas de docker. JS/CSS locales quedan bajo public; documentación, pruebas, SQL y asistentes no son parte del runtime |
| Apache | DocumentRoot `/var/www/clinica/public`, FallbackResource `/index.php`, raíz privada denegada, archivos ocultos denegados, sin listado de directorios |
| Puerto | `PORT` numérico, 1024–65535; predeterminado 10000, escucha 0.0.0.0; comprobar PORT real y `apache2ctl -t` |
| Sesiones | `/var/lib/clinica/sessions`, www-data, 0700; cookies Secure en producción, HttpOnly y SameSite Lax. Archivos efímeros: un reinicio puede cerrar sesiones |
| PDF/CSV | Dompdf con PHP/JS/recursos remotos desactivados, DejaVu Sans local, temporales privados en /tmp. CSV UTF-8 BOM, punto y coma y defensa frente a fórmulas |
| Permisos | www-data necesita escribir solo sesiones y temporales propios. Fuentes, vendor y app deben ser legibles; no requiere dar escritura general al código |
| Inicio/reinicio | start.sh valida entorno y Apache; no contiene migraciones, seed, creación de administrador ni descarga de paquetes. Confirmar además invariancia de la BD en ensayo con reinicios |
| HTTP | Rutas /login, /agenda, /reportes/citas, exportadores, páginas clínicas y /mis-citas pasan por index.php; requieren prueba real del contenedor |
| Privacidad | Contexto excluye .env, logs, sesiones, respaldos y claves; Apache registra ruta sin query string. No publicar logs crudos ni evidencias clínicas |

No se han probado todavía el inicio efectivo de Apache, TLS/libpq en la imagen,
descargas HTTP, escritura de temporales, fuentes españolas ni reinicio del
contenedor. La [propuesta de CI](PROPUESTA_GITHUB_ACTIONS_DOCKER.md) es la vía
para probarlo sin credenciales de Render. 20B crea el workflow y autoriza su
publicación solo en la rama de preparación; el resultado real se informa desde Actions.

### Variables existentes: revisar privadamente en la fase autorizada

| Variable | Uso |
|---|---|
| APP_ENV | production, exigido por start.sh |
| APP_DEBUG | false; sin detalles de PDO en respuestas |
| APP_TIMEZONE | America/La_Paz |
| APP_NAME | Nombre visible de la aplicación |
| PORT | Puerto que proporciona Render |
| DB_HOST / DB_PORT | Host de la conexión elegida y 5432; no introducir una URL en DB_HOST |
| DB_NAME / DB_USER / DB_PASSWORD | Valores individuales, actuales, del mismo recurso autorizado; privados en Render |
| PGSSLMODE | libpq lo interpreta desde el entorno; externo **verify-full** con CA; revisar la política interna ya documentada sin cambiarla en esta fase |
| PGSSLROOTCERT | Archivo CA legible cuando se verifica el certificado; no contiene una clave privada |

Env::load respeta variables ya presentes en el proceso. PDO construye host,
puerto y base con DB_* y pasa usuario/contraseña separados; **no interpreta
DATABASE_URL**. No se requieren variables nuevas ni cambios de `.env.example`.
Una conexión externa de respaldo usa verificación de certificado y hostname;
no sustituir por require, allow, prefer o disable si falla. Revisar por separado
la conexión interna de Render, su CA y política documentada antes de modificarla.

## Migraciones 005–010: revisión, no ejecución

Precondición histórica pendiente de comprobar: producción en 001–004, 8 tablas
y 10 FK. El nombre de recurso `clinica-vida-salud-db` **no confirma**
el nombre PostgreSQL, host ni cuenta actuales. No reutilizar credenciales antiguas.

| Paso | Efecto y comprobación después del paso, antes del COMMIT conjunto | Tablas/FK |
|---|---|---|
| 005 | Crea citas_historial, cita/usuario FK RESTRICT, fechas TIMESTAMPTZ, reprogramación programada→programada con cambio de horario e índice cita/fecha/id. Confirmar tipos, dos FK, CHECK e índice; historial inicialmente vacío | 9/12 |
| 006 | Añade motivo_cancelacion nullable y amplía CHECK para cancelación conservando horario. Confirmar VARCHAR(250), NULL en reprogramaciones y restricciones completas; no basta contar tablas | 9/12 |
| 007 | Añade confirmada, reconstruye GiST programada/confirmada, sustituye función validar_cita_ciclo1 conservando trigger. Confirmar función, trigger activo, predicado y regla de transición programada→confirmada | 9/12 |
| 008 | Añade presente/inasistencia, GiST programada/confirmada/presente/atendida/inasistencia; cancelada libera. CHECK de llegada, atención e inasistencia; UNIQUE parcial cita/tipo. Comprobar plazos, unicidad, trigger 007 y validez de todos los índices | 9/12 |
| 009 | Crea perfiles_clinicos, alergias_paciente, alergias_historial, ocho FK. Confirmar contrato completo, versiones y sustancia normalizada única solo si activa; desconocido/no_registrado no equivale a dato médico confirmado | 12/20 |
| 010 | Crea antecedentes_clinicos, antecedentes_historial, resultados_laboratorio, laboratorios_historial y diez FK. Confirmar columnas, tipos, fechas, versiones, UNIQUE, índices y snapshots de auditoría; no son archivos adjuntos | 16/30 |

Las ocho tablas originales son roles, usuarios, pacientes, medicos,
especialidades, medicos_especialidades, horarios_atencion y citas.
Todas las claves foráneas nuevas conservan referencias RESTRICT; las historias
mantienen trazabilidad. 009/010 no rellenan datos clínicos ni reescriben filas
anteriores. Mantener originales y administrador existente, sin seed adicional.

### Transacción, bloqueos y obstáculos

Los seis SQL no son idempotentes y no abren/cierran la transacción exterior.
Son DDL transaccional, sin CREATE INDEX CONCURRENTLY. Es viable aplicar
**005, 006, 007, 008, 009 y 010 en una sola transacción**, si el ensayo sobre
copia privada satisface todas las precondiciones y límites de tiempo.
No hacer seis commits independientes ni reintentar tras un resultado ambiguo.

007 y 008 toman ACCESS EXCLUSIVE sobre citas/citas_historial; reconstruir GiST
puede bloquear también lecturas y consumir recursos. ALTER TABLE y los índices
de las otras migraciones también necesitan bloqueos. Medir tiempos y espacio
en la copia representativa. Configurar lock_timeout/statement_timeout en el
futuro ejecutor conforme al ensayo, y cancelar si no se adquieren los bloqueos.

**Riesgo de datos concreto:** el GiST antiguo solo protegía las programadas.
Puede haber citas atendidas históricas superpuestas que antes eran legales.
008 las incluye y fallaría. La siguiente consulta es una propuesta de lectura,
NO ejecutada en Render; solo cuenta conflictos, sin identificar pacientes:

```sql
SELECT count(*) AS pares_superpuestos
FROM public.citas a
JOIN public.citas b ON a.id < b.id AND a.medico_id = b.medico_id
WHERE a.estado IN ('programada','confirmada','presente','atendida','inasistencia')
  AND b.estado IN ('programada','confirmada','presente','atendida','inasistencia')
  AND tsrange(a.fecha_hora_inicio AT TIME ZONE 'UTC',
        (a.fecha_hora_inicio AT TIME ZONE 'UTC')
        + make_interval(mins => a.duracion_minutos), '[)')
      && tsrange(b.fecha_hora_inicio AT TIME ZONE 'UTC',
        (b.fecha_hora_inicio AT TIME ZONE 'UTC')
        + make_interval(mins => b.duracion_minutos), '[)');
```

Debe dar cero antes de 008. No cancelar, borrar, cambiar estados ni debilitar
GiST para eludir un conflicto. Detener y plantear una resolución separada.
Si ya hay historial, contar duplicados de llegada/atencion/inasistencia y
comprobar cada CHECK nuevo antes del DDL. Si producción tiene cualquier parte
del Ciclo 2 o objetos ajenos, no asumir que se puede continuar desde 005:
inventariar el contrato exacto y revisar un plan específico.

Antes/después del conjunto se compararán en privado filas, secuencias, catálogo
original y huellas de las ocho tablas, sin imprimir registros ni hashes de
contraseñas. Se espera historial vacío tras 005 y las siete tablas clínicas
vacías tras 009/010, antes de pruebas; no se borrará información si no lo están.
Verificar btree_gist, constraints convalidated, índices indisvalid/indisready,
definición efectiva de GiST y función/trigger, no solo los totales 16/30.
Los contratos existentes de scripts/cu23 y scripts/cu24 y el SQL
scripts/render/verificar-instalacion.sql sirven como referencia de validación.
El verificador general puede reconocer versiones distintas; el futuro ejecutor
debe exigir **la versión exacta esperada en cada paso**, incluso cuando 005–008
comparten 9/12.

### Asistentes existentes y límite de su reutilización

- scripts/aplicar-migracion-005.php hasta 010.php y los verificadores *-local.php
  protegen loopback/dev. **No eliminar ni eludir sus guardas para producción.**
- La versión comprometida de scripts/render-postgres.ps1 contempla
  Comprobar/Inicializar/Verificar/Administrador, no una actualización 005–010.
- Comprobar exige base vacía. Inicializar vuelve a 001–004+seed.
  **Ninguna es el preflight ni la actualización de una base con datos.**
- Administrador no se ejecuta: ya existe una cuenta.
- Verificar es referencia de lectura, pero no prueba certificado desde SQL
  por sí solo; combinar identidad SQL con verify-full del cliente.

Falta preparar y revisar, en una fase autorizada, un ejecutor remoto específico:
identidad externa/BD/usuario/17/TLS, contrato inicial estricto, hashes de SQL,
preflight, mantenimiento, backup validado, un único BEGIN, guardas/timeouts,
validación por paso y COMMIT final. No crear ni ejecutar ese script ahora.
Una desconexión cerca de COMMIT obliga a inspección de solo lectura antes de
cualquier repetición.

## Compatibilidad de la transición

| Código / esquema | Evaluación |
|---|---|
| Ciclo 1 / 001–004 | Estado de referencia; verificarlo y ensayarlo antes |
| CU26 / 001–004 | No compatible: historia, reglas y tablas clínicas ausentes. No desplegar |
| Ciclo 1 / 001–010 | Tablas originales se conservan, pero el código antiguo no entiende todos los estados/ocupación nuevos. No darlo por compatible para escrituras |
| CU26 / 001–010 | Combinación prevista; requiere build, ensayo y comprobaciones HTTP efectivas |

Ni /login HTTP 200 ni que la migración sea aditiva demuestran compatibilidad.
No hacer una transición con instancias antiguas y nuevas escribiendo a la vez.
No hay una variable APP_MAINTENANCE implementada. Acordar un mecanismo real
de suspensión/bloqueo de tráfico y drenaje del servicio, con autorización.
Una página informativa o Auto-Deploy Off por sí solos no bloquean escrituras.

## Secuencia posterior, condicionada a autorización

1. Revisar esta documentación y autorizar por separado la creación/publicación
   del workflow propuesto solo en la rama de preparación. Confirmar que Render
   sigue conectado exclusivamente a main antes de cualquier push.
2. Ejecutar CI real contra el SHA exacto, con datos sintéticos solamente.
   Validar imagen, extensiones, Apache, permisos, PDF/CSV HTTP autenticado,
   TLS y reinicio sin cambios de tablas. Registrar SHA del código y digest.
   Si se corrige código/Docker, revisar el nuevo SHA e inventario y repetir gates.
3. Autorizar acceso de lectura a la base externa actual: identidad y PostgreSQL17,
   TLS verificado, contrato 001–004, conteos/estado y conflictos. No es autorización
   para migrar. Si hay cambios inesperados, detener.
4. Autorizar backup privado y restauración en un destino nuevo aislado.
   Ensayar 005–010 allí con imagen CU26 y datos protegidos sin salida a CI.
   Probar también código anterior y medir duración/bloqueos/compatibilidad.
5. Acordar hora, responsables y recuperación. Autorizar cambiar Auto-Deploy
   de On Commit a Off, verificar sin despliegues pendientes. Hacerlo **antes**
   de autorizar actualizar main; los controles de CI no sustituyen este paso.
6. Autorizar y aplicar mantenimiento real: bloquear tráfico y detener/drenar
   escritores antiguos. Registrar inicio sin mostrar datos clínicos.
7. Con las escrituras detenidas, tomar backup final, checksum y restauración
   aislada validada del mismo archivo. Un backup de ensayo anterior no basta.
   Si la verificación alarga la ventana, ampliarla; no omitirla.
8. Autorizar explícitamente migraciones remotas, host/base y hashes 005–010.
   Repetir identidad/preflight justo antes de escribir. Ejecutar transacción
   conjunta y validaciones por paso; rollback completo ante fallo.
9. Con Auto-Deploy Off, autorizar integración/publicación a main del candidato
   validado; sin los cinco cambios pendientes. Si un merge produce otro SHA,
   comprobar su árbol, volver a validar ese SHA y no desplegarlo sin revisión.
10. Autorizar despliegue manual del **SHA exacto aprobado** (no elegir
    automáticamente el último commit). Mantener cerrado el tráfico durante
    arranque y pruebas; verificar que Render muestra ese commit y /login sano.
    Las etiquetas base mutables exigen registrar también el build de Render.
11. Ejecutar el [checklist](CHECKLIST_RENDER_CICLO2.md), primero lecturas y
    permisos; escrituras con identidades ficticias autorizadas. Aislar fixtures,
    conservar evidencia técnica sin datos y acordar su retiro antes de crearlos.
12. Abrir tráfico solo tras aceptación. Autorizar aparte si se quiere reactivar
    Auto-Deploy; no es necesario para la defensa. Vigilar errores sanitizados
    y cerrar la ventana dejando responsable, SHA, esquema y respaldo registrado.

Render documenta que On Commit publica cambios de la rama conectada y Off
desactiva ese disparador: [despliegues de Render](https://render.com/docs/deploys).
Los valores del servicio aquí son los comunicados por el usuario, no una
inspección nueva del Dashboard.

## Detención y recuperación

Detener ante destino/TLS dudosos, versión o esquema inesperados, backup sin
restauración validada, conflictos GiST, timeout de bloqueos, índices inválidos,
cambio de SHA/dependencias, imagen no probada, fuga de información, permisos
incorrectos, fallos HTTP o mecanismo de mantenimiento sin verificar.

Antes de COMMIT: rollback de toda la transacción, verificar estado original y
seguir con el código anterior solo si sigue siendo compatible y la identidad
es correcta. Si el resultado del COMMIT es ambiguo, no repetir el SQL.

Después de COMMIT: parar tráfico. Preferir corrección compatible del código
sobre 001–010, con revisión. **Volver al código anterior no restaura PostgreSQL.**
Si se decide recuperar datos, usar el backup verificado en otra base nueva
privada, validar, autorizar el cambio de conexión y desplegar el código compatible.
Conservar la base fallida para reconciliar escrituras; no borrar tablas clínicas
ni sobrescribir bases. El procedimiento y el RPO se detallan en
[respaldo y recuperación](RESPALDO_RECUPERACION_POSTGRES.md).

## Pendientes y autorizaciones

- Build y pruebas del contenedor en CI: implementación autorizada por 20B,
  limitada a construcción, comprobaciones CLI, arranque y CSS HTTP sin BD.
  El run posterior al push determinará qué comprobaciones pasaron realmente.
- Lectura de producción, backup, creación/restauración de copia privada,
  ensayo y ejecutor remoto: requieren autorización posterior diferenciada.
- Auto-Deploy, mantenimiento, migraciones, main y despliegue: requieren
  autorización explícita y condiciones satisfechas; no ejecutados.
- Micrófono Edge real pendiente. Se preserva la alternativa manual.
- CU25: recibo temporal sin auditoría persistente de vinculación; selector
  limitado a 100 cuentas. No se crea una migración para resolverlo ahora.
- Confirmar privadamente plan, vigencia y capacidad de Render antes de fijar
  fecha. Free tiene límites y no ofrece recuperación administrada de PostgreSQL:
  [documentación de respaldos](https://render.com/docs/postgresql-backups).
- Sin cambios funcionales, de credenciales, Composer, Docker, SQL o archivos
  protegidos en esta preparación. No se accedió a Panadería Santiago.

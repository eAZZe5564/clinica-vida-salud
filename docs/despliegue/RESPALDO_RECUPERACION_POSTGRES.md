# Respaldo y recuperación de PostgreSQL 17

Preparado el 2026-11-06. **No se accedió a producción, no se creó un respaldo
ni se restauró una base.** Los comandos son propuestas para una fase autorizada.

## Condiciones y destinos

1. Autorizar primero lectura y backup. Confirmar en el Dashboard correcto host
   externo, nombre PostgreSQL, usuario y recurso de Clínica Vida Salud. El
   nombre del recurso no demuestra el valor de current_database().
2. Exigir conexión externa oficial, PostgreSQL17, TLS **verify-full** y CA
   verificada. No reutilizar una URL antigua ni modificar .env. No compartir
   contraseña, URL completa, dump o registros por chat.
3. Restaurar solo en una base **nueva, vacía, privada y aislada**, con nombre
   exclusivo de ensayo y sin servicios conectados. Nunca en producción,
   clinica_vida_salud_dev, Panadería Santiago ni otra base existente.
4. Un backup contiene datos personales/clínicos y hashes de cuentas, incluido
   el administrador. Nunca subirlo a GitHub, Actions, artefactos públicos o
   capturas, aunque no contenga la contraseña de conexión.
5. Elegir carpeta privada fuera de Git y de sincronización pública, con ACL
   limitada al operador y almacenamiento cifrado. Revisar permisos antes de
   escribir. No guardar claves de cifrado junto al dump.

Render indica que Free no tiene recuperación administrada; comprobar plan y
vigencia reales antes de depender de ella.
[Respaldos de Render](https://render.com/docs/postgresql-backups),
[límites Free](https://render.com/docs/free).

## Herramientas y alcance

Ejecutados ahora únicamente `pg_dump --version` y `pg_restore --version`:
**17.10**. Para servidor17 usar cliente17 compatible y actualizado; volver a
comprobar versiones el día de la operación.

pg_dump obtiene una instantánea consistente de una base, no los roles globales
del clúster ni la configuración de Render. No usar pg_dumpall para extraer
credenciales de roles. Registrar privadamente los permisos requeridos por la
cuenta de aplicación, sin contraseñas.
[Manual pg_dump17](https://www.postgresql.org/docs/17/app-pgdump.html).

## Patrón privado de PowerShell

Compatible con Windows PowerShell5.1 y PowerShell7. Se revisará como asistente
operativo en la fase autorizada; no se ha creado ni ejecutado un script nuevo.
No usar Start-Transcript, trazas ni impresión de variables de entorno.
La contraseña se lee oculta y solo se entrega al entorno del proceso hijo;
nunca a argumentos, URL o archivos.

```powershell
function Invoke-PgPrivado {
    param(
        [Parameter(Mandatory=$true)][string]$Ejecutable,
        [Parameter(Mandatory=$true)][string[]]$Argumentos,
        [Parameter(Mandatory=$true)][hashtable]$Conexion,
        [switch]$SoloLectura
    )
    foreach ($arg in $Argumentos) {
        if ($arg -match '["\r\n]') { throw 'Argumento no admitido.' }
    }
    $info = New-Object System.Diagnostics.ProcessStartInfo
    $info.FileName = $Ejecutable
    $info.Arguments = (($Argumentos | ForEach-Object { '"' + $_ + '"' }) -join ' ')
    $info.UseShellExecute = $false
    $info.CreateNoWindow = $true
    $info.RedirectStandardOutput = $true
    $info.RedirectStandardError = $true
    foreach ($key in @($info.EnvironmentVariables.Keys)) {
        if ([string]$key -like 'PG*') { $info.EnvironmentVariables.Remove([string]$key) }
    }
    $info.EnvironmentVariables['PGHOST'] = $Conexion.HostExterno
    $info.EnvironmentVariables['PGPORT'] = '5432'
    $info.EnvironmentVariables['PGDATABASE'] = $Conexion.Base
    $info.EnvironmentVariables['PGUSER'] = $Conexion.Usuario
    $info.EnvironmentVariables['PGSSLMODE'] = 'verify-full'
    $info.EnvironmentVariables['PGSSLROOTCERT'] = $Conexion.Ca
    $info.EnvironmentVariables['PGCONNECT_TIMEOUT'] = '10'
    $info.EnvironmentVariables['PGAPPNAME'] = 'clinica-respaldo-privado'
    if ($SoloLectura) {
        $info.EnvironmentVariables['PGOPTIONS'] = '-c default_transaction_read_only=on'
    }
    $puntero = [IntPtr]::Zero
    $proceso = New-Object System.Diagnostics.Process
    try {
        $puntero = [Runtime.InteropServices.Marshal]::SecureStringToBSTR($Conexion.Clave)
        $info.EnvironmentVariables['PGPASSWORD'] =
            [Runtime.InteropServices.Marshal]::PtrToStringBSTR($puntero)
        $proceso.StartInfo = $info
        if (-not $proceso.Start()) { throw 'No se inició el cliente PostgreSQL.' }
        $salidaTask = $proceso.StandardOutput.ReadToEndAsync()
        $errorTask = $proceso.StandardError.ReadToEndAsync()
        $proceso.WaitForExit()
        $salidaPrivada = $salidaTask.GetAwaiter().GetResult()
        $errorPrivado = $errorTask.GetAwaiter().GetResult()
        if ($proceso.ExitCode -ne 0) {
            throw ('Cliente terminó con código ' + $proceso.ExitCode +
                   '. Detener; revisar el diagnóstico solo en sesión privada.')
        }
        if (-not [string]::IsNullOrWhiteSpace($errorPrivado)) {
            throw 'Cliente emitió un aviso; revisar privadamente antes de aceptar.'
        }
        return $salidaPrivada
    }
    finally {
        $info.EnvironmentVariables.Remove('PGPASSWORD')
        if ($puntero -ne [IntPtr]::Zero) {
            [Runtime.InteropServices.Marshal]::ZeroFreeBSTR($puntero)
        }
        $proceso.Dispose()
    }
}
```

Se leen stdout/stderr simultáneamente para evitar bloqueos, sin imprimirlos.
El error nativo puede incluir información del origen; no publicar el objeto,
stderr ni la conexión. Este patrón limita exposición y persistencia: libpq
necesita la contraseña brevemente en memoria y no se garantiza el borrado
inmediato de todas las copias administradas. Cerrar la sesión privada al terminar.

### Captura y confirmación del origen, pendientes

```powershell
$clientePg = 'C:\Program Files\PostgreSQL\17\bin'
$conexionOrigen = @{
    HostExterno = (Read-Host 'Host EXTERNO actual, sin URL').Trim()
    Base = (Read-Host 'Nombre PostgreSQL actual de Clínica Vida Salud').Trim()
    Usuario = (Read-Host 'Usuario PostgreSQL actual').Trim()
    Ca = (Read-Host 'Ruta absoluta de la CA pública verificada').Trim()
    Clave = (Read-Host 'Contraseña PostgreSQL actual' -AsSecureString)
}
if ($conexionOrigen.HostExterno -notmatch '^dpg-[a-z0-9-]+\.[a-z0-9-]+-postgres\.render\.com$' -or
    $conexionOrigen.Base -notmatch '^clinica_vida_salud(_[a-z0-9]+)*$' -or
    $conexionOrigen.Base -eq 'clinica_vida_salud_dev' -or
    $conexionOrigen.Usuario -notmatch '^[a-zA-Z0-9_]+$' -or
    -not [IO.Path]::IsPathRooted($conexionOrigen.Ca) -or
    -not (Test-Path -LiteralPath $conexionOrigen.Ca -PathType Leaf)) {
    throw 'Destino/CA no admitidos; detener.'
}
$confirmacion = Read-Host 'Tras contrastar el Dashboard, escribe el nombre exacto de la base'
if ($confirmacion -cne $conexionOrigen.Base) { throw 'Destino sin confirmar.' }
$consultaIdentidad = "SELECT json_build_object('base',current_database(),'usuario',current_user,'version',current_setting('server_version_num')::int,'tls',COALESCE((SELECT ssl FROM pg_stat_ssl WHERE pid=pg_backend_pid()),false),'tablas',(SELECT count(*) FROM pg_tables WHERE schemaname='public'),'fk',(SELECT count(*) FROM pg_constraint WHERE connamespace='public'::regnamespace AND contype='f'));"
$argumentosIdentidad = @('-X','-w','-A','-t','-v','ON_ERROR_STOP=1','-c',$consultaIdentidad)
$identidad = (Invoke-PgPrivado -Ejecutable "$clientePg\psql.exe" -SoloLectura -Conexion $conexionOrigen -Argumentos $argumentosIdentidad) | ConvertFrom-Json
if ($identidad.base -cne $conexionOrigen.Base -or
    $identidad.usuario -cne $conexionOrigen.Usuario -or
    $identidad.version -lt 170000 -or $identidad.version -ge 180000 -or
    $identidad.tls -ne $true) { throw 'Identidad, versión o TLS no satisfactorios.' }
if ($identidad.tablas -ne 8 -or $identidad.fk -ne 10) {
    throw 'Esquema distinto del inicial esperado; requiere nuevo plan.'
}
```

El SQL verifica identidad y cifrado; la cadena de confianza y hostname los
valida libpq con verify-full/PGSSLROOTCERT. No cambiar a require, allow, prefer
o disable si falla, ni usar PGHOSTADDR para saltar DNS.
[TLS PostgreSQL17](https://www.postgresql.org/docs/17/libpq-ssl.html).

Completar el contrato estricto 001–004 y el conteo de conflictos GiST del
[plan](PLAN_RENDER_CICLO2.md). 8/10 es necesario pero no suficiente. Comprobar
del asistente original exige base vacía y no es el preflight de esta actualización.

## Archivo privado: acción posterior autorizada

Revisar antes ACL, cifrado y ausencia de sincronización de la carpeta elegida.
Se crea una subcarpeta nueva; no se sobrescriben archivos:

```powershell
$raizRespaldo = (Read-Host 'Carpeta privada existente, fuera de Git y sincronización').Trim()
$repoClinica = [IO.Path]::GetFullPath('C:\Users\vacaa\Desktop\clinica-vida-salud').TrimEnd('\')
$raizReal = (Resolve-Path -LiteralPath $raizRespaldo -ErrorAction Stop).ProviderPath.TrimEnd('\')
if ($raizReal -eq $repoClinica -or
    $raizReal.StartsWith($repoClinica + '\',[StringComparison]::OrdinalIgnoreCase)) {
    throw 'No guardar respaldos dentro del repositorio.'
}
$nombreBackup = 'clinica-' + (Get-Date -Format 'yyyyMMdd-HHmmss') + '-' + [Guid]::NewGuid().ToString('N')
$carpetaBackup = Join-Path $raizReal $nombreBackup
New-Item -ItemType Directory -Path $carpetaBackup -ErrorAction Stop | Out-Null
$archivoDump = Join-Path $carpetaBackup 'clinica.dump'
$argumentosDump = @('--no-password','--format=custom','--lock-wait-timeout=5s',('--file=' + $archivoDump))
$salidaPrivada = Invoke-PgPrivado -Ejecutable "$clientePg\pg_dump.exe" -SoloLectura -Conexion $conexionOrigen -Argumentos $argumentosDump
$archivo = Get-Item -LiteralPath $archivoDump -ErrorAction Stop
if ($archivo.Length -le 0) { throw 'Respaldo vacío; no autorizar migraciones.' }
$sha256 = (Get-FileHash -LiteralPath $archivoDump -Algorithm SHA256).Hash
$rutaToc = Join-Path $carpetaBackup 'contenido-privado.txt'
$argumentosToc = @('--list',('--file=' + $rutaToc),$archivoDump)
$tocPrivado = Invoke-PgPrivado -Ejecutable "$clientePg\pg_restore.exe" -Conexion $conexionOrigen -Argumentos $argumentosToc
$manifiesto = [ordered]@{
    FechaUTC = (Get-Date).ToUniversalTime().ToString('o')
    Archivo = 'clinica.dump'; Bytes = $archivo.Length; SHA256 = $sha256
    PostgreSQLCliente = '17'; Formato = 'custom'
    Estado = 'archivo_y_TOC_comprobados_restauracion_PENDIENTE'
}
$manifiesto | ConvertTo-Json | Set-Content -Encoding UTF8 -LiteralPath (Join-Path $carpetaBackup 'manifiesto-privado.json')
```

No usar filtros de tablas/esquemas ni --schema-only/--data-only. Revisar el TOC
privadamente: todas las tablas originales, TABLE DATA, secuencias/valores,
índices, FK, trigger, función y btree_gist. No imprimir TOC ni dump. Guardar en
registro privado conteos y metadatos y confirmar conservación del administrador,
sin mostrar su hash. --list no se conecta a la BD.

SHA-256, archivo no vacío y TOC son controles parciales. **No demuestran una
restauración completa** ni bastan para autorizar las migraciones.

## Restauración y ensayo: autorización separada

1. Crear, tras autorización, una nueva base en PostgreSQL17 privado aislado,
   con TLS validado y btree_gist disponible. Nombre:
   `clinica_ensayo_ciclo2_` seguido de un UUID sin guiones.
2. Capturar en una hashtable nueva `$conexionEnsayo` su host/base/usuario/CA y
   contraseña oculta. No reutilizar una conexión de origen parcialmente editada.
3. Confirmar identidad independiente, nombre distinto de producción/dev y
   **cero relaciones de usuario en todos los esquemas no internos**, no solo
   cero tablas public. No conectar servicios a ese destino.
4. Verificar SHA del dump y cliente/servidor17. Autorizar explícitamente la
   restauración en esa base nueva vacía, sin --clean ni --create:

```powershell
if ($conexionEnsayo.Base -notmatch '^clinica_ensayo_ciclo2_[a-f0-9]{32}$' -or
    $conexionEnsayo.Base -eq $conexionOrigen.Base -or
    $conexionEnsayo.Base -eq 'clinica_vida_salud_dev') {
    throw 'Destino de ensayo no permitido.'
}
if (-not $ensayoIdentidadVaciaConfirmada) { throw 'Falta preflight real del destino aislado.' }
if ((Get-FileHash -LiteralPath $archivoDump -Algorithm SHA256).Hash -cne $sha256) {
    throw 'Respaldo alterado.'
}
$argumentosRestore = @('--no-password',('--dbname=' + $conexionEnsayo.Base),'--single-transaction','--exit-on-error','--no-owner','--no-privileges',$archivoDump)
$salidaRestauracionPrivada = Invoke-PgPrivado -Ejecutable "$clientePg\pg_restore.exe" -Conexion $conexionEnsayo -Argumentos $argumentosRestore
```

`$ensayoIdentidadVaciaConfirmada` representa una comprobación efectiva del
futuro asistente, todavía pendiente; no asignarle true para eludirla.
`--dbname` es obligatorio: sin él pg_restore genera SQL en vez de restaurar.
no-owner/no-privileges permiten una cuenta aislada distinta; revisar después
los permisos antes de utilizar una base recuperada para servir la aplicación.
[Manual pg_restore17](https://www.postgresql.org/docs/17/app-pgrestore.html).

### Criterios de aceptación

- Restore completo, código0, sin avisos pendientes ni errores ocultos.
- Contrato original exacto: columnas/tipos/CHECK/FK/GiST/trigger/función,
  btree_gist y todos los índices válidos.
- Conteos de cada tabla y valores de secuencias coincidentes con el snapshot;
  comparar huellas de filas solo en memoria o registro privado. Informar
  únicamente coinciden/no coinciden, nunca datos o hashes de cuentas.
- Administrador activo original y roles conservados. No recrearlo ni repetir seed.
- Validar lecturas, integridad y login en entorno privado aislado. No enviar
  muestras a CI, capturas médicas o servicios públicos.
- Ensayar después 005–010 en **una transacción**, validando cada paso y 16/30
  final. Registrar tiempos/bloqueos/espacio, compatibilidad e invariancia de las
  ocho tablas originales. Las nuevas tablas clínicas deben empezar vacías.
- Pruebas ficticias y reinicios solo en el ensayo. No eludir las guardas dev
  de los scripts *-local.php: el ejecutor aislado tendrá guardas propias revisadas.
- Registrar resultado/fecha, SHA del dump/código/SQL y restauración verificada
  en el manifiesto privado. Solo entonces considerar confiable el backup.

pg_dump no bloquea escrituras: comparar conteos tomados en otra sesión mientras
la app escribe puede comparar instantes distintos. En el ensayo inicial usar
snapshot coordinado o una ventana sin escrituras. En el **backup final** exigir
escrituras detenidas desde antes del snapshot hasta concluir el despliegue.
Restaurar y verificar ese mismo backup final antes de migrar; si toma más tiempo,
ampliar mantenimiento, no omitir el control.

## Recuperación y pérdida máxima de datos

| Fallo | Acción |
|---|---|
| Antes de migrar | Conservar código/base y resolver el gate fallido |
| Antes del COMMIT conjunto | Rollback completo y verificación del contrato inicial; reabrir solo con código compatible |
| Desconexión durante COMMIT | Mantener mantenimiento, inspeccionar solo lectura; no repetir SQL a ciegas |
| Después del COMMIT, sin escrituras nuevas | Preferir corrección compatible de código; si se necesita 001–004, restaurar en **otra base nueva**, validar y autorizar cambio de conexión/código |
| Tras nuevas escrituras | Detener tráfico, conservar ambas bases; valorar/reconciliar lo posterior al backup antes de restaurar |

El RPO abarca las escrituras no incluidas después del snapshot. No afirmar RPO0
sin demostrar una ventana efectiva sin escrituras. El RTO se estima midiendo el
ensayo; no hay duración garantizada ahora. Un rollback de Render solo cambia
código, no revierte PostgreSQL.

No borrar tablas clínicas, reinicializar, sobrescribir bases ni eliminar
respaldos para recuperar. Conservar backup y base fallida bajo acceso privado
hasta resolver la reconciliación y retención. Lectura remota, backup,
creación/restauración de ensayo, migraciones y cambios de conexión/despliegue
necesitan sus respectivas autorizaciones posteriores.

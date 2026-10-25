param(
    [ValidateSet('Comprobar','Inicializar','Verificar','Administrador')]
    [string]$Accion = 'Comprobar',
    [string]$CaCertificado
)
$ErrorActionPreference = 'Stop'

function ConvertFrom-RenderUrl {
    param([string]$Url, [string]$HostConfirmado, [string]$BaseConfirmada, [string]$Ca)
    try {
        if ($Url.Length -gt 8192 -or $Url -match '[\x00-\x20\x7f]') { throw 'URL inválida' }
        $uriRender = [Uri]$Url
        if (-not $uriRender.IsAbsoluteUri -or $uriRender.Scheme -notin @('postgres','postgresql') -or $uriRender.Fragment) { throw 'URL inválida' }
        $datosUsuario = $uriRender.UserInfo -split ':', 2
        if ($datosUsuario.Count -ne 2) { throw 'Faltan credenciales' }
        $configRender = @{
            host = $uriRender.DnsSafeHost
            puerto = $(if ($uriRender.Port -eq -1) { 5432 } else { $uriRender.Port })
            base = [Uri]::UnescapeDataString($uriRender.AbsolutePath.TrimStart('/'))
            usuario = [Uri]::UnescapeDataString($datosUsuario[0])
            password = [Uri]::UnescapeDataString($datosUsuario[1])
            host_confirmado = $HostConfirmado
            base_confirmada = $BaseConfirmada
            ca = $Ca
        }
        if ($configRender.host -cnotmatch '^dpg-[a-z0-9-]+\.[a-z0-9-]+-postgres\.render\.com$' -or $configRender.host -cne $HostConfirmado -or
            $configRender.base -cnotmatch '^clinica_vida_salud(?:_[a-z0-9]+)*$' -or $configRender.base.Length -gt 63 -or
            $configRender.base -ceq 'clinica_vida_salud_dev' -or $configRender.base -cne $BaseConfirmada -or
            $configRender.usuario -cnotmatch '^[a-z_][a-z0-9_]{0,62}$' -or $configRender.puerto -ne 5432 -or
            -not $configRender.password -or $configRender.password -match '[\x00-\x1f\x7f]' -or -not (Test-Path -LiteralPath $Ca -PathType Leaf)) { throw 'Destino inválido' }
        return $configRender
    } catch {
        # Las excepciones de Uri pueden incluir la URL: no propagarlas.
        throw 'URL/destino no autorizado. Usa la conexión EXTERNA de la base NUEVA de Clínica Vida Salud, confirma host/base y verifica la CA.'
    }
}

function Invoke-RenderProcess {
    param([string]$Executable, [string]$Arguments, [hashtable]$Config, [string[]]$Entrada = @())
    $inicioRender = New-Object System.Diagnostics.ProcessStartInfo
    $inicioRender.FileName = $Executable
    $inicioRender.Arguments = $Arguments
    $inicioRender.UseShellExecute = $false
    $inicioRender.CreateNoWindow = $true
    $inicioRender.RedirectStandardInput = $true
    $inicioRender.RedirectStandardOutput = $true
    $inicioRender.RedirectStandardError = $true
    $inicioRender.StandardOutputEncoding = New-Object System.Text.UTF8Encoding($false)
    $inicioRender.StandardErrorEncoding = New-Object System.Text.UTF8Encoding($false)
    # No heredar PGSERVICE, PGHOSTADDR, PGOPTIONS u otros desvíos de conexión del equipo.
    foreach ($claveRender in @($inicioRender.EnvironmentVariables.Keys)) {
        if ($claveRender -like 'PG*') { $inicioRender.EnvironmentVariables.Remove($claveRender) }
    }
    $inicioRender.EnvironmentVariables['PGHOST'] = $Config.host
    $inicioRender.EnvironmentVariables['PGPORT'] = '5432'
    $inicioRender.EnvironmentVariables['PGDATABASE'] = $Config.base
    $inicioRender.EnvironmentVariables['PGUSER'] = $Config.usuario
    $inicioRender.EnvironmentVariables['PGPASSWORD'] = $Config.password
    $inicioRender.EnvironmentVariables['PGSSLMODE'] = 'verify-full'
    $inicioRender.EnvironmentVariables['PGSSLROOTCERT'] = $Config.ca
    $inicioRender.EnvironmentVariables['PGCONNECT_TIMEOUT'] = '10'
    $inicioRender.EnvironmentVariables['PGCLIENTENCODING'] = 'UTF8'
    $inicioRender.EnvironmentVariables['PGOPTIONS'] = '-c search_path=public'
    $inicioRender.EnvironmentVariables['PGAPPNAME'] = 'clinica-render-consola'
    $procesoRender = New-Object System.Diagnostics.Process
    $procesoRender.StartInfo = $inicioRender
    try {
        [void]$procesoRender.Start()
        $salidaRender = $procesoRender.StandardOutput.ReadToEndAsync()
        $errorRender = $procesoRender.StandardError.ReadToEndAsync()
        # .NET Framework de Windows PowerShell 5.1 no tiene StandardInputEncoding.
        # Escribir bytes UTF-8 sin BOM mantiene contraseñas Unicode sin depender de la página de códigos.
        $codificadorRender = New-Object System.Text.UTF8Encoding($false)
        $bytesRender = $codificadorRender.GetBytes(([string]::Join("`n", $Entrada) + "`n"))
        $procesoRender.StandardInput.BaseStream.Write($bytesRender, 0, $bytesRender.Length)
        $procesoRender.StandardInput.BaseStream.Close()
        [Array]::Clear($bytesRender, 0, $bytesRender.Length)
        $procesoRender.WaitForExit()
        $salidaTextoRender = $salidaRender.Result
        $errorTextoRender = $errorRender.Result
        if ($procesoRender.ExitCode -ne 0) {
            # No imprimir diagnósticos nativos que puedan incluir configuración sensible.
            throw 'La operación no terminó. Verifica CA/TLS, acceso externo, destino, versión 17, estado del esquema y requisitos de contraseña. No se continúa con otros scripts.'
        }
        if ($salidaTextoRender) { Write-Output $salidaTextoRender.TrimEnd() }
    } finally {
        $inicioRender.EnvironmentVariables['PGPASSWORD'] = ''
        $Entrada = $null
        $errorTextoRender = $null
        $procesoRender.Dispose()
    }
}

function Invoke-RenderPsql {
    param([hashtable]$Config, [string[]]$Archivos)
    $psqlRender = (Get-Command psql -ErrorAction Stop).Source
    $argumentosRender = '-X -w -q --single-transaction -v ON_ERROR_STOP=1 -v "base_esperada=' + $Config.base + '" -v "usuario_esperado=' + $Config.usuario + '"'
    foreach ($archivoRender in $Archivos) {
        $rutaRender = [IO.Path]::GetFullPath((Join-Path (Split-Path -Parent $PSScriptRoot) $archivoRender))
        if (-not (Test-Path -LiteralPath $rutaRender -PathType Leaf) -or $rutaRender.Contains('"')) { throw 'Ruta de instalación inválida' }
        $argumentosRender += ' -f "' + $rutaRender + '"'
    }
    Invoke-RenderProcess -Executable $psqlRender -Arguments $argumentosRender -Config $Config
}

# Dot-sourcing permite probar las funciones sin red, prompts ni operaciones.
if ($MyInvocation.InvocationName -ne '.') {
    $urlSeguraRender = $null
    $claveSeguraRender = $null
    $punteroRender = [IntPtr]::Zero
    $configRender = $null
    try {
        if (-not $CaCertificado -or -not (Test-Path -LiteralPath $CaCertificado -PathType Leaf)) { throw 'Indica -CaCertificado con la ruta de un certificado CA público confiable.' }
        $caRender = (Resolve-Path -LiteralPath $CaCertificado).Path
        $hostRender = Read-Host 'Host EXTERNO de la base NUEVA en tu cuenta NUEVA de Render (sin URL ni contraseña)'
        $baseRender = Read-Host 'Nombre PostgreSQL de esa base (clinica_vida_salud o prefijo clinica_vida_salud_; nunca _dev)'
        $urlSeguraRender = Read-Host 'Pega External Database URL de esa misma base (entrada oculta)' -AsSecureString
        $punteroRender = [Runtime.InteropServices.Marshal]::SecureStringToBSTR($urlSeguraRender)
        $urlTextoRender = [Runtime.InteropServices.Marshal]::PtrToStringBSTR($punteroRender)
        $configRender = ConvertFrom-RenderUrl -Url $urlTextoRender -HostConfirmado $hostRender -BaseConfirmada $baseRender -Ca $caRender
        $urlTextoRender = $null
        [Runtime.InteropServices.Marshal]::ZeroFreeBSTR($punteroRender)
        $punteroRender = [IntPtr]::Zero
        $comprobarRender = 'scripts/render/comprobar-destino.sql'
        $vaciaRender = 'scripts/render/exigir-base-vacia.sql'
        $verificarRender = 'scripts/render/verificar-instalacion.sql'
        switch ($Accion) {
            'Comprobar' { Invoke-RenderPsql $configRender @($comprobarRender, $vaciaRender) }
            'Inicializar' {
                $confirmacionRender = Read-Host ('Solo para la base NUEVA y VACIA en tu cuenta NUEVA. Escribe: INICIALIZAR ' + $baseRender + ' EN ' + $hostRender)
                if ($confirmacionRender -cne ('INICIALIZAR ' + $baseRender + ' EN ' + $hostRender)) { throw 'No se autorizó la inicialización' }
                Invoke-RenderPsql $configRender @($comprobarRender, $vaciaRender,
                    'database/migrations/001_esquema_ciclo1.sql','database/migrations/002_pacientes_cu04_cu06.sql',
                    'database/migrations/003_medicos_especialidades.sql','database/migrations/004_citas_especialidad.sql',
                    'database/seeds/001_datos_base.sql', $verificarRender)
                Write-Output 'Migraciones y catálogos aplicados en una sola transacción. No repitas Inicializar.'
            }
            'Verificar' { Invoke-RenderPsql $configRender @($comprobarRender, $verificarRender) }
            'Administrador' {
                Invoke-RenderPsql $configRender @($comprobarRender, $verificarRender)
                $confirmacionRender = Read-Host ('Escribe: CREAR ADMIN EN ' + $baseRender + ' EN ' + $hostRender)
                if ($confirmacionRender -cne ('CREAR ADMIN EN ' + $baseRender + ' EN ' + $hostRender)) { throw 'No se autorizó la cuenta inicial' }
                $correoRender = Read-Host 'Correo del primer administrador (no uses una cuenta de demostración predefinida)'
                $claveSeguraRender = Read-Host 'Contraseña nueva: mínimo 8 caracteres, mayúscula, minúscula, número y símbolo; máximo 72 bytes' -AsSecureString
                $punteroRender = [Runtime.InteropServices.Marshal]::SecureStringToBSTR($claveSeguraRender)
                $claveTextoRender = [Runtime.InteropServices.Marshal]::PtrToStringBSTR($punteroRender)
                $phpRender = (Get-Command php -ErrorAction Stop).Source
                $scriptRender = Join-Path $PSScriptRoot 'render-administrador.php'
                Invoke-RenderProcess -Executable $phpRender -Arguments ('"' + $scriptRender + '"') -Config $configRender -Entrada @(
                    ($configRender | ConvertTo-Json -Compress), $correoRender, $claveTextoRender)
            }
        }
    } finally {
        if ($punteroRender -ne [IntPtr]::Zero) { [Runtime.InteropServices.Marshal]::ZeroFreeBSTR($punteroRender) }
        if ($urlSeguraRender) { $urlSeguraRender.Dispose() }
        if ($claveSeguraRender) { $claveSeguraRender.Dispose() }
        if ($configRender) { $configRender.password = $null }
        $urlTextoRender = $null
        $claveTextoRender = $null
    }
}

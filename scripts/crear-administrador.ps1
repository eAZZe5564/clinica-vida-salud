$ErrorActionPreference = 'Stop'

$php = (Get-Command php -ErrorAction Stop).Source
$script = Join-Path $PSScriptRoot 'crear-administrador.php'
$correo = Read-Host 'Correo para la cuenta administradora inicial'
$segura = Read-Host 'Contraseña inicial (12-72 bytes; letras y números)' -AsSecureString
$ptr = [IntPtr]::Zero
$proceso = $null

try {
    $ptr = [Runtime.InteropServices.Marshal]::SecureStringToBSTR($segura)
    $contrasena = [Runtime.InteropServices.Marshal]::PtrToStringBSTR($ptr)

    $inicio = New-Object System.Diagnostics.ProcessStartInfo
    $inicio.FileName = $php
    $inicio.Arguments = '"' + $script + '"'
    $inicio.WorkingDirectory = Split-Path -Parent $PSScriptRoot
    $inicio.UseShellExecute = $false
    $inicio.RedirectStandardInput = $true
    $inicio.RedirectStandardOutput = $true
    $inicio.RedirectStandardError = $true
    $inicio.CreateNoWindow = $true

    $proceso = New-Object System.Diagnostics.Process
    $proceso.StartInfo = $inicio
    [void]$proceso.Start()
    $proceso.StandardInput.WriteLine($correo)
    $proceso.StandardInput.WriteLine($contrasena)
    $proceso.StandardInput.Close()

    $salida = $proceso.StandardOutput.ReadToEnd()
    $errorSalida = $proceso.StandardError.ReadToEnd()
    $proceso.WaitForExit()

    if ($salida) { Write-Output $salida.TrimEnd() }
    if ($proceso.ExitCode -ne 0) {
        if ($errorSalida) { Write-Error $errorSalida.TrimEnd() }
        exit $proceso.ExitCode
    }
} finally {
    $contrasena = $null
    if ($ptr -ne [IntPtr]::Zero) {
        [Runtime.InteropServices.Marshal]::ZeroFreeBSTR($ptr)
    }
    if ($segura) { $segura.Dispose() }
    if ($proceso) { $proceso.Dispose() }
}

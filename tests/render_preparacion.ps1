$ErrorActionPreference = 'Stop'
$raizPruebaRender = Split-Path -Parent $PSScriptRoot
. (Join-Path $raizPruebaRender 'scripts/render-postgres.ps1')
$script:totalRender = 0
function Assert-Render([bool]$Valor, [string]$Nombre) {
    if (-not $Valor) { throw $Nombre }
    $script:totalRender++
    Write-Output ('OK: ' + $Nombre)
}
$caPruebaRender = Join-Path $raizPruebaRender 'tests/render_proceso_probe.php'
$urlPruebaRender = 'postgresql://clinica_fixture:SoloFixture%2191@dpg-fixture.oregon-postgres.render.com/clinica_vida_salud_render'
$hostPruebaRender = 'dpg-fixture.oregon-postgres.render.com'
$configPruebaRender = ConvertFrom-RenderUrl $urlPruebaRender $hostPruebaRender 'clinica_vida_salud_render' $caPruebaRender
Assert-Render ($configPruebaRender.password -ceq 'SoloFixture!91' -and $configPruebaRender.puerto -eq 5432) 'URL externa y contraseña codificada interpretadas'
$configQueryRender = ConvertFrom-RenderUrl ($urlPruebaRender + '?sslmode=disable') $hostPruebaRender 'clinica_vida_salud_render' $caPruebaRender
Assert-Render ($configQueryRender.host -ceq $hostPruebaRender -and -not $configQueryRender.ContainsKey('sslmode')) 'query de URL no puede rebajar TLS impuesto por el proceso'
foreach ($adversaRender in @(
    'no es una URL', $urlPruebaRender.Replace('postgresql:','https:'),
    $urlPruebaRender.Replace($hostPruebaRender,'127.0.0.1'), $urlPruebaRender.Replace($hostPruebaRender,'dpg-fixture'),
    $urlPruebaRender.Replace('/clinica_vida_salud_render','/clinica_vida_salud_dev'),
    $urlPruebaRender.Replace('/clinica_vida_salud_render','/panaderia'),
    $urlPruebaRender.Replace('/clinica_vida_salud_render','/clinica_vida_salud_render/otra'),
    $urlPruebaRender.Replace('.com/','.com:5433/'), ($urlPruebaRender + '#fragmento')
)) {
    $rechazadaRender = $false
    try { $null = ConvertFrom-RenderUrl $adversaRender $hostPruebaRender 'clinica_vida_salud_render' $caPruebaRender }
    catch { $rechazadaRender = -not $_.Exception.Message.Contains('SoloFixture') }
    Assert-Render $rechazadaRender 'URL adversa rechazada sin imprimir credenciales'
}
foreach ($casoRender in @(@('otro','clinica_vida_salud_render'), @($hostPruebaRender,'otra'))) {
    $rechazadaRender = $false
    try { $null = ConvertFrom-RenderUrl $urlPruebaRender $casoRender[0] $casoRender[1] $caPruebaRender } catch { $rechazadaRender = $true }
    Assert-Render $rechazadaRender 'confirmación independiente debe coincidir'
}
$phpPruebaRender = (Get-Command php).Source
$probePruebaRender = Join-Path $raizPruebaRender 'tests/render_proceso_probe.php'
$entornoAntesRender = [Environment]::GetEnvironmentVariables()
$salidaPruebaRender = Invoke-RenderProcess $phpPruebaRender ('"' + $probePruebaRender + '"') $configPruebaRender @('Árbol١!xx')
Assert-Render ($salidaPruebaRender -ceq 'OK: entorno aislado y UTF-8') 'subproceso recibe credenciales por entorno y Unicode por stdin'
$errorSeguroRender = $false
try { Invoke-RenderProcess $phpPruebaRender ('"' + $probePruebaRender + '"') $configPruebaRender @('fallar') }
catch { $errorSeguroRender = -not $_.Exception.Message.Contains('SoloFixture') }
Assert-Render $errorSeguroRender 'error nativo no expone credenciales'
$entornoDespuesRender = [Environment]::GetEnvironmentVariables()
$igualesRender = $true
foreach ($clavePruebaRender in $entornoAntesRender.Keys) {
    if ($entornoAntesRender[$clavePruebaRender] -cne $entornoDespuesRender[$clavePruebaRender]) { $igualesRender = $false }
}
Assert-Render ($igualesRender -and $entornoAntesRender.Count -eq $entornoDespuesRender.Count) 'entorno del proceso padre intacto'
Write-Output ('Aprobadas: ' + $script:totalRender + '. Sin red, archivos temporales con secretos ni conexiones PostgreSQL.')

$ErrorActionPreference = 'Stop'

$projectRoot = Split-Path -Parent $PSScriptRoot
$php = Join-Path $env:LOCALAPPDATA 'Microsoft\WinGet\Packages\PHP.PHP.8.3_Microsoft.Winget.Source_8wekyb3d8bbwe\php.exe'
$phpIniDir = Join-Path $projectRoot 'tools\php'
$phpIniPath = Join-Path $phpIniDir 'php.ini'
$phpIniGenerator = Join-Path $phpIniDir 'generate-php-ini.ps1'
$router = Join-Path $projectRoot '.local\router.php'
$runDir = Join-Path $projectRoot '.local\run'
$stdoutLog = Join-Path $runDir 'server.out.log'
$stderrLog = Join-Path $runDir 'server.err.log'

if (-not (Test-Path $php)) {
    throw "PHP nao encontrado em $php"
}

if (-not (Test-Path $router)) {
    throw "Router local nao encontrado em $router"
}

if (-not (Test-Path $phpIniPath)) {
    & powershell.exe -ExecutionPolicy Bypass -File $phpIniGenerator
}

New-Item -ItemType Directory -Force -Path $runDir | Out-Null

if (Test-Path $stdoutLog) {
    Clear-Content -LiteralPath $stdoutLog
}

if (Test-Path $stderrLog) {
    Clear-Content -LiteralPath $stderrLog
}

if (Get-NetTCPConnection -State Listen -LocalPort 8004 -ErrorAction SilentlyContinue) {
    Write-Host 'Servidor HTTP ja esta escutando na porta 8004'
    exit 0
}

Set-Location $projectRoot
$process = Start-Process -FilePath $php -ArgumentList @('-c', $phpIniDir, '-S', '0.0.0.0:8004', '-t', 'public', $router) -WorkingDirectory $projectRoot -RedirectStandardOutput $stdoutLog -RedirectStandardError $stderrLog -PassThru
Wait-Process -Id $process.Id
exit $process.ExitCode

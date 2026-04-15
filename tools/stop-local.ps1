$ErrorActionPreference = 'Stop'

$projectRoot = Split-Path -Parent $PSScriptRoot
$pgCtl = 'C:\Program Files\PostgreSQL\18\bin\pg_ctl.exe'
$pgData = Join-Path $projectRoot '.local\postgres-data'
$matches = @(
    "*$projectRoot*serve-http.ps1*",
    "*127.0.0.1:8004*router.php*",
    "*0.0.0.0:8004*router.php*",
    "*$projectRoot*artisan reverb:start*",
    "*$projectRoot*artisan queue:work*"
)

$processes = Get-CimInstance Win32_Process | Where-Object {
    if ($_.Name -notin @('powershell.exe', 'php.exe')) {
        return $false
    }

    foreach ($match in $matches) {
        if ($_.CommandLine -like $match) {
            return $true
        }
    }

    return $false
}

$processIds = $processes | Select-Object -ExpandProperty ProcessId -Unique
foreach ($processId in $processIds) {
    Stop-Process -Id $processId -Force -ErrorAction SilentlyContinue
}

if ((Test-Path $pgCtl) -and (Test-Path $pgData)) {
    $listener = Get-NetTCPConnection -State Listen -LocalPort 55432 -ErrorAction SilentlyContinue
    if ($listener) {
        & $pgCtl -D $pgData stop -m fast | Out-Null
    }
}

Write-Host 'Servicos locais encerrados.'

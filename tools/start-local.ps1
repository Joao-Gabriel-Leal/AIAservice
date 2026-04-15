$ErrorActionPreference = 'Stop'

$projectRoot = Split-Path -Parent $PSScriptRoot
$php = Join-Path $env:LOCALAPPDATA 'Microsoft\WinGet\Packages\PHP.PHP.8.3_Microsoft.Winget.Source_8wekyb3d8bbwe\php.exe'
$phpIniDir = Join-Path $projectRoot 'tools\php'
$phpIniGenerator = Join-Path $phpIniDir 'generate-php-ini.ps1'
$router = Join-Path $projectRoot '.local\router.php'
$pgCtl = 'C:\Program Files\PostgreSQL\18\bin\pg_ctl.exe'
$pgData = Join-Path $projectRoot '.local\postgres-data'
$pgLog = Join-Path $projectRoot '.local\run\postgres.log'

if (-not (Test-Path $php)) {
    throw "PHP nao encontrado em $php"
}

if (Test-Path $phpIniGenerator) {
    & powershell.exe -ExecutionPolicy Bypass -File $phpIniGenerator | Out-Null
}

New-Item -ItemType Directory -Force -Path (Join-Path $projectRoot '.local\run') | Out-Null

$lanIp = Get-NetIPAddress -AddressFamily IPv4 -ErrorAction SilentlyContinue | Where-Object {
    $_.IPAddress -notlike '127.*' -and
    $_.IPAddress -notlike '169.254.*'
} | Select-Object -First 1 -ExpandProperty IPAddress

if (-not (Get-NetTCPConnection -State Listen -LocalPort 55432 -ErrorAction SilentlyContinue)) {
    & $pgCtl -D $pgData -l $pgLog -o ' -p 55432 -h 127.0.0.1' start | Out-Null
    Start-Sleep -Seconds 2
}

$processSpecs = @(
    @{
        Name = 'App'
        Match = '* -S *:8004 -t public*'
        Script = Join-Path $projectRoot 'tools\serve-http.ps1'
    },
    @{
        Name = 'Reverb'
        Match = '*artisan reverb:start*'
        Command = "Set-Location '$projectRoot'; & '$php' -c '$phpIniDir' artisan reverb:start --host=127.0.0.1 --port=8080"
    },
    @{
        Name = 'Queue'
        Match = '*artisan queue:work*'
        Command = "Set-Location '$projectRoot'; & '$php' -c '$phpIniDir' artisan queue:work --tries=1 --timeout=0"
    }
)

foreach ($spec in $processSpecs) {
    $existing = Get-CimInstance Win32_Process | Where-Object {
        $_.Name -in @('powershell.exe', 'php.exe') -and $_.CommandLine -like $spec.Match
    }

    if ($existing) {
        continue
    }

    if ($spec.ContainsKey('Script')) {
        Start-Process -FilePath 'powershell.exe' -ArgumentList @('-ExecutionPolicy', 'Bypass', '-NoExit', '-File', $spec.Script) -WindowStyle Minimized | Out-Null
    } else {
        Start-Process -FilePath 'powershell.exe' -ArgumentList @('-NoExit', '-Command', $spec.Command) -WindowStyle Minimized | Out-Null
    }

    Start-Sleep -Seconds 1
}

Write-Host 'Aplicacao local: http://127.0.0.1:8004/login'
if ($lanIp) {
    Write-Host "Aplicacao na rede: http://$lanIp`:8004/login"
}
Write-Host 'Reverb: 127.0.0.1:8080'
Write-Host 'PostgreSQL local: 127.0.0.1:55432'

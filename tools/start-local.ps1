$ErrorActionPreference = 'Stop'

$projectRoot = Split-Path -Parent $PSScriptRoot
$php = Join-Path $env:LOCALAPPDATA 'Microsoft\WinGet\Packages\PHP.PHP.8.3_Microsoft.Winget.Source_8wekyb3d8bbwe\php.exe'
$phpIniDir = Join-Path $projectRoot 'tools\php'
$phpIniPath = Join-Path $phpIniDir 'php.ini'
$phpIniGenerator = Join-Path $phpIniDir 'generate-php-ini.ps1'
$serveHttpScript = Join-Path $projectRoot 'tools\serve-http.ps1'
$pgCtl = 'C:\Program Files\PostgreSQL\18\bin\pg_ctl.exe'
$psql = 'C:\Program Files\PostgreSQL\18\bin\psql.exe'
$createdb = 'C:\Program Files\PostgreSQL\18\bin\createdb.exe'
$pgData = Join-Path $projectRoot '.local\postgres-data'
$pgPid = Join-Path $pgData 'postmaster.pid'
$runDir = Join-Path $projectRoot '.local\run'
$pgLog = Join-Path $runDir 'postgres.log'
$viteHotFile = Join-Path $projectRoot 'public\hot'
$appDatabase = 'aiaservice'
$appUser = 'aiaservice'
$appPassword = 'aiaservice_local'
$adminPassword = if ($env:PG_SUPERUSER_PASSWORD) { $env:PG_SUPERUSER_PASSWORD } else { 'postgres' }

function Get-ListeningProcess {
    param(
        [int] $Port
    )

    Get-NetTCPConnection -State Listen -LocalPort $Port -ErrorAction SilentlyContinue |
        Select-Object -First 1
}

function Get-ManagedProcess {
    param(
        [string[]] $Matches
    )

    Get-CimInstance Win32_Process | Where-Object {
        if ($_.Name -notin @('powershell.exe', 'php.exe')) {
            return $false
        }

        foreach ($match in $Matches) {
            if ($_.CommandLine -like $match) {
                return $true
            }
        }

        return $false
    }
}

function Stop-ManagedProcesses {
    param(
        [string[]] $Matches
    )

    $processIds = Get-ManagedProcess -Matches $Matches |
        Select-Object -ExpandProperty ProcessId -Unique

    foreach ($processId in $processIds) {
        Stop-Process -Id $processId -Force -ErrorAction SilentlyContinue
    }
}

function Test-ManagedProcessHealthy {
    param(
        [string[]] $Matches,
        [int] $Port = 0,
        [switch] $RequirePhpProcess
    )

    if ($Port -gt 0) {
        return [bool] (Get-ListeningProcess -Port $Port)
    }

    $processes = Get-ManagedProcess -Matches $Matches
    if ($RequirePhpProcess) {
        return [bool] ($processes | Where-Object Name -eq 'php.exe')
    }

    return [bool] $processes
}

function Ensure-PhpIni {
    if (-not (Test-Path $php)) {
        throw "PHP nao encontrado em $php"
    }

    if (-not (Test-Path $phpIniPath)) {
        if (-not (Test-Path $phpIniGenerator)) {
            throw "Gerador do php.ini nao encontrado em $phpIniGenerator"
        }

        & powershell.exe -ExecutionPolicy Bypass -File $phpIniGenerator
    }
}

function Ensure-StaleViteHotFileIsCleared {
    if (-not (Test-Path $viteHotFile)) {
        return
    }

    $hotEntry = (Get-Content $viteHotFile -ErrorAction SilentlyContinue | Select-Object -First 1).Trim()
    if (-not $hotEntry) {
        Remove-Item -LiteralPath $viteHotFile -Force -ErrorAction SilentlyContinue
        return
    }

    $hotUri = $null
    if (-not [Uri]::TryCreate($hotEntry, [System.UriKind]::Absolute, [ref] $hotUri)) {
        Remove-Item -LiteralPath $viteHotFile -Force -ErrorAction SilentlyContinue
        Write-Host "Arquivo public/hot invalido removido."
        return
    }

    $listener = Get-NetTCPConnection -State Listen -LocalPort $hotUri.Port -ErrorAction SilentlyContinue |
        Select-Object -First 1

    if ($listener) {
        return
    }

    Remove-Item -LiteralPath $viteHotFile -Force -ErrorAction SilentlyContinue
    Write-Host "Arquivo public/hot stale removido para usar os assets de public/build."
}

function Ensure-StalePostmasterPidIsCleared {
    if (Get-ListeningProcess -Port 55432) {
        return
    }

    if (-not (Test-Path $pgPid)) {
        return
    }

    $pidLines = Get-Content $pgPid
    if ($pidLines.Count -lt 2) {
        return
    }

    $postmasterProcessId = $pidLines[0].Trim()
    $dataPath = $pidLines[1].Trim()
    $projectDataPath = $pgData.Replace('\', '/')

    if ($dataPath -ne $projectDataPath) {
        return
    }

    $runningProcess = Get-Process -Id $postmasterProcessId -ErrorAction SilentlyContinue
    if ($runningProcess) {
        return
    }

    Remove-Item -LiteralPath $pgPid -Force
    Write-Host "postmaster.pid stale removido em $pgPid"
}

function Start-PostgresCluster {
    if (-not (Test-Path $pgCtl)) {
        throw "pg_ctl nao encontrado em $pgCtl"
    }

    if (-not (Test-Path $pgData)) {
        throw "Cluster PostgreSQL nao encontrado em $pgData"
    }

    Ensure-StalePostmasterPidIsCleared

    if (Get-ListeningProcess -Port 55432) {
        return
    }

    & $pgCtl -D $pgData -l $pgLog -o ' -p 55432 -h 127.0.0.1' start | Out-Null

    for ($attempt = 0; $attempt -lt 15; $attempt++) {
        Start-Sleep -Seconds 1

        if (Get-ListeningProcess -Port 55432) {
            return
        }
    }

    $tail = if (Test-Path $pgLog) { (Get-Content $pgLog -Tail 20) -join [Environment]::NewLine } else { 'sem log' }
    throw "PostgreSQL nao subiu na porta 55432.`n$tail"
}

function Invoke-PsqlQuery {
    param(
        [string] $Database,
        [string] $Sql,
        [switch] $StopOnError
    )

    $previousPassword = $env:PGPASSWORD
    $env:PGPASSWORD = $adminPassword

    try {
        $output = & $psql -h 127.0.0.1 -p 55432 -U postgres -d $Database -tA -v ON_ERROR_STOP=1 -c $Sql 2>&1
        $exitCode = $LASTEXITCODE

        if ($StopOnError -and $exitCode -ne 0) {
            throw (($output | Out-String).Trim())
        }

        return @{
            ExitCode = $exitCode
            Output = (($output | Out-String).Trim())
        }
    } finally {
        $env:PGPASSWORD = $previousPassword
    }
}

function Ensure-AppRoleAndDatabase {
    if (-not (Test-Path $psql) -or -not (Test-Path $createdb)) {
        Write-Warning 'psql/createdb nao encontrados. Pulando validacao automatica de role e database.'
        return
    }

    $roleCheck = Invoke-PsqlQuery -Database 'postgres' -Sql "SELECT 1 FROM pg_roles WHERE rolname = '$appUser';"
    if ($roleCheck.ExitCode -ne 0) {
        Write-Warning "Nao foi possivel validar a role '$appUser' automaticamente: $($roleCheck.Output)"
        return
    }

    if ($roleCheck.Output -ne '1') {
        Invoke-PsqlQuery -Database 'postgres' -Sql "CREATE ROLE $appUser WITH LOGIN PASSWORD '$appPassword';" -StopOnError | Out-Null
        Write-Host "Role '$appUser' criada."
    }

    $dbCheck = Invoke-PsqlQuery -Database 'postgres' -Sql "SELECT 1 FROM pg_database WHERE datname = '$appDatabase';"
    if ($dbCheck.ExitCode -ne 0) {
        Write-Warning "Nao foi possivel validar o banco '$appDatabase' automaticamente: $($dbCheck.Output)"
        return
    }

    if ($dbCheck.Output -ne '1') {
        $previousPassword = $env:PGPASSWORD
        $env:PGPASSWORD = $adminPassword

        try {
            & $createdb -h 127.0.0.1 -p 55432 -U postgres -O $appUser $appDatabase 2>&1 | Out-Null
            if ($LASTEXITCODE -ne 0) {
                throw "Falha ao criar o banco '$appDatabase'."
            }
        } finally {
            $env:PGPASSWORD = $previousPassword
        }

        Write-Host "Banco '$appDatabase' criado."
    }
}

function Start-ManagedProcess {
    param(
        [string] $Name,
        [string[]] $Matches,
        [string] $FilePath,
        [string[]] $ArgumentList = @(),
        [string] $WorkingDirectory = $projectRoot,
        [int] $Port = 0,
        [switch] $RequirePhpProcess,
        [string[]] $FailureLogs = @(),
        [string] $StdOutLog,
        [string] $StdErrLog
    )

    $existing = Get-ManagedProcess -Matches $Matches
    if ($existing -and (Test-ManagedProcessHealthy -Matches $Matches -Port $Port -RequirePhpProcess:$RequirePhpProcess)) {
        return
    }

    if ($existing) {
        Stop-ManagedProcesses -Matches $Matches
        Start-Sleep -Seconds 1
    }

    $startProcessParams = @{
        FilePath = $FilePath
        ArgumentList = $ArgumentList
        WorkingDirectory = $WorkingDirectory
        WindowStyle = 'Minimized'
    }

    if ($StdOutLog) {
        $startProcessParams.RedirectStandardOutput = $StdOutLog
    }

    if ($StdErrLog) {
        $startProcessParams.RedirectStandardError = $StdErrLog
    }

    Start-Process @startProcessParams | Out-Null

    for ($attempt = 0; $attempt -lt 15; $attempt++) {
        Start-Sleep -Seconds 1

        if (Test-ManagedProcessHealthy -Matches $Matches -Port $Port -RequirePhpProcess:$RequirePhpProcess) {
            return
        }
    }

    $logTail = foreach ($failureLog in $FailureLogs) {
        if (Test-Path $failureLog) {
            "==> $failureLog"
            Get-Content $failureLog -Tail 20
        }
    }

    if ($logTail) {
        throw "$Name nao iniciou corretamente.`n$($logTail -join [Environment]::NewLine)"
    }

    throw "$Name nao iniciou corretamente."
}

if (-not (Test-Path $serveHttpScript)) {
    throw "Script HTTP nao encontrado em $serveHttpScript"
}

New-Item -ItemType Directory -Force -Path $runDir | Out-Null
Ensure-PhpIni
Ensure-StaleViteHotFileIsCleared
Start-PostgresCluster
Ensure-AppRoleAndDatabase

$lanIp = Get-NetIPAddress -AddressFamily IPv4 -ErrorAction SilentlyContinue | Where-Object {
    $_.IPAddress -notlike '127.*' -and
    $_.IPAddress -notlike '169.254.*'
} | Select-Object -First 1 -ExpandProperty IPAddress

$processSpecs = @(
    @{
        Name = 'App'
        Matches = @(
            "*$projectRoot*serve-http.ps1*",
            "*127.0.0.1:8004*router.php*"
        )
        FilePath = 'powershell.exe'
        ArgumentList = @('-ExecutionPolicy', 'Bypass', '-File', $serveHttpScript)
        WorkingDirectory = $projectRoot
        Port = 8004
        RequirePhpProcess = $false
        FailureLogs = @(
            Join-Path $runDir 'server.out.log'
            Join-Path $runDir 'server.err.log'
        )
    },
    @{
        Name = 'Reverb'
        Matches = @("*$projectRoot*artisan reverb:start*")
        FilePath = $php
        ArgumentList = @('-c', $phpIniDir, 'artisan', 'reverb:start', '--host=127.0.0.1', '--port=8080')
        WorkingDirectory = $projectRoot
        Port = 8080
        RequirePhpProcess = $false
        FailureLogs = @(
            Join-Path $runDir 'reverb.out.log'
            Join-Path $runDir 'reverb.err.log'
        )
        StdOutLog = Join-Path $runDir 'reverb.out.log'
        StdErrLog = Join-Path $runDir 'reverb.err.log'
    },
    @{
        Name = 'Queue'
        Matches = @("*$projectRoot*artisan queue:work*")
        FilePath = $php
        ArgumentList = @('-c', $phpIniDir, 'artisan', 'queue:work', '--tries=1', '--timeout=0')
        WorkingDirectory = $projectRoot
        Port = 0
        RequirePhpProcess = $true
        FailureLogs = @(
            Join-Path $runDir 'queue.out.log'
            Join-Path $runDir 'queue.err.log'
        )
        StdOutLog = Join-Path $runDir 'queue.out.log'
        StdErrLog = Join-Path $runDir 'queue.err.log'
    }
)

foreach ($spec in $processSpecs) {
    Start-ManagedProcess -Name $spec.Name -Matches $spec.Matches -FilePath $spec.FilePath -ArgumentList $spec.ArgumentList -WorkingDirectory $spec.WorkingDirectory -Port $spec.Port -RequirePhpProcess:$spec.RequirePhpProcess -FailureLogs $spec.FailureLogs -StdOutLog $spec.StdOutLog -StdErrLog $spec.StdErrLog
}

Write-Host 'Aplicacao local: http://127.0.0.1:8004/login'
if ($lanIp) {
    Write-Host "Aplicacao na rede: http://$lanIp`:8004/login"
}
Write-Host 'Reverb: 127.0.0.1:8080'
Write-Host 'PostgreSQL local: 127.0.0.1:55432'

$ErrorActionPreference = 'Stop'

$phpRoot = Join-Path $env:LOCALAPPDATA 'Microsoft\WinGet\Packages\PHP.PHP.8.3_Microsoft.Winget.Source_8wekyb3d8bbwe'
$extDir = Join-Path $phpRoot 'ext'
$phpIniPath = Join-Path $PSScriptRoot 'php.ini'
$caCertPath = Join-Path $PSScriptRoot 'windows-cacert.pem'

if (-not (Test-Path (Join-Path $phpRoot 'php.exe'))) {
    throw "PHP 8.3 nao encontrado em $phpRoot. Instale via WinGet antes de continuar."
}

if (-not (Test-Path $extDir)) {
    throw "Diretorio de extensoes do PHP nao encontrado em $extDir."
}

$extDirNormalized = $extDir.Replace('\', '/')
$content = @(
    '[PHP]'
    'memory_limit = 1024M'
    'max_execution_time = 300'
    'date.timezone = America/Sao_Paulo'
    ('extension_dir = "{0}"' -f $extDirNormalized)
    ''
    'extension = curl'
    'extension = fileinfo'
    'extension = gd'
    'extension = intl'
    'extension = mbstring'
    'extension = openssl'
    'extension = pdo_pgsql'
    'extension = pgsql'
    'extension = sqlite3'
    'extension = pdo_sqlite'
    'extension = zip'
)

if (Test-Path $caCertPath) {
    $caCertNormalized = $caCertPath.Replace('\', '/')
    $content += @(
        ''
        '[openssl]'
        ('openssl.cafile = "{0}"' -f $caCertNormalized)
        ''
        '[curl]'
        ('curl.cainfo = "{0}"' -f $caCertNormalized)
    )
} else {
    Write-Warning "Arquivo opcional de certificados nao encontrado em $caCertPath. O php.ini sera gerado sem openssl.cafile/curl.cainfo."
}

Set-Content -Path $phpIniPath -Value ($content -join [Environment]::NewLine) -Encoding ASCII

Write-Host "php.ini gerado em $phpIniPath"
Write-Host "Use com: $phpRoot\\php.exe -c $PSScriptRoot artisan ..."

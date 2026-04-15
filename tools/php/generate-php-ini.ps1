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

if (-not (Test-Path $caCertPath)) {
    throw "Arquivo de certificados nao encontrado em $caCertPath."
}

$extDirNormalized = $extDir.Replace('\', '/')
$caCertNormalized = $caCertPath.Replace('\', '/')

$content = @"
[PHP]
memory_limit = 1024M
max_execution_time = 300
date.timezone = America/Sao_Paulo
extension_dir = "$extDirNormalized"

extension = curl
extension = fileinfo
extension = intl
extension = mbstring
extension = openssl
extension = pdo_pgsql
extension = pgsql
extension = sqlite3
extension = pdo_sqlite
extension = zip

[openssl]
openssl.cafile = "$caCertNormalized"

[curl]
curl.cainfo = "$caCertNormalized"
"@

Set-Content -Path $phpIniPath -Value $content -Encoding ASCII

Write-Host "php.ini gerado em $phpIniPath"
Write-Host "Use com: $phpRoot\\php.exe -c $PSScriptRoot artisan ..."

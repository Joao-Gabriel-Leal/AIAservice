# Inciar Projeto

Guia rapido para subir o AIA Service localmente com PostgreSQL real, sem SQLite e sem mock.

## Atalho de amanha

1. Instale PHP 8.3, Node.js, PostgreSQL 18, Composer e Git
2. Rode `powershell.exe -ExecutionPolicy Bypass -File .\tools\php\generate-php-ini.ps1`
3. Rode `composer install` e `npm install`
4. Crie ou reaproveite o banco local `aiaservice`
5. Se precisar, restaure o dump da demo nesse banco
6. Rode `php artisan migrate --force` usando `tools/php`
7. Suba tudo com `powershell.exe -ExecutionPolicy Bypass -File .\tools\start-local.ps1`

Se quiser o passo a passo completo com restore do dump e validacoes, leia tambem `docs/HANDOFF-2026-04-15.md`.

## Como este projeto roda hoje

- aplicacao web: `http://127.0.0.1:8004/login`
- Reverb/WebSocket: `127.0.0.1:8080`
- PostgreSQL local do projeto: `127.0.0.1:55433`
- banco da aplicacao: `aiaservice`
- usuario da aplicacao: `aiaservice`

## Pre-requisitos

- Windows com PowerShell
- PostgreSQL 18 instalado
- PHP 8.3 instalado nesta maquina
- Node.js e npm
- Composer no PATH

Os scripts locais usam o PHP 8.3 instalado via WinGet em:

```text
C:\Users\<usuario>\AppData\Local\Microsoft\WinGet\Packages\PHP.PHP.8.3_Microsoft.Winget.Source_8wekyb3d8bbwe\php.exe
```

## Arquivos importantes

- script para subir tudo: `tools/start-local.ps1`
- script para parar tudo: `tools/stop-local.ps1`
- script do servidor HTTP: `tools/serve-http.ps1`
- gerador do `php.ini`: `tools/php/generate-php-ini.ps1`
- configuracao do ambiente: `.env`
- dados do PostgreSQL local do projeto: `.local/postgres-data`

## Passo 1: conferir se o banco local do projeto ja existe

```powershell
Test-Path .local\postgres-data
Get-NetTCPConnection -State Listen -LocalPort 55433 -ErrorAction SilentlyContinue
```

## Passo 2: verificar se o banco e o usuario da aplicacao ja existem

```powershell
$env:PGPASSWORD='postgres'
& 'C:\Program Files\PostgreSQL\18\bin\psql.exe' -h 127.0.0.1 -p 55433 -U postgres -d postgres -c "SELECT datname FROM pg_database WHERE datname = 'aiaservice';"
& 'C:\Program Files\PostgreSQL\18\bin\psql.exe' -h 127.0.0.1 -p 55433 -U postgres -d postgres -c "SELECT rolname FROM pg_roles WHERE rolname = 'aiaservice';"
```

## Passo 3: se o PostgreSQL local do projeto ainda nao existir, criar a estrutura

Esse passo so e necessario uma vez.

```powershell
Set-Content -Path .local\postgres-password.txt -Value 'postgres'
& 'C:\Program Files\PostgreSQL\18\bin\initdb.exe' -D .local\postgres-data -U postgres -A scram-sha-256 --pwfile=.local\postgres-password.txt --encoding=UTF8
Remove-Item .local\postgres-password.txt -Force
```

Depois suba o PostgreSQL local:

```powershell
& 'C:\Program Files\PostgreSQL\18\bin\pg_ctl.exe' -D .local\postgres-data -l .local\run\postgres.log -o " -p 55433 -h 127.0.0.1" start
```

## Passo 4: se o usuario e o banco ainda nao existirem, criar

```powershell
$env:PGPASSWORD='postgres'
& 'C:\Program Files\PostgreSQL\18\bin\psql.exe' -h 127.0.0.1 -p 55433 -U postgres -d postgres -c "CREATE ROLE aiaservice WITH LOGIN PASSWORD 'aiaservice_local';"
& 'C:\Program Files\PostgreSQL\18\bin\createdb.exe' -h 127.0.0.1 -p 55433 -U postgres -O aiaservice aiaservice
```

## Passo 5: conferir a `.env`

```env
APP_URL=http://127.0.0.1:8004
ASSET_QR_BASE_URL=http://127.0.0.1:8004
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=55433
DB_DATABASE=aiaservice
DB_USERNAME=aiaservice
DB_PASSWORD=aiaservice_local
REVERB_PORT=8080
```

## Passo 6: instalar dependencias

```powershell
composer install
npm install
```

Antes dos comandos PHP no Windows, gere o `php.ini` local do projeto:

```powershell
powershell.exe -ExecutionPolicy Bypass -File .\tools\php\generate-php-ini.ps1
```

## Passo 7: gerar chave e subir migrations no PostgreSQL

```powershell
$php = "$env:LOCALAPPDATA\Microsoft\WinGet\Packages\PHP.PHP.8.3_Microsoft.Winget.Source_8wekyb3d8bbwe\php.exe"
$ini = Join-Path (Get-Location) 'tools\php'

if (-not (Select-String -Path .env -Pattern '^APP_KEY=base64:' -Quiet)) {
    & $php -c $ini artisan key:generate
}

& $php -c $ini artisan migrate --force
```

Se quiser recriar tudo do zero:

```powershell
& $php -c $ini artisan migrate:fresh --seed --force
```

Se voce restaurar o dump da demo no banco local, rode primeiro o restore e depois execute apenas:

```powershell
& $php -c $ini artisan migrate --force
```

O login principal da demo restaurada fica:

- email: `admin@anadem.com.br`
- senha: `Anadem@2026!`

## Passo 8: build do frontend

```powershell
npm run build
```

## Passo 9: subir o sistema

```powershell
powershell.exe -ExecutionPolicy Bypass -File .\tools\start-local.ps1
```

Esse script sobe:

- PostgreSQL local na porta `55433`
- aplicacao HTTP na porta `8004`
- Reverb na porta `8080`
- worker de fila

## Passo 10: acessar no navegador

Abra:

```text
http://127.0.0.1:8004/login
```

Nao abra `127.0.0.1:8080` achando que e a aplicacao:

- `8004` = sistema web
- `8080` = Reverb/WebSocket

## Login inicial

Se o banco vier de seed local:

- email: `admin@aiaservice.local`
- senha: `password`

## Como validar se subiu certo

```powershell
Get-NetTCPConnection -State Listen -LocalPort 8004,8080,55433 -ErrorAction SilentlyContinue
curl.exe -I http://127.0.0.1:8004/login
```

```powershell
$php = "$env:LOCALAPPDATA\Microsoft\WinGet\Packages\PHP.PHP.8.3_Microsoft.Winget.Source_8wekyb3d8bbwe\php.exe"
$ini = Join-Path (Get-Location) 'tools\php'
& $php -c $ini artisan route:list
```

## Como parar tudo

```powershell
powershell.exe -ExecutionPolicy Bypass -File .\tools\stop-local.ps1
```

## Problemas comuns

### A porta 8004 nao abre

```powershell
Get-CimInstance Win32_Process | Where-Object { $_.CommandLine -like '*serve-http.ps1*' -or $_.CommandLine -like '*127.0.0.1:8004*' }
```

### A porta 55433 nao sobe

```powershell
Get-Content .local\run\postgres.log -Tail 100
```

### O banco existe, mas a aplicacao nao conecta

```powershell
$env:PGPASSWORD='aiaservice_local'
& 'C:\Program Files\PostgreSQL\18\bin\psql.exe' -h 127.0.0.1 -p 55433 -U aiaservice -d aiaservice -c "SELECT current_database(), current_user;"
```

### Quero zerar o banco e recriar tudo

```powershell
powershell.exe -ExecutionPolicy Bypass -File .\tools\stop-local.ps1
Remove-Item .local\postgres-data -Recurse -Force
```

Depois repita os passos de criacao do PostgreSQL local, banco e migrations.

## Importacao patrimonial

```powershell
$php = "$env:LOCALAPPDATA\Microsoft\WinGet\Packages\PHP.PHP.8.3_Microsoft.Winget.Source_8wekyb3d8bbwe\php.exe"
$ini = Join-Path (Get-Location) 'tools\php'
& $php -c $ini artisan assets:import-anadem "C:\caminho\GESTAO PATRIMONIAL 1.xlsx"
```

Resultado esperado da carga real da aba `Anadem`:

- 2.000 linhas na staging
- 1.050 ativos promovidos
- 950 linhas pendentes de saneamento
- 0 erros

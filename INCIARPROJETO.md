# Inciar Projeto

Guia rapido para subir o AIA Service localmente com PostgreSQL real, sem SQLite, sem mock e sem storage de arquivos em disco para avatar/anexos.

## Como este projeto roda hoje

- Aplicacao web: `http://127.0.0.1:8004/login`
- Reverb/WebSocket: `127.0.0.1:8080`
- PostgreSQL local do projeto: `127.0.0.1:55432`
- Banco da aplicacao: `aiaservice`
- Usuario da aplicacao: `aiaservice`

## Pre-requisitos

Voce precisa ter:

- Windows com PowerShell
- PostgreSQL instalado
- PHP 8.3 instalado nesta maquina
- Node.js e npm

Observacao:

- este projeto ja usa o PHP local configurado em `tools/php`
- os scripts de start usam o executavel instalado via WinGet em:
  `C:\Users\joaog\AppData\Local\Microsoft\WinGet\Packages\PHP.PHP.8.3_Microsoft.Winget.Source_8wekyb3d8bbwe\php.exe`

## Arquivos importantes

- script para subir tudo: `tools/start-local.ps1`
- script para parar tudo: `tools/stop-local.ps1`
- script do servidor HTTP: `tools/serve-http.ps1`
- configuracao do ambiente: `.env`
- dados do PostgreSQL local do projeto: `.local/postgres-data`

## Passo 1: conferir se o banco local do projeto ja existe

No PowerShell, dentro da pasta do projeto:

```powershell
Test-Path .local\postgres-data
```

Se retornar `True`, a estrutura fisica do PostgreSQL local do projeto ja existe.

Tambem vale conferir se o banco esta escutando na porta certa:

```powershell
Get-NetTCPConnection -State Listen -LocalPort 55432 -ErrorAction SilentlyContinue
```

Se aparecer resultado, o PostgreSQL local do projeto ja esta rodando.

## Passo 2: verificar se o banco e o usuario da aplicacao ja existem

Use o `psql` do PostgreSQL:

```powershell
$env:PGPASSWORD='postgres'
& 'C:\Program Files\PostgreSQL\18\bin\psql.exe' -h 127.0.0.1 -p 55432 -U postgres -d postgres -c "SELECT datname FROM pg_database WHERE datname = 'aiaservice';"
& 'C:\Program Files\PostgreSQL\18\bin\psql.exe' -h 127.0.0.1 -p 55432 -U postgres -d postgres -c "SELECT rolname FROM pg_roles WHERE rolname = 'aiaservice';"
```

Se aparecer:

- `aiaservice` na lista de bancos, o banco ja existe
- `aiaservice` na lista de roles, o usuario ja existe

## Passo 3: se o PostgreSQL local do projeto ainda nao existir, criar a estrutura

Este passo so e necessario uma vez.

```powershell
Set-Content -Path .local\postgres-password.txt -Value 'postgres'
& 'C:\Program Files\PostgreSQL\18\bin\initdb.exe' -D .local\postgres-data -U postgres -A scram-sha-256 --pwfile=.local\postgres-password.txt --encoding=UTF8
Remove-Item .local\postgres-password.txt -Force
```

Depois suba o PostgreSQL local na porta do projeto:

```powershell
& 'C:\Program Files\PostgreSQL\18\bin\pg_ctl.exe' -D .local\postgres-data -l .local\run\postgres.log -o " -p 55432 -h 127.0.0.1" start
```

## Passo 4: se o usuario e o banco ainda nao existirem, criar

```powershell
$env:PGPASSWORD='postgres'
& 'C:\Program Files\PostgreSQL\18\bin\psql.exe' -h 127.0.0.1 -p 55432 -U postgres -d postgres -c "CREATE ROLE aiaservice WITH LOGIN PASSWORD 'aiaservice_local';"
& 'C:\Program Files\PostgreSQL\18\bin\createdb.exe' -h 127.0.0.1 -p 55432 -U postgres -O aiaservice aiaservice
```

Se quiser evitar erro quando eles ja existirem, confira antes no Passo 2.

## Passo 5: conferir a `.env`

Hoje a configuracao local esperada e esta:

```env
APP_URL=http://127.0.0.1:8004

DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=55432
DB_DATABASE=aiaservice
DB_USERNAME=aiaservice
DB_PASSWORD=aiaservice_local

REVERB_PORT=8080
```

## Passo 6: instalar dependencias

Se ainda nao tiver instalado tudo:

```powershell
composer install
npm install
```

Se o `composer` nao estiver no PATH, rode com o PHP local:

```powershell
$php = "$env:LOCALAPPDATA\Microsoft\WinGet\Packages\PHP.PHP.8.3_Microsoft.Winget.Source_8wekyb3d8bbwe\php.exe"
& $php -c "C:\Users\joaog\AIAservice\tools\php" "C:\Users\joaog\AIAservice\tools\composer\composer.phar" install
```

## Passo 7: gerar chave e subir migrations no PostgreSQL

```powershell
$php = "$env:LOCALAPPDATA\Microsoft\WinGet\Packages\PHP.PHP.8.3_Microsoft.Winget.Source_8wekyb3d8bbwe\php.exe"
$ini = "C:\Users\joaog\AIAservice\tools\php"

& $php -c $ini artisan key:generate
& $php -c $ini artisan migrate --seed --force
```

Se quiser recriar tudo do zero:

```powershell
& $php -c $ini artisan migrate:fresh --seed --force
```

## Passo 8: build do frontend

```powershell
npm run build
```

## Passo 9: subir o sistema

O jeito mais simples:

```powershell
powershell.exe -ExecutionPolicy Bypass -File .\tools\start-local.ps1
```

Esse script sobe:

- PostgreSQL local na porta `55432`
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

O seed local cria o super admin com:

- email: `admin@aiaservice.local`
- senha: `password`

## Como validar se subiu certo

### Verificar portas

```powershell
Get-NetTCPConnection -State Listen -LocalPort 8004,8080,55432 -ErrorAction SilentlyContinue
```

### Verificar rota de login

```powershell
curl.exe -I http://127.0.0.1:8004/login
```

O esperado e retornar `HTTP/1.1 200 OK`.

### Verificar se o Laravel esta respondendo

```powershell
$php = "$env:LOCALAPPDATA\Microsoft\WinGet\Packages\PHP.PHP.8.3_Microsoft.Winget.Source_8wekyb3d8bbwe\php.exe"
$ini = "C:\Users\joaog\AIAservice\tools\php"
& $php -c $ini artisan route:list
```

## Como parar tudo

```powershell
powershell.exe -ExecutionPolicy Bypass -File .\tools\stop-local.ps1
```

## Problemas comuns

### A porta 8004 nao abre

Confira se o processo do servidor HTTP esta de pe:

```powershell
Get-CimInstance Win32_Process | Where-Object { $_.CommandLine -like '*serve-http.ps1*' -or $_.CommandLine -like '*127.0.0.1:8004*' }
```

### A porta 55432 nao sobe

Veja o log:

```powershell
Get-Content .local\run\postgres.log -Tail 100
```

### O banco existe, mas a aplicacao nao conecta

Confira a `.env` e teste o acesso direto:

```powershell
$env:PGPASSWORD='aiaservice_local'
& 'C:\Program Files\PostgreSQL\18\bin\psql.exe' -h 127.0.0.1 -p 55432 -U aiaservice -d aiaservice -c "SELECT current_database(), current_user;"
```

### Quero zerar o banco e recriar tudo

```powershell
powershell.exe -ExecutionPolicy Bypass -File .\tools\stop-local.ps1
Remove-Item .local\postgres-data -Recurse -Force
```

Depois repita os passos de criacao do PostgreSQL local, banco e migrations.

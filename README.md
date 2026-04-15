# AIA Service

Sistema interno em Laravel 12 com PostgreSQL, Blade, Tailwind e Livewire, organizado por modulos de dominio.

## Fase atual

Esta entrega cobre a base da Fase 1:

- autenticacao por sessao
- estrutura organizacional `company -> sector -> room -> user`
- CRUDs administrativos
- papeis e escopo por perfil
- board de chamados por setor
- configuracao basica do quadro
- abertura de chamado por catalogo
- detalhe do chamado com historico, anexos e chat
- importacao patrimonial da aba `Anadem`

## Stack

- Laravel 12
- PHP 8.3+
- PostgreSQL 18
- Blade + Tailwind CSS
- Livewire 4
- Laravel Reverb + Echo

## Estrutura modular

```text
app/
`-- Modules/
    |-- Auth/
    |-- Companies/
    |-- Rooms/
    |-- Sectors/
    |-- Shared/
    |-- Tickets/
    `-- Users/
```

## Setup local rapido

1. Copie `.env.example` para `.env`
2. Gere o `tools/php/php.ini` local
3. Instale dependencias
4. Suba as migrations no PostgreSQL local
5. Suba os servicos

```powershell
Copy-Item .env.example .env
powershell.exe -ExecutionPolicy Bypass -File .\tools\php\generate-php-ini.ps1
composer install
npm install
```

O `.env` local esperado para o fluxo atual usa:

```env
APP_URL=http://127.0.0.1:8004
ASSET_QR_BASE_URL=http://127.0.0.1:8004
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=55432
DB_DATABASE=aiaservice
DB_USERNAME=aiaservice
DB_PASSWORD=aiaservice_local
REVERB_PORT=8080
```

Se o `APP_KEY` estiver vazio:

```powershell
$php = "$env:LOCALAPPDATA\Microsoft\WinGet\Packages\PHP.PHP.8.3_Microsoft.Winget.Source_8wekyb3d8bbwe\php.exe"
& $php -c .\tools\php artisan key:generate
```

Depois:

```powershell
$php = "$env:LOCALAPPDATA\Microsoft\WinGet\Packages\PHP.PHP.8.3_Microsoft.Winget.Source_8wekyb3d8bbwe\php.exe"
& $php -c .\tools\php artisan migrate --force
npm run build
powershell.exe -ExecutionPolicy Bypass -File .\tools\start-local.ps1
```

## Scripts locais

- `tools/php/generate-php-ini.ps1`: gera o `php.ini` local para o PHP 8.3 via WinGet
- `tools/start-local.ps1`: sobe PostgreSQL local, app HTTP, Reverb e queue worker
- `tools/serve-http.ps1`: sobe o servidor HTTP em `127.0.0.1:8004`
- `tools/stop-local.ps1`: encerra os servicos locais

## Validacoes rapidas

```powershell
curl.exe -I http://127.0.0.1:8004/login
Get-NetTCPConnection -State Listen -LocalPort 8004,8080,55432 -ErrorAction SilentlyContinue
```

## Importacao patrimonial

```powershell
$php = "$env:LOCALAPPDATA\Microsoft\WinGet\Packages\PHP.PHP.8.3_Microsoft.Winget.Source_8wekyb3d8bbwe\php.exe"
& $php -c .\tools\php artisan assets:import-anadem "C:\caminho\GESTAO PATRIMONIAL 1.xlsx"
```

## Observacoes

- criar ou atualizar um setor provisiona automaticamente o board inicial de chamados
- usuarios usam soft delete
- o chat da tela do chamado usa Echo/Reverb com `wire:poll` como fallback
- anexos e foto de perfil nao dependem de disco local da aplicacao
- o handoff operacional atual fica em `docs/HANDOFF-2026-04-15.md`

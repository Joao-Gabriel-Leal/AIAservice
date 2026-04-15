# AIA Service

Sistema interno em Laravel 12 com PostgreSQL, Blade, Tailwind e Livewire, organizado por modulos de dominio.

## Fase atual

Esta entrega cobre a base da Fase 1:

- autenticacao por sessao;
- estrutura organizacional `company -> sector -> room -> user`;
- CRUDs administrativos;
- papeis e escopo por perfil;
- board de chamados por setor;
- configuracao basica do quadro;
- abertura de chamado por catalogo;
- detalhe do chamado com historico, anexos e chat.

## Stack

- Laravel 12
- PHP 8.3+
- PostgreSQL
- Blade + Tailwind CSS
- Livewire 4
- Laravel Reverb + Echo

## Estrutura modular

```text
app/
└── Modules/
    ├── Auth/
    ├── Companies/
    ├── Rooms/
    ├── Sectors/
    ├── Shared/
    ├── Tickets/
    └── Users/
```

## Setup basico

1. Copie `.env.example` para `.env`
2. Ajuste o PostgreSQL e as credenciais do super admin
3. Rode as dependencias PHP e JS
4. Rode as migrations com seed

Exemplo:

```bash
composer install
npm install
php artisan key:generate
php artisan migrate --seed
npm run build
php artisan serve
```

## Variaveis importantes

- `DB_*`: conexao PostgreSQL
- `SESSION_DRIVER=database`
- avatar e anexos ficam persistidos no proprio PostgreSQL
- `SUPER_ADMIN_NAME`
- `SUPER_ADMIN_EMAIL`
- `SUPER_ADMIN_PASSWORD`
- `REVERB_*`
- `VITE_REVERB_*`

## Testes

```bash
php artisan test
```

## Observacoes

- criar ou atualizar um setor provisiona automaticamente o board inicial de chamados;
- usuarios usam soft delete;
- o chat da tela do chamado usa Echo/Reverb com `wire:poll` como fallback;
- anexos e foto de perfil nao dependem de disco local da aplicacao;
- ha um handoff detalhado em `docs/HANDOFF-2026-04-14.md`.

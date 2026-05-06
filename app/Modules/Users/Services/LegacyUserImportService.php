<?php

namespace App\Modules\Users\Services;

use App\Enums\GlobalUserRole;
use App\Enums\SectorAccessLevel;
use App\Enums\TicketPriority;
use App\Enums\UserRole;
use App\Models\User;
use App\Modules\Companies\Models\Company;
use App\Modules\Sectors\Models\Sector;
use App\Modules\Tickets\Models\Ticket;
use App\Modules\Tickets\Models\TicketMessage;
use App\Modules\Tickets\Services\SectorProvisioningService;
use App\Modules\Tickets\Services\TicketSlaService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use InvalidArgumentException;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Throwable;

class LegacyUserImportService
{
    private const LOGIN_SHEET = 'Planilha1';

    private const USER_SHEET = 'Planilha2';

    private const SECTOR_SHEET = 'Planilha3';

    private const TARGET_COMPANY = 'Anadem';

    private const DEMO_TICKET_PREFIX = '[DEMO-LEGACY]';

    /**
     * @var array<int, string>
     */
    private array $sectorColors = [
        '#3D567B',
        '#2563EB',
        '#0F766E',
        '#B45309',
        '#7C3AED',
        '#BE123C',
        '#047857',
        '#4338CA',
        '#A16207',
        '#0369A1',
    ];

    public function __construct(
        private readonly SectorProvisioningService $sectorProvisioningService,
        private readonly TicketSlaService $ticketSlaService,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function import(string $path, string $password, bool $dryRun = false): array
    {
        if (! is_file($path)) {
            throw new InvalidArgumentException("Arquivo nao encontrado: {$path}");
        }

        $workbook = $this->loadWorkbook($path);

        try {
            $source = $this->extractSource($workbook);

            DB::beginTransaction();

            try {
                $summary = $this->persistSource($source, $password, $path, $dryRun);

                if ($dryRun) {
                    DB::rollBack();
                } else {
                    DB::commit();
                }

                return $summary;
            } catch (Throwable $throwable) {
                DB::rollBack();

                throw $throwable;
            }
        } finally {
            $workbook->disconnectWorksheets();
            unset($workbook);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function extractSource(Spreadsheet $workbook): array
    {
        $loginRows = $this->worksheetRows($workbook, self::LOGIN_SHEET);
        $userRows = $this->worksheetRows($workbook, self::USER_SHEET);
        $sectorRows = $this->worksheetRows($workbook, self::SECTOR_SHEET);

        $legacyRoles = $this->legacyRoles($loginRows);
        $legacyUsers = $this->legacyUsers($userRows);

        return [
            'roles' => $legacyRoles['roles'],
            'users' => $legacyUsers['users'],
            'sectors' => $this->legacySectors($sectorRows, $legacyUsers['users']),
            'source_counts' => [
                'login_rows' => count($loginRows),
                'user_rows' => count($userRows),
                'sector_rows' => count($sectorRows),
                'login_duplicate_emails' => $legacyRoles['duplicate_emails'],
                'user_duplicate_emails' => $legacyUsers['duplicate_emails'],
                'login_missing_emails' => $legacyRoles['missing_emails'],
                'user_missing_emails' => $legacyUsers['missing_emails'],
            ],
            'ignored_fields' => $this->ignoredFieldCounts($userRows),
        ];
    }

    /**
     * @param  array<string, mixed>  $source
     * @return array<string, mixed>
     */
    private function persistSource(array $source, string $password, string $path, bool $dryRun): array
    {
        $summary = [
            'dry_run' => $dryRun,
            'path' => $path,
            'source' => [
                ...$source['source_counts'],
                'sector_names' => count($source['sectors']),
            ],
            'ignored_fields' => $source['ignored_fields'],
            'company' => [
                'name' => self::TARGET_COMPANY,
                'created' => 0,
                'updated' => 0,
                'reactivated' => 0,
            ],
            'sectors' => [
                'created' => 0,
                'updated' => 0,
                'reactivated' => 0,
                'provisioned' => 0,
            ],
            'users' => [
                'created' => 0,
                'updated' => 0,
                'reactivated' => 0,
                'preserved_super_admin' => 0,
                'without_sector' => 0,
            ],
            'accesses' => [
                'created' => 0,
                'updated' => 0,
                'removed' => 0,
            ],
            'demo' => [
                'tickets_created' => 0,
                'tickets_updated' => 0,
                'messages_created' => 0,
                'sectors_without_requester' => 0,
            ],
        ];

        $company = $this->upsertCompany($summary);
        $sectorsByKey = $this->upsertSectors($company, $source['sectors'], $summary);
        $importedUserIds = $this->upsertUsers($source['users'], $source['roles'], $sectorsByKey, $password, $summary);

        $this->upsertDemoTickets($sectorsByKey, $importedUserIds, $summary);

        return $summary;
    }

    /**
     * @param  array<string, mixed>  $summary
     */
    private function upsertCompany(array &$summary): Company
    {
        $company = Company::query()
            ->withTrashed()
            ->whereRaw('lower(name) = ?', [Str::lower(self::TARGET_COMPANY)])
            ->first();

        if (! $company) {
            $summary['company']['created']++;

            return Company::query()->create([
                'name' => self::TARGET_COMPANY,
                'is_active' => true,
            ]);
        }

        if ($company->trashed()) {
            $company->restore();
            $summary['company']['reactivated']++;
        }

        $company->forceFill([
            'name' => self::TARGET_COMPANY,
            'is_active' => true,
        ]);

        if ($company->isDirty()) {
            $company->save();
            $summary['company']['updated']++;
        }

        return $company->fresh();
    }

    /**
     * @param  array<string, string>  $sectorNames
     * @param  array<string, mixed>  $summary
     * @return array<string, Sector>
     */
    private function upsertSectors(Company $company, array $sectorNames, array &$summary): array
    {
        $existingSectors = Sector::query()
            ->withTrashed()
            ->where('company_id', $company->id)
            ->get()
            ->mapWithKeys(fn (Sector $sector) => [$this->lookupKey($sector->name) => $sector]);

        $sectorsByKey = [];
        $index = 0;

        foreach ($sectorNames as $sectorKey => $sectorName) {
            /** @var Sector|null $sector */
            $sector = $existingSectors->get($sectorKey);
            $wasCreated = ! $sector;

            if (! $sector) {
                $sector = new Sector(['company_id' => $company->id]);
            }

            if ($sector->exists && $sector->trashed()) {
                $sector->restore();
                $summary['sectors']['reactivated']++;
            }

            $sector->forceFill([
                'company_id' => $company->id,
                'name' => $sectorName,
                'slug' => $this->uniqueSectorSlug($company->id, $sectorName, $sector->exists ? (int) $sector->id : null),
                'color' => $sector->color ?: $this->sectorColors[$index % count($this->sectorColors)],
                'description' => $sector->description ?: 'Importado da planilha legado de usuarios e setores.',
                'is_active' => true,
            ]);

            if ($wasCreated) {
                $summary['sectors']['created']++;
            } elseif ($sector->isDirty()) {
                $summary['sectors']['updated']++;
            }

            $sector->save();

            $this->sectorProvisioningService->provision($sector->fresh());
            $summary['sectors']['provisioned']++;

            $sectorsByKey[$sectorKey] = $sector->fresh();
            $index++;
        }

        return $sectorsByKey;
    }

    /**
     * @param  array<int, array<string, mixed>>  $users
     * @param  array<string, array<string, mixed>>  $rolesByEmail
     * @param  array<string, Sector>  $sectorsByKey
     * @param  array<string, mixed>  $summary
     * @return array<int, int>
     */
    private function upsertUsers(array $users, array $rolesByEmail, array $sectorsByKey, string $password, array &$summary): array
    {
        $importedUserIds = [];

        foreach ($users as $row) {
            $legacyRole = $rolesByEmail[$row['email']]['role'] ?? 'user';
            $userRole = $this->mapLegacyRoleToUserRole($legacyRole);
            $accessLevel = $this->mapLegacyRoleToAccessLevel($legacyRole);
            $sector = $row['sector_key'] ? ($sectorsByKey[$row['sector_key']] ?? null) : null;

            /** @var User|null $user */
            $user = User::query()->withTrashed()->where('email', $row['email'])->first();
            $wasCreated = ! $user;

            if (! $user) {
                $user = new User(['email' => $row['email']]);
            }

            if ($user->exists && $this->isSuperAdmin($user)) {
                $summary['users']['preserved_super_admin']++;
                $importedUserIds[] = (int) $user->id;

                continue;
            }

            if ($user->exists && $user->trashed()) {
                $user->restore();
                $summary['users']['reactivated']++;
            }

            $payload = [
                'name' => $row['name'],
                'email' => $row['email'],
                'email_verified_at' => $user->email_verified_at ?? now(),
                'role' => $userRole,
                'global_role' => GlobalUserRole::COLLABORATOR,
                'sector_id' => $sector?->id,
                'room_id' => null,
                'must_change_password' => true,
                'is_active' => true,
            ];

            if ($wasCreated || ! (bool) $user->must_change_password) {
                $payload['password'] = Hash::make($password);
            }

            $user->forceFill($payload);

            if ($wasCreated && $row['created_at']) {
                $user->created_at = $row['created_at'];
            }

            if ($wasCreated) {
                $summary['users']['created']++;
            } elseif ($user->isDirty()) {
                $summary['users']['updated']++;
            }

            $user->save();

            if (! $sector) {
                $summary['users']['without_sector']++;
            }

            $this->syncUserAccesses($user->fresh(), $sector, $accessLevel, $summary);
            $importedUserIds[] = (int) $user->id;
        }

        return array_values(array_unique($importedUserIds));
    }

    /**
     * @param  array<string, mixed>  $summary
     */
    private function syncUserAccesses(User $user, ?Sector $sector, SectorAccessLevel $accessLevel, array &$summary): void
    {
        $desiredSectorId = $sector?->id;
        $existingAccesses = $user->sectorAccesses()->get();

        foreach ($existingAccesses as $existingAccess) {
            $existingLevel = $this->enumValue($existingAccess->access_level);

            if ($desiredSectorId === null || (int) $existingAccess->sector_id !== (int) $desiredSectorId || $existingLevel !== $accessLevel->value) {
                $existingAccess->delete();
                $summary['accesses']['removed']++;
            }
        }

        if ($desiredSectorId === null) {
            return;
        }

        $access = $user->sectorAccesses()->where('sector_id', $desiredSectorId)->first();

        if (! $access) {
            $user->sectorAccesses()->create([
                'sector_id' => $desiredSectorId,
                'access_level' => $accessLevel,
            ]);

            $summary['accesses']['created']++;

            return;
        }

        if ($this->enumValue($access->access_level) !== $accessLevel->value) {
            $access->update(['access_level' => $accessLevel]);
            $summary['accesses']['updated']++;
        }
    }

    /**
     * @param  array<string, Sector>  $sectorsByKey
     * @param  array<int, int>  $importedUserIds
     * @param  array<string, mixed>  $summary
     */
    private function upsertDemoTickets(array $sectorsByKey, array $importedUserIds, array &$summary): void
    {
        $fallbackRequester = User::query()
            ->whereIn('id', $importedUserIds)
            ->where('global_role', GlobalUserRole::COLLABORATOR->value)
            ->where('is_active', true)
            ->orderBy('id')
            ->first();

        foreach (array_values($sectorsByKey) as $sectorIndex => $sector) {
            $requester = User::query()
                ->where('sector_id', $sector->id)
                ->where('global_role', GlobalUserRole::COLLABORATOR->value)
                ->where('is_active', true)
                ->orderBy('id')
                ->first() ?? $fallbackRequester;

            if (! $requester) {
                $summary['demo']['sectors_without_requester']++;

                continue;
            }

            $assignee = User::query()
                ->withSectorAccess((int) $sector->id, [
                    SectorAccessLevel::SECTOR_ADMIN,
                    SectorAccessLevel::TECHNICIAN,
                ])
                ->where('is_active', true)
                ->orderBy('id')
                ->first();

            $board = $this->sectorProvisioningService->provision($sector->fresh());
            $board->loadMissing(['groups', 'statuses', 'catalogItems']);
            $catalogItem = $board->catalogItems->first();

            foreach ($this->demoTicketStates() as $stateIndex => $state) {
                $title = sprintf('%s %s - %s', self::DEMO_TICKET_PREFIX, $sector->name, $state['title']);
                $createdAt = CarbonImmutable::create(2026, 5, 1, 9, 0, 0)
                    ->addDays($sectorIndex % 5)
                    ->addHours($stateIndex * 2);

                $ticket = Ticket::query()
                    ->withTrashed()
                    ->where('sector_id', $sector->id)
                    ->where('title', $title)
                    ->first();

                $wasCreated = ! $ticket;

                if (! $ticket) {
                    $ticket = new Ticket;
                }

                if ($ticket->exists && $ticket->trashed()) {
                    $ticket->restore();
                }

                $group = $board->groups->firstWhere('slug', $state['group_slug']) ?? $board->defaultGroup();
                $status = $board->statuses->firstWhere('slug', $state['status_slug'])
                    ?? $board->statuses->firstWhere('is_default', true)
                    ?? $board->statuses->first();

                $ticket->forceFill([
                    'sector_id' => $sector->id,
                    'ticket_board_id' => $board->id,
                    'ticket_group_id' => $group?->id,
                    'ticket_status_id' => $status?->id,
                    'service_catalog_item_id' => $catalogItem?->id,
                    'room_id' => null,
                    'title' => $title,
                    'description' => "Chamado demonstrativo criado pela importacao legado para testar o fluxo do setor {$sector->name}.",
                    'requester_id' => $requester->id,
                    'assignee_id' => $assignee?->id,
                    'priority' => $state['priority'],
                    'resolved_at' => null,
                    'last_activity_at' => $createdAt->addMinutes(30),
                    'created_at' => $ticket->exists ? $ticket->created_at : $createdAt,
                    'updated_at' => $createdAt->addMinutes(30),
                ]);
                $ticket->save();

                $this->ticketSlaService->applyPolicy($ticket->fresh());

                $ticket->forceFill([
                    'first_responded_at' => $state['first_responded'] ? $createdAt->addMinutes(45) : null,
                    'resolved_at' => $state['resolved'] ? $createdAt->addHours(4) : null,
                    'last_activity_at' => $state['resolved'] ? $createdAt->addHours(4) : $createdAt->addMinutes(45),
                    'updated_at' => $state['resolved'] ? $createdAt->addHours(4) : $createdAt->addMinutes(45),
                ])->save();

                if ($wasCreated) {
                    $summary['demo']['tickets_created']++;
                } else {
                    $summary['demo']['tickets_updated']++;
                }

                $message = "Mensagem demonstrativa da carga legado para o estado {$state['label']}.";

                if (! TicketMessage::query()->where('ticket_id', $ticket->id)->where('message', $message)->exists()) {
                    TicketMessage::query()->create([
                        'ticket_id' => $ticket->id,
                        'user_id' => $requester->id,
                        'message' => $message,
                        'is_system' => true,
                    ]);

                    $summary['demo']['messages_created']++;
                }
            }
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function demoTicketStates(): array
    {
        return [
            [
                'title' => 'Solicitacao aguardando triagem',
                'label' => 'Novo',
                'group_slug' => 'aberto',
                'status_slug' => 'novo',
                'priority' => TicketPriority::MEDIUM->value,
                'first_responded' => false,
                'resolved' => false,
            ],
            [
                'title' => 'Atendimento em andamento',
                'label' => 'Em atendimento',
                'group_slug' => 'em-andamento',
                'status_slug' => 'em-atendimento',
                'priority' => TicketPriority::HIGH->value,
                'first_responded' => true,
                'resolved' => false,
            ],
            [
                'title' => 'Demanda resolvida',
                'label' => 'Resolvido',
                'group_slug' => 'finalizado',
                'status_slug' => 'resolvido',
                'priority' => TicketPriority::LOW->value,
                'first_responded' => true,
                'resolved' => true,
            ],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function worksheetRows(Spreadsheet $workbook, string $sheetName): array
    {
        $sheet = $workbook->getSheetByName($sheetName);

        if (! $sheet instanceof Worksheet) {
            throw new InvalidArgumentException("A aba {$sheetName} nao foi encontrada no arquivo informado.");
        }

        $rawRows = $sheet->toArray(null, true, true, true);
        $headerRow = array_shift($rawRows) ?? [];
        $headers = [];

        foreach ($headerRow as $column => $header) {
            $key = $this->normalizeHeader($header);

            if ($key !== '') {
                $headers[$column] = $key;
            }
        }

        $rows = [];

        foreach ($rawRows as $rawRow) {
            $row = [];

            foreach ($headers as $column => $header) {
                $row[$header] = $this->normalizeCellValue($rawRow[$column] ?? null);
            }

            if (! $this->rowIsEmpty($row)) {
                $rows[] = $row;
            }
        }

        return $rows;
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array{roles: array<string, array<string, mixed>>, duplicate_emails: int, missing_emails: int}
     */
    private function legacyRoles(array $rows): array
    {
        $roles = [];
        $duplicateEmails = 0;
        $missingEmails = 0;

        foreach ($rows as $row) {
            $email = $this->normalizeEmail($row['email'] ?? null);

            if (! $email) {
                $missingEmails++;

                continue;
            }

            if (isset($roles[$email])) {
                $duplicateEmails++;

                continue;
            }

            $roles[$email] = [
                'role' => $this->sanitizeString($row['role'] ?? null) ?? 'user',
                'has_password_hash' => $this->sanitizeString($row['password_hash'] ?? null) !== null,
            ];
        }

        return [
            'roles' => $roles,
            'duplicate_emails' => $duplicateEmails,
            'missing_emails' => $missingEmails,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array{users: array<int, array<string, mixed>>, duplicate_emails: int, missing_emails: int}
     */
    private function legacyUsers(array $rows): array
    {
        $users = [];
        $seenEmails = [];
        $duplicateEmails = 0;
        $missingEmails = 0;

        foreach ($rows as $row) {
            $email = $this->normalizeEmail($row['email'] ?? null);

            if (! $email) {
                $missingEmails++;

                continue;
            }

            if (isset($seenEmails[$email])) {
                $duplicateEmails++;

                continue;
            }

            $seenEmails[$email] = true;
            $sectorName = $this->sanitizeString($row['setor'] ?? null);

            $users[] = [
                'name' => $this->sanitizeString($row['name'] ?? null) ?? Str::before($email, '@'),
                'email' => $email,
                'sector_name' => $sectorName,
                'sector_key' => $sectorName ? $this->lookupKey($sectorName) : null,
                'created_at' => $this->normalizeDateTime($row['created_at'] ?? null),
            ];
        }

        return [
            'users' => $users,
            'duplicate_emails' => $duplicateEmails,
            'missing_emails' => $missingEmails,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $sectorRows
     * @param  array<int, array<string, mixed>>  $users
     * @return array<string, string>
     */
    private function legacySectors(array $sectorRows, array $users): array
    {
        $sectors = [];

        foreach ($sectorRows as $row) {
            $name = $this->sanitizeString($row['name'] ?? null);

            if ($name) {
                $sectors[$this->lookupKey($name)] ??= $name;
            }
        }

        foreach ($users as $user) {
            if ($user['sector_name']) {
                $sectors[$user['sector_key']] ??= $user['sector_name'];
            }
        }

        ksort($sectors);

        return $sectors;
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<string, int>
     */
    private function ignoredFieldCounts(array $rows): array
    {
        $fields = [
            'phone',
            'cpf',
            'empresa_vinculo',
            'company_id',
            'department_id',
            'data_nascimento',
            'suspended_until',
            'blocked',
            'must_change_password',
            'sexo',
        ];

        $counts = array_fill_keys($fields, 0);

        foreach ($rows as $row) {
            foreach ($fields as $field) {
                if ($this->sanitizeString($row[$field] ?? null) !== null) {
                    $counts[$field]++;
                }
            }
        }

        return $counts;
    }

    private function mapLegacyRoleToUserRole(?string $legacyRole): UserRole
    {
        return match ($this->lookupKey($legacyRole)) {
            'ADMIN', 'DEVELOPER' => UserRole::SECTOR_ADMIN,
            'PROFESSIONAL' => UserRole::TECHNICIAN,
            default => UserRole::REQUESTER,
        };
    }

    private function mapLegacyRoleToAccessLevel(?string $legacyRole): SectorAccessLevel
    {
        return match ($this->lookupKey($legacyRole)) {
            'ADMIN', 'DEVELOPER' => SectorAccessLevel::SECTOR_ADMIN,
            'PROFESSIONAL' => SectorAccessLevel::TECHNICIAN,
            default => SectorAccessLevel::REQUESTER,
        };
    }

    private function isSuperAdmin(User $user): bool
    {
        return $this->enumValue($user->global_role) === GlobalUserRole::SUPER_ADMIN->value
            || $this->enumValue($user->role) === UserRole::SUPER_ADMIN->value;
    }

    private function enumValue(mixed $value): ?string
    {
        if ($value instanceof GlobalUserRole || $value instanceof UserRole || $value instanceof SectorAccessLevel) {
            return $value->value;
        }

        return is_string($value) ? $value : null;
    }

    private function uniqueSectorSlug(int $companyId, string $name, ?int $ignoreId = null): string
    {
        $baseSlug = Str::slug($name) ?: 'setor';
        $slug = $baseSlug;
        $suffix = 2;

        while (
            Sector::query()
                ->withTrashed()
                ->where('company_id', $companyId)
                ->where('slug', $slug)
                ->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))
                ->exists()
        ) {
            $slug = "{$baseSlug}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }

    private function normalizeHeader(mixed $value): string
    {
        return Str::of((string) ($value ?? ''))
            ->ascii()
            ->lower()
            ->replaceMatches('/[^a-z0-9]+/', '_')
            ->trim('_')
            ->value();
    }

    private function normalizeCellValue(mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        if (is_string($value)) {
            $normalized = trim(preg_replace('/\s+/', ' ', $value) ?? $value);

            if ($normalized === '' || Str::lower($normalized) === '[null]') {
                return null;
            }

            return $normalized;
        }

        return $value;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function rowIsEmpty(array $row): bool
    {
        foreach ($row as $value) {
            if ($this->sanitizeString($value) !== null) {
                return false;
            }
        }

        return true;
    }

    private function normalizeEmail(mixed $value): ?string
    {
        $email = $this->sanitizeString($value);

        if (! $email || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return null;
        }

        return Str::lower($email);
    }

    private function sanitizeString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_numeric($value)) {
            $numeric = trim((string) $value);

            return $numeric !== '' ? $numeric : null;
        }

        if (! is_string($value)) {
            return null;
        }

        $normalized = trim(preg_replace('/\s+/', ' ', $value) ?? $value);

        if ($normalized === '' || Str::lower($normalized) === '[null]') {
            return null;
        }

        return $normalized;
    }

    private function normalizeDateTime(mixed $value): ?CarbonImmutable
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            return CarbonImmutable::instance(ExcelDate::excelToDateTimeObject((float) $value));
        }

        $normalized = $this->sanitizeString($value);

        if (! $normalized) {
            return null;
        }

        foreach (['Y-m-d H:i:s.u', 'Y-m-d H:i:s', 'Y-m-d H:i', 'd/m/Y H:i:s', 'd/m/Y'] as $format) {
            try {
                $date = CarbonImmutable::createFromFormat($format, $normalized);
            } catch (Throwable) {
                $date = null;
            }

            if ($date instanceof CarbonImmutable) {
                return $date;
            }
        }

        try {
            return CarbonImmutable::parse($normalized);
        } catch (Throwable) {
            return null;
        }
    }

    private function lookupKey(?string $value): string
    {
        return Str::of($value ?? '')
            ->ascii()
            ->replaceMatches('/[^A-Za-z0-9]+/', ' ')
            ->trim()
            ->upper()
            ->value();
    }

    private function loadWorkbook(string $path): Spreadsheet
    {
        $reader = IOFactory::createReaderForFile($path);
        $reader->setReadDataOnly(true);

        return $reader->load($path);
    }
}

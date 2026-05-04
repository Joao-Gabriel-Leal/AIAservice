<?php

namespace App\Modules\Licenses\Services;

use App\Enums\GlobalUserRole;
use App\Enums\LicenseAssignmentStatus;
use App\Enums\LicenseStatus;
use App\Enums\UserRole;
use App\Models\User;
use App\Modules\Licenses\Models\License;
use App\Modules\Licenses\Models\LicenseAssignment;
use App\Modules\Sectors\Models\Sector;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class SapLicenseImportService
{
    public const TARGET_SECTOR = 'AIA Tech';

    public const VENDOR_NAME = 'SAP';

    public const PRODUCT_NAME = 'SAP Business One';

    public const IMPORT_NOTE = 'Importado automaticamente da planilha SAP.';

    /**
     * @var array<int, string>
     */
    public const LICENSE_TYPES = [
        'Profissional',
        'Limited Financials',
        'Limited Logistics',
        'Indirect Access',
    ];

    /**
     * @return array<string, mixed>
     */
    public function import(string $path): array
    {
        if (! is_file($path)) {
            throw new InvalidArgumentException("Arquivo nao encontrado: {$path}");
        }

        $spreadsheet = $this->loadWorkbook($path);

        try {
            $sheet = $spreadsheet->getSheetByName('Planilha1') ?? $spreadsheet->getSheet(0);

            if (! $sheet instanceof Worksheet) {
                throw new InvalidArgumentException('Nenhuma aba valida foi encontrada na planilha SAP.');
            }

            return DB::transaction(function () use ($path, $sheet) {
                $sector = Sector::query()->where('name', self::TARGET_SECTOR)->first();

                if (! $sector) {
                    throw new InvalidArgumentException('O setor AIA Tech nao foi encontrado para receber a importacao SAP.');
                }

                $rows = $this->sheetRows($sheet);
                $activeRows = array_values(array_filter($rows, fn (array $row) => ! $row['blocked']));

                $createdUsers = 0;
                $reusedUsers = 0;
                $createdAssignments = 0;
                $updatedAssignments = 0;
                $releasedAssignments = 0;
                $licensesSummary = [];

                foreach (self::LICENSE_TYPES as $licenseType) {
                    $licenseRows = array_values(array_filter($activeRows, fn (array $row) => $row['license_flags'][$licenseType] === true));
                    $license = $this->upsertLicense($sector, $licenseType, count($licenseRows));
                    $activeReferences = [];

                    foreach ($licenseRows as $row) {
                        ['user' => $user, 'created' => $wasCreated] = $this->upsertImportedUser($sector, $row['user_code'], $row['user_name']);

                        if ($wasCreated) {
                            $createdUsers++;
                        } else {
                            $reusedUsers++;
                        }

                        $activeReferences[] = $row['user_code'];

                        $assignment = LicenseAssignment::query()->firstOrNew([
                            'license_id' => $license->id,
                            'external_reference' => $row['user_code'],
                        ]);

                        $wasNewAssignment = ! $assignment->exists;

                        $assignment->fill([
                            'user_id' => $user->id,
                            'assigned_email' => $user->email,
                            'display_name' => $row['user_name'] ?: $user->name,
                            'seat_label' => $licenseType,
                            'status' => LicenseAssignmentStatus::ACTIVE,
                            'assigned_at' => $assignment->assigned_at ?? now(),
                            'released_at' => null,
                            'notes' => self::IMPORT_NOTE,
                        ]);

                        if (! $assignment->exists) {
                            $assignment->created_by = null;
                        }

                        $assignment->save();

                        if ($wasNewAssignment) {
                            $createdAssignments++;
                        } else {
                            $updatedAssignments++;
                        }
                    }

                    $releasedAssignments += $this->releaseMissingImportedAssignments($license, $activeReferences);

                    $licensesSummary[$licenseType] = [
                        'license_id' => $license->id,
                        'seats_total' => $license->seats_total,
                        'active_assignments' => count($licenseRows),
                    ];
                }

                return [
                    'path' => $path,
                    'sheet' => $sheet->getTitle(),
                    'sector' => $sector->name,
                    'processed_rows' => count($activeRows),
                    'ignored_blocked_rows' => count($rows) - count($activeRows),
                    'created_users' => $createdUsers,
                    'reused_users' => $reusedUsers,
                    'created_assignments' => $createdAssignments,
                    'updated_assignments' => $updatedAssignments,
                    'released_assignments' => $releasedAssignments,
                    'licenses' => $licensesSummary,
                ];
            });
        } finally {
            $spreadsheet->disconnectWorksheets();
            unset($spreadsheet);
        }
    }

    /**
     * @return array<int, array{user_code: string, user_name: string, blocked: bool, license_flags: array<string, bool>}>
     */
    private function sheetRows(Worksheet $sheet): array
    {
        $rows = [];
        $highestRow = (int) $sheet->getHighestDataRow();

        for ($row = 2; $row <= $highestRow; $row++) {
            $userCode = $this->sanitizeString($sheet->getCell("A{$row}")->getValue());
            $userName = $this->sanitizeString($sheet->getCell("B{$row}")->getValue());
            $blockedRaw = $sheet->getCell("C{$row}")->getValue();

            $licenseFlags = [];

            foreach (self::LICENSE_TYPES as $index => $licenseType) {
                $column = chr(ord('D') + $index);
                $licenseFlags[$licenseType] = $this->isTruthy($sheet->getCell("{$column}{$row}")->getValue());
            }

            if ($userCode === null && $userName === null && ! in_array(true, $licenseFlags, true)) {
                continue;
            }

            $rows[] = [
                'user_code' => $userCode ?? 'sem-codigo-'.$row,
                'user_name' => $userName ?? 'Usuario SAP '.$row,
                'blocked' => $this->isTruthy($blockedRaw),
                'license_flags' => $licenseFlags,
            ];
        }

        return $rows;
    }

    private function upsertLicense(Sector $sector, string $licenseType, int $seatsTotal): License
    {
        $license = License::query()->withTrashed()->firstOrNew([
            'sector_id' => $sector->id,
            'vendor_name' => self::VENDOR_NAME,
            'product_name' => self::PRODUCT_NAME,
            'plan_name' => $licenseType,
        ]);

        $license->fill([
            'status' => LicenseStatus::ACTIVE,
            'seats_total' => $seatsTotal,
            'notes' => $this->mergeImportNote($license->notes),
        ]);

        if (! $license->exists) {
            $license->created_by = null;
        }

        if ($license->trashed()) {
            $license->deleted_at = null;
        }

        $license->save();

        return $license->fresh();
    }

    /**
     * @return array{user: User, created: bool}
     */
    private function upsertImportedUser(Sector $sector, string $userCode, string $userName): array
    {
        $email = $this->syntheticEmailForCode($userCode);
        $user = User::query()->withTrashed()->firstOrNew([
            'email' => $email,
        ]);
        $created = ! $user->exists;

        $user->fill([
            'name' => $userName !== '' ? $userName : 'Usuario SAP '.$userCode,
            'role' => UserRole::REQUESTER,
            'global_role' => GlobalUserRole::COLLABORATOR,
            'sector_id' => $sector->id,
            'room_id' => null,
            'must_change_password' => true,
            'is_active' => true,
        ]);

        if (! $user->exists) {
            $user->password = Str::password(24);
        }

        if ($user->trashed()) {
            $user->deleted_at = null;
        }

        $user->save();

        $user->sectorAccesses()->updateOrCreate(
            ['sector_id' => $sector->id],
            ['access_level' => UserRole::REQUESTER->value],
        );

        return [
            'user' => $user->fresh(['sectorAccesses']),
            'created' => $created,
        ];
    }

    /**
     * @param  array<int, string>  $activeReferences
     */
    private function releaseMissingImportedAssignments(License $license, array $activeReferences): int
    {
        $query = LicenseAssignment::query()
            ->where('license_id', $license->id)
            ->where('notes', self::IMPORT_NOTE)
            ->where('status', LicenseAssignmentStatus::ACTIVE->value);

        if ($activeReferences !== []) {
            $query->whereNotIn('external_reference', $activeReferences);
        }

        return $query->update([
            'status' => LicenseAssignmentStatus::RELEASED->value,
            'released_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function mergeImportNote(?string $notes): string
    {
        $normalized = trim((string) $notes);

        if ($normalized === '') {
            return self::IMPORT_NOTE;
        }

        if (str_contains($normalized, self::IMPORT_NOTE)) {
            return $normalized;
        }

        return $normalized."\n".self::IMPORT_NOTE;
    }

    private function syntheticEmailForCode(string $userCode): string
    {
        $slug = Str::of($userCode)
            ->ascii()
            ->lower()
            ->replaceMatches('/[^a-z0-9]+/', '-')
            ->trim('-')
            ->value();

        $slug = $slug !== '' ? $slug : 'usuario-sap';

        return 'sap.'.$slug.'@aia-tech.local';
    }

    private function sanitizeString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_string($value)) {
            $normalized = trim(preg_replace('/\s+/', ' ', $value) ?? $value);

            return $normalized !== '' ? $normalized : null;
        }

        if (is_numeric($value)) {
            return trim((string) $value) !== '' ? trim((string) $value) : null;
        }

        return null;
    }

    private function isTruthy(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (float) $value > 0;
        }

        $raw = trim((string) ($value ?? ''));

        if (in_array($raw, ['✔', '✓', '☑', 'x', 'X'], true)) {
            return true;
        }

        $normalized = Str::of($raw)
            ->ascii()
            ->lower()
            ->trim()
            ->value();

        return in_array($normalized, ['sim', 's', 'yes', 'true', '1', 'x'], true);
    }

    private function loadWorkbook(string $path): Spreadsheet
    {
        $reader = IOFactory::createReaderForFile($path);
        $reader->setReadDataOnly(true);

        return $reader->load($path);
    }
}

<?php

namespace App\Modules\Assets\Services;

use App\Enums\AssetAllocationStatus;
use App\Enums\AssetStatus;
use App\Modules\Assets\Models\Asset;
use App\Modules\Assets\Models\AssetFinancialProfile;
use App\Modules\Assets\Models\AssetImportBatch;
use App\Modules\Assets\Models\AssetImportRow;
use App\Modules\Rooms\Models\Room;
use App\Modules\Sectors\Models\Sector;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class LegacyAssetImportService
{
    public const SOURCE_SHEET = 'Anadem';

    public const REFERENCE_SHEET = 'BASE DE DADOS';

    /**
     * @var array<string, string>
     */
    private array $defaultRoomsBySector = [
        'TI' => 'Infraestrutura',
        'COMPRAS' => 'Homologacao',
        'FINANCEIRO' => 'Tesouraria',
        'JURIDICO' => 'Contratos',
        'CEO PRESIDENCIA' => 'Diretoria',
    ];

    /**
     * @var array<int, string>
     */
    private array $genericCollaborators = [
        'USO DE TODOS DO SETOR',
        'USO LIVRE',
        'ESTOQUE',
    ];

    public function import(string $path): AssetImportBatch
    {
        if (! is_file($path)) {
            throw new InvalidArgumentException("Arquivo nao encontrado: {$path}");
        }

        $spreadsheet = $this->loadWorkbook($path);

        try {
            $sourceSheet = $spreadsheet->getSheetByName(self::SOURCE_SHEET);
            $referenceSheet = $spreadsheet->getSheetByName(self::REFERENCE_SHEET);

            if (! $sourceSheet instanceof Worksheet) {
                throw new InvalidArgumentException('A aba Anadem nao foi encontrada no arquivo informado.');
            }

            if (! $referenceSheet instanceof Worksheet) {
                throw new InvalidArgumentException('A aba BASE DE DADOS nao foi encontrada no arquivo informado.');
            }

            return DB::transaction(function () use ($path, $sourceSheet, $referenceSheet) {
                $referenceCategories = $this->referenceCategories($referenceSheet);
                $sectorMap = $this->sectorMap();
                $roomMap = $this->roomMap();

                $batch = AssetImportBatch::query()->create([
                    'source_path' => $path,
                    'source_sheet' => self::SOURCE_SHEET,
                    'reference_sheet' => self::REFERENCE_SHEET,
                    'started_at' => now(),
                    'metadata' => [
                        'reference_categories' => count($referenceCategories),
                    ],
                ]);

                $counts = [
                    'total_rows' => 0,
                    'ready_rows' => 0,
                    'pending_review_rows' => 0,
                    'error_rows' => 0,
                    'promoted_rows' => 0,
                ];

                $uniqueAssetCodes = [];
                $readyRows = [];

                foreach ($this->sourceRows($sourceSheet) as $sourceRow => $rawPayload) {
                    $normalized = $this->normalizeRow($rawPayload, $referenceCategories, $sectorMap, $roomMap);
                    $normalized['legacy_source_row'] = $sourceRow;

                    $counts['total_rows']++;

                    if (filled($normalized['asset_code'])) {
                        $uniqueAssetCodes[(string) $normalized['asset_code']] = true;
                    }

                    if ($normalized['processing_status'] === 'ready') {
                        $counts['ready_rows']++;
                    } elseif ($normalized['processing_status'] === 'pending_review') {
                        $counts['pending_review_rows']++;
                    } else {
                        $counts['error_rows']++;
                    }

                    $importRow = AssetImportRow::query()->create([
                        'batch_id' => $batch->id,
                        'source_sheet' => self::SOURCE_SHEET,
                        'source_row' => $sourceRow,
                        'asset_code' => $normalized['asset_code'],
                        'item_name' => $normalized['item_name'],
                        'legacy_status' => $normalized['legacy_status'],
                        'processing_status' => $normalized['processing_status'],
                        'pending_reason' => $normalized['pending_reason'],
                        'resolved_status' => $normalized['resolved_status'],
                        'resolved_sector_id' => $normalized['resolved_sector_id'],
                        'resolved_room_id' => $normalized['resolved_room_id'],
                        'allocation_status' => $normalized['allocation_status'],
                        'raw_payload' => $rawPayload,
                        'normalized_payload' => Arr::except($normalized, [
                            'legacy_status',
                            'processing_status',
                            'pending_reason',
                        ]),
                    ]);

                    if ($normalized['processing_status'] === 'ready') {
                        $readyRows[] = [
                            'row' => $importRow,
                            'normalized' => $normalized,
                        ];
                    }
                }

                foreach ($readyRows as $entry) {
                    $asset = $this->upsertAsset($entry['normalized']);
                    $this->upsertFinancialProfile($asset, $entry['normalized']);

                    $entry['row']->forceFill([
                        'asset_id' => $asset->id,
                        'imported_at' => now(),
                    ])->save();

                    $counts['promoted_rows']++;
                }

                $batch->forceFill([
                    ...$counts,
                    'finished_at' => now(),
                    'metadata' => [
                        'reference_categories' => count($referenceCategories),
                        'unique_asset_codes' => count($uniqueAssetCodes),
                        'source_highest_row' => $sourceSheet->getHighestDataRow(),
                    ],
                ])->save();

                return $batch->fresh();
            });
        } finally {
            $spreadsheet->disconnectWorksheets();
            unset($spreadsheet);
        }
    }

    /**
     * @return array<string, array{name: string, useful_life_months: int|null}>
     */
    private function referenceCategories(Worksheet $sheet): array
    {
        $categories = [];
        $highestRow = (int) $sheet->getHighestDataRow();

        for ($row = 3; $row <= $highestRow; $row++) {
            $name = $this->sanitizeString($sheet->getCell("B{$row}")->getValue());

            if ($name === null) {
                continue;
            }

            $categories[$this->normalizeLookupKey($name)] = [
                'name' => $name,
                'useful_life_months' => $this->sanitizeInteger($sheet->getCell("C{$row}")->getValue()),
            ];
        }

        return $categories;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function sourceRows(Worksheet $sheet): array
    {
        $rows = [];
        $highestRow = (int) $sheet->getHighestDataRow();

        for ($row = 4; $row <= $highestRow; $row++) {
            $payload = [];

            foreach (range('B', 'P') as $column) {
                $payload[$column] = $sheet->getCell("{$column}{$row}")->getValue();
            }

            if ($this->rowIsEmpty($payload)) {
                continue;
            }

            $rows[$row] = $payload;
        }

        return $rows;
    }

    /**
     * @param  array<string, mixed>  $rawPayload
     * @param  array<string, array{name: string, useful_life_months: int|null}>  $referenceCategories
     * @param  array<string, array{id: int, name: string}>  $sectorMap
     * @param  array<int, array<string, int>>  $roomMap
     * @return array<string, mixed>
     */
    private function normalizeRow(array $rawPayload, array $referenceCategories, array $sectorMap, array $roomMap): array
    {
        $assetCode = $this->sanitizeString($rawPayload['B'] ?? null);
        $itemName = $this->sanitizeString($rawPayload['D'] ?? null);
        $categoryLabel = $this->sanitizeString($rawPayload['E'] ?? null);
        $sourceLink = $this->sanitizeString($rawPayload['F'] ?? null);
        $legacyStatus = Str::upper($this->sanitizeString($rawPayload['G'] ?? null) ?? '');
        $legacyCollaboratorName = $this->sanitizeString($rawPayload['H'] ?? null);
        $legacyPositionText = $this->sanitizeString($rawPayload['I'] ?? null);
        $legacyObservation = $this->sanitizeString($rawPayload['J'] ?? null);
        $invoiceNumber = $this->sanitizeString($rawPayload['K'] ?? null);

        $referenceCategory = $categoryLabel === null
            ? null
            : ($referenceCategories[$this->normalizeLookupKey($categoryLabel)] ?? null);

        $resolvedSector = $legacyPositionText === null
            ? null
            : ($sectorMap[$this->normalizeLookupKey($legacyPositionText)] ?? null);

        $resolvedSectorId = $resolvedSector['id'] ?? null;
        $resolvedRoomId = null;

        if ($resolvedSectorId !== null) {
            $roomName = $this->defaultRoomName($resolvedSector['name']);
            $resolvedRoomId = $roomMap[$resolvedSectorId][$this->normalizeLookupKey($roomName)] ?? null;
        }

        $resolvedStatus = null;
        $processingStatus = 'ready';
        $pendingReason = null;

        if ($assetCode === null) {
            $processingStatus = 'error';
            $pendingReason = 'Linha sem placa patrimonial.';
        } elseif ($legacyStatus === '') {
            $processingStatus = 'pending_review';
            $pendingReason = 'Status vazio no legado.';
        } elseif ($itemName === null) {
            $processingStatus = 'error';
            $pendingReason = 'Linha sem nome do item.';
        } elseif ($legacyStatus === 'ATIVO') {
            $resolvedStatus = $this->isCollaboratorHelpful($legacyCollaboratorName)
                ? AssetStatus::EM_USO->value
                : AssetStatus::DISPONIVEL->value;
        } elseif ($legacyStatus === 'BAIXADO') {
            $resolvedStatus = AssetStatus::BAIXADO->value;
        } else {
            $processingStatus = 'error';
            $pendingReason = "Status legado nao suportado: {$legacyStatus}.";
        }

        $allocationStatus = $resolvedSectorId !== null && $resolvedRoomId !== null
            ? AssetAllocationStatus::ALLOCATED->value
            : AssetAllocationStatus::PENDING_REVIEW->value;

        return [
            'asset_code' => $assetCode,
            'item_name' => $itemName,
            'category_code' => $categoryLabel,
            'category_name' => $referenceCategory['name'] ?? $categoryLabel,
            'source_link' => $sourceLink,
            'legacy_status' => $legacyStatus !== '' ? $legacyStatus : null,
            'legacy_collaborator_name' => $legacyCollaboratorName,
            'legacy_position_text' => $legacyPositionText,
            'legacy_observation' => $legacyObservation,
            'invoice_number' => $invoiceNumber,
            'registered_at' => $this->normalizeDate($rawPayload['L'] ?? null),
            'acquisition_value' => $this->sanitizeDecimal($rawPayload['M'] ?? null),
            'useful_life_months' => $this->sanitizeInteger($rawPayload['N'] ?? null) ?? ($referenceCategory['useful_life_months'] ?? null),
            'remaining_life_months' => $this->sanitizeInteger($rawPayload['O'] ?? null),
            'depreciation_amount' => $this->sanitizeDecimal($rawPayload['P'] ?? null),
            'resolved_status' => $resolvedStatus,
            'processing_status' => $processingStatus,
            'pending_reason' => $pendingReason,
            'resolved_sector_id' => $resolvedSectorId,
            'resolved_room_id' => $resolvedRoomId,
            'allocation_status' => $allocationStatus,
        ];
    }

    /**
     * @param  array<string, mixed>  $normalized
     */
    private function upsertAsset(array $normalized): Asset
    {
        $asset = Asset::query()->firstOrNew([
            'asset_code' => $normalized['asset_code'],
        ]);

        if (! $asset->exists) {
            $asset->uuid = (string) Str::uuid();
        }

        $asset->fill([
            'name' => $normalized['item_name'],
            'description' => $normalized['legacy_observation'],
            'serial_number' => null,
            'brand' => null,
            'model' => null,
            'status' => $normalized['resolved_status'],
            'allocation_status' => $normalized['allocation_status'],
            'legacy_source_sheet' => self::SOURCE_SHEET,
            'legacy_source_row' => $normalized['legacy_source_row'] ?? null,
            'current_sector_id' => $normalized['resolved_sector_id'],
            'current_room_id' => $normalized['resolved_room_id'],
            'current_user_id' => null,
            'created_by' => $asset->created_by,
        ]);

        $asset->save();

        return $asset;
    }

    /**
     * @param  array<string, mixed>  $normalized
     */
    private function upsertFinancialProfile(Asset $asset, array $normalized): AssetFinancialProfile
    {
        return AssetFinancialProfile::query()->updateOrCreate(
            ['asset_id' => $asset->id],
            [
                'category_code' => $normalized['category_code'],
                'category_name' => $normalized['category_name'],
                'source_link' => $normalized['source_link'],
                'invoice_number' => $normalized['invoice_number'],
                'registered_at' => $normalized['registered_at'],
                'acquisition_value' => $normalized['acquisition_value'],
                'useful_life_months' => $normalized['useful_life_months'],
                'remaining_life_months' => $normalized['remaining_life_months'],
                'depreciation_amount' => $normalized['depreciation_amount'],
                'legacy_status' => $normalized['legacy_status'],
                'legacy_collaborator_name' => $normalized['legacy_collaborator_name'],
                'legacy_position_text' => $normalized['legacy_position_text'],
                'legacy_observation' => $normalized['legacy_observation'],
            ],
        );
    }

    /**
     * @return array<string, array{id: int, name: string}>
     */
    private function sectorMap(): array
    {
        return Sector::query()
            ->get(['id', 'name'])
            ->mapWithKeys(fn (Sector $sector) => [
                $this->normalizeLookupKey($sector->name) => [
                    'id' => $sector->id,
                    'name' => $sector->name,
                ],
            ])
            ->all();
    }

    /**
     * @return array<int, array<string, int>>
     */
    private function roomMap(): array
    {
        $map = [];

        Room::query()
            ->get(['id', 'sector_id', 'name'])
            ->each(function (Room $room) use (&$map): void {
                $map[$room->sector_id][$this->normalizeLookupKey($room->name)] = $room->id;
            });

        return $map;
    }

    private function defaultRoomName(string $sectorName): string
    {
        $normalizedSector = Str::upper($this->normalizeLookupKey($sectorName));

        return $this->defaultRoomsBySector[$normalizedSector] ?? 'Operacao';
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function rowIsEmpty(array $payload): bool
    {
        foreach ($payload as $value) {
            if ($this->sanitizeString($value) !== null) {
                return false;
            }
        }

        return true;
    }

    private function isCollaboratorHelpful(?string $value): bool
    {
        if ($value === null) {
            return false;
        }

        $normalized = Str::upper($this->normalizeLookupKey($value));

        return $normalized !== '' && ! in_array($normalized, $this->genericCollaborators, true);
    }

    private function normalizeLookupKey(?string $value): string
    {
        return Str::of($value ?? '')
            ->ascii()
            ->replaceMatches('/[^A-Za-z0-9]+/', ' ')
            ->trim()
            ->upper()
            ->value();
    }

    private function normalizeDate(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            return ExcelDate::excelToDateTimeObject((float) $value)->format('Y-m-d');
        }

        $normalized = $this->sanitizeString($value);

        if ($normalized === null) {
            return null;
        }

        $formats = ['d/m/Y', 'Y-m-d', 'd-m-Y'];

        foreach ($formats as $format) {
            $date = \DateTimeImmutable::createFromFormat($format, $normalized);

            if ($date instanceof \DateTimeImmutable) {
                return $date->format('Y-m-d');
            }
        }

        return null;
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

    private function sanitizeInteger(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            return (int) round((float) $value);
        }

        $normalized = trim((string) $value);

        if (preg_match('/^-?\d+$/', $normalized) !== 1) {
            return null;
        }

        return (int) $normalized;
    }

    private function sanitizeDecimal(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            return round((float) $value, 2);
        }

        $normalized = trim((string) $value);
        $normalized = str_replace(['R$', ' '], '', $normalized);

        if (str_contains($normalized, ',') && str_contains($normalized, '.')) {
            $normalized = str_replace('.', '', $normalized);
            $normalized = str_replace(',', '.', $normalized);
        } elseif (str_contains($normalized, ',')) {
            $normalized = str_replace(',', '.', $normalized);
        }

        return is_numeric($normalized) ? round((float) $normalized, 2) : null;
    }

    private function loadWorkbook(string $path): Spreadsheet
    {
        $reader = IOFactory::createReaderForFile($path);
        $reader->setReadDataOnly(true);
        $reader->setLoadSheetsOnly([self::SOURCE_SHEET, self::REFERENCE_SHEET]);

        return $reader->load($path);
    }
}

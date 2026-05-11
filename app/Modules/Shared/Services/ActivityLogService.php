<?php

namespace App\Modules\Shared\Services;

use App\Models\User;
use App\Modules\Shared\Models\ActivityLog;
use BackedEnum;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;

class ActivityLogService
{
    public function log(?User $causer, Model $subject, string $event, ?string $description = null, array $properties = []): ActivityLog
    {
        return ActivityLog::query()->create([
            'sector_id' => $properties['sector_id'] ?? data_get($subject, 'sector_id'),
            'causer_id' => $causer?->id,
            'subject_type' => $subject->getMorphClass(),
            'subject_id' => $subject->getKey(),
            'event' => $event,
            'description' => $description,
            'properties' => $properties,
        ]);
    }

    public function logChanges(
        ?User $causer,
        Model $subject,
        string $event,
        string $description,
        array $before,
        array $after,
        array $properties = [],
    ): ?ActivityLog {
        $changes = $this->changes($before, $after);

        if ($changes === []) {
            return null;
        }

        return $this->log($causer, $subject, $event, $description, [
            ...$properties,
            'changes' => $changes,
        ]);
    }

    public function changes(array $before, array $after): array
    {
        $fields = collect(array_keys($before))
            ->merge(array_keys($after))
            ->unique()
            ->values();

        return $fields
            ->mapWithKeys(function (string $field) use ($before, $after): array {
                $beforeValue = $this->normalizeValue($before[$field] ?? null);
                $afterValue = $this->normalizeValue($after[$field] ?? null);

                if ($beforeValue === $afterValue) {
                    return [];
                }

                return [
                    $field => [
                        'before' => $beforeValue,
                        'after' => $afterValue,
                    ],
                ];
            })
            ->all();
    }

    public function snapshot(Model $model, array $fields): array
    {
        return collect($fields)
            ->mapWithKeys(fn (string $field): array => [$field => $this->normalizeValue($model->getAttribute($field))])
            ->all();
    }

    public function protectedChange(): array
    {
        return [
            'before' => '[protegido]',
            'after' => '[alterado]',
        ];
    }

    private function normalizeValue(mixed $value): mixed
    {
        if ($value instanceof BackedEnum) {
            return $value->value;
        }

        if ($value instanceof DateTimeInterface) {
            return $value->format(DateTimeInterface::ATOM);
        }

        if (is_array($value)) {
            return collect($value)
                ->map(fn (mixed $item): mixed => $this->normalizeValue($item))
                ->all();
        }

        if (is_bool($value) || is_int($value) || is_float($value) || is_string($value) || $value === null) {
            return $value;
        }

        return (string) json_encode($value);
    }
}

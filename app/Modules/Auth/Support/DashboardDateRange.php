<?php

namespace App\Modules\Auth\Support;

use Carbon\CarbonImmutable;

class DashboardDateRange
{
    public function __construct(
        public readonly CarbonImmutable $from,
        public readonly CarbonImmutable $to,
        public readonly ?int $presetDays = null,
    ) {}

    public static function forPreset(int $days): self
    {
        $to = CarbonImmutable::now()->endOfDay();
        $from = $to->subDays($days - 1)->startOfDay();

        return new self($from, $to, $days);
    }

    public function days(): int
    {
        return max(1, (int) $this->from->diffInDays($this->to) + 1);
    }

    public function fromDateString(): string
    {
        return $this->from->toDateString();
    }

    public function toDateString(): string
    {
        return $this->to->toDateString();
    }

    public function label(): string
    {
        return $this->from->format('d/m/Y').' ate '.$this->to->format('d/m/Y');
    }

    /**
     * @return array{date_from:string,date_to:string,range_label:string,days:int,preset:int|null}
     */
    public function toArray(): array
    {
        return [
            'date_from' => $this->fromDateString(),
            'date_to' => $this->toDateString(),
            'range_label' => $this->label(),
            'days' => $this->days(),
            'preset' => $this->presetDays,
        ];
    }
}

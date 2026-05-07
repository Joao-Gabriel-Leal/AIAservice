<?php

namespace App\Modules\Tickets\Services;

use App\Enums\TicketPriority;
use App\Models\User;
use App\Modules\SectorTemplates\Models\SectorTemplate;
use App\Modules\SectorTemplates\Services\SectorTemplateApplierService;
use App\Modules\Sectors\Models\Sector;
use App\Modules\Tickets\Models\ServiceCatalogItem;
use App\Modules\Tickets\Models\TicketBoard;
use App\Modules\Tickets\Models\TicketForm;
use App\Modules\Tickets\Models\TicketGroup;
use App\Modules\Tickets\Models\TicketStatus;
use Illuminate\Support\Str;

class SectorProvisioningService
{
    public function __construct(
        private readonly TicketSlaService $ticketSlaService,
        private readonly SectorTemplateApplierService $sectorTemplateApplierService,
    ) {
    }

    public function provision(Sector $sector, ?SectorTemplate $template = null): TicketBoard
    {
        $board = TicketBoard::query()
            ->where('sector_id', $sector->id)
            ->where('is_default', true)
            ->orderBy('id')
            ->first()
            ?? TicketBoard::query()
                ->where('sector_id', $sector->id)
                ->orderBy('id')
                ->first();

        if (! $board) {
            $board = TicketBoard::query()->create([
                'sector_id' => $sector->id,
                'name' => "Quadro {$sector->name}",
                'slug' => $this->uniqueBoardSlug($sector->id, "Quadro {$sector->name}"),
                'description' => "Quadro padrao do setor {$sector->name}.",
                'is_default' => true,
                'is_active' => true,
            ]);
        }

        if ($board->wasRecentlyCreated) {
            $board->operators()->sync($this->defaultOperatorIds($sector->id));
        }

        $groups = collect([
            ['name' => 'Aberto', 'slug' => 'aberto', 'color' => '#2563eb', 'sort_order' => 1, 'is_default' => true, 'is_closed' => false],
            ['name' => 'Em andamento', 'slug' => 'em-andamento', 'color' => '#f59e0b', 'sort_order' => 2, 'is_default' => false, 'is_closed' => false],
            ['name' => 'Finalizado', 'slug' => 'finalizado', 'color' => '#10b981', 'sort_order' => 3, 'is_default' => false, 'is_closed' => true],
        ])->map(function (array $data) use ($board) {
            return TicketGroup::query()->firstOrCreate(
                ['ticket_board_id' => $board->id, 'slug' => $data['slug']],
                [
                    ...$data,
                    'is_collapsed_by_default' => false,
                    'is_active' => true,
                ],
            );
        });

        collect([
            ['name' => 'Novo', 'slug' => 'novo', 'color' => '#2563eb', 'sort_order' => 1, 'is_default' => true, 'is_closed' => false],
            ['name' => 'Em atendimento', 'slug' => 'em-atendimento', 'color' => '#f59e0b', 'sort_order' => 2, 'is_default' => false, 'is_closed' => false],
            ['name' => 'Resolvido', 'slug' => 'resolvido', 'color' => '#10b981', 'sort_order' => 3, 'is_default' => false, 'is_closed' => true],
        ])->each(fn (array $data) => TicketStatus::query()->firstOrCreate(
            ['ticket_board_id' => $board->id, 'slug' => $data['slug']],
            [
                ...$data,
                'is_active' => true,
            ],
        ));

        if ($template && $board->wasRecentlyCreated) {
            return $this->sectorTemplateApplierService->apply($template->loadMissing([
                'fields',
                'catalogItems',
                'automationRules.conditions',
                'automationRules.actions',
                'slaPolicy.targets',
            ]), $board->fresh(['groups', 'statuses']));
        }

        if (! $board->wasRecentlyCreated && ($board->forms()->exists() || $board->catalogItems()->exists() || $board->automationRules()->exists())) {
            $this->ticketSlaService->ensurePolicy($board);

            return $board->fresh(['groups', 'statuses', 'forms', 'catalogItems', 'automationRules', 'slaPolicy.targets']);
        }

        $defaultForm = TicketForm::query()->firstOrCreate(
            ['ticket_board_id' => $board->id, 'name' => 'Abertura padrao'],
            [
                'description' => 'Formulario padrao para abertura de chamados.',
                'is_default' => true,
                'is_active' => true,
            ],
        );

        ServiceCatalogItem::query()->firstOrCreate(
            ['ticket_board_id' => $board->id, 'name' => 'Solicitacao geral'],
            [
                'ticket_form_id' => $defaultForm->id,
                'description' => 'Catalogo padrao para demandas gerais.',
                'default_ticket_group_id' => $groups->firstWhere('is_default', true)?->id ?? $groups->first()?->id,
                'default_priority' => TicketPriority::MEDIUM,
                'is_active' => true,
            ],
        );

        $this->ticketSlaService->ensurePolicy($board);

        return $board->fresh(['groups', 'statuses', 'forms', 'catalogItems']);
    }

    public function createAdditionalBoard(Sector $sector, string $name, ?string $description = null, array $operatorIds = []): TicketBoard
    {
        $board = TicketBoard::query()->create([
            'sector_id' => $sector->id,
            'name' => $name,
            'slug' => $this->uniqueBoardSlug($sector->id, $name),
            'description' => $description,
            'is_default' => false,
            'is_active' => true,
        ]);

        $groups = collect([
            ['name' => 'Aberto', 'slug' => 'aberto', 'color' => '#2563eb', 'sort_order' => 1, 'is_default' => true, 'is_closed' => false],
            ['name' => 'Em andamento', 'slug' => 'em-andamento', 'color' => '#f59e0b', 'sort_order' => 2, 'is_default' => false, 'is_closed' => false],
            ['name' => 'Finalizado', 'slug' => 'finalizado', 'color' => '#10b981', 'sort_order' => 3, 'is_default' => false, 'is_closed' => true],
        ])->map(function (array $data) use ($board) {
            return TicketGroup::query()->create([
                'ticket_board_id' => $board->id,
                ...$data,
                'is_collapsed_by_default' => false,
                'is_active' => true,
            ]);
        });

        collect([
            ['name' => 'Novo', 'slug' => 'novo', 'color' => '#2563eb', 'sort_order' => 1, 'is_default' => true, 'is_closed' => false],
            ['name' => 'Em atendimento', 'slug' => 'em-atendimento', 'color' => '#f59e0b', 'sort_order' => 2, 'is_default' => false, 'is_closed' => false],
            ['name' => 'Resolvido', 'slug' => 'resolvido', 'color' => '#10b981', 'sort_order' => 3, 'is_default' => false, 'is_closed' => true],
        ])->each(fn (array $data) => TicketStatus::query()->create([
            'ticket_board_id' => $board->id,
            ...$data,
            'is_active' => true,
        ]));

        $defaultForm = TicketForm::query()->create([
            'ticket_board_id' => $board->id,
            'name' => 'Abertura padrao',
            'description' => 'Formulario padrao para abertura de chamados.',
            'is_default' => true,
            'is_active' => true,
        ]);

        ServiceCatalogItem::query()->create([
            'ticket_board_id' => $board->id,
            'ticket_form_id' => $defaultForm->id,
            'name' => 'Solicitacao geral',
            'description' => 'Catalogo padrao para demandas gerais.',
            'default_ticket_group_id' => $groups->firstWhere('is_default', true)?->id ?? $groups->first()?->id,
            'default_priority' => TicketPriority::MEDIUM,
            'is_active' => true,
        ]);

        $this->ticketSlaService->ensurePolicy($board);
        $board->operators()->sync($operatorIds);

        return $board->fresh(['groups', 'statuses', 'forms', 'catalogItems', 'operators']);
    }

    private function uniqueBoardSlug(int $sectorId, string $name): string
    {
        $base = Str::slug($name) ?: 'quadro';
        $slug = $base;
        $suffix = 1;

        while (TicketBoard::query()->where('sector_id', $sectorId)->where('slug', $slug)->exists()) {
            $suffix++;
            $slug = "{$base}-{$suffix}";
        }

        return $slug;
    }

    private function defaultOperatorIds(int $sectorId): array
    {
        return User::query()
            ->withSectorAccess($sectorId, ['technician'])
            ->where('is_active', true)
            ->pluck('id')
            ->map(fn ($userId) => (int) $userId)
            ->all();
    }
}

<?php

namespace App\Modules\Tickets\Livewire;

use App\Modules\Sectors\Models\Sector;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class CentralPage extends Component
{
    public function render(): View
    {
        return view('livewire.tickets.central-page', [
            'sectors' => Sector::query()
                ->with([
                    'company',
                    'board.catalogItems' => fn ($query) => $query
                        ->where('is_active', true)
                        ->with('form')
                        ->orderBy('name'),
                ])
                ->where('is_active', true)
                ->orderBy('name')
                ->get(),
        ])->layout('layouts.portal', [
            'title' => 'Central de formularios',
            'subtitle' => 'Escolha o setor e abra o chamado certo sem depender de cadastro duplicado por area.',
        ]);
    }
}

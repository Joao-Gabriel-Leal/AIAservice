<?php

namespace App\Modules\KnowledgeBase\Exports;

use App\Support\Exports\ExcelFileName;
use App\Support\Exports\ExcelSheetData;
use Illuminate\Support\Collection;

class KnowledgeBaseArticlesExport
{
    public function __construct(
        private readonly Collection $articles,
        private readonly bool $includeAdminColumns = false,
    ) {
    }

    public function fileName(): string
    {
        return ExcelFileName::make($this->includeAdminColumns ? 'base-conhecimento-gerenciar' : 'base-conhecimento');
    }

    /**
     * @return array<int, ExcelSheetData>
     */
    public function sheets(): array
    {
        $headings = ['Titulo', 'Resumo', 'Setor', 'Empresa', 'Visibilidade', 'Autor', 'Atualizado em'];

        if ($this->includeAdminColumns) {
            $headings = [...$headings, 'Anexos', 'Status'];
        }

        return [
            new ExcelSheetData(
                'Artigos',
                $headings,
                $this->articles->map(function ($article) {
                    $row = [
                        $article->title,
                        $article->summary,
                        $article->sector?->name ?? '',
                        $article->sector?->company?->name ?? '',
                        $article->visibility->label(),
                        $article->author?->name ?? '',
                        $article->updated_at?->format('d/m/Y H:i'),
                    ];

                    if ($this->includeAdminColumns) {
                        $row[] = (int) ($article->attachments_count ?? 0);
                        $row[] = $article->is_active ? 'Ativo' : 'Inativo';
                    }

                    return $row;
                }),
            ),
        ];
    }
}

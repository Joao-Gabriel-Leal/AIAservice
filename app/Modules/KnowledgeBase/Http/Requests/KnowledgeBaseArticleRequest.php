<?php

namespace App\Modules\KnowledgeBase\Http\Requests;

use App\Enums\KnowledgeBaseArticleStatus;
use App\Enums\KnowledgeBaseVisibility;
use App\Modules\KnowledgeBase\Models\KnowledgeBaseArticle;
use App\Modules\Sectors\Models\Sector;
use App\Modules\Tickets\Models\Ticket;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class KnowledgeBaseArticleRequest extends FormRequest
{
    public function authorize(): bool
    {
        $article = $this->route('article');
        $ticket = $this->route('ticket');

        if ($article instanceof KnowledgeBaseArticle) {
            return Gate::allows('update', $article);
        }

        if ($ticket instanceof Ticket) {
            return Gate::allows('createFromTicket', [KnowledgeBaseArticle::class, $ticket]);
        }

        return Gate::allows('create', KnowledgeBaseArticle::class);
    }

    public function rules(): array
    {
        return [
            'sector_id' => ['required', 'integer', 'exists:sectors,id'],
            'title' => ['required', 'string', 'max:160'],
            'summary' => ['required', 'string', 'max:500'],
            'cover_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'remove_cover_image' => ['nullable', 'boolean'],
            'content' => ['required', 'string'],
            'visibility' => ['required', Rule::enum(KnowledgeBaseVisibility::class)],
            'editorial_status' => ['nullable', Rule::enum(KnowledgeBaseArticleStatus::class)],
            'is_active' => ['nullable', 'boolean'],
            'generated_from_ticket_id' => ['nullable', 'integer', 'exists:tickets,id'],
            'attachments' => ['nullable', 'array'],
            'attachments.*' => ['nullable', 'file', 'max:10240'],
            'remove_attachments' => ['nullable', 'array'],
            'remove_attachments.*' => ['nullable', 'integer'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $actor = $this->user();
            $sectorId = (int) $this->input('sector_id');
            $ticket = $this->route('ticket');

            if (! Sector::query()->whereKey($sectorId)->exists()) {
                return;
            }

            if ($ticket instanceof Ticket) {
                if ($sectorId !== $ticket->sector_id) {
                    $validator->errors()->add('sector_id', 'O rascunho deve usar o mesmo setor do chamado.');
                }

                if ((int) $this->input('generated_from_ticket_id', 0) !== $ticket->id) {
                    $validator->errors()->add('generated_from_ticket_id', 'O artigo precisa permanecer vinculado ao chamado de origem.');
                }

                if (! $actor->isSuperAdmin() && ! $actor->isSectorAdmin($ticket->sector_id)) {
                    if ($this->input('editorial_status', KnowledgeBaseArticleStatus::DRAFT->value) !== KnowledgeBaseArticleStatus::DRAFT->value) {
                        $validator->errors()->add('editorial_status', 'Apenas admins podem publicar artigos.');
                    }

                    if ($this->boolean('is_active')) {
                        $validator->errors()->add('is_active', 'Apenas admins podem ativar artigos na base.');
                    }
                }

                return;
            }

            if (! $actor->isSuperAdmin() && ! $actor->isSectorAdmin($sectorId)) {
                $validator->errors()->add('sector_id', 'Voce so pode gerenciar bases dos setores que administra.');
            }

            $article = $this->route('article');

            if ($article instanceof KnowledgeBaseArticle && ! $actor->isSuperAdmin() && ! $actor->isSectorAdmin($article->sector_id)) {
                $validator->errors()->add('sector_id', 'Voce nao pode alterar artigos de outro setor.');
            }
        });
    }
}

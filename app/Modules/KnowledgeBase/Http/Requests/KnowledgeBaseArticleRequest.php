<?php

namespace App\Modules\KnowledgeBase\Http\Requests;

use App\Enums\KnowledgeBaseVisibility;
use App\Modules\KnowledgeBase\Models\KnowledgeBaseArticle;
use App\Modules\Sectors\Models\Sector;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class KnowledgeBaseArticleRequest extends FormRequest
{
    public function authorize(): bool
    {
        $article = $this->route('article');

        if ($article instanceof KnowledgeBaseArticle) {
            return Gate::allows('update', $article);
        }

        return Gate::allows('create', KnowledgeBaseArticle::class);
    }

    public function rules(): array
    {
        return [
            'sector_id' => ['required', 'integer', 'exists:sectors,id'],
            'title' => ['required', 'string', 'max:160'],
            'summary' => ['required', 'string', 'max:500'],
            'content' => ['required', 'string'],
            'visibility' => ['required', Rule::enum(KnowledgeBaseVisibility::class)],
            'is_active' => ['nullable', 'boolean'],
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

            if (! Sector::query()->whereKey($sectorId)->exists()) {
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

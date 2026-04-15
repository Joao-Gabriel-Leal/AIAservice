<?php

namespace App\Modules\SectorTemplates\Models;

use App\Modules\Sectors\Models\Sector;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

class SectorTemplate extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'description',
        'form_name',
        'form_description',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function sectors(): HasMany
    {
        return $this->hasMany(Sector::class);
    }

    public function fields(): HasMany
    {
        return $this->hasMany(SectorTemplateField::class)->orderBy('sort_order')->orderBy('id');
    }

    public function catalogItems(): HasMany
    {
        return $this->hasMany(SectorTemplateCatalogItem::class)->orderBy('sort_order')->orderBy('id');
    }

    public function automationRules(): HasMany
    {
        return $this->hasMany(SectorTemplateAutomationRule::class)->orderBy('sort_order')->orderBy('id');
    }

    public function slaPolicy(): HasOne
    {
        return $this->hasOne(SectorTemplateSlaPolicy::class);
    }

    public static function defaultGroupOptions(): array
    {
        return [
            'aberto' => 'Aberto',
            'em-andamento' => 'Em andamento',
            'finalizado' => 'Finalizado',
        ];
    }

    public static function defaultStatusOptions(): array
    {
        return [
            'novo' => 'Novo',
            'em-atendimento' => 'Em atendimento',
            'resolvido' => 'Resolvido',
        ];
    }

    public function defaultFormName(): string
    {
        return trim((string) $this->form_name) !== '' ? (string) $this->form_name : 'Abertura padrao';
    }

    public function defaultFormDescription(): ?string
    {
        $description = trim((string) ($this->form_description ?? ''));

        return $description !== '' ? $description : null;
    }

    public function normalizedSlug(): string
    {
        return Str::slug($this->slug ?: $this->name);
    }
}

<?php

namespace App\Modules\Audit\Support;

use App\Models\User;
use App\Modules\Assets\Models\Asset;
use App\Modules\KnowledgeBase\Models\KnowledgeBaseArticle;
use App\Modules\Licenses\Models\License;
use App\Modules\Shared\Models\ActivityLog;
use App\Modules\Tickets\Models\Ticket;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

class ActivityLogPresenter
{
    public function eventLabelFromString(string $event): string
    {
        $log = new ActivityLog;
        $log->event = $event;

        return $this->eventLabel($log);
    }

    public function eventLabel(ActivityLog $log): string
    {
        return match ($log->event) {
            'user.created' => 'Usuario criado',
            'user.updated' => 'Usuario atualizado',
            'user.deleted' => 'Usuario removido',
            'user.password.changed' => 'Senha alterada pelo usuario',
            'user.password.changed_by_admin' => 'Senha alterada por administrador',
            'user.password.reset' => 'Senha redefinida',
            'user.password.reset_by_admin' => 'Senha redefinida por administrador',
            'license.created' => 'Licenca cadastrada',
            'license.updated' => 'Licenca atualizada',
            'license.assignment.created' => 'Licenca atribuida',
            'license.assignment.updated' => 'Atribuicao de licenca atualizada',
            'license.assignment.transferred' => 'Licenca transferida',
            'license.assignment.released' => 'Licenca liberada',
            'ticket.created' => 'Chamado criado',
            'ticket.updated' => 'Chamado atualizado',
            'ticket.field.updated' => 'Campo do chamado atualizado',
            'ticket.deleted' => 'Chamado removido',
            'knowledge_base.article.created' => 'Artigo criado',
            'knowledge_base.article.created_from_ticket' => 'Artigo criado a partir de chamado',
            'knowledge_base.article.updated' => 'Artigo atualizado',
            'knowledge_base.article.deleted' => 'Artigo removido',
            default => Str::of($log->event)->replace(['.', '_'], ' ')->headline()->toString(),
        };
    }

    public function subjectTypeLabel(ActivityLog $log): string
    {
        return match ($log->subject_type) {
            User::class => 'Usuario',
            Ticket::class => 'Chamado',
            License::class => 'Licenca',
            KnowledgeBaseArticle::class => 'Artigo',
            Asset::class => 'Patrimonio',
            default => class_basename((string) $log->subject_type),
        };
    }

    public function subjectName(ActivityLog $log): string
    {
        $subject = $log->subject;

        if ($subject instanceof User) {
            return $subject->name.' <'.$subject->email.'>';
        }

        if ($subject instanceof Ticket) {
            return trim($subject->fullReference().' - '.$subject->title);
        }

        if ($subject instanceof License) {
            return $subject->displayName();
        }

        if ($subject instanceof KnowledgeBaseArticle) {
            return $subject->title;
        }

        if ($subject instanceof Asset) {
            return $subject->name ?? 'Patrimonio #'.$log->subject_id;
        }

        return data_get($log->properties, 'before.title')
            ?? data_get($log->properties, 'before.name')
            ?? data_get($log->properties, 'before.email')
            ?? data_get($log->properties, 'to.display_name')
            ?? data_get($log->properties, 'assignment.display_name')
            ?? '#'.$log->subject_id;
    }

    public function subjectUrl(ActivityLog $log): ?string
    {
        $subject = $log->subject;

        if ($subject instanceof User && Route::has('users.show')) {
            return route('users.show', $subject);
        }

        if ($subject instanceof Ticket && Route::has('tickets.show')) {
            return route('tickets.show', $subject);
        }

        if ($subject instanceof License && Route::has('licenses.show')) {
            return route('licenses.show', $subject);
        }

        if ($subject instanceof KnowledgeBaseArticle && Route::has('knowledge-base.show')) {
            return route('knowledge-base.show', $subject);
        }

        if ($subject instanceof Asset && Route::has('assets.show')) {
            return route('assets.show', $subject);
        }

        return null;
    }

    public function changes(ActivityLog $log): array
    {
        $properties = $log->properties ?? [];
        $rows = $this->changeRowsFromStructuredChanges(data_get($properties, 'changes'));

        if ($rows !== []) {
            return $rows;
        }

        $rows = $this->changeRowsFromBeforeAfter(
            data_get($properties, 'before'),
            data_get($properties, 'after'),
        );

        if ($rows !== []) {
            return $rows;
        }

        $rows = $this->changeRowsFromBeforeAfter(
            data_get($properties, 'from'),
            data_get($properties, 'to'),
        );

        if ($rows !== []) {
            return $rows;
        }

        if (array_key_exists('value', $properties)) {
            return [[
                'field' => data_get($properties, 'field_name', 'value'),
                'label' => $this->fieldLabel((string) data_get($properties, 'field_name', 'value')),
                'before' => '-',
                'after' => $this->formatValue($properties['value']),
            ]];
        }

        return [];
    }

    public function fieldLabel(string $field): string
    {
        return match ($field) {
            'name' => 'Nome',
            'email' => 'E-mail',
            'global_role' => 'Perfil global',
            'role' => 'Perfil legado',
            'sector_id' => 'Setor',
            'room_id' => 'Sala',
            'must_change_password' => 'Troca de senha pendente',
            'is_active' => 'Ativo',
            'sector_accesses' => 'Acessos por setor',
            'password' => 'Senha',
            'vendor_name' => 'Fornecedor',
            'product_name' => 'Produto',
            'plan_name' => 'Plano',
            'license_reference' => 'Referencia da licenca',
            'supplier_name' => 'Fornecedor da compra',
            'seats_total' => 'Assentos',
            'status' => 'Status',
            'billing_cycle' => 'Ciclo de cobranca',
            'cost_amount' => 'Custo',
            'cost_currency' => 'Moeda',
            'purchased_at' => 'Compra',
            'renewal_date' => 'Renovacao',
            'expires_at' => 'Expiracao',
            'auto_renew' => 'Renovacao automatica',
            'notes' => 'Observacoes',
            'title' => 'Titulo',
            'description' => 'Descricao',
            'requester_id' => 'Solicitante',
            'assignee_id' => 'Responsavel',
            'ticket_board_id' => 'Quadro',
            'ticket_group_id' => 'Etapa',
            'ticket_status_id' => 'Status do chamado',
            'service_catalog_item_id' => 'Item de catalogo',
            'priority' => 'Prioridade',
            'resolved_at' => 'Resolvido em',
            'is_major_incident' => 'Incidente massivo',
            'major_incident_ticket_id' => 'Incidente relacionado',
            'created_by' => 'Criado por',
            'generated_from_ticket_id' => 'Chamado de origem',
            'summary' => 'Resumo',
            'cover_image_path' => 'Imagem de capa',
            'content' => 'Conteudo',
            'visibility' => 'Visibilidade',
            'editorial_status' => 'Status editorial',
            'display_name' => 'Nome exibido',
            'assigned_email' => 'E-mail atribuido',
            'external_reference' => 'Referencia externa',
            'user_id' => 'Usuario',
            default => Str::of($field)->replace('_', ' ')->headline()->toString(),
        };
    }

    public function formatValue(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '-';
        }

        if (is_bool($value)) {
            return $value ? 'Sim' : 'Nao';
        }

        if (is_array($value)) {
            if ($value === []) {
                return '[]';
            }

            $encoded = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

            return Str::limit(is_string($encoded) ? $encoded : 'Valor complexo', 180);
        }

        return Str::limit((string) $value, 180);
    }

    private function changeRowsFromStructuredChanges(mixed $changes): array
    {
        if (! is_array($changes)) {
            return [];
        }

        return collect($changes)
            ->filter(fn (mixed $change, mixed $field): bool => is_string($field))
            ->map(function (mixed $change, string $field): array {
                $before = is_array($change) && array_key_exists('before', $change)
                    ? $change['before']
                    : null;
                $after = is_array($change) && array_key_exists('after', $change)
                    ? $change['after']
                    : $change;

                return [
                    'field' => $field,
                    'label' => $this->fieldLabel($field),
                    'before' => $this->formatValue($before),
                    'after' => $this->formatValue($after),
                ];
            })
            ->values()
            ->all();
    }

    private function changeRowsFromBeforeAfter(mixed $before, mixed $after): array
    {
        if (! is_array($before) || ! is_array($after)) {
            return [];
        }

        return collect(array_keys($before))
            ->merge(array_keys($after))
            ->unique()
            ->filter(fn (mixed $field): bool => is_string($field))
            ->mapWithKeys(fn (string $field): array => [$field => [
                'before' => $before[$field] ?? null,
                'after' => $after[$field] ?? null,
            ]])
            ->filter(fn (array $change): bool => $this->formatValue($change['before']) !== $this->formatValue($change['after']))
            ->map(fn (array $change, string $field): array => [
                'field' => $field,
                'label' => $this->fieldLabel($field),
                'before' => $this->formatValue($change['before']),
                'after' => $this->formatValue($change['after']),
            ])
            ->values()
            ->all();
    }
}

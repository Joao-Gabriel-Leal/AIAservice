<?php

namespace App\Modules\Emails\Support;

use App\Models\User;

class EmailTemplateCatalog
{
    public const ACCOUNT_CREATED = 'account_created';

    public const USER_DEFAULT_PASSWORD_RESET = 'user_default_password_reset';

    public const TICKET_CREATED = 'ticket_created';

    public const TICKET_UPDATED = 'ticket_updated';

    public const TICKET_MESSAGE_CREATED = 'ticket_message_created';

    public const TICKET_INTERNAL_MENTION = 'ticket_internal_mention';

    public const TICKET_SLA_ALERT = 'ticket_sla_alert';

    public const TICKET_RATING_REQUEST = 'ticket_rating_request';

    public const TICKET_MANUAL_TIME_ENTRY_PENDING = 'ticket_manual_time_entry_pending';

    public const TICKET_TIME_ENTRY_REVIEWED = 'ticket_time_entry_reviewed';

    public const TICKET_ACTIVITY = 'ticket_activity';

    public function definitions(): array
    {
        return [
            self::ACCOUNT_CREATED => [
                'label' => 'Conta criada',
                'description' => 'Aviso enviado quando um administrador cria um usuario.',
                'subject' => 'Sua conta foi criada em {{ app_name }}',
                'html_body' => $this->defaultHtml(
                    'Sua conta esta pronta',
                    'Ola, {{ recipient_name }}.',
                    'Seu acesso ao {{ app_name }} foi criado. Use o login {{ login_email }} para entrar. {{ password_note }}',
                    'Acessar sistema',
                ),
                'variables' => [
                    'login_email' => 'E-mail de acesso',
                    'login_url' => 'URL de login',
                    'password_note' => 'Mensagem sobre senha temporaria',
                ],
            ],
            self::USER_DEFAULT_PASSWORD_RESET => [
                'label' => 'Senha padrao redefinida',
                'description' => 'Aviso enviado quando um administrador redefine a senha de um usuario para a senha padrao.',
                'subject' => 'Sua senha foi redefinida em {{ app_name }}',
                'html_body' => $this->defaultHtml(
                    'Senha redefinida',
                    'Ola, {{ recipient_name }}.',
                    'Sua senha foi redefinida por um administrador. Use o login {{ login_email }} e a senha temporaria {{ temporary_password }}. Voce devera trocar a senha no proximo acesso.',
                    'Acessar sistema',
                ),
                'variables' => [
                    'login_email' => 'E-mail de acesso',
                    'login_url' => 'URL de login',
                    'temporary_password' => 'Senha temporaria padrao',
                ],
            ],
            self::TICKET_CREATED => [
                'label' => 'Chamado criado',
                'description' => 'Aviso de abertura de chamado para envolvidos e operadores.',
                'subject' => '{{ notification_title }} - {{ ticket_reference }}',
                'html_body' => $this->ticketHtml('Novo chamado criado'),
                'variables' => $this->ticketVariables(),
            ],
            self::TICKET_UPDATED => [
                'label' => 'Chamado atualizado',
                'description' => 'Alteracoes de responsavel, etapa, prioridade ou reabertura.',
                'subject' => '{{ notification_title }} - {{ ticket_reference }}',
                'html_body' => $this->ticketHtml('Chamado atualizado'),
                'variables' => $this->ticketVariables(),
            ],
            self::TICKET_MESSAGE_CREATED => [
                'label' => 'Nova mensagem',
                'description' => 'Mensagem publica adicionada ao chamado.',
                'subject' => '{{ notification_title }} - {{ ticket_reference }}',
                'html_body' => $this->ticketHtml('Nova mensagem no chamado'),
                'variables' => $this->ticketVariables(),
            ],
            self::TICKET_INTERNAL_MENTION => [
                'label' => 'Mencao interna',
                'description' => 'Mencao em atualizacao interna do chamado.',
                'subject' => '{{ notification_title }} - {{ ticket_reference }}',
                'html_body' => $this->ticketHtml('Atualizacao interna'),
                'variables' => $this->ticketVariables(),
            ],
            self::TICKET_SLA_ALERT => [
                'label' => 'Alerta de SLA',
                'description' => 'Avisos de SLA proximo do prazo ou estourado.',
                'subject' => '{{ notification_title }} - {{ ticket_reference }}',
                'html_body' => $this->ticketHtml('Alerta de SLA'),
                'variables' => $this->ticketVariables(),
            ],
            self::TICKET_RATING_REQUEST => [
                'label' => 'Pedido de avaliacao',
                'description' => 'Solicitacao de avaliacao apos encerramento do chamado.',
                'subject' => '{{ notification_title }} - {{ ticket_reference }}',
                'html_body' => $this->ticketHtml('Avalie o atendimento'),
                'variables' => $this->ticketVariables(),
            ],
            self::TICKET_MANUAL_TIME_ENTRY_PENDING => [
                'label' => 'Apontamento pendente',
                'description' => 'Apontamento manual aguardando aprovacao.',
                'subject' => '{{ notification_title }} - {{ ticket_reference }}',
                'html_body' => $this->ticketHtml('Apontamento manual pendente'),
                'variables' => $this->ticketVariables(),
            ],
            self::TICKET_TIME_ENTRY_REVIEWED => [
                'label' => 'Apontamento revisado',
                'description' => 'Aviso para o usuario quando um apontamento e aprovado ou rejeitado.',
                'subject' => '{{ notification_title }} - {{ ticket_reference }}',
                'html_body' => $this->ticketHtml('Apontamento manual revisado'),
                'variables' => [
                    ...$this->ticketVariables(),
                    'time_entry_status' => 'Resultado da revisao',
                ],
            ],
            self::TICKET_ACTIVITY => [
                'label' => 'Automacao de chamado',
                'description' => 'Notificacao customizada disparada por automacao.',
                'subject' => '{{ notification_title }} - {{ ticket_reference }}',
                'html_body' => $this->ticketHtml('Atualizacao automatica'),
                'variables' => $this->ticketVariables(),
            ],
        ];
    }

    public function definition(string $type): ?array
    {
        return $this->definitions()[$type] ?? null;
    }

    public function types(): array
    {
        return array_keys($this->definitions());
    }

    public function variablesFor(string $type): array
    {
        $definition = $this->definition($type);

        if (! $definition) {
            return [];
        }

        return [
            ...$this->commonVariables(),
            ...$definition['variables'],
        ];
    }

    public function sampleContext(string $type, ?User $user = null): array
    {
        $recipientName = $user?->name ?: 'Joao Silva';
        $recipientEmail = $user?->email ?: 'joao.silva@example.com';
        $base = [
            'app_name' => config('app.name', 'AIA Service'),
            'recipient_name' => $recipientName,
            'recipient_email' => $recipientEmail,
            'action_url' => url('/tickets/123'),
            'action_label' => 'Abrir chamado',
            'notification_title' => 'Atualizacao de chamado',
            'notification_message' => 'Existe uma nova atualizacao aguardando sua leitura.',
            'ticket_reference' => 'AIA-2026-000123',
            'ticket_title' => 'Notebook sem acesso a rede',
            'ticket_priority' => 'Alta',
            'ticket_status' => 'Em atendimento',
            'ticket_sector' => 'Suporte',
            'requester_name' => 'Maria Oliveira',
            'assignee_name' => 'Carlos Pereira',
            'login_email' => $recipientEmail,
            'login_url' => route('login'),
            'password_note' => 'Use a senha temporaria informada pelo administrador e altere no primeiro acesso.',
            'temporary_password' => 'Anadem@2026!',
            'time_entry_status' => 'aprovado',
        ];

        if (in_array($type, [self::ACCOUNT_CREATED, self::USER_DEFAULT_PASSWORD_RESET], true)) {
            return [
                ...$base,
                'action_url' => route('login'),
                'action_label' => 'Acessar sistema',
                'notification_title' => $type === self::ACCOUNT_CREATED ? 'Sua conta foi criada' : 'Sua senha foi redefinida',
                'notification_message' => $type === self::ACCOUNT_CREATED ? 'Seu acesso ja esta disponivel.' : 'Use a senha temporaria e troque-a no proximo acesso.',
            ];
        }

        return $base;
    }

    private function commonVariables(): array
    {
        return [
            'app_name' => 'Nome da aplicacao',
            'recipient_name' => 'Nome do destinatario',
            'recipient_email' => 'E-mail do destinatario',
            'action_url' => 'Link principal',
            'action_label' => 'Texto do botao principal',
            'notification_title' => 'Titulo da notificacao',
            'notification_message' => 'Mensagem da notificacao',
        ];
    }

    private function ticketVariables(): array
    {
        return [
            'ticket_reference' => 'Referencia publica do chamado',
            'ticket_title' => 'Titulo do chamado',
            'ticket_priority' => 'Prioridade do chamado',
            'ticket_status' => 'Status ou etapa atual',
            'ticket_sector' => 'Setor do chamado',
            'requester_name' => 'Solicitante',
            'assignee_name' => 'Responsavel',
        ];
    }

    private function ticketHtml(string $heading): string
    {
        return $this->defaultHtml(
            $heading,
            '{{ ticket_reference }} - {{ ticket_title }}',
            '{{ notification_message }}',
            '{{ action_label }}',
            <<<'HTML'
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="margin-top:20px;border-collapse:collapse;border:1px solid #dbe3ef;border-radius:14px;overflow:hidden;">
    <tr>
        <td style="padding:12px 14px;background:#f8fafc;color:#475569;font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.08em;">Setor</td>
        <td style="padding:12px 14px;color:#0f172a;font-size:14px;">{{ ticket_sector }}</td>
    </tr>
    <tr>
        <td style="padding:12px 14px;background:#f8fafc;color:#475569;font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.08em;">Prioridade</td>
        <td style="padding:12px 14px;color:#0f172a;font-size:14px;">{{ ticket_priority }}</td>
    </tr>
    <tr>
        <td style="padding:12px 14px;background:#f8fafc;color:#475569;font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.08em;">Status</td>
        <td style="padding:12px 14px;color:#0f172a;font-size:14px;">{{ ticket_status }}</td>
    </tr>
    <tr>
        <td style="padding:12px 14px;background:#f8fafc;color:#475569;font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.08em;">Solicitante</td>
        <td style="padding:12px 14px;color:#0f172a;font-size:14px;">{{ requester_name }}</td>
    </tr>
    <tr>
        <td style="padding:12px 14px;background:#f8fafc;color:#475569;font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.08em;">Responsavel</td>
        <td style="padding:12px 14px;color:#0f172a;font-size:14px;">{{ assignee_name }}</td>
    </tr>
</table>
HTML
        );
    }

    private function defaultHtml(string $heading, string $lead, string $body, string $buttonLabel, string $extra = ''): string
    {
        return <<<HTML
<div style="font-family:Arial,Helvetica,sans-serif;color:#0f172a;line-height:1.55;">
    <p style="margin:0 0 8px;color:#2563eb;font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.12em;">{{ app_name }}</p>
    <h1 style="margin:0;color:#0f172a;font-size:24px;line-height:1.25;">{$heading}</h1>
    <p style="margin:16px 0 0;color:#334155;font-size:16px;">{$lead}</p>
    <p style="margin:16px 0 0;color:#475569;font-size:15px;">{$body}</p>
    {$extra}
    <p style="margin:28px 0 0;">
        <a href="{{ action_url }}" style="display:inline-block;border-radius:12px;background:#1d4ed8;color:#ffffff;font-size:14px;font-weight:700;text-decoration:none;padding:12px 18px;">{$buttonLabel}</a>
    </p>
</div>
HTML;
    }
}

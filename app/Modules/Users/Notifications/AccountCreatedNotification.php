<?php

namespace App\Modules\Users\Notifications;

use App\Modules\Emails\Services\EmailTemplateService;
use App\Modules\Emails\Support\EmailTemplateCatalog;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AccountCreatedNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly string $email,
        private readonly bool $mustChangePassword,
        private readonly ?string $accessUrl = null,
    ) {}

    public function via(object $notifiable): array
    {
        return app(EmailTemplateService::class)->channelsFor(EmailTemplateCatalog::ACCOUNT_CREATED);
    }

    public function toMail(object $notifiable): MailMessage
    {
        $accessUrl = $this->accessUrl ?: route('login');
        $passwordNote = $this->mustChangePassword
            ? 'Use a senha temporaria informada pelo administrador e troque-a no primeiro acesso.'
            : 'Sua conta esta pronta para uso.';

        return app(EmailTemplateService::class)->mailMessage(
            EmailTemplateCatalog::ACCOUNT_CREATED,
            $notifiable,
            [
                'action_url' => $accessUrl,
                'action_label' => 'Acessar sistema',
                'notification_title' => 'Sua conta foi criada',
                'notification_message' => 'Sua conta no sistema foi criada por um administrador.',
                'login_email' => $this->email,
                'login_url' => $accessUrl,
                'password_note' => $passwordNote,
            ],
        );
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Sua conta foi criada',
            'message' => $this->mustChangePassword
                ? 'Sua conta esta pronta e exige troca de senha no primeiro acesso.'
                : 'Sua conta esta pronta para uso.',
            'url' => $this->mustChangePassword
                ? route('password.force-change', absolute: false)
                : route('dashboard', absolute: false),
            'email' => $this->email,
            'must_change_password' => $this->mustChangePassword,
        ];
    }
}

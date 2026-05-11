<?php

namespace App\Modules\Users\Notifications;

use App\Modules\Emails\Services\EmailTemplateService;
use App\Modules\Emails\Support\EmailTemplateCatalog;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class DefaultPasswordResetNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly string $email,
        private readonly string $temporaryPassword,
        private readonly ?string $accessUrl = null,
    ) {}

    public function via(object $notifiable): array
    {
        return app(EmailTemplateService::class)->channelsFor(EmailTemplateCatalog::USER_DEFAULT_PASSWORD_RESET);
    }

    public function toMail(object $notifiable): MailMessage
    {
        $accessUrl = $this->accessUrl ?: route('login');

        return app(EmailTemplateService::class)->mailMessage(
            EmailTemplateCatalog::USER_DEFAULT_PASSWORD_RESET,
            $notifiable,
            [
                'action_url' => $accessUrl,
                'action_label' => 'Acessar sistema',
                'notification_title' => 'Sua senha foi redefinida',
                'notification_message' => 'Sua senha foi redefinida por um administrador.',
                'login_email' => $this->email,
                'login_url' => $accessUrl,
                'temporary_password' => $this->temporaryPassword,
            ],
        );
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Sua senha foi redefinida',
            'message' => 'Use a senha temporaria enviada por e-mail e troque-a no proximo acesso.',
            'url' => route('password.force-change', absolute: false),
            'email' => $this->email,
            'must_change_password' => true,
        ];
    }

    public function temporaryPassword(): string
    {
        return $this->temporaryPassword;
    }
}

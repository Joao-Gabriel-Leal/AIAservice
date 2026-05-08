<?php

namespace App\Modules\Users\Notifications;

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
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $accessUrl = $this->accessUrl ?: route('login');

        $mail = (new MailMessage)
            ->subject('Sua conta foi criada')
            ->greeting("Ola, {$notifiable->name}!")
            ->line('Sua conta no sistema foi criada por um administrador.')
            ->line("Login de acesso: {$this->email}")
            ->line("URL de acesso: {$accessUrl}");

        if ($this->mustChangePassword) {
            $mail->line('Use a senha temporaria informada pelo administrador e troque-a no primeiro acesso.');
        } else {
            $mail->line('Sua conta esta pronta para uso.');
        }

        return $mail
            ->action('Acessar sistema', $accessUrl)
            ->line('Se voce nao esperava este acesso, fale com o administrador responsavel.');
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

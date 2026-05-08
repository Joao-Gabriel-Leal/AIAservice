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
        private readonly ?string $setPasswordUrl = null,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject('Sua conta foi criada')
            ->greeting("Ola, {$notifiable->name}!")
            ->line('Sua conta no sistema foi criada por um administrador.')
            ->line("Login de acesso: {$this->email}");

        if ($this->mustChangePassword) {
            $mail->line('Defina sua senha pelo link abaixo antes de acessar o sistema.');
        }

        if ($this->setPasswordUrl) {
            return $mail
                ->action('Definir senha', $this->setPasswordUrl)
                ->line('Se o link expirar, use a opcao de esqueci minha senha na tela de login.');
        }

        return $mail
            ->action('Acessar sistema', route('login'))
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

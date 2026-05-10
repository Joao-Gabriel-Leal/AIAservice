<?php

namespace App\Modules\Emails\Services;

use App\Models\User;
use App\Modules\Emails\Models\EmailTemplate;
use App\Modules\Emails\Support\EmailTemplateCatalog;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class EmailTemplateService
{
    private const PLACEHOLDER_PATTERN = '/\{\{\s*([^}]+?)\s*\}\}/';

    public function __construct(
        private readonly EmailTemplateCatalog $catalog,
    ) {}

    public function templates(): Collection
    {
        $records = EmailTemplate::query()
            ->with('updatedBy')
            ->get()
            ->keyBy('type');

        return collect($this->catalog->definitions())
            ->map(function (array $definition, string $type) use ($records): array {
                $record = $records->get($type);

                return [
                    'type' => $type,
                    'label' => $definition['label'],
                    'description' => $definition['description'],
                    'subject' => $record?->subject ?? $definition['subject'],
                    'html_body' => $record?->html_body ?? $definition['html_body'],
                    'is_enabled' => $record?->is_enabled ?? true,
                    'variables' => $this->catalog->variablesFor($type),
                    'has_override' => $record !== null,
                    'updated_at' => $record?->updated_at,
                    'updated_by' => $record?->updatedBy,
                ];
            })
            ->values();
    }

    public function template(string $type): ?array
    {
        return $this->templates()->firstWhere('type', $type);
    }

    public function exists(string $type): bool
    {
        return $this->catalog->definition($type) !== null;
    }

    public function save(string $type, string $subject, string $htmlBody, bool $isEnabled, User $user): EmailTemplate
    {
        $this->validateTemplate($type, $subject, $htmlBody);

        return EmailTemplate::query()->updateOrCreate(
            ['type' => $type],
            [
                'subject' => $subject,
                'html_body' => $htmlBody,
                'is_enabled' => $isEnabled,
                'updated_by' => $user->id,
            ],
        );
    }

    public function restore(string $type): void
    {
        EmailTemplate::query()
            ->where('type', $type)
            ->delete();
    }

    public function channelsFor(string $type): array
    {
        return $this->isEnabled($type)
            ? ['database', 'mail']
            : ['database'];
    }

    public function isEnabled(string $type): bool
    {
        $definition = $this->catalog->definition($type);

        if (! $definition) {
            return true;
        }

        return EmailTemplate::query()
            ->where('type', $type)
            ->value('is_enabled') ?? true;
    }

    public function mailMessage(string $type, object $notifiable, array $context = []): MailMessage
    {
        $rendered = $this->render($type, $notifiable, $context);

        return (new MailMessage)
            ->subject($rendered['subject'])
            ->view('emails.operational-template', [
                'html' => $rendered['html'],
                'subject' => $rendered['subject'],
            ]);
    }

    public function renderPreview(string $type, string $subject, string $htmlBody, ?User $user = null): array
    {
        $this->validateTemplate($type, $subject, $htmlBody);

        return $this->render($type, $user ?? new User, $this->catalog->sampleContext($type, $user), [
            'subject' => $subject,
            'html_body' => $htmlBody,
        ]);
    }

    public function render(string $type, object $notifiable, array $context = [], ?array $override = null): array
    {
        $definition = $this->catalog->definition($type);

        if (! $definition) {
            Log::warning('Unknown email template type requested.', ['type' => $type]);

            $definition = [
                'subject' => '{{ notification_title }}',
                'html_body' => '<p>{{ notification_message }}</p>',
            ];
        }

        $record = $override === null
            ? EmailTemplate::query()->where('type', $type)->first()
            : null;

        $subject = (string) ($override['subject'] ?? $record?->subject ?? $definition['subject']);
        $htmlBody = (string) ($override['html_body'] ?? $record?->html_body ?? $definition['html_body']);
        $data = $this->normalizeContext($type, $notifiable, $context);

        return [
            'type' => $type,
            'subject' => $this->renderSubject($subject, $data),
            'html' => $this->renderHtml($htmlBody, $data),
        ];
    }

    public function validateTemplate(string $type, string $subject, string $htmlBody): void
    {
        $definition = $this->catalog->definition($type);

        if (! $definition) {
            throw ValidationException::withMessages([
                'type' => 'Tipo de e-mail invalido.',
            ]);
        }

        $securityError = $this->securityErrorFor($htmlBody);

        if ($securityError !== null) {
            throw ValidationException::withMessages([
                'html_body' => $securityError,
            ]);
        }

        $allowedVariables = array_keys($this->catalog->variablesFor($type));
        $unknownVariables = $this->unknownVariables($subject."\n".$htmlBody, $allowedVariables);

        if ($unknownVariables !== []) {
            throw ValidationException::withMessages([
                'html_body' => 'Variaveis nao permitidas: '.implode(', ', $unknownVariables).'.',
            ]);
        }
    }

    private function normalizeContext(string $type, object $notifiable, array $context): array
    {
        $allowedVariables = array_keys($this->catalog->variablesFor($type));
        $empty = array_fill_keys($allowedVariables, '');
        $base = [
            'app_name' => config('app.name', 'AIA Service'),
            'recipient_name' => data_get($notifiable, 'name', 'usuario'),
            'recipient_email' => data_get($notifiable, 'email', ''),
            'action_url' => url('/'),
            'action_label' => 'Abrir',
            'notification_title' => data_get($context, 'notification_title', 'Atualizacao'),
            'notification_message' => data_get($context, 'notification_message', ''),
        ];

        return [
            ...$empty,
            ...$base,
            ...$context,
        ];
    }

    private function renderHtml(string $template, array $data): string
    {
        return preg_replace_callback(
            self::PLACEHOLDER_PATTERN,
            fn (array $match): string => e((string) ($data[trim($match[1])] ?? '')),
            $template,
        ) ?? '';
    }

    private function renderSubject(string $template, array $data): string
    {
        $subject = preg_replace_callback(
            self::PLACEHOLDER_PATTERN,
            fn (array $match): string => trim(strip_tags((string) ($data[trim($match[1])] ?? ''))),
            $template,
        ) ?? '';

        return trim(preg_replace('/\s+/', ' ', $subject) ?? $subject);
    }

    private function securityErrorFor(string $htmlBody): ?string
    {
        if (preg_match('/<\s*script\b/i', $htmlBody)) {
            return 'O HTML nao pode conter tags script.';
        }

        if (preg_match('/\son[a-z]+\s*=/i', $htmlBody)) {
            return 'O HTML nao pode conter eventos JavaScript.';
        }

        if (preg_match('/javascript\s*:/i', $htmlBody)) {
            return 'O HTML nao pode conter links javascript:.';
        }

        if (preg_match('/<\?(?:php|=)?/i', $htmlBody) || str_contains($htmlBody, '{!!')) {
            return 'O HTML nao pode conter PHP ou Blade executavel.';
        }

        return null;
    }

    private function unknownVariables(string $content, array $allowedVariables): array
    {
        preg_match_all(self::PLACEHOLDER_PATTERN, $content, $matches);

        return collect($matches[1] ?? [])
            ->map(fn (string $variable): string => trim($variable))
            ->filter()
            ->reject(fn (string $variable): bool => preg_match('/^[A-Za-z0-9_]+$/', $variable) === 1)
            ->merge(
                collect($matches[1] ?? [])
                    ->map(fn (string $variable): string => trim($variable))
                    ->filter(fn (string $variable): bool => preg_match('/^[A-Za-z0-9_]+$/', $variable) === 1)
                    ->diff($allowedVariables),
            )
            ->unique()
            ->values()
            ->all();
    }
}

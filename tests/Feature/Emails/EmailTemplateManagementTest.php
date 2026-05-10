<?php

namespace Tests\Feature\Emails;

use App\Models\User;
use App\Modules\Emails\Mail\OperationalTemplateTestMail;
use App\Modules\Emails\Models\EmailTemplate;
use App\Modules\Emails\Services\EmailTemplateService;
use App\Modules\Emails\Support\EmailTemplateCatalog;
use App\Modules\Users\Notifications\AccountCreatedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class EmailTemplateManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_global_admin_can_view_email_templates_and_collaborator_cannot(): void
    {
        $admin = User::factory()->developer()->create();
        $collaborator = User::factory()->create();

        $response = $this->actingAs($admin)->get(route('admin.emails.index'));

        $response->assertOk();

        foreach (app(EmailTemplateCatalog::class)->definitions() as $definition) {
            $response->assertSeeText($definition['label']);
        }

        $this->actingAs($collaborator)
            ->get(route('admin.emails.index'))
            ->assertForbidden();
    }

    public function test_email_template_screen_renders_only_the_active_editor(): void
    {
        $admin = User::factory()->developer()->create();

        $response = $this->actingAs($admin)->get(route('admin.emails.index'));

        $response
            ->assertOk()
            ->assertSee('Tipos de envio')
            ->assertSee('Editar HTML avancado')
            ->assertSee('data-active-email-type="'.EmailTemplateCatalog::ACCOUNT_CREATED.'"', false)
            ->assertSee('data-preview-url="'.route('admin.emails.preview', EmailTemplateCatalog::ACCOUNT_CREATED).'"', false)
            ->assertDontSee('data-preview-url="'.route('admin.emails.preview', EmailTemplateCatalog::TICKET_CREATED).'"', false);

        $this->assertSame(1, substr_count($response->getContent(), '<textarea name="html_body"'));
    }

    public function test_email_template_screen_selects_type_from_query_string(): void
    {
        $admin = User::factory()->developer()->create();

        $response = $this->actingAs($admin)
            ->get(route('admin.emails.index', ['type' => EmailTemplateCatalog::TICKET_CREATED]));

        $response
            ->assertOk()
            ->assertSee('data-active-email-type="'.EmailTemplateCatalog::TICKET_CREATED.'"', false)
            ->assertSee('action="'.route('admin.emails.update', EmailTemplateCatalog::TICKET_CREATED).'"', false)
            ->assertSee('<option value="'.EmailTemplateCatalog::TICKET_CREATED.'" selected>', false);

        $this->assertSame(1, substr_count($response->getContent(), '<textarea name="html_body"'));
    }

    public function test_templates_start_enabled_without_database_overrides(): void
    {
        $this->assertDatabaseCount('email_templates', 0);

        $templates = app(EmailTemplateService::class)->templates();

        $this->assertCount(10, $templates);
        $this->assertTrue($templates->every(fn (array $template): bool => $template['is_enabled'] === true));
        $this->assertTrue($templates->every(fn (array $template): bool => $template['has_override'] === false));
    }

    public function test_admin_can_save_custom_subject_and_html_and_render_it(): void
    {
        $admin = User::factory()->developer()->create();
        $recipient = User::factory()->create([
            'name' => 'Maria Operadora',
            'email' => 'maria@example.com',
        ]);

        $this->actingAs($admin)
            ->patch(route('admin.emails.update', EmailTemplateCatalog::ACCOUNT_CREATED), [
                'subject' => 'Bem-vindo {{ recipient_name }}',
                'html_body' => '<p>Login: {{ login_email }}</p><p>{{ password_note }}</p>',
                'is_enabled' => '1',
            ])
            ->assertRedirect(route('admin.emails.index', ['type' => EmailTemplateCatalog::ACCOUNT_CREATED]));

        $rendered = app(EmailTemplateService::class)->render(
            EmailTemplateCatalog::ACCOUNT_CREATED,
            $recipient,
            [
                'login_email' => $recipient->email,
                'password_note' => 'Troque a senha no primeiro acesso.',
            ],
        );

        $this->assertSame('Bem-vindo Maria Operadora', $rendered['subject']);
        $this->assertStringContainsString('Login: maria@example.com', $rendered['html']);
        $this->assertStringContainsString('Troque a senha no primeiro acesso.', $rendered['html']);
    }

    public function test_admin_can_restore_template_and_return_to_selected_type(): void
    {
        $admin = User::factory()->developer()->create();

        EmailTemplate::query()->create([
            'type' => EmailTemplateCatalog::TICKET_CREATED,
            'subject' => 'Custom',
            'html_body' => '<p>{{ ticket_reference }}</p>',
            'is_enabled' => true,
            'updated_by' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->delete(route('admin.emails.restore', EmailTemplateCatalog::TICKET_CREATED))
            ->assertRedirect(route('admin.emails.index', ['type' => EmailTemplateCatalog::TICKET_CREATED]));

        $this->assertDatabaseMissing('email_templates', [
            'type' => EmailTemplateCatalog::TICKET_CREATED,
        ]);
    }

    public function test_disabling_email_type_keeps_database_notification_only(): void
    {
        $admin = User::factory()->developer()->create();
        $recipient = User::factory()->create();

        $this->actingAs($admin)
            ->patch(route('admin.emails.update', EmailTemplateCatalog::ACCOUNT_CREATED), [
                'subject' => 'Conta criada',
                'html_body' => '<p>{{ recipient_name }}</p>',
            ])
            ->assertRedirect();

        $notification = new AccountCreatedNotification($recipient->email, true);

        $this->assertSame(['database'], $notification->via($recipient));

        $recipient->notify($notification);

        $this->assertDatabaseHas('notifications', [
            'notifiable_type' => User::class,
            'notifiable_id' => $recipient->id,
            'type' => AccountCreatedNotification::class,
        ]);
    }

    public function test_preview_replaces_variables_and_rejects_unsafe_html(): void
    {
        $admin = User::factory()->developer()->create([
            'name' => 'Dev Admin',
        ]);

        $this->actingAs($admin)
            ->postJson(route('admin.emails.preview', EmailTemplateCatalog::TICKET_CREATED), [
                'subject' => 'Chamado {{ ticket_reference }}',
                'html_body' => '<p>Ola {{ recipient_name }}</p><a href="{{ action_url }}">Abrir</a>',
            ])
            ->assertOk()
            ->assertJsonPath('subject', 'Chamado AIA-2026-000123')
            ->assertJsonFragment([
                'type' => EmailTemplateCatalog::TICKET_CREATED,
            ])
            ->assertSee('Ola Dev Admin', false);

        $this->actingAs($admin)
            ->postJson(route('admin.emails.preview', EmailTemplateCatalog::TICKET_CREATED), [
                'subject' => 'Inseguro',
                'html_body' => '<script>alert(1)</script>',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('html_body');
    }

    public function test_admin_can_send_template_test_with_current_mailer(): void
    {
        Mail::fake();

        $admin = User::factory()->developer()->create();

        $this->actingAs($admin)
            ->postJson(route('admin.emails.test', EmailTemplateCatalog::ACCOUNT_CREATED), [
                'subject' => 'Teste {{ recipient_name }}',
                'html_body' => '<p>{{ login_email }}</p>',
            ])
            ->assertOk();

        Mail::assertSent(OperationalTemplateTestMail::class, function (OperationalTemplateTestMail $mail) use ($admin): bool {
            return $mail->hasTo($admin->email);
        });
    }

    protected function tearDown(): void
    {
        EmailTemplate::query()->delete();

        parent::tearDown();
    }
}

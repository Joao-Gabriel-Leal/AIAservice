<?php

namespace Tests\Feature\Management;

use App\Enums\GlobalUserRole;
use App\Enums\KnowledgeBaseArticleStatus;
use App\Enums\KnowledgeBaseVisibility;
use App\Enums\UserRole;
use App\Models\User;
use App\Modules\Companies\Models\Company;
use App\Modules\KnowledgeBase\Models\KnowledgeBaseArticle;
use App\Modules\Sectors\Models\Sector;
use App\Modules\Shared\Models\ActivityLog;
use App\Modules\Users\Http\Controllers\UserController;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class GlobalAuditLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_global_logs_page_is_restricted_and_available_in_admin_navigation(): void
    {
        $context = $this->auditContext();

        $this->actingAs($context['admin'])
            ->get(route('users.index'))
            ->assertOk()
            ->assertSee(route('admin.logs.index', absolute: false), false)
            ->assertSeeText('Logs');

        $this->actingAs($context['collaborator'])
            ->get(route('admin.logs.index'))
            ->assertForbidden();
    }

    public function test_user_creation_and_password_reset_are_audited_with_field_changes(): void
    {
        Notification::fake();

        $context = $this->auditContext();

        $this->actingAs($context['admin'])
            ->post(route('users.store'), [
                'name' => 'Pessoa Auditada',
                'email' => 'auditada@example.com',
                'global_role' => GlobalUserRole::COLLABORATOR->value,
                'sector_accesses' => [
                    $context['sector']->id => 'technician',
                ],
                'must_change_password' => '1',
                'is_active' => '1',
            ])
            ->assertRedirect(route('users.index', absolute: false));

        $createdUser = User::query()->where('email', 'auditada@example.com')->firstOrFail();

        $this->assertDatabaseHas('activity_logs', [
            'subject_type' => User::class,
            'subject_id' => $createdUser->id,
            'causer_id' => $context['admin']->id,
            'event' => 'user.created',
        ]);

        $this->actingAs($context['admin'])
            ->post(route('users.reset-default-password', $createdUser))
            ->assertRedirect(route('users.show', $createdUser, absolute: false));

        $passwordLog = ActivityLog::query()
            ->where('subject_type', User::class)
            ->where('subject_id', $createdUser->id)
            ->where('event', 'user.password.reset_by_admin')
            ->firstOrFail();

        $this->assertSame('[protegido]', data_get($passwordLog->properties, 'changes.password.before'));
        $this->assertSame('[alterado]', data_get($passwordLog->properties, 'changes.password.after'));
        $this->assertTrue(Hash::check(UserController::DEFAULT_PASSWORD, $createdUser->fresh()->password));

        $this->actingAs($context['admin'])
            ->get(route('admin.logs.index', ['search' => 'auditada@example.com']))
            ->assertOk()
            ->assertSeeText('Usuario criado')
            ->assertSeeText('Senha redefinida por administrador')
            ->assertSeeText('Pessoa Auditada');
    }

    public function test_deleted_knowledge_base_article_is_audited_with_previous_snapshot(): void
    {
        $context = $this->auditContext();
        $article = KnowledgeBaseArticle::query()->create([
            'sector_id' => $context['sector']->id,
            'created_by' => $context['admin']->id,
            'title' => 'Procedimento sensivel',
            'summary' => 'Resumo do procedimento',
            'content' => 'Conteudo operacional',
            'visibility' => KnowledgeBaseVisibility::PRIVATE->value,
            'editorial_status' => KnowledgeBaseArticleStatus::PUBLISHED->value,
            'is_active' => true,
        ]);

        $this->actingAs($context['admin'])
            ->delete(route('knowledge-base.destroy', $article))
            ->assertRedirect(route('knowledge-base.manage', absolute: false));

        $log = ActivityLog::query()
            ->where('subject_type', KnowledgeBaseArticle::class)
            ->where('subject_id', $article->id)
            ->where('event', 'knowledge_base.article.deleted')
            ->firstOrFail();

        $this->assertSame('Procedimento sensivel', data_get($log->properties, 'before.title'));
        $this->assertSoftDeleted('knowledge_base_articles', ['id' => $article->id]);

        $this->actingAs($context['admin'])
            ->get(route('admin.logs.index', ['event' => 'knowledge_base.article.deleted']))
            ->assertOk()
            ->assertSeeText('Artigo removido')
            ->assertSeeText('Procedimento sensivel')
            ->assertSeeText('Titulo');
    }

    private function auditContext(): array
    {
        $company = Company::query()->create([
            'name' => 'Empresa Auditoria',
            'is_active' => true,
        ]);

        $sector = Sector::query()->create([
            'company_id' => $company->id,
            'name' => 'Tecnologia Auditoria',
            'slug' => 'tecnologia-auditoria',
            'is_active' => true,
        ]);

        $admin = User::factory()->developer()->create();
        $collaborator = User::factory()->create([
            'sector_id' => $sector->id,
            'role' => UserRole::REQUESTER,
        ]);

        return compact('company', 'sector', 'admin', 'collaborator');
    }
}

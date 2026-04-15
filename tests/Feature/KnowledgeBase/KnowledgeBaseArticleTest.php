<?php

namespace Tests\Feature\KnowledgeBase;

use App\Enums\GlobalUserRole;
use App\Enums\KnowledgeBaseVisibility;
use App\Enums\UserRole;
use App\Models\User;
use App\Modules\Companies\Models\Company;
use App\Modules\KnowledgeBase\Models\KnowledgeBaseArticle;
use App\Modules\KnowledgeBase\Models\KnowledgeBaseAttachment;
use App\Modules\Sectors\Models\Sector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class KnowledgeBaseArticleTest extends TestCase
{
    use RefreshDatabase;

    public function test_sector_admin_can_create_article_only_for_managed_sector(): void
    {
        [$sectorA, $sectorB] = $this->seedSectors();
        $sectorAdmin = User::factory()->create([
            'global_role' => GlobalUserRole::COLLABORATOR,
            'role' => UserRole::SECTOR_ADMIN,
            'sector_id' => $sectorA->id,
        ]);

        $this->actingAs($sectorAdmin);

        $this->post(route('knowledge-base.store'), [
            'sector_id' => $sectorA->id,
            'title' => 'Guia de acesso',
            'summary' => 'Resumo do guia',
            'content' => 'Conteudo completo',
            'visibility' => KnowledgeBaseVisibility::PRIVATE->value,
            'is_active' => '1',
        ])->assertRedirect(route('knowledge-base.manage', absolute: false));

        $this->assertDatabaseHas('knowledge_base_articles', [
            'sector_id' => $sectorA->id,
            'created_by' => $sectorAdmin->id,
            'title' => 'Guia de acesso',
            'visibility' => KnowledgeBaseVisibility::PRIVATE->value,
        ]);

        $this->post(route('knowledge-base.store'), [
            'sector_id' => $sectorB->id,
            'title' => 'Guia proibido',
            'summary' => 'Resumo',
            'content' => 'Conteudo',
            'visibility' => KnowledgeBaseVisibility::PUBLIC->value,
            'is_active' => '1',
        ])->assertSessionHasErrors('sector_id');
    }

    public function test_sector_admin_cannot_edit_article_from_other_sector(): void
    {
        [$sectorA, $sectorB] = $this->seedSectors();
        $sectorAdmin = User::factory()->create([
            'global_role' => GlobalUserRole::COLLABORATOR,
            'role' => UserRole::SECTOR_ADMIN,
            'sector_id' => $sectorA->id,
        ]);

        $foreignArticle = KnowledgeBaseArticle::query()->create([
            'sector_id' => $sectorB->id,
            'created_by' => User::factory()->superAdmin()->create()->id,
            'title' => 'Artigo setor B',
            'summary' => 'Resumo setor B',
            'content' => 'Conteudo setor B',
            'visibility' => KnowledgeBaseVisibility::PRIVATE,
            'is_active' => true,
        ]);

        $this->actingAs($sectorAdmin);

        $this->get(route('knowledge-base.edit', $foreignArticle))->assertForbidden();
        $this->put(route('knowledge-base.update', $foreignArticle), [
            'sector_id' => $sectorB->id,
            'title' => 'Titulo alterado',
            'summary' => 'Resumo alterado',
            'content' => 'Conteudo alterado',
            'visibility' => KnowledgeBaseVisibility::PRIVATE->value,
            'is_active' => '1',
        ])->assertForbidden();
    }

    public function test_technician_can_view_private_article_only_from_operational_sector(): void
    {
        [$sectorA, $sectorB] = $this->seedSectors();
        $technician = User::factory()->create([
            'global_role' => GlobalUserRole::COLLABORATOR,
            'role' => UserRole::TECHNICIAN,
            'sector_id' => $sectorA->id,
        ]);

        $visibleArticle = KnowledgeBaseArticle::query()->create([
            'sector_id' => $sectorA->id,
            'created_by' => User::factory()->superAdmin()->create()->id,
            'title' => 'Runbook interno A',
            'summary' => 'Resumo A',
            'content' => 'Conteudo privado A',
            'visibility' => KnowledgeBaseVisibility::PRIVATE,
            'is_active' => true,
        ]);

        $hiddenArticle = KnowledgeBaseArticle::query()->create([
            'sector_id' => $sectorB->id,
            'created_by' => User::factory()->superAdmin()->create()->id,
            'title' => 'Runbook interno B',
            'summary' => 'Resumo B',
            'content' => 'Conteudo privado B',
            'visibility' => KnowledgeBaseVisibility::PRIVATE,
            'is_active' => true,
        ]);

        $this->actingAs($technician);

        $this->get(route('knowledge-base.index'))
            ->assertOk()
            ->assertSee('Runbook interno A')
            ->assertDontSee('Runbook interno B');

        $this->get(route('knowledge-base.show', $visibleArticle))->assertOk();
        $this->get(route('knowledge-base.show', $hiddenArticle))->assertForbidden();
    }

    public function test_requester_can_view_public_articles_from_any_sector_but_not_private_ones(): void
    {
        [$sectorA, $sectorB] = $this->seedSectors();
        $requester = User::factory()->create([
            'global_role' => GlobalUserRole::COLLABORATOR,
            'role' => UserRole::REQUESTER,
            'sector_id' => $sectorA->id,
        ]);

        $publicArticle = KnowledgeBaseArticle::query()->create([
            'sector_id' => $sectorB->id,
            'created_by' => User::factory()->superAdmin()->create()->id,
            'title' => 'Perguntas frequentes',
            'summary' => 'Resumo publico',
            'content' => 'Conteudo publico',
            'visibility' => KnowledgeBaseVisibility::PUBLIC,
            'is_active' => true,
        ]);

        $privateArticle = KnowledgeBaseArticle::query()->create([
            'sector_id' => $sectorB->id,
            'created_by' => User::factory()->superAdmin()->create()->id,
            'title' => 'Manual interno',
            'summary' => 'Resumo privado',
            'content' => 'Conteudo privado',
            'visibility' => KnowledgeBaseVisibility::PRIVATE,
            'is_active' => true,
        ]);

        $this->actingAs($requester);

        $this->get(route('knowledge-base.index'))
            ->assertOk()
            ->assertSee('Perguntas frequentes')
            ->assertDontSee('Manual interno');

        $this->get(route('knowledge-base.show', $publicArticle))->assertOk();
        $this->get(route('knowledge-base.show', $privateArticle))->assertForbidden();
    }

    public function test_search_returns_matches_from_title_summary_and_content(): void
    {
        [$sectorA] = $this->seedSectors();
        $user = User::factory()->create([
            'global_role' => GlobalUserRole::COLLABORATOR,
            'role' => UserRole::REQUESTER,
            'sector_id' => $sectorA->id,
        ]);

        KnowledgeBaseArticle::query()->create([
            'sector_id' => $sectorA->id,
            'created_by' => User::factory()->superAdmin()->create()->id,
            'title' => 'Reset de senha',
            'summary' => 'Fluxo para redefinir credenciais',
            'content' => 'Abra o portal e escolha esqueci minha senha.',
            'visibility' => KnowledgeBaseVisibility::PUBLIC,
            'is_active' => true,
        ]);

        KnowledgeBaseArticle::query()->create([
            'sector_id' => $sectorA->id,
            'created_by' => User::factory()->superAdmin()->create()->id,
            'title' => 'VPN',
            'summary' => 'Conexao remota corporativa',
            'content' => 'Use o cliente aprovado pela empresa.',
            'visibility' => KnowledgeBaseVisibility::PUBLIC,
            'is_active' => true,
        ]);

        $this->actingAs($user);

        $this->get(route('knowledge-base.index', ['search' => 'senha']))
            ->assertOk()
            ->assertSee('Reset de senha')
            ->assertDontSee('VPN');

        $this->get(route('knowledge-base.index', ['search' => 'credenciais']))
            ->assertOk()
            ->assertSee('Reset de senha')
            ->assertDontSee('VPN');

        $this->get(route('knowledge-base.index', ['search' => 'cliente aprovado']))
            ->assertOk()
            ->assertSee('VPN')
            ->assertDontSee('Reset de senha');
    }

    public function test_inactive_or_deleted_articles_do_not_appear_in_default_query(): void
    {
        [$sectorA] = $this->seedSectors();
        $user = User::factory()->create([
            'global_role' => GlobalUserRole::COLLABORATOR,
            'role' => UserRole::REQUESTER,
            'sector_id' => $sectorA->id,
        ]);

        KnowledgeBaseArticle::query()->create([
            'sector_id' => $sectorA->id,
            'created_by' => User::factory()->superAdmin()->create()->id,
            'title' => 'Artigo ativo',
            'summary' => 'Resumo ativo',
            'content' => 'Conteudo ativo',
            'visibility' => KnowledgeBaseVisibility::PUBLIC,
            'is_active' => true,
        ]);

        $inactive = KnowledgeBaseArticle::query()->create([
            'sector_id' => $sectorA->id,
            'created_by' => User::factory()->superAdmin()->create()->id,
            'title' => 'Artigo inativo',
            'summary' => 'Resumo inativo',
            'content' => 'Conteudo inativo',
            'visibility' => KnowledgeBaseVisibility::PUBLIC,
            'is_active' => false,
        ]);

        $deleted = KnowledgeBaseArticle::query()->create([
            'sector_id' => $sectorA->id,
            'created_by' => User::factory()->superAdmin()->create()->id,
            'title' => 'Artigo removido',
            'summary' => 'Resumo removido',
            'content' => 'Conteudo removido',
            'visibility' => KnowledgeBaseVisibility::PUBLIC,
            'is_active' => true,
        ]);

        $deleted->delete();

        $this->actingAs($user);

        $this->get(route('knowledge-base.index'))
            ->assertOk()
            ->assertSee('Artigo ativo')
            ->assertDontSee('Artigo inativo')
            ->assertDontSee('Artigo removido');

        $this->get(route('knowledge-base.show', $inactive))->assertForbidden();
        $this->get(route('knowledge-base.show', $deleted->id))->assertNotFound();
    }

    public function test_sector_admin_can_upload_and_download_article_attachment(): void
    {
        [$sectorA] = $this->seedSectors();
        $sectorAdmin = User::factory()->create([
            'global_role' => GlobalUserRole::COLLABORATOR,
            'role' => UserRole::SECTOR_ADMIN,
            'sector_id' => $sectorA->id,
        ]);

        $this->actingAs($sectorAdmin);

        $file = UploadedFile::fake()->create('manual-vpn.pdf', 120, 'application/pdf');

        $this->post(route('knowledge-base.store'), [
            'sector_id' => $sectorA->id,
            'title' => 'Guia VPN',
            'summary' => 'Resumo VPN',
            'content' => 'Conteudo VPN',
            'visibility' => KnowledgeBaseVisibility::PRIVATE->value,
            'is_active' => '1',
            'attachments' => [$file],
        ])->assertRedirect(route('knowledge-base.manage', absolute: false));

        $article = KnowledgeBaseArticle::query()->with('attachments')->firstOrFail();
        $attachment = $article->attachments->first();

        $this->assertNotNull($attachment);
        $this->assertSame('manual-vpn.pdf', $attachment->original_name);

        $this->get(route('knowledge-base.attachments.show', $attachment))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_sector_admin_can_remove_existing_attachment_on_update(): void
    {
        [$sectorA] = $this->seedSectors();
        $sectorAdmin = User::factory()->create([
            'global_role' => GlobalUserRole::COLLABORATOR,
            'role' => UserRole::SECTOR_ADMIN,
            'sector_id' => $sectorA->id,
        ]);

        $article = KnowledgeBaseArticle::query()->create([
            'sector_id' => $sectorA->id,
            'created_by' => $sectorAdmin->id,
            'title' => 'Base interna',
            'summary' => 'Resumo interno',
            'content' => 'Conteudo interno',
            'visibility' => KnowledgeBaseVisibility::PRIVATE,
            'is_active' => true,
        ]);

        $attachment = new KnowledgeBaseAttachment([
            'uploaded_by_id' => $sectorAdmin->id,
            'original_name' => 'procedimento.txt',
            'mime_type' => 'text/plain',
            'size' => strlen('conteudo'),
        ]);
        $attachment->content = $attachment->encodeContentForStorage('conteudo');
        $article->attachments()->save($attachment);

        $this->actingAs($sectorAdmin);

        $this->put(route('knowledge-base.update', $article), [
            'sector_id' => $sectorA->id,
            'title' => 'Base interna',
            'summary' => 'Resumo interno atualizado',
            'content' => 'Conteudo interno atualizado',
            'visibility' => KnowledgeBaseVisibility::PRIVATE->value,
            'is_active' => '1',
            'remove_attachments' => [$attachment->id],
        ])->assertRedirect(route('knowledge-base.manage', absolute: false));

        $this->assertSoftDeleted('knowledge_base_attachments', [
            'id' => $attachment->id,
        ]);
    }

    private function seedSectors(): array
    {
        $company = Company::query()->create([
            'name' => 'Empresa Base',
            'is_active' => true,
        ]);

        $sectorA = Sector::query()->create([
            'company_id' => $company->id,
            'name' => 'Tecnologia',
            'slug' => 'tecnologia',
            'is_active' => true,
        ]);

        $sectorB = Sector::query()->create([
            'company_id' => $company->id,
            'name' => 'Financeiro',
            'slug' => 'financeiro',
            'is_active' => true,
        ]);

        return [$sectorA, $sectorB];
    }
}

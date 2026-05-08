<?php

namespace Tests\Feature\KnowledgeBase;

use App\Enums\GlobalUserRole;
use App\Enums\KnowledgeBaseArticleStatus;
use App\Enums\KnowledgeBaseVisibility;
use App\Enums\UserRole;
use App\Models\User;
use App\Modules\Companies\Models\Company;
use App\Modules\KnowledgeBase\Models\KnowledgeBaseArticle;
use App\Modules\KnowledgeBase\Models\KnowledgeBaseAttachment;
use App\Modules\KnowledgeBase\Models\KnowledgeBaseArticleFeedback;
use App\Modules\KnowledgeBase\Models\KnowledgeBaseArticleTicketUsage;
use App\Modules\Sectors\Models\Sector;
use App\Modules\Tickets\Models\Ticket;
use App\Modules\Tickets\Services\SectorProvisioningService;
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

    public function test_technician_can_create_draft_article_from_closed_ticket(): void
    {
        [$sectorA] = $this->seedSectors();
        $technician = User::factory()->create([
            'global_role' => GlobalUserRole::COLLABORATOR,
            'role' => UserRole::TECHNICIAN,
            'sector_id' => $sectorA->id,
        ]);

        $ticket = $this->closedTicketForSector($sectorA, $technician);

        $this->actingAs($technician)
            ->post(route('knowledge-base.from-ticket.store', $ticket), [
                'sector_id' => $sectorA->id,
                'generated_from_ticket_id' => $ticket->id,
                'title' => 'Solucao VPN',
                'summary' => 'Resumo da correcao aplicada',
                'content' => 'Passo a passo da solucao.',
                'visibility' => KnowledgeBaseVisibility::PRIVATE->value,
                'editorial_status' => KnowledgeBaseArticleStatus::DRAFT->value,
            ])
            ->assertRedirect(route('tickets.show', $ticket, absolute: false));

        $article = KnowledgeBaseArticle::query()->firstOrFail();

        $this->assertSame($ticket->id, $article->generated_from_ticket_id);
        $this->assertSame($sectorA->id, $article->sector_id);
        $this->assertSame($technician->id, $article->created_by);
        $this->assertSame(KnowledgeBaseArticleStatus::DRAFT, $article->editorial_status);
        $this->assertFalse($article->is_active);
        $this->assertDatabaseHas('knowledge_base_article_ticket_usages', [
            'knowledge_base_article_id' => $article->id,
            'ticket_id' => $ticket->id,
            'used_by_id' => $technician->id,
        ]);
    }

    public function test_sector_admin_can_publish_draft_created_from_ticket(): void
    {
        [$sectorA] = $this->seedSectors();
        $sectorAdmin = User::factory()->create([
            'global_role' => GlobalUserRole::COLLABORATOR,
            'role' => UserRole::SECTOR_ADMIN,
            'sector_id' => $sectorA->id,
        ]);

        $technician = User::factory()->create([
            'global_role' => GlobalUserRole::COLLABORATOR,
            'role' => UserRole::TECHNICIAN,
            'sector_id' => $sectorA->id,
        ]);

        $ticket = $this->closedTicketForSector($sectorA, $technician);

        $article = KnowledgeBaseArticle::query()->create([
            'sector_id' => $sectorA->id,
            'created_by' => $technician->id,
            'generated_from_ticket_id' => $ticket->id,
            'title' => 'Rascunho VPN',
            'summary' => 'Resumo inicial',
            'content' => 'Conteudo inicial',
            'visibility' => KnowledgeBaseVisibility::PRIVATE,
            'editorial_status' => KnowledgeBaseArticleStatus::DRAFT,
            'is_active' => false,
        ]);

        $this->actingAs($sectorAdmin)
            ->put(route('knowledge-base.update', $article), [
                'sector_id' => $sectorA->id,
                'generated_from_ticket_id' => $ticket->id,
                'title' => 'Runbook VPN publicado',
                'summary' => 'Resumo final revisado',
                'content' => 'Conteudo final revisado',
                'visibility' => KnowledgeBaseVisibility::PRIVATE->value,
                'editorial_status' => KnowledgeBaseArticleStatus::PUBLISHED->value,
                'is_active' => '1',
            ])
            ->assertRedirect(route('knowledge-base.manage', absolute: false));

        $article->refresh();

        $this->assertSame(KnowledgeBaseArticleStatus::PUBLISHED, $article->editorial_status);
        $this->assertTrue($article->is_active);
        $this->assertSame('Runbook VPN publicado', $article->title);
    }

    public function test_feedback_is_upserted_per_user_and_article(): void
    {
        [$sectorA] = $this->seedSectors();
        $user = User::factory()->create([
            'global_role' => GlobalUserRole::COLLABORATOR,
            'role' => UserRole::REQUESTER,
            'sector_id' => $sectorA->id,
        ]);

        $article = KnowledgeBaseArticle::query()->create([
            'sector_id' => $sectorA->id,
            'created_by' => User::factory()->superAdmin()->create()->id,
            'title' => 'Guia de acesso remoto',
            'summary' => 'Resumo do guia',
            'content' => 'Conteudo do guia',
            'visibility' => KnowledgeBaseVisibility::PUBLIC,
            'editorial_status' => KnowledgeBaseArticleStatus::PUBLISHED,
            'is_active' => true,
        ]);

        $this->actingAs($user)
            ->post(route('knowledge-base.feedback', $article), ['is_helpful' => '1'])
            ->assertRedirect();

        $this->actingAs($user)
            ->post(route('knowledge-base.feedback', $article), ['is_helpful' => '0'])
            ->assertRedirect();

        $this->assertSame(1, KnowledgeBaseArticleFeedback::query()->count());
        $this->assertDatabaseHas('knowledge_base_article_feedback', [
            'knowledge_base_article_id' => $article->id,
            'user_id' => $user->id,
            'is_helpful' => false,
        ]);
    }

    public function test_knowledge_base_pages_render_pluralized_feedback_and_usage_copy(): void
    {
        [$sectorA] = $this->seedSectors();

        $requester = User::factory()->create([
            'global_role' => GlobalUserRole::COLLABORATOR,
            'role' => UserRole::REQUESTER,
            'sector_id' => $sectorA->id,
        ]);

        $technician = User::factory()->create([
            'global_role' => GlobalUserRole::COLLABORATOR,
            'role' => UserRole::TECHNICIAN,
            'sector_id' => $sectorA->id,
        ]);

        $article = KnowledgeBaseArticle::query()->create([
            'sector_id' => $sectorA->id,
            'created_by' => $technician->id,
            'title' => 'Guia de acessos',
            'summary' => 'Resumo de acessos',
            'content' => 'Conteudo de acessos',
            'visibility' => KnowledgeBaseVisibility::PUBLIC,
            'editorial_status' => KnowledgeBaseArticleStatus::PUBLISHED,
            'is_active' => true,
        ]);

        KnowledgeBaseArticleFeedback::query()->create([
            'knowledge_base_article_id' => $article->id,
            'user_id' => User::factory()->create()->id,
            'is_helpful' => true,
        ]);

        foreach (User::factory()->count(2)->create() as $user) {
            KnowledgeBaseArticleFeedback::query()->create([
                'knowledge_base_article_id' => $article->id,
                'user_id' => $user->id,
                'is_helpful' => false,
            ]);
        }

        $ticketA = $this->closedTicketForSector($sectorA, $technician, 'Chamado A');
        $ticketB = $this->closedTicketForSector($sectorA, $technician, 'Chamado B');

        foreach ([$ticketA, $ticketB] as $ticket) {
            KnowledgeBaseArticleTicketUsage::query()->create([
                'knowledge_base_article_id' => $article->id,
                'ticket_id' => $ticket->id,
                'used_by_id' => $technician->id,
            ]);
        }

        $this->actingAs($requester)
            ->get(route('knowledge-base.show', $article))
            ->assertOk()
            ->assertSeeText('1 voto util')
            ->assertSeeText('2 votos nao uteis')
            ->assertSeeText('2 usos em chamados')
            ->assertDontSeeText('voto(s) util(eis)')
            ->assertDontSeeText('voto(s) nao util(eis)')
            ->assertDontSeeText('uso(s) em chamados');

        $this->actingAs($requester)
            ->get(route('knowledge-base.index'))
            ->assertOk()
            ->assertSeeText('1 voto util')
            ->assertSeeText('2 usos em chamados')
            ->assertDontSeeText('util(eis)')
            ->assertDontSeeText('uso(s)');
    }

    public function test_visible_query_ranks_articles_by_feedback_and_ticket_usage(): void
    {
        [$sectorA] = $this->seedSectors();
        $technician = User::factory()->create([
            'global_role' => GlobalUserRole::COLLABORATOR,
            'role' => UserRole::TECHNICIAN,
            'sector_id' => $sectorA->id,
        ]);

        $topArticle = KnowledgeBaseArticle::query()->create([
            'sector_id' => $sectorA->id,
            'created_by' => User::factory()->superAdmin()->create()->id,
            'title' => 'VPN confiavel',
            'summary' => 'Solucao validada',
            'content' => 'Conteudo A',
            'visibility' => KnowledgeBaseVisibility::PRIVATE,
            'editorial_status' => KnowledgeBaseArticleStatus::PUBLISHED,
            'is_active' => true,
            'updated_at' => now()->subHour(),
        ]);

        $middleArticle = KnowledgeBaseArticle::query()->create([
            'sector_id' => $sectorA->id,
            'created_by' => User::factory()->superAdmin()->create()->id,
            'title' => 'Email alternativo',
            'summary' => 'Solucao secundaria',
            'content' => 'Conteudo B',
            'visibility' => KnowledgeBaseVisibility::PRIVATE,
            'editorial_status' => KnowledgeBaseArticleStatus::PUBLISHED,
            'is_active' => true,
            'updated_at' => now()->subMinutes(30),
        ]);

        $lowArticle = KnowledgeBaseArticle::query()->create([
            'sector_id' => $sectorA->id,
            'created_by' => User::factory()->superAdmin()->create()->id,
            'title' => 'Manual antigo',
            'summary' => 'Pouco util',
            'content' => 'Conteudo C',
            'visibility' => KnowledgeBaseVisibility::PRIVATE,
            'editorial_status' => KnowledgeBaseArticleStatus::PUBLISHED,
            'is_active' => true,
            'updated_at' => now(),
        ]);

        foreach (User::factory()->count(3)->create() as $user) {
            KnowledgeBaseArticleFeedback::query()->create([
                'knowledge_base_article_id' => $topArticle->id,
                'user_id' => $user->id,
                'is_helpful' => true,
            ]);
        }

        foreach (User::factory()->count(2)->create() as $user) {
            KnowledgeBaseArticleFeedback::query()->create([
                'knowledge_base_article_id' => $middleArticle->id,
                'user_id' => $user->id,
                'is_helpful' => true,
            ]);
        }

        KnowledgeBaseArticleFeedback::query()->create([
            'knowledge_base_article_id' => $lowArticle->id,
            'user_id' => User::factory()->create()->id,
            'is_helpful' => false,
        ]);

        $ticketA = $this->closedTicketForSector($sectorA, $technician);
        $ticketB = $this->closedTicketForSector($sectorA, $technician, 'Chamado B');

        KnowledgeBaseArticleTicketUsage::query()->create([
            'knowledge_base_article_id' => $topArticle->id,
            'ticket_id' => $ticketA->id,
            'used_by_id' => $technician->id,
        ]);
        KnowledgeBaseArticleTicketUsage::query()->create([
            'knowledge_base_article_id' => $middleArticle->id,
            'ticket_id' => $ticketB->id,
            'used_by_id' => $technician->id,
        ]);

        $response = $this->actingAs($technician)
            ->get(route('knowledge-base.index'))
            ->assertOk()
            ->assertSeeInOrder([
                'VPN confiavel',
                'Email alternativo',
                'Manual antigo',
            ]);

        $content = $response->getContent();

        $this->assertSame(1, substr_count($content, 'VPN confiavel'));
        $this->assertSame(1, substr_count($content, 'Email alternativo'));
        $this->assertSame(1, substr_count($content, 'Manual antigo'));
        $this->assertSame(2, substr_count($content, 'Mais util'));
    }

    public function test_knowledge_base_index_does_not_highlight_articles_without_helpful_votes_or_usages(): void
    {
        [$sectorA] = $this->seedSectors();
        $technician = User::factory()->create([
            'global_role' => GlobalUserRole::COLLABORATOR,
            'role' => UserRole::TECHNICIAN,
            'sector_id' => $sectorA->id,
        ]);

        KnowledgeBaseArticle::query()->create([
            'sector_id' => $sectorA->id,
            'created_by' => User::factory()->superAdmin()->create()->id,
            'title' => 'Procedimento sem metricas',
            'summary' => 'Ainda sem validacao',
            'content' => 'Conteudo sem metricas',
            'visibility' => KnowledgeBaseVisibility::PRIVATE,
            'editorial_status' => KnowledgeBaseArticleStatus::PUBLISHED,
            'is_active' => true,
        ]);

        $this->actingAs($technician)
            ->get(route('knowledge-base.index'))
            ->assertOk()
            ->assertSeeText('Procedimento sem metricas')
            ->assertDontSeeText('Mais util');
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

    private function closedTicketForSector(Sector $sector, User $requester, string $title = 'Chamado encerrado'): Ticket
    {
        $board = app(SectorProvisioningService::class)->provision($sector);
        $closedGroup = $board->groups()->where('is_closed', true)->firstOrFail();
        $closedStatus = $board->statuses()->where('is_closed', true)->firstOrFail();

        return Ticket::query()->create([
            'sector_id' => $sector->id,
            'ticket_board_id' => $board->id,
            'ticket_group_id' => $closedGroup->id,
            'ticket_status_id' => $closedStatus->id,
            'title' => $title,
            'description' => 'Descricao do chamado encerrado.',
            'requester_id' => $requester->id,
            'assignee_id' => $requester->id,
            'resolved_at' => now(),
            'last_activity_at' => now(),
        ]);
    }
}

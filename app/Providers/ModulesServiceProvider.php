<?php

namespace App\Providers;

use App\Models\User;
use App\Modules\Assets\Models\Asset;
use App\Modules\Assets\Policies\AssetPolicy;
use App\Modules\Companies\Models\Company;
use App\Modules\Companies\Policies\CompanyPolicy;
use App\Modules\KnowledgeBase\Models\KnowledgeBaseArticle;
use App\Modules\KnowledgeBase\Policies\KnowledgeBaseArticlePolicy;
use App\Modules\Licenses\Models\License;
use App\Modules\Licenses\Policies\LicensePolicy;
use App\Modules\Rooms\Models\Room;
use App\Modules\Rooms\Policies\RoomPolicy;
use App\Modules\Sectors\Models\Sector;
use App\Modules\Sectors\Policies\SectorPolicy;
use App\Modules\SectorTemplates\Models\SectorTemplate;
use App\Modules\SectorTemplates\Policies\SectorTemplatePolicy;
use App\Modules\Tickets\Models\Ticket;
use App\Modules\Tickets\Models\TicketBoard;
use App\Modules\Tickets\Models\TicketTimeEntry;
use App\Modules\Tickets\Policies\TicketBoardPolicy;
use App\Modules\Tickets\Policies\TicketPolicy;
use App\Modules\Tickets\Policies\TicketTimeEntryPolicy;
use App\Modules\Users\Policies\UserPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class ModulesServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->registerPolicies();
        $this->registerRoutes();
    }

    private function registerPolicies(): void
    {
        Gate::policy(Asset::class, AssetPolicy::class);
        Gate::policy(Company::class, CompanyPolicy::class);
        Gate::policy(KnowledgeBaseArticle::class, KnowledgeBaseArticlePolicy::class);
        Gate::policy(License::class, LicensePolicy::class);
        Gate::policy(SectorTemplate::class, SectorTemplatePolicy::class);
        Gate::policy(Sector::class, SectorPolicy::class);
        Gate::policy(Room::class, RoomPolicy::class);
        Gate::policy(User::class, UserPolicy::class);
        Gate::policy(Ticket::class, TicketPolicy::class);
        Gate::policy(TicketBoard::class, TicketBoardPolicy::class);
        Gate::policy(TicketTimeEntry::class, TicketTimeEntryPolicy::class);
    }

    private function registerRoutes(): void
    {
        foreach (glob(app_path('Modules/*/routes/web.php')) as $routeFile) {
            Route::middleware('web')->group($routeFile);
        }
    }
}

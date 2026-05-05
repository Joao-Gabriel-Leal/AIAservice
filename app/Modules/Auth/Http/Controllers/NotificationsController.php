<?php

namespace App\Modules\Auth\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Users\Notifications\AccountCreatedNotification;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Notifications\DatabaseNotification;

class NotificationsController extends Controller
{
    public function index(): View
    {
        $filter = $this->resolveFilter(request()->string('filter')->toString());

        $notifications = auth()->user()
            ->notifications()
            ->when($filter === 'unread', fn ($query) => $query->whereNull('read_at'))
            ->when($filter === 'read', fn ($query) => $query->whereNotNull('read_at'))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        $counts = [
            'all' => auth()->user()->notifications()->count(),
            'unread' => auth()->user()->unreadNotifications()->count(),
            'read' => auth()->user()->readNotifications()->count(),
        ];

        return view('modules.notifications.index', compact('notifications', 'filter', 'counts'));
    }

    public function markAsRead(string $notification): RedirectResponse
    {
        $notification = $this->notificationForCurrentUser($notification);

        if (is_null($notification->read_at)) {
            $notification->markAsRead();
        }

        return back()->with('status', 'Notificacao marcada como lida.');
    }

    public function markAllAsRead(): RedirectResponse
    {
        auth()->user()
            ->unreadNotifications
            ->markAsRead();

        return back()->with('status', 'Todas as notificacoes foram marcadas como lidas.');
    }

    public function open(string $notification): RedirectResponse
    {
        $notification = $this->notificationForCurrentUser($notification);

        if (is_null($notification->read_at)) {
            $notification->markAsRead();
        }

        $url = $this->resolveOpenUrl($notification);

        return redirect()->to($url);
    }

    private function resolveOpenUrl(DatabaseNotification $notification): string
    {
        if ($notification->type === AccountCreatedNotification::class) {
            return ($notification->data['must_change_password'] ?? false)
                ? route('profile.edit').'#seguranca'
                : route('dashboard');
        }

        return $notification->data['url'] ?? route('notifications.index');
    }

    private function notificationForCurrentUser(string $notificationId): DatabaseNotification
    {
        return auth()->user()
            ->notifications()
            ->whereKey($notificationId)
            ->firstOrFail();
    }

    private function resolveFilter(string $filter): string
    {
        return in_array($filter, ['all', 'unread', 'read'], true) ? $filter : 'all';
    }
}

<?php

namespace App\Modules\Auth\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Users\Notifications\AccountCreatedNotification;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Str;

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
        $data = is_array($notification->data) ? $notification->data : [];

        if ($notification->type === AccountCreatedNotification::class) {
            return auth()->user()?->must_change_password
                ? route('password.force-change')
                : route('dashboard');
        }

        return $this->normalizeNotificationUrl(data_get($data, 'url'));
    }

    private function normalizeNotificationUrl(mixed $url): string
    {
        $fallback = route('notifications.index');

        if (! is_string($url)) {
            return $fallback;
        }

        $url = trim($url);

        if ($url === '') {
            return $fallback;
        }

        if (Str::startsWith($url, '/') && ! Str::startsWith($url, '//')) {
            return $url;
        }

        if (! filter_var($url, FILTER_VALIDATE_URL)) {
            return $fallback;
        }

        $parts = parse_url($url);

        if (! is_array($parts)) {
            return $fallback;
        }

        $host = strtolower((string) ($parts['host'] ?? ''));
        $currentHost = strtolower((string) request()->getHost());
        $appHost = strtolower((string) parse_url((string) config('app.url'), PHP_URL_HOST));
        $internalHosts = array_filter([$currentHost, $appHost, 'localhost', '127.0.0.1', '::1']);

        if (! in_array($host, $internalHosts, true)) {
            return $fallback;
        }

        $path = $parts['path'] ?? '/';
        $query = isset($parts['query']) ? '?'.$parts['query'] : '';
        $fragment = isset($parts['fragment']) ? '#'.$parts['fragment'] : '';

        return $path.$query.$fragment;
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

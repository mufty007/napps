<?php

namespace App\Http\Controllers;

use App\Support\Notifications\NotificationPreferences;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

/**
 * In-app notifications for every module, and per-category channel choices.
 * Notification data follows one shape: title, body, url, icon.
 */
class NotificationController extends Controller
{
    public function index(Request $request, NotificationPreferences $preferences): View|Response
    {
        $user = $request->user();
        $filter = $request->query('filter') === 'unread' ? 'unread' : 'all';

        $notifications = ($filter === 'unread' ? $user->unreadNotifications() : $user->notifications())
            ->paginate(30)
            ->withQueryString();

        $data = [
            'notifications' => $notifications,
            'filter' => $filter,
            'unreadCount' => $user->unreadNotifications()->count(),
            'categories' => $preferences->categories(),
            'preferences' => $preferences,
            'user' => $user,
        ];

        if ($request->ajax() || $request->header('X-Partial') === 'notifications') {
            return response()
                ->view('notifications.partials.list', $data)
                ->header('X-Unread-Count', (string) $data['unreadCount']);
        }

        return view('notifications.index', $data);
    }

    /**
     * Mark one as read and follow its link.
     */
    public function open(Request $request, string $id): RedirectResponse|JsonResponse
    {
        $notification = $request->user()->notifications()->findOrFail($id);
        $notification->markAsRead();

        $url = $notification->data['url'] ?? null;
        $redirect = is_string($url) && str_starts_with($url, url('/'))
            ? $url
            : route('notifications.index');

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'redirect' => $redirect,
                'unreadCount' => $request->user()->unreadNotifications()->count(),
            ]);
        }

        return redirect()->to($redirect);
    }

    public function readAll(Request $request): RedirectResponse|JsonResponse
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'status' => __('All caught up.'),
                'unreadCount' => 0,
            ]);
        }

        return back()->with('status', __('All caught up.'));
    }

    public function updatePreferences(Request $request, NotificationPreferences $preferences): RedirectResponse
    {
        $user = $request->user();
        $stored = $user->notification_preferences ?? [];
        // Category keys contain dots ("community.activity"), so read the array
        // directly rather than through dot-notation input helpers.
        $chosen = (array) $request->input('channels', []);

        foreach (array_keys($preferences->categories()) as $category) {
            foreach (NotificationPreferences::OPTIONAL as $channel) {
                $stored[$category][$channel] = filter_var($chosen[$category][$channel] ?? false, FILTER_VALIDATE_BOOL);
            }
        }

        $stored['settings']['read_receipts'] = $request->boolean('settings.read_receipts');
        $stored['settings']['typing'] = $request->boolean('settings.typing');

        $user->forceFill(['notification_preferences' => $stored])->save();

        return back()->with('status', __('Notification settings saved.'));
    }
}

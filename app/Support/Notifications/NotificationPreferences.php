<?php

namespace App\Support\Notifications;

use App\Models\User;

/**
 * Kinds of message people can tune (PRD: in-app, SMS, email per type).
 *
 * In-app is always on. Modules register their categories with the extra
 * channels they offer and the defaults; notifications ask channelsFor().
 * Urgent official notices do not use this: they always go by SMS.
 */
class NotificationPreferences
{
    /** Channels a person may switch on or off. */
    public const OPTIONAL = ['sms', 'mail'];

    /** @var array<string, array{label: string, description: string, defaults: array<string, bool>, order: int}> */
    private array $categories = [];

    /**
     * @param  array<string, bool>  $defaults  e.g. ['sms' => false, 'mail' => true]
     */
    public function register(string $key, string $label, string $description, array $defaults = [], int $order = 50): static
    {
        $this->categories[$key] = [
            'label' => $label,
            'description' => $description,
            'defaults' => array_merge(array_fill_keys(self::OPTIONAL, false), array_intersect_key($defaults, array_flip(self::OPTIONAL))),
            'order' => $order,
        ];

        return $this;
    }

    /**
     * @return array<string, array{label: string, description: string, defaults: array<string, bool>, order: int}>
     */
    public function categories(): array
    {
        return collect($this->categories)->sortBy('order')->all();
    }

    public function wants(User $user, string $category, string $channel): bool
    {
        $stored = $user->notification_preferences[$category][$channel] ?? null;

        return is_bool($stored) ? $stored : (bool) ($this->categories[$category]['defaults'][$channel] ?? false);
    }

    /**
     * Channels for a notification in this category: always in-app, plus
     * SMS / email if the person chose them (email only if they have one).
     *
     * @return list<string>
     */
    public function channelsFor(User $user, string $category): array
    {
        $channels = ['database'];

        if ($this->wants($user, $category, 'sms')) {
            $channels[] = 'sms';
        }

        if ($user->email && $this->wants($user, $category, 'mail')) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    /**
     * A person's own settings ("show when I've read messages" and the like)
     * live alongside their channel choices.
     */
    public function setting(User $user, string $key, bool $default): bool
    {
        $value = $user->notification_preferences['settings'][$key] ?? null;

        return is_bool($value) ? $value : $default;
    }
}

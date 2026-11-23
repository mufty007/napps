<?php

namespace Modules\Membership\Notifications;

use App\Enums\Role;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Modules\Membership\Models\MembershipApplication;
use Modules\Membership\Notifications\Concerns\SmsAndMail;

/**
 * Tells chapter executives a school needs verifying (MEM-1: "the chapter is
 * notified"), and state executives one needs approving.
 */
class ApplicationNeedsReview extends Notification
{
    use SmsAndMail;

    public function __construct(
        public readonly MembershipApplication $application,
        public readonly Role $reviewer,
    ) {}

    public function toSms(object $notifiable): string
    {
        return config('napps.name').': '.$this->line().' '.$this->url();
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject($this->reviewer === Role::ChapterExecutive
                ? __('New school to verify: :school', ['school' => $this->application->school->name])
                : __('School awaiting state approval: :school', ['school' => $this->application->school->name]))
            ->line($this->line())
            ->line(__('Reference: :ref', ['ref' => $this->application->reference()]))
            ->action(__('Review the application'), $this->url());
    }

    /**
     * @return array<string, string>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => $this->reviewer === Role::ChapterExecutive ? __('New school to verify') : __('School awaiting state approval'),
            'body' => $this->line(),
            'url' => $this->url(),
            'icon' => 'clipboard-check',
        ];
    }

    private function line(): string
    {
        $school = $this->application->school;

        return $this->reviewer === Role::ChapterExecutive
            ? __(':school has applied to join :chapter. Please verify it:', ['school' => $school->name, 'chapter' => $school->chapter?->name])
            : __(':chapter verified :school. It needs state approval:', ['school' => $school->name, 'chapter' => $school->chapter?->name]);
    }

    private function url(): string
    {
        return route('membership.applications.show', $this->application);
    }
}

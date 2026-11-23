<?php

namespace Modules\Membership\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Modules\Membership\Enums\ApplicationStatus;
use Modules\Membership\Models\MembershipApplication;
use Modules\Membership\Notifications\Concerns\SmsAndMail;
use Modules\Membership\Services\MembershipDocuments;

/**
 * Keeps the applicant informed at each step. On approval it carries the
 * certificate (MEM-2: "approval sends a certificate with a QR code").
 */
class ApplicationUpdate extends Notification
{
    use SmsAndMail;

    /** OTP-grade route: applicants are waiting on this. */
    public bool $transactional = true;

    public function __construct(public readonly MembershipApplication $application) {}

    public function toSms(object $notifiable): string
    {
        return config('napps.name').': '.$this->message().' '.$this->url();
    }

    public function toMail(object $notifiable): MailMessage
    {
        $school = $this->application->school;

        $mail = (new MailMessage)
            ->subject(match ($this->application->status) {
                ApplicationStatus::Approved => __(':school is now a NAPPS member', ['school' => $school->name]),
                ApplicationStatus::Rejected => __('Your NAPPS application for :school', ['school' => $school->name]),
                default => __('NAPPS application :ref: :status', ['ref' => $this->application->reference(), 'status' => $this->application->status->label()]),
            })
            ->line($this->message())
            ->action($this->application->status === ApplicationStatus::Approved ? __('Download your certificate') : __('View your application'), $this->url());

        if ($this->application->status === ApplicationStatus::Approved) {
            $mail->attachData(
                app(MembershipDocuments::class)->certificate($school)->output(),
                'napps-membership-certificate.pdf',
                ['mime' => 'application/pdf'],
            );
        }

        return $mail;
    }

    /**
     * @return array<string, string>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => __('Application :ref: :status', ['ref' => $this->application->reference(), 'status' => $this->application->status->label()]),
            'body' => $this->message(),
            'url' => route('membership.apply.show', $this->application),
            'icon' => match ($this->application->status) {
                ApplicationStatus::Approved => 'award',
                ApplicationStatus::Rejected => 'circle-x',
                default => 'clipboard-check',
            },
        ];
    }

    private function message(): string
    {
        $school = $this->application->school;
        $replace = [
            'school' => $school->name,
            'ref' => $this->application->reference(),
            'chapter' => $school->chapter?->name,
            'state' => $school->state?->name,
            'number' => $school->membership_number,
            'reason' => $this->application->rejection_reason,
        ];

        return match ($this->application->status) {
            ApplicationStatus::Submitted => __('We received the application for :school (ref :ref). :chapter will review it.', $replace),
            ApplicationStatus::Verified => __(':chapter verified :school and sent it to :state State for approval.', $replace),
            ApplicationStatus::Approved => __('Congratulations! :school is now a NAPPS member, number :number. Your certificate:', $replace),
            ApplicationStatus::Rejected => __('The application for :school was not approved: :reason', $replace),
        };
    }

    private function url(): string
    {
        return $this->application->status === ApplicationStatus::Approved
            ? app(MembershipDocuments::class)->certificateUrl($this->application->school)
            : route('membership.apply.show', $this->application);
    }
}

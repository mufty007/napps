<?php

namespace Modules\Fees\Notifications;

use App\Support\Money;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Modules\Fees\Enums\ReminderKind;
use Modules\Fees\Models\FeeInvoice;
use Modules\Fees\Notifications\Concerns\FeeNotice;

/**
 * A fee reminder: before the due date, on it, and 7, 14 and 30 days after.
 */
class FeeReminderDue extends Notification implements ShouldQueue
{
    use FeeNotice, Queueable;

    public function __construct(
        public readonly FeeInvoice $invoice,
        public readonly ReminderKind $kind,
        public readonly string $schoolName,
    ) {}

    public function toSms(object $notifiable): string
    {
        return config('napps.name').': '.$this->message().' '.$this->url();
    }

    /**
     * @return array<string, string>
     */
    public function toArray(object $notifiable): array
    {
        return ['title' => __('Fee reminder'), 'body' => $this->message(), 'url' => $this->url(), 'icon' => 'bell-ring'];
    }

    private function message(): string
    {
        $amount = Money::format($this->invoice->balanceKobo());
        $student = $this->invoice->student_name;
        $date = $this->invoice->due_date->format('j M Y');

        return match ($this->kind) {
            ReminderKind::Before => __(':school: reminder, :amount fees for :student fall due on :date.', ['school' => $this->schoolName, 'amount' => $amount, 'student' => $student, 'date' => $date]),
            ReminderKind::On => __(':school: :amount fees for :student are due today.', ['school' => $this->schoolName, 'amount' => $amount, 'student' => $student]),
            default => __(':school: :amount fees for :student were due on :date and are still unpaid.', ['school' => $this->schoolName, 'amount' => $amount, 'student' => $student, 'date' => $date]),
        };
    }

    private function url(): string
    {
        return route('fees.parent.show', $this->invoice);
    }
}

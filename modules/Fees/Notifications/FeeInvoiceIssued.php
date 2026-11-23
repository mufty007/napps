<?php

namespace Modules\Fees\Notifications;

use App\Support\Money;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Modules\Fees\Models\FeeInvoice;
use Modules\Fees\Notifications\Concerns\FeeNotice;

/**
 * Sent to the guardian when a school issues a fee invoice.
 */
class FeeInvoiceIssued extends Notification implements ShouldQueue
{
    use FeeNotice, Queueable;

    public function __construct(
        public readonly FeeInvoice $invoice,
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
        return ['title' => __('New fee invoice'), 'body' => $this->message(), 'url' => $this->url(), 'icon' => 'receipt'];
    }

    private function message(): string
    {
        return __(':school: fees of :amount for :student are due by :date.', [
            'school' => $this->schoolName,
            'amount' => Money::format($this->invoice->balanceKobo()),
            'student' => $this->invoice->student_name,
            'date' => $this->invoice->due_date->format('j M Y'),
        ]);
    }

    private function url(): string
    {
        return route('fees.parent.show', $this->invoice);
    }
}

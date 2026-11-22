<x-layouts.auth
    :title="__('Test checkout')"
    :heading="__('Test checkout')"
    :subheading="__('Payments are simulated on this server. No money moves.')"
>
    <div class="rounded-2xl bg-stone-50 p-5 ring-1 ring-stone-200">
        <p class="text-sm text-stone-500">{{ __('Amount') }}</p>
        <p class="mt-1 text-3xl font-semibold tracking-tight text-stone-900 tabular">{{ \App\Support\Money::format($transaction->amount_kobo) }}</p>
        <p class="mt-3 break-all text-xs text-stone-500">{{ __('Reference') }}: <span class="font-mono">{{ $transaction->reference }}</span></p>
    </div>

    @if ($transaction->status->isFinal())
        <x-ui.alert class="mt-6" tone="info">{{ __('This payment is already :status.', ['status' => mb_strtolower($transaction->status->label())]) }}</x-ui.alert>
        <x-ui.button :href="route('payments.callback', ['reference' => $transaction->reference])" class="mt-4 w-full">{{ __('Back to the app') }}</x-ui.button>
    @else
        <form method="POST" action="{{ route('payments.fake.complete', $transaction->reference) }}" class="mt-6 space-y-3">
            @csrf
            <x-ui.button type="submit" name="outcome" value="success" size="lg" class="w-full" icon="circle-check">{{ __('Pay successfully') }}</x-ui.button>
            <x-ui.button type="submit" name="outcome" value="failed" variant="secondary" class="w-full">{{ __('Simulate a declined card') }}</x-ui.button>
            <x-ui.button type="submit" name="outcome" value="abandoned" variant="ghost" class="w-full">{{ __('Cancel payment') }}</x-ui.button>
        </form>
    @endif
</x-layouts.auth>

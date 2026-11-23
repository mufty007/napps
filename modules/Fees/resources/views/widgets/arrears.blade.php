{{-- Dashboard card for proprietors and bursars. Fed by Modules\Fees\Support\ArrearsWidget. --}}
@php
    $colors = ['current' => 'bg-sky-500', '1-30' => 'bg-amber-400', '31-60' => 'bg-amber-500', '61-90' => 'bg-orange-600', '90+' => 'bg-red-600'];
@endphp
<div class="mb-6" x-reveal>
    <x-ui.card :title="__('Fee arrears')" :description="__('Unpaid school fees by how late they are.')">
        <x-slot:actions>
            <x-ui.button :href="route('fees.invoices.index', ['status' => 'overdue'])" variant="ghost" size="sm" icon-right="arrow-right">{{ __('Open') }}</x-ui.button>
        </x-slot:actions>

        @if ($ageingTotalKobo === 0)
            <p class="flex items-center gap-2 text-sm font-semibold text-emerald-800">
                <x-ui.icon name="circle-check" class="text-emerald-600" /> {{ __('No fees outstanding') }}
            </p>
        @else
            <p class="text-sm text-stone-500">{{ __('Total owed') }}</p>
            <p class="mt-1 text-3xl font-semibold tracking-tight text-stone-900 tabular">{{ \App\Support\Money::format($ageingTotalKobo) }}</p>

            <ul class="mt-5 space-y-3">
                @foreach ($ageing as $bucket)
                    <li class="grid grid-cols-[6.5rem_minmax(0,1fr)_auto] items-center gap-3 text-sm">
                        <span class="text-stone-600">{{ $bucket['label'] }}</span>
                        <span class="h-2.5 overflow-hidden rounded-full bg-stone-100" aria-hidden="true">
                            <span class="block h-full rounded-full {{ $colors[$bucket['key']] ?? 'bg-stone-400' }}" style="width: {{ $bucket['kobo'] > 0 ? max(3, round($bucket['kobo'] / $ageingMaxKobo * 100)) : 0 }}%"></span>
                        </span>
                        <span class="text-right font-semibold tabular text-stone-900">
                            {{ \App\Support\Money::format($bucket['kobo']) }}
                            <span class="sr-only">{{ trans_choice(':count invoice|:count invoices', $bucket['count']) }}</span>
                        </span>
                    </li>
                @endforeach
            </ul>
        @endif
    </x-ui.card>
</div>

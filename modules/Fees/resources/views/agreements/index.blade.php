<x-layouts.app :title="__('Fee agreements')">
    <x-ui.page-header
        :eyebrow="__('Fees')"
        :title="__('Fee agreements')"
        :description="__('Agreements parents have signed with your school. They go into the claim pack if a debt is escalated.')"
    >
        <x-slot:actions>
            <x-ui.button :href="route('fees.invoices.index')" variant="secondary" icon="arrow-left">{{ __('Invoices') }}</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    @if ($agreements->isEmpty())
        <x-ui.card>
            <x-ui.empty-state icon="file-signature" :title="__('No signed agreements yet')" :description="__('Parents sign from their My fees page once you have sent them an invoice.')" />
        </x-ui.card>
    @else
        <x-ui.table>
            <x-slot:toolbar><span class="font-semibold text-stone-900">{{ trans_choice(':count agreement|:count agreements', $agreements->total()) }}</span></x-slot:toolbar>
            <x-slot:head>
                <th scope="col">{{ __('Child') }}</th>
                <th scope="col" class="hidden sm:table-cell">{{ __('Parent') }}</th>
                <th scope="col" class="hidden whitespace-nowrap md:table-cell">{{ __('Signed') }}</th>
                <th scope="col">{{ __('Consents') }}</th>
                <th scope="col"><span class="sr-only">{{ __('Copy') }}</span></th>
            </x-slot:head>
            @foreach ($agreements as $agreement)
                <tr>
                    <td class="font-semibold text-stone-900">{{ $agreement->student_name }}</td>
                    <td class="hidden sm:table-cell">{{ $agreement->guardian?->name }}</td>
                    <td class="hidden whitespace-nowrap tabular md:table-cell">{{ $agreement->signed_at->format('j M Y') }}</td>
                    <td>
                        <span class="flex flex-wrap gap-1.5">
                            <x-ui.badge :tone="$agreement->consent_reminders ? 'success' : 'neutral'">{{ __('Reminders') }}</x-ui.badge>
                            <x-ui.badge :tone="$agreement->consent_debtor_check ? 'success' : 'neutral'">{{ __('Debtor check') }}</x-ui.badge>
                        </span>
                    </td>
                    <td class="text-right">
                        @if ($agreement->pdf_path)
                            <x-ui.button :href="route('fees.agreements.show', $agreement)" variant="ghost" size="sm" icon="download" target="_blank" rel="noopener">{{ __('PDF') }}</x-ui.button>
                        @endif
                    </td>
                </tr>
            @endforeach
            <x-slot:footer>{{ $agreements->links() }}</x-slot:footer>
        </x-ui.table>
    @endif
</x-layouts.app>

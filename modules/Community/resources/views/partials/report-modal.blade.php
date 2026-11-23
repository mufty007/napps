{{-- One per page. Open with $dispatch('report', { type: 'post', id: 5 }). --}}
<div x-data="{ type: '', id: '' }" x-on:report.window="type = $event.detail.type; id = $event.detail.id; $dispatch('open-modal', 'report')">
    <x-ui.modal name="report" :title="__('Report to the moderators')">
        <form method="POST" action="{{ route('community.reports.store') }}" class="space-y-4">
            @csrf
            <input type="hidden" name="type" x-bind:value="type">
            <input type="hidden" name="id" x-bind:value="id">
            <fieldset>
                <legend class="text-sm font-medium text-stone-800">{{ __('What is wrong?') }}</legend>
                <div class="mt-1 space-y-0.5">
                    @foreach (\Modules\Community\Models\Report::REASONS as $value => $label)
                        <label class="flex min-h-11 cursor-pointer items-center gap-3 text-sm text-stone-700">
                            <input type="radio" name="reason" value="{{ $value }}" required class="size-5 border-stone-300 text-brand-700 focus:ring-brand-600">
                            {{ __($label) }}
                        </label>
                    @endforeach
                </div>
            </fieldset>
            <div class="space-y-1.5">
                <label for="report-details" class="block text-sm font-medium text-stone-800">{{ __('Anything the moderators should know? (optional)') }}</label>
                <textarea id="report-details" name="details" rows="3" maxlength="1000" class="block w-full rounded-xl border-0 py-2.5 text-base text-stone-900 shadow-sm ring-1 ring-inset ring-stone-300 focus:ring-2 focus:ring-inset focus:ring-brand-600 sm:text-sm"></textarea>
            </div>
            <p class="text-sm text-stone-500">{{ __('Moderators for this chapter or state review reports. The author is not told who reported.') }}</p>
            <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                <x-ui.button variant="ghost" x-on:click="$dispatch('close-modal', 'report')">{{ __('Cancel') }}</x-ui.button>
                <x-ui.button type="submit" variant="danger" icon="flag">{{ __('Send report') }}</x-ui.button>
            </div>
        </form>
    </x-ui.modal>
</div>

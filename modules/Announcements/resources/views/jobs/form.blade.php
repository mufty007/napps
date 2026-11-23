<x-layouts.app :title="$job->exists ? __('Edit job') : __('Post a job')">
    <x-ui.page-header
        :title="$job->exists ? __('Edit job listing') : __('Post a job')"
        :description="__('Visible on the public job board when published.')"
    />

    <form method="POST" action="{{ $job->exists ? route('jobs.update', $job) : route('jobs.store') }}" class="mx-auto max-w-2xl space-y-5">
        @csrf
        @if ($job->exists) @method('PUT') @endif

        <x-ui.card>
            <div class="space-y-5">
                <x-ui.input name="title" :label="__('Role title')" :value="old('title', $job->title)" required autofocus />
                <div class="grid gap-5 sm:grid-cols-2">
                    <x-ui.input name="school_name" :label="__('School / employer')" :value="old('school_name', $job->school_name)" />
                    <x-ui.input name="location" :label="__('Location')" :value="old('location', $job->location)" placeholder="{{ __('e.g. Kano, Tarauni') }}" />
                </div>
                <x-ui.select name="employment_type" :label="__('Employment type')" :value="old('employment_type', $job->employment_type)" :options="[
                    'full_time' => __('Full-time'),
                    'part_time' => __('Part-time'),
                    'contract' => __('Contract'),
                ]" required />
                <div class="grid gap-5 sm:grid-cols-2">
                    <x-ui.input name="contact_email" type="email" :label="__('Application email')" :value="old('contact_email', $job->contact_email)" />
                    <x-ui.input name="contact_phone" type="tel" :label="__('Contact phone')" :value="old('contact_phone', $job->contact_phone)" />
                </div>
                <x-ui.input name="closes_at" type="date" :label="__('Closes on (optional)')" :value="old('closes_at', optional($job->closes_at)->format('Y-m-d'))" />
                <x-ui.input name="summary" :label="__('Short summary')" :value="old('summary', $job->summary)" />
                <div>
                    <label for="body" class="block text-sm font-medium text-stone-800">{{ __('Full description') }}</label>
                    <textarea id="body" name="body" rows="10" required class="mt-1.5 block w-full rounded-xl border-0 py-2.5 text-base text-stone-900 shadow-sm ring-1 ring-inset ring-stone-300 focus:ring-2 focus:ring-inset focus:ring-brand-600 sm:text-sm">{{ old('body', $job->body) }}</textarea>
                    @error('body') <p class="mt-1.5 text-sm text-red-700">{{ $message }}</p> @enderror
                </div>
            </div>
        </x-ui.card>

        <div class="flex flex-wrap gap-3">
            <x-ui.button type="submit" name="publish" value="1" icon-right="send">{{ __('Publish') }}</x-ui.button>
            <x-ui.button type="submit" variant="secondary">{{ __('Save draft') }}</x-ui.button>
            @if ($job->exists && $job->published_at)
                <x-ui.button type="submit" name="unpublish" value="1" variant="ghost">{{ __('Unpublish') }}</x-ui.button>
            @endif
            <x-ui.button :href="route('jobs.manage')" variant="ghost">{{ __('Cancel') }}</x-ui.button>
        </div>
    </form>
</x-layouts.app>

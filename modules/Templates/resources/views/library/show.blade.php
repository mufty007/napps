@php
    $previewStyles = 'body{font-family:system-ui,sans-serif;line-height:1.6;color:#1c1917;padding:1.25rem;max-width:42rem;margin:auto;font-size:15px}'
        .'h1{font-size:1.25rem;text-align:center;text-transform:uppercase}'
        .'.ph{background:#fef3c7;color:#78350f;border-radius:4px;padding:0 4px}.blank{color:#78716c}';
    $previewDocument = '<!doctype html><meta charset="utf-8"><style>'.$previewStyles.'</style>'.$preview;
@endphp
<x-layouts.app :title="$template->name">
    <x-ui.page-header
        :eyebrow="$template->categoryLabel()"
        :title="$template->name"
        :description="$template->description"
    >
        <x-slot:actions>
            @if ($template->isPublished() || $canPublish)
                <x-ui.button :href="route('templates.fill.create', $template)" icon="file-down">{{ __('Fill in & download') }}</x-ui.button>
            @endif
            @if ($canPublish)
                <x-ui.button :href="route('templates.edit', $template)" variant="secondary" icon="pencil">{{ __('Edit') }}</x-ui.button>
            @endif
            <x-ui.button :href="route('templates.index')" variant="ghost">{{ __('Back') }}</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    @error('publish')
        <x-ui.alert tone="danger" class="mb-5">{{ $message }}</x-ui.alert>
    @enderror

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            @unless ($template->isReviewed())
                <x-ui.alert tone="warning" :title="__('Not yet legally reviewed')">
                    {{ __('Treat this document as a draft and have a lawyer check it before you rely on it.') }}
                </x-ui.alert>
            @endunless

            <x-ui.card :title="__('Preview')" :description="__('Highlighted fields are filled in on the next step.')" :padding="false">
                <iframe
                    title="{{ __('Preview of :name', ['name' => $template->name]) }}"
                    sandbox=""
                    class="h-[70vh] w-full rounded-b-2xl bg-white"
                    srcdoc="{{ $previewDocument }}"
                ></iframe>
            </x-ui.card>
        </div>

        <div class="space-y-6">
            <x-ui.card :title="__('Status')">
                <dl class="space-y-3 text-sm">
                    <div class="flex items-center justify-between gap-3">
                        <dt class="text-stone-500">{{ __('Version') }}</dt>
                        <dd class="font-semibold tabular text-stone-900">{{ $template->version }}</dd>
                    </div>
                    <div class="flex items-center justify-between gap-3">
                        <dt class="text-stone-500">{{ __('Library') }}</dt>
                        <dd>
                            @if ($template->isPublished())
                                <x-ui.badge tone="success" dot>{{ __('Published') }}</x-ui.badge>
                            @else
                                <x-ui.badge>{{ __('Draft') }}</x-ui.badge>
                            @endif
                        </dd>
                    </div>
                    <div class="flex items-center justify-between gap-3">
                        <dt class="text-stone-500">{{ __('Legal review') }}</dt>
                        <dd>
                            @if ($template->isReviewed())
                                <x-ui.badge tone="success" dot>{{ __('Reviewed') }}</x-ui.badge>
                            @else
                                <x-ui.badge tone="warning" dot>{{ __('Pending') }}</x-ui.badge>
                            @endif
                        </dd>
                    </div>
                    @if ($template->isReviewed())
                        <p class="text-xs text-stone-500">
                            {{ __('Reviewed :date by :name', ['date' => $template->legally_reviewed_at->format('j M Y'), 'name' => $template->reviewer?->name ?? __('the secretariat')]) }}
                        </p>
                    @endif
                </dl>

                @if ($canPublish)
                    <div class="mt-5 flex flex-col gap-2 border-t border-stone-100 pt-5">
                        @if ($template->isReviewed())
                            <form method="POST" action="{{ route('templates.review', $template) }}">
                                @csrf
                                <x-ui.button type="submit" name="clear" value="1" variant="secondary" class="w-full">{{ __('Clear legal review') }}</x-ui.button>
                            </form>
                        @else
                            <form method="POST" action="{{ route('templates.review', $template) }}">
                                @csrf
                                <x-ui.button type="submit" variant="secondary" icon="shield-check" class="w-full">{{ __('Mark as legally reviewed') }}</x-ui.button>
                            </form>
                        @endif

                        <form method="POST" action="{{ route('templates.publish', $template) }}">
                            @csrf
                            @if ($template->isPublished())
                                <x-ui.button type="submit" name="unpublish" value="1" variant="ghost" class="w-full">{{ __('Unpublish') }}</x-ui.button>
                            @else
                                <x-ui.button type="submit" icon="send" class="w-full">{{ __('Publish to library') }}</x-ui.button>
                            @endif
                        </form>
                    </div>
                @endif
            </x-ui.card>

            <x-ui.card :title="__('Fields to fill in')">
                @if ($fields === [])
                    <p class="text-sm text-stone-500">{{ __('This document has no fields to fill in.') }}</p>
                @else
                    <ul class="space-y-2 text-sm">
                        @foreach ($fields as $key => $field)
                            <li class="flex items-center justify-between gap-3">
                                <span class="text-stone-800">{{ $field['label'] }}</span>
                                <code class="rounded bg-stone-100 px-1.5 py-0.5 text-xs text-stone-600">&#123;&#123;{{ $key }}&#125;&#125;</code>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-ui.card>
        </div>
    </div>
</x-layouts.app>

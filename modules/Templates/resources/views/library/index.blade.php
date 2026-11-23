<x-layouts.app :title="__('Templates')">
    <x-ui.page-header
        :eyebrow="__('Documents')"
        :title="__('Template library')"
        :description="__('Legally reviewed documents for your school. Fill in the details and download a ready-to-sign PDF.')"
    >
        @if ($canPublish)
            <x-slot:actions>
                <x-ui.button :href="route('templates.create')" icon="plus">{{ __('New template') }}</x-ui.button>
            </x-slot:actions>
        @endif
    </x-ui.page-header>

    @php
        $labels = \Modules\Templates\Models\DocumentTemplate::categoryLabels();
        $tabs = ['' => ['label' => __('All'), 'count' => $total, 'href' => route('templates.index')]];
        foreach ($labels as $value => $label) {
            $tabs[$value] = ['label' => $label, 'count' => $counts[$value] ?? 0, 'href' => route('templates.index', ['category' => $value])];
        }
    @endphp

    <x-ui.status-tabs :tabs="$tabs" :active="$category ?? ''" class="mb-5 overflow-x-auto" />

    @if ($templates->isEmpty())
        <x-ui.card>
            <x-ui.empty-state icon="file-text" :title="__('No templates here yet')" :description="__('Published templates appear in this list.')" />
        </x-ui.card>
    @else
        <ul class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            @foreach ($templates as $template)
                <li>
                    <x-ui.card class="flex h-full flex-col">
                        <div class="flex flex-wrap items-center gap-2">
                            <x-ui.badge tone="brand">{{ $template->categoryLabel() }}</x-ui.badge>
                            @if ($template->isReviewed())
                                <x-ui.badge tone="success" dot>{{ __('Legally reviewed') }}</x-ui.badge>
                            @else
                                <x-ui.badge tone="warning" dot>{{ __('Not reviewed') }}</x-ui.badge>
                            @endif
                            @if ($canPublish && ! $template->isPublished())
                                <x-ui.badge>{{ __('Draft') }}</x-ui.badge>
                            @endif
                        </div>
                        <h2 class="mt-3 text-base font-semibold text-stone-900">{{ $template->name }}</h2>
                        <p class="mt-1 flex-1 text-sm text-stone-500">{{ $template->description }}</p>
                        <p class="mt-3 text-xs text-stone-500 tabular">{{ __('Version :v', ['v' => $template->version]) }}</p>
                        <div class="mt-4 flex flex-wrap gap-2">
                            <x-ui.button :href="route('templates.show', $template)" variant="secondary" size="sm" class="min-h-11">{{ __('Preview') }}</x-ui.button>
                            @if ($template->isPublished() || $canPublish)
                                <x-ui.button :href="route('templates.fill.create', $template)" size="sm" class="min-h-11" icon="file-down">{{ __('Fill in') }}</x-ui.button>
                            @endif
                        </div>
                    </x-ui.card>
                </li>
            @endforeach
        </ul>
    @endif
</x-layouts.app>

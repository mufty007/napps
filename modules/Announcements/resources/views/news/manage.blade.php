<x-layouts.app :title="__('Manage news')">
    <x-ui.page-header :title="__('News & updates')" :description="__('Publish circulars and association news.')">
        <x-slot:actions>
            <x-ui.button :href="route('news.create')" icon="plus">{{ __('New post') }}</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    @if ($posts->isEmpty())
        <x-ui.card>
            <x-ui.empty-state icon="newspaper" :title="__('No posts yet')" :description="__('Write the first update for your members.')" />
        </x-ui.card>
    @else
        <x-ui.table>
            <x-slot:toolbar><span class="font-semibold text-stone-900">{{ trans_choice(':count post|:count posts', $posts->total()) }}</span></x-slot:toolbar>
            <x-slot:head>
                <th>{{ __('Title') }}</th>
                <th>{{ __('Status') }}</th>
                <th></th>
            </x-slot:head>
            @foreach ($posts as $post)
                <tr>
                    <td class="font-semibold text-stone-900">{{ $post->title }}</td>
                    <td>
                        @if ($post->isPublished())
                            <x-ui.badge tone="success">{{ __('Published') }}</x-ui.badge>
                        @else
                            <x-ui.badge>{{ __('Draft') }}</x-ui.badge>
                        @endif
                    </td>
                    <td class="text-right">
                        <a href="{{ route('news.edit', $post) }}" class="inline-flex min-h-11 items-center font-semibold text-brand-700 hover:text-brand-800">{{ __('Edit') }}</a>
                    </td>
                </tr>
            @endforeach
            <x-slot:footer>{{ $posts->links() }}</x-slot:footer>
        </x-ui.table>
    @endif
</x-layouts.app>

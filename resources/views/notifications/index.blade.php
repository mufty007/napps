<x-layouts.app :title="__('Notifications')">
    <x-ui.page-header :title="__('Notifications')" :description="__('Replies, messages, group requests and notices from NAPPS, in one place.')">
        <x-slot:actions>
            @if ($unreadCount > 0)
                <form method="POST" action="{{ route('notifications.read-all') }}"
                    x-data
                    @submit.prevent="
                        fetch($el.action, { method: 'POST', headers: { Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content, 'X-Requested-With': 'XMLHttpRequest' }, body: new FormData($el) })
                            .then(r => r.json())
                            .then(d => { window.toast?.(d.status); document.querySelectorAll('[data-unread-badge]').forEach(el => { el.classList.add('hidden'); el.textContent = '0'; }); $el.remove(); location.reload(); })
                            .catch(() => $el.submit())
                    ">
                    @csrf
                    <x-ui.button type="submit" variant="secondary" icon="check-check">{{ __('Mark all read') }}</x-ui.button>
                </form>
            @endif
        </x-slot:actions>
    </x-ui.page-header>

    <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_20rem]">
        <div x-data="ajaxFilter({ target: '[data-ajax-results]' })"
            @click.prevent="if ($event.target.closest('[data-ajax-tab]')) load($event.target.closest('a').href)">
            @include('notifications.partials.list')
        </div>

        <x-ui.card :title="__('How we reach you')" :description="__('You always see notifications here. Choose what also comes by SMS or email. Urgent NAPPS notices always come by SMS.')">
            <form method="POST" action="{{ route('notifications.preferences') }}" class="space-y-5">
                @csrf
                @method('PUT')
                @foreach ($categories as $key => $category)
                    <fieldset>
                        <legend class="text-sm font-semibold text-stone-900">{{ __($category['label']) }}</legend>
                        <p class="text-sm text-stone-500">{{ __($category['description']) }}</p>
                        <div class="mt-1 flex flex-wrap gap-x-5">
                            <x-ui.checkbox :name="'channels['.$key.'][sms]'" :id="'pref-'.$key.'-sms'" :label="__('SMS')" :checked="$preferences->wants($user, $key, 'sms')" />
                            @if ($user->email)
                                <x-ui.checkbox :name="'channels['.$key.'][mail]'" :id="'pref-'.$key.'-mail'" :label="__('Email')" :checked="$preferences->wants($user, $key, 'mail')" />
                            @endif
                        </div>
                    </fieldset>
                @endforeach

                <fieldset class="border-t border-stone-100 pt-4">
                    <legend class="text-sm font-semibold text-stone-900">{{ __('Chat privacy') }}</legend>
                    <x-ui.checkbox name="settings[read_receipts]" id="pref-read-receipts" :label="__('Let people see when I have read their messages')" :checked="$preferences->setting($user, 'read_receipts', true)" />
                    <x-ui.checkbox name="settings[typing]" id="pref-typing" :label="__('Show when I am typing')" :checked="$preferences->setting($user, 'typing', true)" />
                </fieldset>

                <x-ui.button type="submit" class="w-full">{{ __('Save') }}</x-ui.button>
            </form>
        </x-ui.card>
    </div>
</x-layouts.app>

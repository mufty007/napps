<x-layouts.app :title="__('Groups')">
    <x-ui.page-header :eyebrow="__('Community')" :title="__('Groups')" :description="__('Your chapter and state, plus interest groups and committees.')">
        <x-slot:actions>
            <x-ui.button icon="plus" x-data x-on:click="$dispatch('open-modal', 'new-group')">{{ __('New group') }}</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>


    <div class="space-y-6">
        @if ($invitations->isNotEmpty())
            <x-ui.card :title="__('Invitations')" :padding="false">
                <ul class="divide-y divide-stone-100">
                    @foreach ($invitations as $group)
                        <li class="flex flex-wrap items-center justify-between gap-3 px-5 py-3.5">
                            <span class="min-w-0"><span class="block font-semibold text-stone-900">{{ $group->name }}</span><span class="text-sm text-stone-500">{{ $group->typeLabel() }}</span></span>
                            <form method="POST" action="{{ route('community.groups.accept', $group) }}">@csrf<x-ui.button type="submit" size="sm">{{ __('Join') }}</x-ui.button></form>
                        </li>
                    @endforeach
                </ul>
            </x-ui.card>
        @endif

        <section>
            <h2 class="mb-3 text-sm font-semibold uppercase tracking-wider text-stone-500">{{ __('Your spaces') }}</h2>
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($automatic as $space)
                    @include('community::groups.partials.card', ['group' => $space, 'count' => null])
                @endforeach
                @foreach ($groups as $group)
                    @include('community::groups.partials.card', ['group' => $group, 'count' => $group->active_members_count])
                @endforeach
            </div>
            @if ($requested->isNotEmpty())
                <p class="mt-3 text-sm text-stone-500">{{ __('Waiting for approval: :groups', ['groups' => $requested->pluck('name')->join(', ')]) }}</p>
            @endif
        </section>

        <section>
            <h2 class="mb-3 text-sm font-semibold uppercase tracking-wider text-stone-500">{{ __('Discover') }}</h2>
            @if ($discover->isEmpty())
                <x-ui.card><x-ui.empty-state icon="users" :title="__('No other groups yet')" :description="__('Start one for proprietors who share your interests: nursery schools, new proprietors, secondary schools.')" /></x-ui.card>
            @else
                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($discover as $group)
                        @include('community::groups.partials.card', ['group' => $group, 'count' => $group->active_members_count, 'join' => true])
                    @endforeach
                </div>
            @endif
        </section>
    </div>

    <x-ui.modal name="new-group" :title="__('New group')">
        <form method="POST" action="{{ route('community.groups.store') }}" class="space-y-4" x-data="{ type: @js(old('type', 'interest')) }">
            @csrf
            <x-ui.input name="name" :label="__('Name')" required minlength="3" :placeholder="__('e.g. Kano nursery proprietors')" />
            <div class="space-y-1.5">
                <label for="group-description" class="block text-sm font-medium text-stone-800">{{ __('What is it for? (optional)') }}</label>
                <textarea id="group-description" name="description" rows="3" class="block w-full rounded-xl border-0 py-2.5 text-base shadow-sm ring-1 ring-inset ring-stone-300 focus:ring-2 focus:ring-inset focus:ring-brand-600 sm:text-sm">{{ old('description') }}</textarea>
            </div>
            @if ($canCreateCommittee)
                <x-ui.select name="type" :label="__('Kind')" x-model="type" :options="['interest' => __('Interest group'), 'committee' => __('Committee (private, invite only)')]" />
            @else
                <input type="hidden" name="type" value="interest">
            @endif
            <div x-show="type === 'interest'">
                <x-ui.select name="privacy" :label="__('Who can join')" :value="old('privacy', 'open')" :options="['open' => __('Open: any member can join'), 'request' => __('Request: an admin approves'), 'invite' => __('Invite only')]" />
            </div>
            <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                <x-ui.button variant="ghost" x-on:click="$dispatch('close-modal', 'new-group')">{{ __('Cancel') }}</x-ui.button>
                <x-ui.button type="submit">{{ __('Create group') }}</x-ui.button>
            </div>
        </form>
    </x-ui.modal>
    @if ($errors->hasAny(['name', 'privacy', 'type']))
        <div x-data x-init="$nextTick(() => $dispatch('open-modal', 'new-group'))"></div>
    @endif
</x-layouts.app>

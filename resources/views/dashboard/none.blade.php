<x-layouts.app :title="__('Welcome')">
    <x-ui.page-header :title="__('Welcome, :name', ['name' => Str::before(auth()->user()->name, ' ')])" :description="__('Your account is ready. Access depends on your role in NAPPS.')" />

    <x-dashboard.widgets :widgets="$widgets" />

    <div class="grid gap-4 md:grid-cols-2">
        <x-ui.card x-reveal>
            <div class="flex gap-4">
                <span class="flex size-11 shrink-0 items-center justify-center rounded-2xl bg-brand-50 text-brand-700"><x-ui.icon name="school" /></span>
                <div>
                    <h2 class="font-semibold text-stone-900">{{ __('I own a school') }}</h2>
                    <p class="mt-1 text-sm text-stone-600">{{ __('Apply for NAPPS membership. Your chapter verifies the school, your state approves it, and you receive a digital certificate.') }}</p>
                    @if (Route::has('membership.apply'))
                        <x-ui.button :href="route('membership.apply')" class="mt-4" icon-right="arrow-right">{{ __('Apply for membership') }}</x-ui.button>
                    @else
                        <p class="mt-3 text-sm font-medium text-stone-500">{{ __('Online applications open soon. Your chapter executive can register your school meanwhile.') }}</p>
                    @endif
                </div>
            </div>
        </x-ui.card>

        <x-ui.card x-reveal="60">
            <div class="flex gap-4">
                <span class="flex size-11 shrink-0 items-center justify-center rounded-2xl bg-accent-100 text-accent-600"><x-ui.icon name="user-plus" /></span>
                <div>
                    <h2 class="font-semibold text-stone-900">{{ __('I work at a school or for NAPPS') }}</h2>
                    <p class="mt-1 text-sm text-stone-600">{{ __('Ask your proprietor or executive to add :phone. You will see your screens the next time you sign in.', ['phone' => \App\Support\PhoneNumber::format(auth()->user()->phone)]) }}</p>
                </div>
            </div>
        </x-ui.card>

        <x-ui.card class="md:col-span-2" x-reveal="120">
            <div class="flex gap-4">
                <span class="flex size-11 shrink-0 items-center justify-center rounded-2xl bg-stone-100 text-stone-600"><x-ui.icon name="users" /></span>
                <div>
                    <h2 class="font-semibold text-stone-900">{{ __('I am a parent') }}</h2>
                    <p class="mt-1 text-sm text-stone-600">{{ __('Parents are added by their chapter, not through this form. If your chapter already added :phone, sign out and sign back in. Otherwise ask the chapter executive to enrol you.', ['phone' => \App\Support\PhoneNumber::format(auth()->user()->phone)]) }}</p>
                </div>
            </div>
        </x-ui.card>
    </div>
</x-layouts.app>

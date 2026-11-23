@php
    $signedIn = auth()->check();
    $benefits = [
        ['icon' => 'hand-coins', 'title' => __('One place for dues'), 'body' => __('Pay NAPPS dues online, get an instant receipt, and keep a clear record of what your school owes and has paid.')],
        ['icon' => 'shield-check', 'title' => __('Verified membership'), 'body' => __('A digital certificate with a QR code that parents, landlords and ministries can check on the public verify page.')],
        ['icon' => 'wallet', 'title' => __('Fee collection tools'), 'body' => __('Term invoices, parent payments, reminders and arrears ageing, so you spend less time chasing fees.')],
        ['icon' => 'scale', 'title' => __('Debt recovery support'), 'body' => __('Escalate long-overdue accounts to a claim pack, with chapter mediation first and a legal panel behind it.')],
        ['icon' => 'file-text', 'title' => __('Legally reviewed templates'), 'body' => __('Fee agreements, demand letters, transfer clearance forms and a model lease, ready to fill in and print as PDF.')],
        ['icon' => 'users-round', 'title' => __('A members-only community'), 'body' => __('Forums, groups and chat with proprietors in your chapter and across the country.')],
        ['icon' => 'vote', 'title' => __('One school, one vote'), 'body' => __('Take part in chapter and state elections from your phone. Every active member school has an equal say.')],
        ['icon' => 'calendar-days', 'title' => __('Events and training'), 'body' => __('RSVP to meetings, webinars and AGMs, check in with a QR pass, and read the agenda and minutes afterwards.')],
        ['icon' => 'clipboard-list', 'title' => __('A voice in policy'), 'body' => __('Answer association surveys so the leadership can speak for real needs in your chapter and state.')],
        ['icon' => 'briefcase', 'title' => __('Job board'), 'body' => __('Advertise vacancies for teachers, bursars and support staff to the association network.')],
        ['icon' => 'contact', 'title' => __('Member directory'), 'body' => __('Find neighbouring schools and their proprietors in your chapter.')],
    ];
@endphp
<x-dynamic-component :component="$signedIn ? 'layouts.app' : 'layouts.public'" :title="__('Member benefits')">
    <div @class(['mx-auto max-w-5xl px-4 py-12 sm:px-6 sm:py-16' => ! $signedIn])>
        <x-ui.page-header
            :eyebrow="__('NAPPS membership')"
            :title="__('What membership gives your school')"
            :description="__('Everything below is included with active NAPPS membership. Keep your dues up to date to keep full access, including your vote.')"
        />

        <ul class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($benefits as $benefit)
                <li>
                    <x-ui.card class="h-full">
                        <span class="flex size-10 items-center justify-center rounded-xl bg-brand-50 text-brand-700 ring-1 ring-brand-100">
                            <x-ui.icon :name="$benefit['icon']" />
                        </span>
                        <h2 class="mt-3 text-base font-semibold text-stone-900">{{ $benefit['title'] }}</h2>
                        <p class="mt-1 text-sm text-stone-600">{{ $benefit['body'] }}</p>
                    </x-ui.card>
                </li>
            @endforeach
        </ul>

        @unless ($signedIn)
            <div class="mt-10 flex flex-wrap gap-3">
                <x-ui.button :href="route('join')" icon-right="arrow-right">{{ __('Join as a school') }}</x-ui.button>
                <x-ui.button :href="route('login')" variant="secondary">{{ __('Sign in') }}</x-ui.button>
            </div>
        @endunless
    </div>
</x-dynamic-component>

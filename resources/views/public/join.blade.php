<x-layouts.public :title="__('Join NAPPS')">
    <section class="relative overflow-hidden bg-brand-900 text-white">
        <div class="relative mx-auto max-w-6xl px-4 py-14 sm:px-6 sm:py-20">
            <div class="flex flex-col gap-8 lg:flex-row lg:items-center lg:justify-between">
                <div class="max-w-2xl">
                    <p class="text-sm font-semibold text-brand-100">{{ __('School membership') }}</p>
                    <h1 class="mt-3 text-3xl font-semibold tracking-tight sm:text-4xl">{{ __('Join your state hub and chapter') }}</h1>
                    <p class="mt-4 text-lg text-brand-100">
                        {{ __('Private schools join NAPPS through the chapter that covers their state and LGA. Your chapter verifies the school, your state approves it, and you receive a digital membership certificate.') }}
                    </p>
                </div>
                <img src="{{ asset('images/napps-seal.png') }}" alt="{{ config('napps.full_name') }}" class="mx-auto h-40 w-auto lg:mx-0 lg:h-48" width="238" height="222" decoding="async">
            </div>
        </div>
    </section>

    <section class="mx-auto max-w-6xl px-4 py-14 sm:px-6">
        <h2 class="text-2xl font-semibold tracking-tight text-stone-900">{{ __('How registration works') }}</h2>
        <ol class="mt-8 grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
            @foreach ([
                ['1', __('Create your account'), __('Proprietors and school staff sign up with a phone number. Parents are added by their chapter — they do not self-register.')],
                ['2', __('Pick state & chapter'), __('Choose your state hub, then the chapter that covers your school\'s LGA.')],
                ['3', __('Chapter verifies'), __('Your chapter executive checks documents and premises, then verifies the application.')],
                ['4', __('State approves'), __('Your state executive approves membership. You get a certificate with a QR code parents can scan.')],
            ] as [$step, $title, $text])
                <li class="rounded-2xl bg-white p-5 ring-1 ring-stone-200" x-reveal>
                    <span class="flex size-9 items-center justify-center rounded-full bg-brand-700 text-sm font-bold text-white">{{ $step }}</span>
                    <h3 class="mt-4 font-semibold text-stone-900">{{ $title }}</h3>
                    <p class="mt-2 text-sm leading-relaxed text-stone-600">{{ $text }}</p>
                </li>
            @endforeach
        </ol>

        <div class="mt-12 flex flex-wrap gap-3">
            @auth
                @if (Route::has('membership.apply'))
                    <x-ui.button :href="route('membership.apply')" size="lg" icon-right="arrow-right">{{ __('Apply for your school') }}</x-ui.button>
                @endif
                <x-ui.button :href="route('dashboard')" variant="secondary" size="lg">{{ __('Open dashboard') }}</x-ui.button>
            @else
                <x-ui.button :href="route('register')" size="lg" icon-right="arrow-right">{{ __('Create a school account') }}</x-ui.button>
                <x-ui.button :href="route('login')" variant="secondary" size="lg">{{ __('Sign in') }}</x-ui.button>
            @endauth
            @if (Route::has('verify.index'))
                <x-ui.button :href="route('verify.index')" variant="ghost" size="lg" icon="shield-check">{{ __('Verify a member school') }}</x-ui.button>
            @endif
        </div>

        <x-ui.alert class="mt-10" tone="info" :title="__('Parents')">
            {{ __('Parents do not sign up here. Ask your chapter executive to add you with your phone number, then sign in.') }}
        </x-ui.alert>
    </section>
</x-layouts.public>

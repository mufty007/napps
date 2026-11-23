@php
    $clauses = [
        __('I will pay the school fees set out on each invoice the school sends me, by the due date shown on it.'),
        __('I can pay online, by bank transfer or at the school bursary, and the school will record every payment against my child\'s invoice.'),
        __('If fees stay unpaid for more than 90 days, the school may refer the debt to its NAPPS chapter for mediation and, if it is still unpaid, to a legal panel lawyer.'),
        __('The school will keep this agreement and my payment record for as long as the law requires.'),
    ];
@endphp

<x-layouts.app :title="__('Fee agreement')">
    <x-ui.page-header
        :eyebrow="__('Fees')"
        :title="__('Fee agreement')"
        :description="__('Confirm how you will pay, and choose what the school may contact you about.')"
    >
        <x-slot:actions>
            <x-ui.button :href="route('fees.parent.index')" variant="secondary" icon="arrow-left">{{ __('My fees') }}</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    @if ($schools->isEmpty())
        <x-ui.card>
            <x-ui.empty-state icon="file-signature" :title="__('No school has billed you yet')" :description="__('You can sign an agreement once a school sends you an invoice.')" />
        </x-ui.card>
    @else
        <div class="grid gap-6 lg:grid-cols-[minmax(0,1.3fr)_minmax(0,1fr)]">
            <form method="POST" action="{{ route('fees.agreements.store') }}" class="min-w-0 space-y-6"
                x-data="{ school: @js((string) old('school_id', $selectedSchool)), students: @js($students) }">
                @csrf

                <x-ui.card :title="__('Who is this for?')">
                    <div class="space-y-5">
                        <x-ui.select name="school_id" :label="__('School')" :options="$schools" :value="$selectedSchool" x-model="school" required />
                        <div class="space-y-1.5">
                            <x-ui.input name="student_name" :label="__('Child\'s name')" list="student-names" required autocomplete="off" />
                            <datalist id="student-names">
                                <template x-for="name in (students[school] ?? [])" :key="name"><option :value="name"></option></template>
                            </datalist>
                        </div>
                    </div>
                </x-ui.card>

                <x-ui.card :title="__('Your choices')">
                    <div class="space-y-1">
                        <x-ui.checkbox name="consent_reminders" :label="__('The school may send me SMS reminders about fees that are due or overdue.')" :checked="true" />
                        <x-ui.checkbox name="consent_debtor_check" :label="__('If I enrol a child at another NAPPS school, that school may check whether I have unpaid fees at this one.')" :checked="false" />
                    </div>
                </x-ui.card>

                <x-ui.card :title="__('Sign')">
                    <div class="space-y-4">
                        <x-ui.input name="signature" :label="__('Type your full name to sign')" :value="auth()->user()->name" required autocomplete="name" />
                        <x-ui.checkbox name="accept" :label="__('I have read the agreement and I accept it.')" />
                        <x-ui.button type="submit" size="lg" icon="pen-line" class="w-full sm:w-auto">{{ __('Sign agreement') }}</x-ui.button>
                    </div>
                </x-ui.card>
            </form>

            <x-ui.side-panel :title="__('The agreement')">
                <ol class="space-y-3 text-sm text-stone-700">
                    @foreach ($clauses as $i => $clause)
                        <li class="flex gap-3"><span class="flex size-6 shrink-0 items-center justify-center rounded-full bg-brand-700 text-xs font-bold text-white">{{ $i + 1 }}</span><span>{{ $clause }}</span></li>
                    @endforeach
                </ol>
            </x-ui.side-panel>
        </div>
    @endif
</x-layouts.app>

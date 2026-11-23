@php
    $editing = $student->exists;
    $title = $editing ? __('Edit :name', ['name' => $student->fullName()]) : __('Enrol a pupil');
@endphp
<x-layouts.app :title="$title">
    <x-ui.page-header
        :eyebrow="__('Records')"
        :title="$title"
        :description="$editing ? __('Keep the guardian and class up to date. Every change is logged.') : __('Add a pupil to the register with a guardian we can reach.')"
    >
        <x-slot:actions>
            <x-ui.button :href="$editing ? route('records.students.show', $student) : route('records.students.index')" variant="secondary" icon="arrow-left">{{ $editing ? __('Back to record') : __('All students') }}</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <div class="grid gap-6 lg:grid-cols-[1.4fr_1fr]">
        <x-ui.card :title="__('Pupil details')">
            <form method="POST" action="{{ $editing ? route('records.students.update', $student) : route('records.students.store') }}" class="space-y-5">
                @csrf
                @if ($editing) @method('PUT') @endif

                @if (! $editing && $schools->isNotEmpty())
                    <x-ui.select name="school_id" :label="__('School')" :options="$schools" :placeholder="__('Choose a school')" required />
                @endif

                <div class="grid gap-5 sm:grid-cols-2">
                    <x-ui.input name="first_name" :label="__('First name')" :value="$student->first_name" required autocomplete="off" />
                    <x-ui.input name="last_name" :label="__('Last name')" :value="$student->last_name" required autocomplete="off" />
                </div>

                <div class="grid gap-5 sm:grid-cols-2">
                    <x-ui.input name="admission_no" :label="__('Admission number')" :value="$student->admission_no" autocomplete="off" />
                    <x-ui.input name="class_name" :label="__('Class')" :value="$student->class_name" :placeholder="__('e.g. Primary 4')" autocomplete="off" />
                </div>

                <div class="grid gap-5 sm:grid-cols-2">
                    <x-ui.input name="guardian_name" :label="__('Guardian name')" :value="$student->guardian_name" required autocomplete="off" />
                    <x-ui.input name="guardian_phone" type="tel" inputmode="tel" :label="__('Guardian phone')" :value="$student->guardian_phone" :hint="__('Used for fee reminders and, with consent, the debtor check.')" autocomplete="off" />
                </div>

                <div class="grid gap-5 sm:grid-cols-2">
                    <x-ui.input name="enrolled_at" type="date" :label="__('Enrolled on')" :value="$student->enrolled_at?->toDateString()" :max="today()->toDateString()" />
                    @if ($editing)
                        <x-ui.select name="status" :label="__('Status')" :value="$student->status->value" :options="collect(\Modules\Records\Enums\StudentStatus::cases())->mapWithKeys(fn ($s) => [$s->value => $s->label()])" />
                    @endif
                </div>

                @if ($editing)
                    <x-ui.input name="exited_at" type="date" :label="__('Left on')" :value="$student->exited_at?->toDateString()" :hint="__('Filled in automatically when the status changes from active.')" />
                @endif

                @if ($canSetBalance)
                    <x-ui.input name="outstanding" inputmode="decimal" prefix="₦" :label="__('Fees still owed')" :value="\App\Support\Money::toNaira($student->outstanding_kobo)" :hint="__('In naira. This is the figure shared (as a total only) with transfer clearance and the debtor check.')" />
                @endif

                <div class="rounded-xl bg-stone-50 p-4 ring-1 ring-stone-200">
                    <input type="hidden" name="debtor_check_consent" value="0">
                    <x-ui.checkbox name="debtor_check_consent" :checked="$student->debtor_check_consent" :label="__('The guardian agrees that other NAPPS member schools may ask whether fees are outstanding.')" />
                    <p class="mt-1 text-sm text-stone-500">{{ __('Other schools only ever learn “clear” or “outstanding” and a total, never your school, the child or the invoices. Needs a guardian phone number.') }}</p>
                </div>

                <x-ui.button type="submit" icon-right="check">{{ $editing ? __('Save changes') : __('Enrol pupil') }}</x-ui.button>
            </form>
        </x-ui.card>

        <x-ui.side-panel :title="__('About these records')">
            <ul class="space-y-3 text-sm text-stone-700">
                <li>{{ __('Only people with a role at this school can see these records.') }}</li>
                <li>{{ __('Changes, document views and debtor checks are written to the audit log (NDPA).') }}</li>
                <li>{{ __('Removing a pupil archives the record; it is not erased.') }}</li>
            </ul>
        </x-ui.side-panel>
    </div>
</x-layouts.app>

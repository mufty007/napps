<x-layouts.app :title="__('Home')">
    <x-ui.page-header :title="__('Assigned cases')" :description="__('Claim files that member schools have sent to you.')" />

    <x-ui.card>
        <x-ui.empty-state icon="scale" :title="__('No cases assigned')" :description="__('When a school sends you a claim file, it appears here with its stage and documents.')" />
    </x-ui.card>
</x-layouts.app>

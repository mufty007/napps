<x-layouts.app :title="__('Home')">
    <x-ui.page-header :title="__('My children')" :description="__('Balances, receipts, report cards and notices from your children\'s schools.')" />

    <x-ui.card>
        <x-ui.empty-state
            icon="graduation-cap"
            :title="__('No children linked yet')"
            :description="__('Your child\'s school links them to your phone number when they enrol. Contact the school bursar if you expected to see them here.')"
        />
    </x-ui.card>

    <x-dashboard.widgets :widgets="$widgets" />
</x-layouts.app>

@php
    $problem = auth()->user()?->canManageLeads() ? \App\Support\MailSettings::deliveryProblem() : null;
@endphp

@if ($problem)
    <x-filament::callout color="danger" icon="heroicon-o-exclamation-triangle" heading="Lead emails need attention" :description="$problem" style="margin-bottom: 1.5rem">
        <x-slot name="footer">
            <div style="display: flex; gap: 1rem; flex-wrap: wrap">
                @if (auth()->user()->isAdmin())
                    <x-filament::link :href="\App\Filament\Pages\ManageEmail::getUrl()">Check email settings</x-filament::link>
                @endif
                <x-filament::link :href="\App\Filament\Resources\Leads\LeadResource::getUrl('index', ['tab' => 'all', 'filters' => ['emailed' => ['value' => '0']]])">See the enquiries</x-filament::link>
            </div>
        </x-slot>
    </x-filament::callout>
@endif

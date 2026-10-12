<?php

namespace App\Filament\Resources\Leads\Pages;

use App\Filament\Resources\Leads\LeadResource;
use App\Models\Lead;
use App\Support\CsvExport;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListLeads extends ListRecords
{
    protected static string $resource = LeadResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('export')->label('Export CSV')->icon('heroicon-o-arrow-down-tray')->color('gray')
                ->action(fn () => CsvExport::download('leads', $this->getFilteredSortedTableQuery()->with(['service', 'assignee']), [
                    'created_at' => 'Received', 'name' => 'Name', 'company' => 'Company', 'email' => 'Email', 'phone' => 'Phone',
                    'country' => 'Country', 'requirement' => 'Needs', 'service.title' => 'Service', 'product' => 'Product', 'timeline' => 'Timeline',
                    'status' => 'Status', 'assignee.name' => 'Owner', 'follow_up_at' => 'Follow-up', 'value' => 'Value',
                    'source_page' => 'Source page', 'utm_source' => 'UTM source', 'utm_medium' => 'UTM medium',
                    'utm_campaign' => 'UTM campaign', 'utm_term' => 'Search keyword', 'gclid' => 'Google click ID', 'ip' => 'IP address', 'message' => 'Message',
                ])),
            CreateAction::make(),
        ];
    }

    public function getTabs(): array
    {
        return [
            'open' => Tab::make('Open')->badge(Lead::open()->count())
                ->modifyQueryUsing(fn (Builder $query) => $query->open()),
            'overdue' => Tab::make('Follow-up due')->badge(Lead::overdue()->count() ?: null)->badgeColor('danger')
                ->modifyQueryUsing(fn (Builder $query) => $query->overdue()),
            'mine' => Tab::make('Assigned to me')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('assigned_to', auth()->id())),
            'won' => Tab::make('Won')->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'won')),
            'all' => Tab::make('All'),
        ];
    }
}

<?php

namespace App\Filament\Resources\Leads\Schemas;

use App\Models\Lead;
use App\Support\Privacy;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ToggleButtons;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class LeadForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->components([
                Grid::make(1)->columnSpan(2)->schema([
                    Section::make('Contact')
                        ->columns(2)
                        ->schema([
                            TextInput::make('name')->required(),
                            TextInput::make('company'),
                            TextInput::make('email')->email(),
                            TextInput::make('phone')->tel(),
                            TextInput::make('country'),
                            Select::make('manufacturer_type')->options([
                                'indian' => 'Indian manufacturer',
                                'foreign' => 'Foreign manufacturer',
                                'importer' => 'Importer / brand owner',
                                'other' => 'Other',
                            ]),
                        ]),
                    Section::make('Requirement')
                        ->columns(2)
                        ->schema([
                            Select::make('requirement')->label('Needs')->options(Lead::REQUIREMENTS),
                            Select::make('service_path')
                                ->label('Service')
                                ->relationship('service', 'title')
                                ->searchable()
                                ->preload(),
                            TextInput::make('product'),
                            Select::make('timeline')->options([
                                'urgent' => 'Urgent (< 1 month)',
                                '1-3-months' => '1–3 months',
                                '3-6-months' => '3–6 months',
                                'exploring' => 'Just exploring',
                            ]),
                            Select::make('type')->options(Lead::TYPES)->required(),
                            Textarea::make('message')->rows(4)->columnSpanFull(),
                        ]),
                ]),
                Grid::make(1)->columnSpan(1)->schema([
                    Section::make('Pipeline')->schema([
                        ToggleButtons::make('status')
                            ->options(Lead::STATUSES)
                            ->colors([
                                'new' => 'info',
                                'contacted' => 'warning',
                                'qualified' => 'primary',
                                'proposal' => 'primary',
                                'won' => 'success',
                                'lost' => 'danger',
                            ])
                            ->live()
                            ->required(),
                        TextInput::make('lost_reason')
                            ->visible(fn (Get $get) => $get('status') === 'lost')
                            ->datalist(['Price', 'Went with competitor', 'No response', 'Not eligible', 'Project cancelled']),
                        Select::make('assigned_to')
                            ->label('Owner')
                            ->relationship('assignee', 'name')
                            ->searchable()
                            ->preload(),
                        DateTimePicker::make('follow_up_at')
                            ->label('Next follow-up')
                            ->seconds(false)
                            ->helperText('Shows up as overdue on the dashboard once this passes.'),
                        TextInput::make('value')->label('Deal value')->numeric()->prefix('₹'),
                        Textarea::make('notes')->rows(5)->helperText('Internal only — never shown to the customer.'),
                    ]),
                    Section::make('Attribution')
                        ->collapsed()
                        ->schema([
                            TextInput::make('source_page')->disabled(),
                            TextInput::make('referrer')->disabled(),
                            TextInput::make('utm_source')->disabled(),
                            TextInput::make('utm_medium')->disabled(),
                            TextInput::make('utm_campaign')->disabled(),
                            TextInput::make('utm_term')->label('Search keyword')->disabled(),
                            TextInput::make('gclid')->label('Google Ads click ID')->disabled(),
                        ]),
                    Section::make('Consent record')
                        ->collapsed()
                        ->description('What this person agreed to, for DPDP/GDPR requests.')
                        ->schema([
                            Toggle::make('consent')->label('Agreed to be contacted')->disabled(),
                            Toggle::make('marketing_opt_in')->label('Opted in to regulatory updates')->disabled(),
                            TextInput::make('consent_version')->label('Privacy notice version')->disabled(),
                            DateTimePicker::make('consented_at')->label('Consent given')->disabled(),
                            TextInput::make('ip')->label('Submitted from IP')->placeholder('Not recorded / removed')->disabled()
                                ->helperText(fn () => 'Removed automatically after '.Privacy::settings()['ipDays'].' days (Site settings → Privacy & cookies).'),
                            Textarea::make('user_agent')->label('Browser')->rows(2)->placeholder('—')->disabled(),
                            DateTimePicker::make('anonymised_at')->label('Anonymised')->disabled()
                                ->visible(fn (?Lead $record) => (bool) $record?->anonymised_at),
                        ]),
                ]),
            ]);
    }
}

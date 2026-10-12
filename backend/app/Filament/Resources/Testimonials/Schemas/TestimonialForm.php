<?php

namespace App\Filament\Resources\Testimonials\Schemas;

use App\Filament\Components\ImageUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ToggleButtons;
use Filament\Schemas\Schema;

class TestimonialForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')->required(),
                TextInput::make('context')->label('Service · company')->placeholder('BIS CRS · Electronics brand')
                    ->helperText('Name the service: landing pages for that service (BIS, MSDS, EPR…) show the review first.'),
                Textarea::make('quote')->required()->rows(4)->columnSpanFull()
                    ->helperText('A real client’s words only. Google Ads can suspend ads for made-up reviews.'),
                ToggleButtons::make('rating')->options([1 => '1★', 2 => '2★', 3 => '3★', 4 => '4★', 5 => '5★'])->inline()->default(5)->required(),
                ImageUpload::make('photo', 'testimonials')->avatar(),
                Toggle::make('is_featured')->label('Feature on homepage'),
                Toggle::make('is_published')->label('Live')->default(true)
                    ->helperText('Live reviews show across the whole website: home page, service pages, landing pages and the star rating faces.'),
            ]);
    }
}

<?php

namespace App\Filament\Components;

use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;

class FaqRepeater
{
    public static function make(string $name = 'faqs'): Repeater
    {
        return Repeater::make($name)
            ->label('FAQs')
            ->helperText('Shown on the page and as FAQ rich results in Google.')
            ->schema([
                TextInput::make('q')->label('Question')->required(),
                Textarea::make('a')->label('Answer')->rows(3)->required(),
            ])
            ->itemLabel(fn (array $state) => $state['q'] ?? null)
            ->collapsed()
            ->reorderable()
            ->addActionLabel('Add FAQ')
            ->defaultItems(0);
    }
}

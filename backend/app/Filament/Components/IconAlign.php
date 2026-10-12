<?php

namespace App\Filament\Components;

use Filament\Forms\Components\ToggleButtons;
use Filament\Support\Icons\Heroicon;

/** Where a section's icons (and the text under or beside them) sit in each card. */
class IconAlign
{
    public const OPTIONS = ['left' => 'Left', 'center' => 'Centre', 'right' => 'Right'];

    public static function make(string $name = 'iconAlign', string $default = 'left'): ToggleButtons
    {
        return ToggleButtons::make($name)
            ->label('Icon position')
            ->options(self::OPTIONS)
            ->icons(['left' => Heroicon::OutlinedBars3BottomLeft, 'center' => Heroicon::OutlinedBars3, 'right' => Heroicon::OutlinedBars3BottomRight])
            ->inline()
            ->grouped()
            ->default($default)
            ->formatStateUsing(fn (?string $state) => $state ?: $default)
            ->in(array_keys(self::OPTIONS))
            ->helperText('Applies to every card in this section; the text lines up with the icon.');
    }
}

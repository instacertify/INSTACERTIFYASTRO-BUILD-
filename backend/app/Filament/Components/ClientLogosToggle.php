<?php

namespace App\Filament\Components;

use Filament\Forms\Components\Toggle;

/** Per page: the "Trusted by" client logo strip after the content, using the logos kept on the home page settings. */
class ClientLogosToggle
{
    public static function make(): Toggle
    {
        return Toggle::make('show_client_logos')
            ->label('Show client logos after the content')
            ->helperText('The scrolling "Trusted by" strip. Add, remove or reorder the logos in Website → Home page → Numbers & logos → Client logos.');
    }
}

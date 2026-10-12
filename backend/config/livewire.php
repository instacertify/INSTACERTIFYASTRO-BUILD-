<?php

/*
 * Only the settings that differ from Livewire's defaults; Livewire merges the rest in. The whole "payload" group is
 * repeated because the merge replaces top-level keys.
 */
return [

    'payload' => [
        'max_size' => 1024 * 1024,
        // Tables in the rich editor (and the table block's own editor inside a modal) nest deeper than the default 10.
        'max_nesting_depth' => 32,
        'max_calls' => 50,
        'max_components' => 200,
    ],

];

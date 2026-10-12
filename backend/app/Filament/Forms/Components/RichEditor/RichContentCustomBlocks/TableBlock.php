<?php

namespace App\Filament\Forms\Components\RichEditor\RichContentCustomBlocks;

use Filament\Actions\Action;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\RichEditor\RichContentRenderer;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;

class TableBlock extends SizedBlock
{
    private const STARTER = '<table><tbody><tr><th><p>Heading</p></th><th><p>Heading</p></th><th><p>Heading</p></th></tr><tr><td><p></p></td><td><p></p></td><td><p></p></td></tr><tr><td><p></p></td><td><p></p></td><td><p></p></td></tr></tbody></table>';

    public static function getId(): string
    {
        return 'table';
    }

    public static function getLabel(): string
    {
        return 'Table with size';
    }

    public static function getIcon(): Heroicon
    {
        return Heroicon::OutlinedTableCells;
    }

    public static function configureEditorAction(Action $action): Action
    {
        return $action
            ->modalWidth(Width::FourExtraLarge)
            ->modalDescription('Type into the table, add or remove rows and columns with the buttons above it, then pick its size.')
            ->schema([
                RichEditor::make('table')->label('Table')->required()->default(self::STARTER)
                    ->toolbarButtons([
                        ['bold', 'italic', 'link'],
                        ['tableAddRowAfter', 'tableDeleteRow', 'tableAddColumnAfter', 'tableDeleteColumn', 'tableMergeCells', 'tableSplitCell', 'tableToggleHeaderRow'],
                        ['undo', 'redo'],
                    ])
                    ->helperText('Click a cell first, then use the row and column buttons. The first row is the heading row.'),
                self::sizeField('full'),
                self::alignField(),
                self::captionField(),
            ]);
    }

    public static function getPreviewLabel(array $config): string
    {
        return 'Table · '.self::SIZES[self::size($config)].(filled($config['caption'] ?? null) ? ' · '.$config['caption'] : '');
    }

    public static function shouldApplyProseStylingToPreview(array $config): bool
    {
        return true;
    }

    public static function toPreviewHtml(array $config): string
    {
        return '<div style="width: '.self::previewWidth($config).'; margin: '.self::previewMargin($config).'; overflow-x: auto;">'.self::table($config).'</div>';
    }

    public static function toHtml(array $config, array $data): string
    {
        $table = self::table($config);
        if (! str_contains($table, '<table')) {
            return '';
        }

        return view('filament.forms.components.rich-editor.rich-content-custom-blocks.table.index', [
            'table' => $table,
            'caption' => $config['caption'] ?? null,
            'classes' => self::classes('table', $config),
        ])->render();
    }

    /** The table as clean HTML: it is parsed and rebuilt by the editor's own renderer, so nothing else gets through. */
    private static function table(array $config): string
    {
        $table = $config['table'] ?? '';

        return filled($table) && (is_string($table) || is_array($table)) ? RichContentRenderer::make($table)->toHtml() : '';
    }
}

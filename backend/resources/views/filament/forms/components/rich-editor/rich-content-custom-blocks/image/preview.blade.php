<div style="width: {{ $width }}; margin: {{ $margin }};">
    @if ($src)
        <img src="{{ $src }}" alt="{{ $alt }}" style="display: block; width: 100%; height: auto; margin: 0; border-radius: 0.75rem;" />
        @if (filled($caption))
            <p style="margin: 0.5rem 0 0; font-size: 0.875rem; color: #64748b; text-align: center;">{{ $caption }}</p>
        @endif
    @else
        <p style="margin: 0; color: #b91c1c;">No image uploaded. Click the block to add one.</p>
    @endif
</div>

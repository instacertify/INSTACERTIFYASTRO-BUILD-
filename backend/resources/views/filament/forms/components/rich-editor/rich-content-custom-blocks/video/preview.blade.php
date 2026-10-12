<div style="width: {{ $width }}; margin: {{ $margin }};">
    @if ($video)
        <div style="position: relative; aspect-ratio: {{ $video['portrait'] ? '9 / 16' : '16 / 9' }}; max-height: 28rem; border-radius: 0.75rem; overflow: hidden; background: #0f172a;">
            <img src="{{ $video['thumbnail'] }}" alt="" style="width: 100%; height: 100%; object-fit: cover; margin: 0;" />
            <span style="position: absolute; inset: 0; display: flex; align-items: center; justify-content: center;">
                <span style="display: flex; width: 3.5rem; height: 2.5rem; align-items: center; justify-content: center; border-radius: 0.75rem; background: #dc2626; color: #fff; font-size: 1.25rem;">▶</span>
            </span>
        </div>
        @if (filled($title))
            <p style="margin: 0.5rem 0 0; font-size: 0.875rem; color: #475569;">{{ $title }}</p>
        @endif
    @else
        <p style="margin: 0; color: #b91c1c;">This YouTube link doesn’t work. Click the block to fix it.</p>
    @endif
</div>

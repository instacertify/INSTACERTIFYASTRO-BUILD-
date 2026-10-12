<figure class="{{ $classes }}">
    {!! $table !!}
    @if (filled($caption))
        <figcaption>{{ $caption }}</figcaption>
    @endif
</figure>

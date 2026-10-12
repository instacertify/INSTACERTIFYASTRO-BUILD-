<figure class="{{ $classes }}">
    <img src="{{ $src }}" @if ($width && $height) width="{{ $width }}" height="{{ $height }}" @endif alt="{{ $alt }}" loading="lazy" decoding="async" />
    @if (filled($caption))
        <figcaption>{{ $caption }}</figcaption>
    @endif
</figure>

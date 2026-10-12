<figure class="{{ $classes }}">
    <div class="embed-frame"><iframe src="{{ $video['embed'] }}" title="{{ $title }}" loading="lazy" allow="accelerometer; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" referrerpolicy="strict-origin-when-cross-origin" allowfullscreen></iframe></div>
    @if (filled($caption))
        <figcaption>{{ $caption }}</figcaption>
    @endif
</figure>

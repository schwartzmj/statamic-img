@if(isset($noOptimize) && $noOptimize == true)
    <img src="{{ $asset->url }}" alt="{{ $asset->alt || '' }}" width="{{ $asset->width }}" height="{{ $asset->height }}"
        @isset($rest)  @foreach($rest as $key => $value) {{$key}}="{{$value}}"  @endforeach @endisset
        loading="{{ $priority ? 'eager' : 'lazy' }}"
    />
@else
<picture>
    <source
        srcset="{{ $webpSources }}"
        sizes="{{ $sizes }}"
        type="image/webp"
        />
        <source
        srcset="{{ $originalSources }}"
        sizes="{{ $sizes }}"
        type="{{ $asset->mime_type }}"
    />
    <img
        @isset($rest) @foreach($rest as $key => $value) {{$key}}="{{$value}}"  @endforeach @endisset
        src="{{ $defaultImgSrc }}"
        alt="{{ $asset->alt }}"
        width="{{ $asset->width }}"
        height="{{ $asset->height }}"
        loading="{{ $priority ? 'eager' : 'lazy' }}"
    />
</picture>
@endif

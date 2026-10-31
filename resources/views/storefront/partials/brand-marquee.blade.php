<section class="brand-marquee" aria-label="Nuestras marcas" data-brand-marquee>
    <div class="brand-marquee-heading"><h3>Nuestras marcas</h3><button type="button" data-brand-pause aria-pressed="false">Pausar movimiento</button></div>
    <div class="brand-marquee-window"><div class="brand-marquee-track">
        @for($copy = 0; $copy < 2; $copy++)
        <div class="brand-marquee-group" @if($copy) aria-hidden="true" @endif>
            @foreach(['Volvo'=>'volvo','Komatsu'=>'komatsu','Caterpillar'=>'caterpillar','Cummins'=>null,'Mack'=>null,'Sany'=>null] as $name=>$logo)
            <a href="{{ \App\Support\StorefrontUrl::catalog(null, ['marca'=>$name]) }}" @if($copy) tabindex="-1" @endif aria-label="Repuestos {{ $name }}">
                @if($logo)<img src="{{ asset('storefront/images/brands/'.$logo.'.svg') }}" alt="{{ $name }}" width="160" height="48" loading="lazy">@else<span>{{ $name }}</span>@endif
            </a>
            @endforeach
        </div>
        @endfor
    </div></div>
</section>

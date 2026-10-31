<a href="{{ route('storefront.home') }}" class="cp-brand" aria-label="Quantum Soluciones, ir al inicio"><img src="{{ asset('storefront/images/logo.webp') }}" alt="" width="40" height="44"><img src="{{ asset('storefront/images/letras.webp') }}" alt="Quantum Soluciones" width="142" height="36"></a>
<div class="cp-sidebar-body">
    <div class="cp-sidebar-heading">Tu espacio Quantum</div>
    <nav class="cp-nav" aria-label="Secciones del portal">
        @foreach($navItems as $key => $item)
            <button type="button" wire:click="setView('{{ $key }}')" @if($mobile) x-on:click="$refs.navigation.close()" @endif class="cp-nav-item {{ $view === $key ? 'is-active' : '' }}" @if($view === $key) aria-current="page" @endif><x-portal.icon :name="$item['icon']" /><span>{{ $item['label'] }}</span>@if($key === 'quotations' && $quotations->count())<span class="cp-nav-count">{{ $quotations->count() }}</span>@elseif($key === 'tickets' && $openTickets)<span class="cp-nav-count">{{ $openTickets }}</span>@endif</button>
        @endforeach
    </nav>
    <a class="cp-sidebar-catalog" href="{{ route('storefront.products.index') }}"><x-portal.icon name="plus" /><span>Cotizar repuestos</span><x-portal.icon name="arrow" /></a>
</div>
<div class="cp-sidebar-bottom"><div class="cp-sidebar-account"><span class="cp-avatar">{{ $initials }}</span><span><strong>{{ $user->name }}</strong><small>{{ $user->email }}</small></span></div><form method="POST" action="{{ route('logout') }}">@csrf<button type="submit" class="cp-logout"><x-portal.icon name="logout" />Cerrar sesión</button></form></div>

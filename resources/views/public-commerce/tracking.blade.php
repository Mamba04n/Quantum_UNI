@extends('public-commerce.layout')
@section('title','Seguimiento de pedido')
@section('content')
<div class="tracking-heading"><div><p class="eyebrow">Quantum · Entregas</p><h1>Seguimiento de tu pedido</h1><p class="muted">De nuestra bodega hasta tus manos.</p></div>
<form action="{{ route('public.tracking') }}" method="GET" class="tracking-search"><label for="tracking-code">Código de seguimiento</label><div class="search"><input id="tracking-code" name="code" value="{{ $code }}" placeholder="QS-…" maxlength="16" required autocomplete="off"><button>Consultar</button></div></form></div>
@if($data)
<section class="tracking-overview"><div><p class="eyebrow">{{ $data['code'] }}</p><h2>{{ $data['labels'][$data['status']] }}</h2>@if($data['events']->isNotEmpty())<p class="muted">Actualizado {{ $data['events']->first()->created_at->format('d/m/Y') }} a las {{ $data['events']->first()->created_at->format('H:i') }}</p>@endif</div><span class="status-indicator"><span aria-hidden="true"></span>{{ $data['status']==='delivered'?'Entrega completada':'Pedido en curso' }}</span></section>
<ol class="steps">
@php $current=array_search($data['status'],array_keys($data['labels']),true); @endphp
@foreach($data['labels'] as $status=>$label)<li class="{{ $loop->index<=$current?'reached':'' }}" @if($status===$data['status']) aria-current="step" @endif><span aria-hidden="true">{{ $loop->index<$current?'✓':$loop->iteration }}</span><div>{{ $label }}@if($status===$data['status'])<small>Estado actual</small>@elseif($loop->index<$current)<small>Completado</small>@else<small>Por completar</small>@endif</div></li>@endforeach</ol>
<div class="tracking-detail-grid"><section class="tracking-history"><h2>Actualizaciones</h2><p class="muted">El recorrido de tu pedido.</p><ol class="history">@foreach($data['events'] as $event)<li><time datetime="{{ $event->created_at->toIso8601String() }}">{{ $event->created_at->format('d/m/Y') }}<small>{{ $event->created_at->format('H:i') }}</small></time><div>{{ $event->public_message }}</div></li>@endforeach</ol></section>
<aside class="delivery-details"><h2>Tu entrega</h2><dl><div><dt>Pedido</dt><dd>{{ $data['count'] }} {{ $data['count']===1?'producto':'productos' }}</dd></div>@if($data['estimated'])<div><dt>Entrega estimada</dt><dd>{{ \Carbon\Carbon::parse($data['estimated'])->format('d/m/Y') }}</dd></div>@endif<div><dt>Código de seguimiento</dt><dd class="tracking-code">{{ $data['code'] }}</dd></div></dl><a class="support-link" href="{{ url('/contacto') }}">Consultar sobre mi entrega →</a></aside></div>
@elseif($code!=='')<div class="empty-result"><h2>Revisa tu código</h2><p role="status">No encontramos un pedido con ese código. Revisa el número e inténtalo nuevamente.</p></div>
@else<p class="tracking-intro">Introduce el código que recibiste para consultar el estado de tu pedido. No necesitas iniciar sesión.</p>@endif
@endsection

<div class="preliq-price-control">
    @foreach(['NIO' => 'assigned_nio', 'USD' => 'assigned_usd'] as $currency => $field)
        <input x-show="priceCurrency === '{{ $currency }}'" x-cloak
               aria-label="Precio final unitario {{ $currency === 'NIO' ? 'C$' : 'USD' }}"
               aria-describedby="price-help-{{ $key }}" type="number" min="0.01" step="0.01"
               wire:model.live.blur="{{ $group }}.{{ $key }}.{{ $field }}"
               @disabled(($row['item_status'] ?? '') === 'not_quoted')
               class="{{ $control }} text-right tabular-nums font-semibold"
               placeholder="{{ number_format($result[$currency === 'NIO' ? 'price_nio' : 'price'] ?? 0, 2, '.', '') }}"
               title="Precio por unidad. Deja vacío para usar el margen de utilidad.">
    @endforeach
    <select x-model="priceCurrency" aria-label="Moneda del precio final unitario" class="{{ $control }}" @disabled(($row['item_status'] ?? '') === 'not_quoted')>
        <option value="NIO">C$</option><option value="USD">USD</option>
    </select>
</div>
<p id="price-help-{{ $key }}" class="preliq-price-note text-right tabular-nums">
    <span x-show="priceCurrency === 'NIO'" x-cloak>USD {{ number_format($result['price'] ?? 0, 2) }}</span>
    <span x-show="priceCurrency === 'USD'" x-cloak>C$ {{ number_format($result['price_nio'] ?? 0, 2) }}</span>
    <span class="text-gray-400">· {{ (float) ($row['assigned_nio'] ?? 0) > 0 || (float) ($row['assigned_usd'] ?? 0) > 0 ? 'Manual' : 'Por margen' }}</span>
</p>
@foreach(['assigned_nio', 'assigned_usd'] as $field)
    @error($group.'.'.$key.'.'.$field)<span class="block whitespace-normal text-xs text-rose-700">{{ $message }}</span>@enderror
@endforeach

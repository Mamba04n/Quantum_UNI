<?php

namespace App\Services;

class PreliquidationCalculator
{
    public static function defaults(): array
    {
        return ['version' => 2, 'transport' => 'air', 'taxed' => true, 'handling' => 0, 'freight' => 0,
            'commission' => 4, 'financing' => 0, 'iva' => 15, 'dai' => 5, 'isc' => 5,
            'customs' => 90, 'local_transport' => 30, 'margin' => 30, 'exchange' => null];
    }

    public function forResponse(\App\Models\SupplierBidResponse $response): array
    {
        $rows = [];
        foreach ($response->items as $item) {
            $rows[$item->id] = [
                'quoted_part_number' => $item->quoted_part_number, 'quoted_quantity' => $item->quoted_quantity,
                'unit_cost' => $item->unit_cost, 'item_status' => $item->item_status,
                'weight_lb' => $response->preliquidation['weights'][$item->id] ?? 0,
                'assigned_usd' => $response->preliquidation['assigned'][$item->id]['usd'] ?? null,
                'assigned_nio' => $response->preliquidation['assigned'][$item->id]['nio'] ?? null,
            ];
        }
        return $this->calculate($response->preliquidation['settings'] ?? self::defaults(), $rows);
    }

    public function calculate(array $settings, array $items): array
    {
        $s = array_replace(self::defaults(), $settings);
        $legacy = ($settings['version'] ?? 1) < 2;
        $weight = 0;
        foreach ($items as $item) {
            if (($item['item_status'] ?? '') !== 'not_quoted') {
                $weight += max(0, (float) ($item['weight_lb'] ?? 0)) * max(0, (float) ($item['quoted_quantity'] ?? 0));
            }
        }
        $rate = ((float) $s['commission'] + (float) $s['financing'] + ($s['taxed'] ? (float) $s['iva'] + (float) $s['dai'] + (float) $s['isc'] : 0)) / 100;
        $factor = 1 - (float) $s['margin'] / 100;
        $handlingRate = $legacy ? ($weight > 0 ? (float) $s['handling'] / $weight : 0) : (float) $s['handling'];
        $freightRate = $legacy ? ($weight > 0 ? (float) $s['freight'] / $weight : 0) : (float) $s['freight'];
        $rows = [];
        $exw = $ddpTotal = $sales = 0;
        foreach ($items as $key => $item) {
            if (($item['item_status'] ?? '') === 'not_quoted') {
                continue;
            }
            $qty = max(0, (float) ($item['quoted_quantity'] ?? 0));
            $cost = max(0, (float) ($item['unit_cost'] ?? 0));
            $share = $weight > 0 ? max(0, (float) ($item['weight_lb'] ?? 0)) / $weight : 0;
            $unitWeight = max(0, (float) ($item['weight_lb'] ?? 0));
            $cif = $cost + ($handlingRate + $freightRate) * $unitWeight;
            $ddp = $cif * (1 + $rate) + ((float) $s['customs'] + (float) $s['local_transport']) * $share;
            $price = $factor > 0 ? $ddp / $factor : 0;
            $suggestedPrice = $price;
            $exchange = (float) $s['exchange'];
            $assignedUsd = max(0, (float) ($item['assigned_usd'] ?? 0));
            $assignedNio = max(0, (float) ($item['assigned_nio'] ?? 0));
            if ($assignedUsd > 0) {
                $price = $assignedUsd;
            } elseif ($assignedNio > 0 && $exchange > 0) {
                $price = $assignedNio / $exchange;
            }
            $priceNio = $assignedUsd <= 0 && $assignedNio > 0 ? $assignedNio : $price * $exchange;
            $rows[$key] = ['cif' => $cif, 'ddp' => $ddp, 'price' => $price, 'total' => $price * $qty];
            $issues = [];
            if (trim((string) ($item['quoted_part_number'] ?? '')) === '') $issues[] = 'Sin Part Number';
            if ($unitWeight <= 0) $issues[] = 'Falta peso';
            if ($cost <= 0) $issues[] = 'Falta precio';
            if ($qty <= 0) $issues[] = 'Falta cantidad';
            $rows[$key] += ['subtotal_exw' => $cost * $qty, 'total_weight' => $unitWeight * $qty,
                'cif_nio' => $cif * (float) $s['exchange'], 'ddp_nio' => $ddp * (float) $s['exchange'],
                'price_nio' => $priceNio, 'total_nio' => $priceNio * $qty,
                'suggested_price' => $suggestedPrice,
                'assigned_unit' => $assignedUsd > 0 || $assignedNio > 0 ? $price : 0,
                'assigned_total' => $assignedUsd > 0 || $assignedNio > 0 ? $price * $qty : 0,
                'assigned_unit_nio' => $assignedUsd > 0 || $assignedNio > 0 ? $priceNio : 0,
                'assigned_total_nio' => $assignedUsd > 0 || $assignedNio > 0 ? $priceNio * $qty : 0,
                'net_profit' => ($price - $ddp) * $qty, 'gross_profit' => ($price - $cost) * $qty,
                'gross_margin' => $price > 0 ? (1 - $cost / $price) * 100 : 0,
                'issues' => $issues,
                'breakdown' => ['EXW' => $cost, 'Manejo' => $handlingRate * $unitWeight, 'Freight' => $freightRate * $unitWeight,
                    'Comisión' => $cif * (float) $s['commission'] / 100, 'Financiamiento' => $cif * (float) $s['financing'] / 100,
                    'IVA' => $s['taxed'] ? $cif * (float) $s['iva'] / 100 : 0,
                    'DAI' => $s['taxed'] ? $cif * (float) $s['dai'] / 100 : 0,
                    'ISC' => $s['taxed'] ? $cif * (float) $s['isc'] / 100 : 0,
                    'Desaduanaje' => (float) $s['customs'] * $share, 'Transporte' => (float) $s['local_transport'] * $share]];
            $exw += $cost * $qty;
            $ddpTotal += $ddp * $qty;
            $sales += $price * $qty;
        }
        $base = $exw + ($handlingRate + $freightRate) * $weight;
        $costs = ['handling' => $handlingRate * $weight, 'freight' => $freightRate * $weight,
            'customs' => (float) $s['customs'], 'local_transport' => (float) $s['local_transport']];
        foreach (['commission', 'financing', 'iva', 'dai', 'isc'] as $tax) {
            $costs[$tax] = in_array($tax, ['iva', 'dai', 'isc']) && !$s['taxed'] ? 0 : $base * (float) $s[$tax] / 100;
        }
        $summary = ['exw' => $exw, 'cif' => $base, 'ddp' => $ddpTotal, 'sales' => $sales, 'gross_profit' => $sales - $exw, 'net_profit' => $sales - $ddpTotal];
        return ['rows' => $rows, 'costs' => $costs,
            'costs_nio' => array_map(fn ($amount) => $amount * (float) $s['exchange'], $costs),
            'cost_rates' => array_map(fn ($amount) => $weight > 0 ? $amount / $weight : 0, $costs),
            'nio' => array_map(fn ($amount) => $amount * (float) $s['exchange'], $summary),
            'divisor' => $factor, 'count' => count($rows),
            'complete' => count(array_filter($rows, fn ($row) => $row['issues'] === [])),
            'gross_margin' => $sales > 0 ? (1 - $exw / $sales) * 100 : 0,
            'net_margin' => $sales > 0 ? (1 - $ddpTotal / $sales) * 100 : 0,
            'weight' => $weight, 'exw' => $exw, 'cif' => $base, 'cif_nio' => $base * (float) $s['exchange'], 'ddp' => $ddpTotal,
            'sales' => $sales, 'sales_nio' => $sales * (float) $s['exchange'],
            'gross_profit' => $sales - $exw, 'net_profit' => $sales - $ddpTotal];
    }
}

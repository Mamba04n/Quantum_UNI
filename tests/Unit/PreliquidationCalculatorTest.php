<?php

namespace Tests\Unit;

use App\Services\PreliquidationCalculator;
use PHPUnit\Framework\TestCase;

class PreliquidationCalculatorTest extends TestCase
{
    private function settings(array $overrides = []): array
    {
        return array_replace(PreliquidationCalculator::defaults(), ['freight' => 3, 'exchange' => 37], $overrides);
    }

    public function test_matches_all_cached_excel_rows_and_supplier_totals_for_both_transports(): void
    {
        $sheets = json_decode(file_get_contents(__DIR__.'/../Fixtures/preliquidation-excel.json'), true);
        foreach ($sheets as $mode => $sheet) {
            $actual = (new PreliquidationCalculator)->calculate($this->settings(['transport' => $mode, 'freight' => $mode === 'air' ? 3 : 1]), $sheet['rows']);
            foreach ($sheet['expected'] as $field => $expected) $this->assertEqualsWithDelta($expected, $actual[$field], 0.000001, "$mode $field");
            foreach ($sheet['rows'] as $index => $row) {
                foreach ($row['expected'] as $field => $expected) $this->assertEqualsWithDelta($expected, $actual['rows'][$index][$field], 0.000001, "$mode row $index $field");
            }
        }
    }

    public function test_one_item_and_dependency_changes_are_scoped_to_supplier(): void
    {
        $calc = new PreliquidationCalculator;
        $row = ['quoted_part_number' => '332/A2428', 'quoted_quantity' => 2, 'unit_cost' => 3.46, 'weight_lb' => .07];
        $settings = $this->settings(['customs' => 0, 'local_transport' => 0]);
        $baseline = $calc->calculate($settings, [$row, $row]);
        $this->assertEqualsWithDelta(6.92, $baseline['rows'][0]['subtotal_exw'], .000001);
        $this->assertEqualsWithDelta(3.67, $baseline['rows'][0]['cif'], .000001);
        $this->assertEqualsWithDelta(3.67 * 1.29 / .7, $baseline['rows'][0]['price'], .000001);
        foreach (['unit_cost' => 4, 'weight_lb' => .1, 'quoted_quantity' => 4] as $field => $value) {
            $changed = $calc->calculate($settings, [array_replace($row, [$field => $value]), $row]);
            $this->assertSame($baseline['rows'][1], $changed['rows'][1]);
            $this->assertNotSame($baseline['sales'], $changed['sales']);
        }
        foreach (['freight' => 2.75, 'margin' => 40] as $field => $value) {
            $changed = $calc->calculate(array_replace($settings, [$field => $value]), [$row, $row]);
            $this->assertNotSame($baseline['rows'][0]['price'], $changed['rows'][0]['price']);
            $this->assertNotSame($baseline['rows'][1]['price'], $changed['rows'][1]['price']);
            $this->assertSame($baseline, $calc->calculate($settings, [$row, $row]), 'Other supplier remains unchanged');
        }
        $fx = $calc->calculate(array_replace($settings, ['exchange' => 40]), [$row, $row]);
        $this->assertSame($baseline['sales'], $fx['sales']);
        $this->assertEqualsWithDelta($baseline['sales'] * 40, $fx['sales_nio'], .000001);
    }

    public function test_total_weight_allocations_legacy_rates_and_tax_exemption(): void
    {
        $calc = new PreliquidationCalculator;
        $rows = [['quoted_quantity' => 2, 'unit_cost' => 100, 'weight_lb' => 10], ['quoted_quantity' => 3, 'unit_cost' => 20, 'weight_lb' => 5]];
        $settings = $this->settings();
        $base = $calc->calculate($settings, $rows);
        $this->assertEqualsWithDelta($base['exw'] + array_sum($base['costs']), $base['ddp'], .000001);
        $legacy = $calc->calculate(array_replace($settings, ['version' => 1, 'freight' => 105]), $rows);
        $this->assertSame($base['ddp'], $legacy['ddp']);
        $untaxed = $calc->calculate(array_replace($settings, ['taxed' => false]), $rows);
        foreach (['iva', 'dai', 'isc'] as $tax) $this->assertEquals(0, $untaxed['costs'][$tax]);
        $this->assertGreaterThan(0, $untaxed['costs']['commission']);
        $rows[0]['weight_lb'] = 20;
        $changed = $calc->calculate($settings, $rows);
        $this->assertSame($base['rows'][1]['cif'], $changed['rows'][1]['cif']);
        $this->assertNotSame($base['rows'][1]['ddp'], $changed['rows'][1]['ddp'], 'Shared fixed costs redistribute with total weight');
    }
}

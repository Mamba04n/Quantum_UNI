<?php

namespace Tests\Unit;

use App\Livewire\Compras\PurchaseOrderForm;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class PurchaseOrderSpreadsheetNumberTest extends TestCase
{
    #[DataProvider('spreadsheetNumbers')]
    public function test_it_preserves_spreadsheet_numeric_values(mixed $input, float $expected): void
    {
        $parser = new ReflectionMethod(PurchaseOrderForm::class, 'parseSpreadsheetNumber');

        $this->assertSame($expected, $parser->invoke(new PurchaseOrderForm, $input));
    }

    public static function spreadsheetNumbers(): array
    {
        return [
            'raw integer' => [90000, 90000.0],
            'raw decimal' => [90000.25, 90000.25],
            'US formatted' => ['90,000.00', 90000.0],
            'Spanish formatted' => ['90.000,00', 90000.0],
            'decimal comma' => ['900,50', 900.5],
            'currency symbol' => ['C$ 90,000.00', 90000.0],
        ];
    }
}

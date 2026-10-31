<?php
namespace Tests\Feature;

use App\Services\PreliquidationItemParser;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PreliquidationParserTest extends TestCase
{
    public function test_paste_and_csv_preserve_independent_rows(): void
    {
        $parser = new PreliquidationItemParser;
        $rows = $parser->text("Part Number\tDescription\tQty\tUnit Price\tWeight\n332/A2428\tSEAL DUST\t2\t3.46\t0.07\n332/A2429\tPACKING\t2\t11.53\t0.11");
        $this->assertCount(2, $rows);
        $this->assertSame(3.46, $rows[0]['unit_cost']);
        $this->assertSame(.11, $rows[1]['weight_lb']);
        $this->assertSame('A,1', $parser->text('"A,1","Test, part",2,3.46,0.07')[0]['quoted_part_number']);
        $formatted = $parser->text("A\tTest\t2\t$ 1,215.48\t0,07");
        $this->assertSame(1215.48, $formatted[0]['unit_cost']);
        $this->assertSame(.07, $formatted[0]['weight_lb']);
    }

    public function test_invalid_paste_is_rejected_before_any_import(): void
    {
        $this->expectException(ValidationException::class);
        (new PreliquidationItemParser)->text("A\tValid\t2\t3.46\t0.07\nB\tInvalid\t-1\t5\t0.1");
    }
}

<?php

namespace Tests\Feature;

use App\Services\DocumentSequenceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DocumentSequenceServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_preprinted_invoice_starts_at_02_and_increments(): void
    {
        $service = app(DocumentSequenceService::class);

        $this->assertSame('02', $service->formattedPeek('preprinted_invoice', '', 2, 2));
        $this->assertSame('02', $service->formattedNext('preprinted_invoice', '', 2, 2));
        $this->assertSame('03', $service->formattedNext('preprinted_invoice', '', 2, 2));
    }

    public function test_finance_sequences_are_unique_and_readable(): void
    {
        $service = app(DocumentSequenceService::class);

        $this->assertSame('CXP-000001', $service->formattedNext('account_payable', 'CXP-', 6));
        $this->assertSame('CXP-000002', $service->formattedNext('account_payable', 'CXP-', 6));
        $this->assertSame('CXC-000001', $service->formattedNext('account_receivable', 'CXC-', 6));
    }
}

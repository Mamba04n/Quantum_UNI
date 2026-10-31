<?php

namespace Tests\Feature;

use App\Livewire\Ventas\Concerns\QuotationProcess\ManagesProcessComments;
use App\Models\QuotationRequest;
use App\Models\User;
use App\Services\OrderTrackerService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;
use Livewire\Livewire;
use Livewire\WithFileUploads;
use Tests\TestCase;

class PhaseCommentAttachmentsTest extends TestCase
{
    public function test_invalid_files_are_rejected_before_saving(): void
    {
        Storage::fake('private');
        $user = \Mockery::mock(User::class)->makePartial();
        $user->shouldReceive('can')->andReturnUsing(fn ($ability) => $ability === 'supplier_bid.view');
        $this->actingAs($user);
        Gate::before(fn () => true);
        $service = \Mockery::mock(OrderTrackerService::class);
        $service->shouldNotReceive('trackerForQuotation');
        $this->app->instance(OrderTrackerService::class, $service);

        foreach ([UploadedFile::fake()->create('script.php', 1, 'application/x-php'), UploadedFile::fake()->create('grande.pdf', 10241, 'application/pdf')] as $file) {
            $component = new PhaseCommentHarness;
            $component->newComment = 'Documento de la fase';
            $component->commentFiles = [$file];
            try {
                $component->addComment($service);
                $this->fail('El archivo invÃƒÂ¡lido fue aceptado.');
            } catch (\Illuminate\Validation\ValidationException $exception) {
                $this->assertArrayHasKey('commentFiles.0', $exception->errors());
            }
        }
    }

    public function test_download_checks_actual_phase_permission(): void
    {
        $user = \Mockery::mock(User::class)->makePartial();
        $user->shouldReceive('can')->andReturnUsing(fn ($ability) => $ability === 'customer_request.view');
        $this->actingAs($user);


        Livewire::test(PhaseCommentHarness::class)
            ->set('activeTab', 'request')
            ->call('downloadCommentFile', 123)
            ->assertForbidden();
    }
}

class PhaseCommentHarness extends Component
{
    use WithFileUploads, ManagesProcessComments;

    public string $newComment = '';
    public string $commentVisibility = 'internal';
    public string $activeTab = 'bid';
    public array $tabs = ['bid' => ['permission' => 'supplier_bid.view'], 'request' => ['permission' => 'customer_request.view']];
    public array $flowStages = ['supplier_bid' => ['tab' => 'bid']];

    protected function selectedQuote(): QuotationRequest { return new QuotationRequest; }
    protected function stageKeyForQuote($quote): string { return 'supplier_bid'; }
    public function render() { return '<div></div>'; }
}

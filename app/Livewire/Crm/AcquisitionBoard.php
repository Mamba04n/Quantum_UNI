<?php

namespace App\Livewire\Crm;

use App\Models\ActivityLog;
use App\Models\Entity;
use App\Services\CustomerAcquisitionService;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Layout('layouts.app')]
class AcquisitionBoard extends Component
{
    public string $search = '';

    public string $responsible = '';

    public string $source = '';

    public string $classification = '';

    public string $campaign = '';

    public string $campaignName = '';

    public string $campaignDescription = '';

    #[Locked]
    public string $campaignToken = '';

    #[Locked]
    public int $historyLimit = 50;

    public string $city = '';

    public string $sector = '';

    public string $dateFrom = '';

    public string $dateTo = '';

    public string $discarded = 'active';

    public bool $overdue = false;

    public int $limit = 100;

    #[Locked]
    public ?int $selectedId = null;

    #[Locked]
    public int $version = 0;

    #[Locked]
    public string $token = '';

    public bool $creating = false;

    public bool $duplicateAck = false;

    public string $targetStage = 'prospectos';

    public string $discardReason = '';

    public string $discardNotes = '';

    public string $clientSearch = '';

    public ?int $convertEntityId = null;

    public array $form = [];

    public array $interaction = [];

    public string $notice = '';

    private function service(): CustomerAcquisitionService
    {
        return app(CustomerAcquisitionService::class);
    }

    public function mount(): void
    {
        $this->service()->permit('view');
        $this->campaignToken = (string) Str::uuid();
    }

    public function createCampaign(): void
    {
        $campaign = $this->service()->createCampaign(['name' => $this->campaignName, 'description' => $this->campaignDescription], $this->campaignToken);
        $this->campaign = (string) $campaign->id;
        $this->campaignName = $this->campaignDescription = '';
        $this->campaignToken = (string) Str::uuid();
        $this->notice = 'Campaña disponible. Asigna empresas desde Nuevo prospecto o desde su detalle.';
    }

    public function moreHistory(): void
    {
        $this->historyLimit += 50;
    }

    public function updated($property): void
    {
        if (! str_starts_with($property, 'form.') && ! str_starts_with($property, 'interaction.')) {
            $this->limit = 100;
        }
    }

    public function more(): void
    {
        $this->limit = min(5000, $this->limit + 100);
    }

    public function clearFilters(): void
    {
        $this->reset('search', 'responsible', 'source', 'classification', 'campaign', 'city', 'sector', 'dateFrom', 'dateTo', 'discarded', 'overdue', 'limit');
    }

    public function create(): void
    {
        $this->service()->permit('create');
        $this->resetValidation();
        $this->selectedId = null;
        $this->creating = true;
        $this->duplicateAck = false;
        $this->token = (string) Str::uuid();
        $this->form = ['name' => '', 'source' => '', 'city' => '', 'sector' => '', 'phone' => '', 'email' => '', 'responsible_id' => auth()->id(), 'comment' => '', 'contacts' => [], 'machines' => [], 'campaign_id' => $this->campaign ?: null];
        $this->dispatch('acquisition-open', stage: 'prospectos');
    }

    public function open(int $id): void
    {
        $p = $this->service()->visible()->with('opportunity')->findOrFail($id);
        $this->resetValidation();
        $this->creating = false;
        $this->selectedId = $p->id;
        $this->version = $p->version;
        $this->duplicateAck = false;
        $this->form = $p->only(['name', 'source', 'city', 'sector', 'phone', 'email', 'responsible_id', 'comment', 'ruc', 'website', 'need', 'products', 'services', 'classification', 'contacts', 'machines', 'entity_id', 'next_action', 'campaign_id', 'context', 'classification_criteria']);
        $this->form['context'] ??= [];
        $this->form['classification_criteria'] ??= [];
        $this->form['contacts'] ??= [];
        $this->form['machines'] ??= [];
        $this->form['next_contact_at'] = $p->next_contact_at?->format('Y-m-d');
        $this->targetStage = $p->stage;
        $this->convertEntityId = $p->entity_id;
        $this->interaction = ['channel' => 'WhatsApp', 'date' => now()->format('Y-m-d\TH:i'), 'person' => $p->contacts[0]['name'] ?? '', 'position' => '', 'summary' => '', 'result' => '', 'next_action' => $p->next_action ?? '', 'next_contact_at' => $p->next_contact_at?->format('Y-m-d') ?? today()->format('Y-m-d')];
        $this->historyLimit = 50;
        $this->discardReason = $this->discardNotes = $this->clientSearch = '';
        $this->dispatch('acquisition-open', stage: $p->stage);
    }

    public function close(): void
    {
        $this->selectedId = null;
        $this->creating = false;
        $this->resetValidation();
    }

    public function addContact(): void
    {
        $this->form['contacts'][] = ['name' => '', 'position' => '', 'phone' => '', 'email' => '', 'commercial_role' => '', 'buying_functions' => []];
    }

    public function removeContact(int $index): void
    {
        unset($this->form['contacts'][$index]);
        $this->form['contacts'] = array_values($this->form['contacts']);
    }

    public function addMachine(): void
    {
        $this->form['machines'][] = ['type' => '', 'brand' => '', 'model' => '', 'serial' => '', 'engine' => '', 'quantity' => 1, 'notes' => ''];
    }

    public function removeMachine(int $index): void
    {
        unset($this->form['machines'][$index]);
        $this->form['machines'] = array_values($this->form['machines']);
    }

    public function save(): void
    {
        if (!empty($this->form['contacts'])) {
            $this->form['contacts'] = array_values(array_filter($this->form['contacts'], fn ($c) => filled($c['name'] ?? null)));
        }
        if (!empty($this->form['machines'])) {
            $this->form['machines'] = array_values(array_filter($this->form['machines'], fn ($m) => filled($m['type'] ?? null)));
        }

        $p = $this->creating ? $this->service()->create($this->form, $this->token, $this->duplicateAck) : $this->service()->update($this->selectedId ?? 0, $this->version, $this->form, $this->duplicateAck);
        $this->notice = 'Prospecto guardado exitosamente.';
        // Reassignment may remove the record from the current user's scope.
        if ($this->service()->visible()->whereKey($p->id)->exists()) {
            $this->open($p->id);
        } else {
            $this->close();
            $this->dispatch('acquisition-close');
        }
    }

    public function move(int $id, int $version, string $stage): void
    {
        $this->resetValidation();
        $p = $this->service()->move($id, $version, $stage);
        $this->notice = 'Etapa actualizada.';
        if ($this->selectedId === $id) {
            $this->open($p->id);
        }
    }

    public function moveSelected(): void
    {
        $this->move($this->selectedId ?? 0, $this->version, $this->targetStage);
    }

    public function recordContact(bool $advanceToContactados = false): void
    {
        $p = $this->service()->contact($this->selectedId ?? 0, $this->version, $this->interaction);
        $this->notice = 'Contacto registrado exitosamente.';
        if ($advanceToContactados) {
            $fresh = $this->service()->visible()->find($this->selectedId);
            if ($fresh && $fresh->stage === 'prospectos') {
                try {
                    $fresh = $this->service()->move($fresh->id, $fresh->version, 'contactados');
                    $this->notice = 'Contacto registrado y prospecto avanzado a Contactados.';
                } catch (\Exception $e) {
                    // Si alguna regla adicional fallase, se conserva la notificación de contacto
                }
            }
        }
        $this->open($this->selectedId);
    }

    public function recordContactAndAdvance(): void
    {
        $this->recordContact(advanceToContactados: true);
    }

    public function discard(): void
    {
        $this->service()->discard($this->selectedId ?? 0, $this->version, $this->discardReason, $this->discardNotes);
        $this->notice = 'Prospecto descartado; su historial se conserva.';
        $this->open($this->selectedId);
    }

    public function convert(): void
    {
        $o = $this->service()->convert($this->selectedId ?? 0, $this->version, $this->convertEntityId, $this->duplicateAck);
        $this->notice = 'Oportunidad comercial #'.$o->id.' creada. No se generó una cotización.';
        $this->open($this->selectedId);
    }

    public function render()
    {
        $service = $this->service();
        $q = $service->visible()->with(['responsible:id,name', 'opportunity'])->when($this->discarded === 'active', fn ($q) => $q->whereNull('discarded_at'))->when($this->discarded === 'discarded', fn ($q) => $q->whereNotNull('discarded_at'));
        foreach (['responsible' => 'responsible_id', 'source' => 'source', 'classification' => 'classification', 'campaign' => 'campaign_id'] as $property => $column) {
            if ($this->$property !== '') {
                $q->where($column, $this->$property);
            }
        }
        foreach (['city', 'sector'] as $column) {
            if (trim($this->$column) !== '') {
                $q->where($column, 'like', '%'.trim($this->$column).'%');
            }
        }
        if ($this->overdue) {
            $q->whereDate('next_contact_at', '<', today())->whereNull('discarded_at')->whereDoesntHave('opportunity');
        }
        foreach (['dateFrom' => '>=', 'dateTo' => '<='] as $property => $operator) {
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $this->$property)) {
                $q->whereDate('created_at', $operator, $this->$property);
            }
        }
        if ($search = trim($this->search)) {
            $q->where(function ($q) use ($search) {
                foreach (['name', 'ruc', 'phone', 'email', 'comment', 'need', 'products', 'services', 'contacts', 'machines', 'context'] as $column) {
                    $q->orWhere($column, 'like', '%'.$search.'%');
                }
            });
        }
        $counts = (clone $q)->selectRaw('stage, count(*) as total')->groupBy('stage')->pluck('total', 'stage');
        $columns = [];
        foreach (config('acquisition.stages') as $key => $label) {
            $columns[$key] = (clone $q)->where('stage', $key)->orderByRaw('next_contact_at IS NULL')->orderBy('next_contact_at')->orderByDesc('id')->limit($this->limit)->get();
        }
        $selected = $this->selectedId ? $service->visible()->with(['responsible', 'opportunity', 'entity'])->findOrFail($this->selectedId) : null;
        $historyQuery = $selected ? ActivityLog::with('user:id,name')->where('module', 'customer_acquisition')->where('metadata->prospect_id', $selected->id) : null;
        $historyCount = $historyQuery ? (clone $historyQuery)->count() : 0;
        $history = $historyQuery ? $historyQuery->latest('id')->limit($this->historyLimit)->get() : collect();
        $matches = ($this->creating || $selected) && filled($this->form['name'] ?? null) ? $service->duplicates($this->form, $this->selectedId) : [];
        $clients = auth()->user()->hasPermissionTo('crm.clients.manage') && trim($this->clientSearch) !== '' ? Entity::where('type', 'cliente')->where(fn ($q) => $q->where('name', 'like', '%'.$this->clientSearch.'%')->orWhere('rfc_ruc', 'like', '%'.$this->clientSearch.'%'))->limit(15)->get(['id', 'name', 'rfc_ruc']) : collect();

        $campaigns = $service->campaigns()->orderBy('name')->get(['id', 'name']);
        $campaignSummary = null;
        if ($this->campaign !== '' && $campaigns->contains('id', $this->campaign)) {
            $members = $service->visible()->where('campaign_id', $this->campaign);
            $campaignSummary = ['total' => (clone $members)->count(), 'discarded' => (clone $members)->whereNotNull('discarded_at')->count(), 'stages' => (clone $members)->whereNull('discarded_at')->selectRaw('stage, count(*) as total')->groupBy('stage')->pluck('total', 'stage')];
        }

        return view('livewire.crm.acquisition-board', compact('columns', 'counts', 'selected', 'history', 'historyCount', 'matches', 'clients', 'campaigns', 'campaignSummary') + ['employees' => $service->employees()->orderBy('name')->get(['id', 'name'])]);
    }
}

<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\CommercialOpportunity;
use App\Models\CrmProspect;
use App\Models\Entity;
use App\Models\ProspectingCampaign;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class CustomerAcquisitionService
{
    public function permit(string $action): void
    {
        abort_unless(auth()->user()?->hasPermissionTo('prospects.view') && auth()->user()?->hasPermissionTo('prospects.'.$action), 403);
    }

    public function visible(): Builder
    {
        $this->permit('view');

        return CrmProspect::query()->when(! auth()->user()->hasPermissionTo('prospects.view_all'), fn ($q) => $q->where('responsible_id', auth()->id()));
    }

    public function employees(): Builder
    {
        return User::query()->where('is_active', true)->whereHas('roles', fn ($q) => $q->where('slug', '!=', User::ROLE_CLIENTE_TIENDA));
    }

    public function campaigns(): Builder
    {
        $this->permit('view');

        return ProspectingCampaign::query()->when(! auth()->user()->hasPermissionTo('prospects.view_all'), fn ($q) => $q->where(fn ($q) => $q->where('created_by', auth()->id())->orWhereHas('prospects', fn ($q) => $q->where('responsible_id', auth()->id()))));
    }

    public function createCampaign(array $data, string $token): ProspectingCampaign
    {
        $this->permit('create');
        $v = Validator::make($data + ['token' => $token], ['name' => 'required|string|max:180', 'description' => 'nullable|string|max:3000', 'token' => 'required|uuid'])->validate();
        $name = trim($v['name']);
        if ($name === '') {
            throw ValidationException::withMessages(['campaignName' => 'Escribe el nombre de la campaña.']);
        }

        return DB::transaction(function () use ($v, $name, $token) {
            DB::table('crm_identity_locks')->where('id', 1)->lockForUpdate()->first();
            $old = ProspectingCampaign::where('submission_token', $token)->first();
            if ($old) {
                return $this->campaigns()->findOrFail($old->id);
            }
            $campaign = ProspectingCampaign::firstOrCreate(['created_by' => auth()->id(), 'name_fingerprint' => hash('sha256', $this->normal($name))], ['name' => $name, 'description' => $v['description'] ?? null, 'submission_token' => $token]);
            if ($campaign->wasRecentlyCreated) {
                ActivityLog::log('customer_acquisition', 'campaign_created', 'Campaña de prospección creada: '.$name, ['campaign_id' => $campaign->id]);
            }

            return $campaign;
        });
    }

    private function log(CrmProspect $p, string $action, string $message, array $details = []): void
    {
        ActivityLog::log('customer_acquisition', $action, $message, ['prospect_id' => $p->id] + $details);
    }

    private function normal(string $value): string
    {
        return Str::lower(preg_replace('/[^\pL\pN]/u', '', Str::ascii($value)));
    }

    /** Returns visible names only; hidden records are represented without personal details. */
    public function duplicates(array $data, ?int $except = null, ?int $limit = 15): array
    {
        $this->permit('view');
        $hits = [];
        $keys = ['ruc' => 'rfc_ruc', 'phone' => 'phone_mobile', 'email' => 'email', 'name' => 'name'];
        foreach ([['prospect', CrmProspect::query()->when($except, fn ($q) => $q->whereKeyNot($except)), array_combine(array_keys($keys), array_keys($keys))], ['client', Entity::where('type', 'cliente')->with('contacts'), $keys]] as [$type, $query, $fields]) {
            foreach ($query->select(array_unique(array_merge(['id'], $type === 'prospect' ? ['responsible_id', 'contacts'] : [], array_values($fields))))->lazy(200) as $record) {
                $matched = [];
                foreach ($fields as $input => $column) {
                    $provided = [$data[$input] ?? ''];
                    $existing = [(string) $record->$column];
                    if (in_array($input, ['phone', 'email'])) {
                        $provided = array_merge($provided, array_column($data['contacts'] ?? [], $input));
                        $contactKey = $type === 'client' && $input === 'phone' ? 'mobile' : $input;
                        $existing = array_merge($existing, collect($record->contacts ?? [])->pluck($contactKey)->all());
                    }
                    $normalize = fn ($values) => array_values(array_filter(array_map(fn ($v) => $this->normal((string) $v), $values)));
                    if (array_intersect($normalize($provided), $normalize($existing))) {
                        $matched[] = $input;
                    }
                }
                if (! $matched) {
                    continue;
                }
                $canSee = $type === 'client' ? auth()->user()->hasPermissionTo('crm.clients.manage') : (auth()->user()->hasPermissionTo('prospects.view_all') || $record->responsible_id === auth()->id());
                $hits[] = ['type' => $type, 'id' => $canSee ? $record->id : null, 'name' => $canSee ? $record->name : 'Registro de otro responsable', 'exact' => count(array_diff($matched, ['name'])) > 0];
                if ($limit !== null && count($hits) >= $limit) {
                    return $hits;
                }
            }
        }

        return $hits;
    }

    private function validateData(array $data): array
    {
        foreach ($data as $key => $value) {
            if (is_string($value)) {
                $data[$key] = trim($value) === '' ? null : trim($value);
            }
        }
        $rules = ['name' => 'required|string|max:255', 'source' => ['required', Rule::in(config('acquisition.sources'))], 'responsible_id' => 'required|integer', 'classification' => ['nullable', Rule::in(['A', 'B', 'C'])], 'email' => 'nullable|email|max:255', 'website' => 'nullable|url:http,https|max:255', 'entity_id' => ['nullable', 'integer', Rule::exists('entities', 'id')->where('type', 'cliente')], 'next_contact_at' => 'nullable|date_format:Y-m-d', 'contacts' => 'array|max:30', 'contacts.*.name' => 'required|string|max:255', 'contacts.*.position' => 'nullable|string|max:255', 'contacts.*.phone' => 'nullable|string|max:60', 'contacts.*.email' => 'nullable|email|max:255', 'machines' => 'array|max:50', 'machines.*.type' => 'required|string|max:100', 'machines.*.brand' => 'nullable|string|max:100', 'machines.*.model' => 'nullable|string|max:100', 'machines.*.serial' => 'nullable|string|max:150', 'machines.*.quantity' => 'required|integer|min:1|max:100000', 'machines.*.notes' => 'nullable|string|max:1000'];
        $rules += [
            'campaign_id' => 'nullable|integer',
            'context' => 'array:'.implode(',', array_keys(config('acquisition.context_fields'))),
            'classification_criteria' => 'array:'.implode(',', array_keys(config('acquisition.criteria'))),
            'contacts.*' => 'array:name,position,phone,email,commercial_role,buying_functions',
            'contacts.*.commercial_role' => ['nullable', Rule::in(config('acquisition.contact_roles'))],
            'contacts.*.buying_functions' => 'array|max:4',
            'contacts.*.buying_functions.*' => ['string', Rule::in(array_keys(config('acquisition.buying_functions')))],
            'machines.*' => 'array:type,brand,model,serial,quantity,notes,engine',
            'machines.*.engine' => 'nullable|string|max:255',
        ];
        foreach (array_keys(config('acquisition.context_fields')) as $key) {
            $rules['context.'.$key] = 'nullable|string|max:3000';
        }
        foreach (array_keys(config('acquisition.criteria')) as $key) {
            $rules['classification_criteria.'.$key] = ['nullable', Rule::in(config('acquisition.levels'))];
        }
        foreach (['ruc', 'city', 'sector', 'phone'] as $key) {
            $rules[$key] = 'nullable|string|max:100';
        }
        foreach (['comment', 'need', 'products', 'services', 'next_action'] as $key) {
            $rules[$key] = 'nullable|string|max:5000';
        }
        $v = Validator::make($data, $rules)->validate();
        foreach ($v['contacts'] ?? [] as $index => $contact) {
            $function = config('acquisition.role_functions')[$contact['commercial_role'] ?? ''] ?? null;
            if ($function) {
                $v['contacts'][$index]['buying_functions'] = array_values(array_unique(array_merge($contact['buying_functions'] ?? [], [$function])));
            }
        }
        if (! empty($v['campaign_id']) && ! $this->campaigns()->whereKey($v['campaign_id'])->exists()) {
            throw ValidationException::withMessages(['campaign_id' => 'Selecciona una campaña disponible para tu cuenta.']);
        }
        if (! $this->employees()->whereKey($v['responsible_id'])->exists()) {
            throw ValidationException::withMessages(['responsible_id' => 'Selecciona un responsable interno activo.']);
        }

        return $v;
    }

    private function identity(array $v, ?int $except, bool $ack): void
    {
        foreach ($this->duplicates($v, $except, null) as $hit) {
            if ($hit['exact'] && ($hit['type'] === 'prospect' || ! $hit['id'] || (int) ($v['entity_id'] ?? 0) !== $hit['id'])) {
                throw ValidationException::withMessages(['duplicate' => 'Hay un registro con el mismo RUC, teléfono o correo. Revisa el prospecto existente o vincula el cliente correcto.']);
            }
            if (! $hit['exact'] && ! $ack && ! ($hit['type'] === 'client' && (int) ($v['entity_id'] ?? 0) === $hit['id'])) {
                throw ValidationException::withMessages(['duplicate' => 'Existe una coincidencia por nombre. Revisa las coincidencias y confirma que es otro registro.']);
            }
        }
    }

    public function create(array $data, string $token, bool $ack = false): CrmProspect
    {
        $this->permit('create');
        Validator::make(['token' => $token], ['token' => 'required|uuid'])->validate();

        return DB::transaction(function () use ($data, $token, $ack) {
            DB::table('crm_identity_locks')->where('id', 1)->lockForUpdate()->first();
            if ($old = CrmProspect::where('submission_token', $token)->first()) {
                return $this->visible()->findOrFail($old->id);
            }
            $v = $this->validateData($data);
            if (! empty($v['entity_id'])) {
                abort_unless(auth()->user()->hasPermissionTo('crm.clients.manage'), 403);
            }
            if ((int) $v['responsible_id'] !== auth()->id()) {
                $this->permit('reassign');
            }
            if (filled($v['classification'] ?? null)) {
                $this->permit('classify');
            }
            if (array_filter($v['classification_criteria'] ?? [])) {
                $this->permit('classify');
            }
            $this->identity($v, null, $ack);
            $p = CrmProspect::create($v + ['created_by' => auth()->id(), 'submission_token' => $token]);
            $this->log($p, 'created', 'Prospecto creado');

            return $p;
        });
    }

    private function mutate(int $id, int $version, string $action, callable $callback): CrmProspect
    {
        $this->permit($action);

        return DB::transaction(function () use ($id, $version, $callback) {
            $p = $this->visible()->lockForUpdate()->findOrFail($id);
            if ($p->version !== $version) {
                throw ValidationException::withMessages(['conflict' => 'Otro usuario modificó este prospecto. Recarga su detalle antes de continuar.']);
            }
            if ($p->discarded_at || $p->opportunity()->exists()) {
                throw ValidationException::withMessages(['stage' => 'El prospecto está descartado o ya tiene una oportunidad. Su historial se conserva en modo consulta.']);
            }
            $callback($p);
            $p->version++;
            $p->save();

            return $p;
        });
    }

    private function stageRules(CrmProspect $p, string $stage): void
    {
        if ($stage !== 'prospectos' && (empty($p->contacts) || ! $p->last_contact_at || ! filled($p->last_contact_channel))) {
            throw ValidationException::withMessages(['stage' => 'Registra una persona de contacto y un acercamiento real antes de pasar a Contactados.']);
        }
        if (in_array($stage, ['interesados', 'calificados']) && (! filled($p->last_result) || ! filled($p->need) || (! filled($p->products) && ! filled($p->services) && ! filled($p->context['detected_opportunity'] ?? null)))) {
            throw ValidationException::withMessages(['stage' => 'Registra la respuesta del contacto, una necesidad y el producto/servicio de interés u oportunidad detectada.']);
        }
        if ($stage === 'calificados' && ! in_array($p->classification, ['A', 'B', 'C'])) {
            throw ValidationException::withMessages(['stage' => 'Asigna una clasificación A, B o C antes de calificar.']);
        }
        if ($stage === 'calificados' && (! collect($p->classification_criteria ?? [])->contains(fn ($v) => in_array($v, config('acquisition.levels'))) || ! collect($p->context ?? [])->contains(fn ($v) => filled($v)))) {
            throw ValidationException::withMessages(['stage' => 'Completa al menos un dato de contexto comercial y valora un criterio antes de calificar. La clasificación A/B/C sigue siendo manual.']);
        }
    }

    public function update(int $id, int $version, array $data, bool $ack = false): CrmProspect
    {
        return DB::transaction(function () use ($id, $version, $data, $ack) {
            DB::table('crm_identity_locks')->where('id', 1)->lockForUpdate()->first();

            return $this->mutate($id, $version, 'edit', function ($p) use ($data, $ack) {
                $v = $this->validateData($data);
                if (($v['entity_id'] ?? null) != $p->entity_id && ! empty($v['entity_id'])) {
                    abort_unless(auth()->user()->hasPermissionTo('crm.clients.manage'), 403);
                }
                if ((int) $v['responsible_id'] !== $p->responsible_id) {
                    $this->permit('reassign');
                }
                if (($v['classification'] ?? null) !== $p->classification) {
                    $this->permit('classify');
                }
                if (isset($v['classification_criteria']) && $v['classification_criteria'] != ($p->classification_criteria ?? [])) {
                    $this->permit('classify');
                }
                $this->identity($v, $p->id, $ack);
                $before = $p->only(array_keys($v));
                $p->fill($v);
                $this->stageRules($p, $p->stage);
                if (($before['classification'] ?? null) !== $p->classification) {
                    $this->log($p, 'classification', 'Clasificación: '.($before['classification'] ?? 'Sin clasificar').' → '.($p->classification ?? 'Sin clasificar'));
                }
                if ((int) $before['responsible_id'] !== $p->responsible_id) {
                    $this->log($p, 'reassigned', 'Responsable reasignado', ['from' => $before['responsible_id'], 'to' => $p->responsible_id]);
                }
                if (($before['campaign_id'] ?? null) != $p->campaign_id) {
                    $this->log($p, 'campaign', 'Campaña de prospección actualizada', ['from' => $before['campaign_id'] ?? null, 'to' => $p->campaign_id]);
                }
                if (($before['classification_criteria'] ?? []) != ($p->classification_criteria ?? [])) {
                    $this->log($p, 'criteria', 'Criterios de clasificación actualizados', ['criteria' => $p->classification_criteria]);
                }
                $this->log($p, 'updated', 'Datos del prospecto actualizados', ['before' => $before, 'after' => $p->only(array_keys($v))]);
            });
        });
    }

    public function move(int $id, int $version, string $stage): CrmProspect
    {
        Validator::make(['stage' => $stage], ['stage' => Rule::in(array_keys(config('acquisition.stages')))])->validate();

        return $this->mutate($id, $version, 'move', function ($p) use ($stage) {
            $this->stageRules($p, $stage);
            $from = $p->stage;
            $p->stage = $stage;
            $this->log($p, 'stage', 'Etapa: '.config('acquisition.stages')[$from].' → '.config('acquisition.stages')[$stage]);
        });
    }

    public function contact(int $id, int $version, array $data): CrmProspect
    {
        $v = Validator::make($data, ['channel' => ['required', Rule::in(config('acquisition.channels'))], 'date' => 'required|date_format:Y-m-d\TH:i|before_or_equal:now', 'person' => 'required|string|max:255', 'position' => 'nullable|string|max:255', 'summary' => 'required|string|max:3000', 'result' => 'required|string|max:3000', 'next_action' => 'required|string|max:1000', 'next_contact_at' => 'required|date_format:Y-m-d'])->validate();

        return $this->mutate($id, $version, 'edit', function ($p) use ($v) {
            if ($p->last_contact_at && Carbon::parse($v['date'])->lt($p->last_contact_at)) {
                throw ValidationException::withMessages(['date' => 'El contacto no puede ser anterior al último registrado.']);
            }
            $people = $p->contacts ?? [];
            if (! collect($people)->contains(fn ($c) => Str::lower($c['name']) === Str::lower($v['person']))) {
                $people[] = ['name' => $v['person'], 'position' => $v['position'] ?? ''];
            }
            $p->fill(['contacts' => $people, 'last_contact_at' => $v['date'], 'last_contact_channel' => $v['channel'], 'last_result' => $v['result'], 'next_action' => $v['next_action'], 'next_contact_at' => $v['next_contact_at']]);
            $this->log($p, 'contact', 'Contacto por '.$v['channel'].' con '.$v['person'], $v);
        });
    }

    public function discard(int $id, int $version, string $reason, string $notes): CrmProspect
    {
        Validator::make(compact('reason', 'notes'), ['reason' => ['required', Rule::in(config('acquisition.discard_reasons'))], 'notes' => ($reason === 'Otro' ? 'required' : 'nullable').'|string|max:2000'])->validate();

        return $this->mutate($id, $version, 'discard', function ($p) use ($reason, $notes) {
            $p->discarded_at = now();
            $p->discard_reason = $reason.($notes ? ': '.$notes : '');
            $this->log($p, 'discarded', 'Prospecto descartado: '.$p->discard_reason);
        });
    }

    public function convert(int $id, int $version, ?int $entityId, bool $ack): CommercialOpportunity
    {
        $this->permit('convert');

        return DB::transaction(function () use ($id, $version, $entityId, $ack) {
            DB::table('crm_identity_locks')->where('id', 1)->lockForUpdate()->first();
            $p = $this->visible()->lockForUpdate()->findOrFail($id);
            if ($old = $p->opportunity) {
                return $old;
            }
            if ($p->version !== $version || $p->discarded_at || $p->stage !== 'calificados') {
                throw ValidationException::withMessages(['stage' => 'Recarga el prospecto: debe estar activo y calificado para convertirlo.']);
            }
            $this->stageRules($p, 'calificados');
            $entityId = $entityId ?: $p->entity_id;
            $data = $p->toArray();
            $data['entity_id'] = $entityId;
            // Existing client must be chosen deliberately; never silently merge its data.
            $this->identity($data, $p->id, $ack);
            if ($entityId) {
                abort_unless(auth()->user()->hasPermissionTo('crm.clients.manage'), 403);
                $entity = Entity::where('type', 'cliente')->findOrFail($entityId);
            } else {
                $entity = Entity::create(['type' => 'cliente', 'name' => $p->name, 'rfc_ruc' => $p->ruc, 'phone_mobile' => $p->phone, 'email' => $p->email, 'city' => $p->city, 'website' => $p->website]);
                foreach ($p->contacts ?? [] as $c) {
                    $entity->contacts()->create(['name' => $c['name'], 'position' => $c['position'] ?? null, 'email' => $c['email'] ?? null, 'mobile' => $c['phone'] ?? null, 'is_primary' => ($c['commercial_role'] ?? '') === 'Contacto principal', 'notes' => trim(($c['commercial_role'] ?? '').' · '.implode(', ', array_map(fn ($key) => config('acquisition.buying_functions')[$key] ?? $key, $c['buying_functions'] ?? [])), ' ·')]);
                }
                foreach ($p->machines ?? [] as $m) {
                    $entity->machines()->create(['machine_type' => $m['type'], 'name' => trim(($m['brand'] ?? '').' '.($m['model'] ?? '')) ?: $m['type'], 'brand' => $m['brand'] ?? null, 'model' => $m['model'] ?? null, 'serial_number' => $m['serial'] ?? null, 'technical_details' => filled($m['engine'] ?? null) ? 'Motor conocido: '.$m['engine'] : null, 'notes' => 'Cantidad detectada: '.$m['quantity'].'. '.($m['notes'] ?? '')]);
                }
            }
            $o = CommercialOpportunity::create(['prospect_id' => $p->id, 'entity_id' => $entity->id, 'responsible_id' => $p->responsible_id, 'created_by' => auth()->id(), 'title' => $p->name, 'need' => $p->need, 'snapshot' => $p->toArray()]);
            $p->entity_id = $entity->id;
            $p->version++;
            $p->save();
            $this->log($p, 'converted', 'Oportunidad comercial creada', ['opportunity_id' => $o->id, 'entity_id' => $entity->id]);

            return $o;
        });
    }
}

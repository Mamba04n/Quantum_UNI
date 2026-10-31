<?php

namespace App\Domain\Clientes\Services;

use App\Models\ActivityLog;
use App\Models\Entity;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CustomerService
{
    /** @return Builder<Entity> */
    public function activeCustomerQuery(): Builder
    {
        $query = Entity::query()->where('type', 'cliente');

        if (Schema::hasColumn('entities', 'status')) {
            $query->where(function (Builder $query) {
                $query->whereIn('status', ['active', 'activo'])
                    ->orWhereNull('status');
            });
        }

        return $query->orderBy('name');
    }

    public function createFromAdmin(array $data): Entity
    {
        return $this->createOrUpdateCustomer($data, 'admin_create');
    }

    public function updateFromAdmin(Entity $customer, array $data): Entity
    {
        return $this->createOrUpdateCustomer($data, 'admin_update', $customer);
    }

    public function registerPortalCustomer(array $data): Entity
    {
        return $this->createOrUpdateCustomer($data, 'portal');
    }

    public function syncPortalAccess(Entity $customer, string $email, ?string $password, bool $active = true): Entity
    {
        $email = mb_strtolower(trim($email));

        return DB::transaction(function () use ($customer, $email, $password, $active): Entity {
            $customer = Entity::where('type', 'cliente')->lockForUpdate()->findOrFail($customer->id);
            $user = $customer->user_id ? User::lockForUpdate()->find($customer->user_id) : null;

            if (! $user) {
                $user = User::whereRaw('LOWER(TRIM(email)) = ?', [$email])->lockForUpdate()->first();
                if ($user) {
                    $this->assertExistingPortalUserCanBeLinked($user);
                    $linkedElsewhere = Entity::where('type', 'cliente')->where('user_id', $user->id)->whereKeyNot($customer->id)->exists();
                    if ($linkedElsewhere) {
                        throw ValidationException::withMessages(['email' => 'Estas credenciales ya pertenecen a otro cliente.']);
                    }
                } else {
                    if (! filled($password)) {
                        throw ValidationException::withMessages(['portal_password' => 'Define una contraseña para crear el acceso al portal.']);
                    }
                    $user = new User();
                }
            }

            $emailOwner = User::whereRaw('LOWER(TRIM(email)) = ?', [$email])->whereKeyNot($user->id)->exists();
            if ($emailOwner) {
                throw ValidationException::withMessages(['email' => 'Este correo ya está siendo utilizado por otra cuenta.']);
            }

            $user->forceFill([
                'name' => $customer->name,
                'email' => $email,
                'phone' => $customer->phone_mobile,
                'address' => $customer->exact_address ?: $customer->address,
                'is_active' => $active,
                'email_verified_at' => $user->email_verified_at ?: now(),
            ]);
            if (filled($password)) {
                $user->password = Hash::make($password);
            }
            $user->save();

            $this->assignCustomerRole($user);
            $customer->forceFill(['user_id' => $user->id, 'email' => $email])->save();

            ActivityLog::log('crm', 'customer.portal_access_updated', "Acceso al portal actualizado para {$customer->name}.", [
                'customer_id' => $customer->id,
                'user_id' => $user->id,
                'active' => $active,
            ]);

            return $customer->refresh()->load('user');
        });
    }

    public function disablePortalAccess(Entity $customer): Entity
    {
        return DB::transaction(function () use ($customer): Entity {
            $customer = Entity::where('type', 'cliente')->lockForUpdate()->findOrFail($customer->id);
            if ($customer->user_id && ($user = User::find($customer->user_id))) {
                $user->forceFill(['is_active' => false])->save();
                ActivityLog::log('crm', 'customer.portal_access_disabled', "Acceso al portal desactivado para {$customer->name}.", [
                    'customer_id' => $customer->id,
                    'user_id' => $user->id,
                ]);
            }

            return $customer->refresh()->load('user');
        });
    }

    public function createOrUpdateFromUser(User $user, string $source = 'admin'): Entity
    {
        return $this->createOrUpdateCustomer([
            'name' => $user->name,
            'email' => $user->email,
            'phone_mobile' => $user->phone,
            'address' => $user->address,
            'exact_address' => $user->address,
            'user' => $user,
        ], $source);
    }

    private function createOrUpdateCustomer(array $data, string $source, ?Entity $customer = null): Entity
    {
        try {
            return DB::transaction(function () use ($data, $source, $customer) {
                $user = $data['user'] ?? null;

                if ($source === 'portal' && ! $user instanceof User && filled($data['email'] ?? null)) {
                    $user = $this->createOrLinkCustomerUser($data);
                }

                $payload = $this->customerPayload($data, $user);

                if ($customer) {
                    if (! array_key_exists('status', $data)) {
                        unset($payload['status']);
                    }
                    $this->assertFingerprintsAvailable($payload, $customer->id);
                    $customer->update($payload);
                } else {
                    $customer = $this->findExistingCustomer($data, $user);

                    if ($customer) {
                        if ($source === 'admin_create') {
                            throw ValidationException::withMessages([
                                'customer' => 'Ya existe un cliente con la misma identidad.',
                            ]);
                        }

                        if ($source === 'portal' && $customer->user_id !== $user?->id) {
                            throw ValidationException::withMessages([
                                'email' => 'El cliente existente requiere revisión administrativa.',
                            ]);
                        }

                        if (! array_key_exists('status', $data)) {
                            unset($payload['status']);
                        }
                        $this->assertFingerprintsAvailable($payload, $customer->id);
                        $customer->update(array_filter($payload, fn ($value) => $value !== null));
                    } else {
                        $this->assertFingerprintsAvailable($payload);
                        $customer = Entity::create($payload);
                    }
                }

                ActivityLog::log('crm', "customer.created_from_{$source}", "Cliente {$customer->name} creado o actualizado desde {$source}.", [
                    'customer_id' => $customer->id,
                    'user_id' => $user?->id,
                    'source' => $source,
                ]);

                return $customer->refresh();
            });
        } catch (QueryException $exception) {
            if ($this->isUniqueConstraintViolation($exception)) {
                throw ValidationException::withMessages([
                    'customer' => 'Ya existe un cliente con la misma identidad.',
                ]);
            }

            throw $exception;
        }
    }

    private function isUniqueConstraintViolation(QueryException $exception): bool
    {
        return in_array((string) $exception->getCode(), ['23000', '23505'], true)
            || (int) ($exception->errorInfo[1] ?? 0) === 1062;
    }

    private function createOrLinkCustomerUser(array $data): User
    {
        $user = User::where('email', $data['email'])->first();

        if (! $user) {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'phone' => $data['phone_mobile'] ?? $data['phone'] ?? null,
                'address' => $data['exact_address'] ?? $data['address'] ?? null,
                'password' => Hash::make($data['password'] ?? Str::random(32)),
                'is_active' => true,
            ]);

            ActivityLog::log('crm', 'customer.user_linked', "Usuario cliente {$user->email} creado y vinculado.", [
                'user_id' => $user->id,
            ]);
        } else {
            $this->assertExistingPortalUserCanBeLinked($user);

            $user->forceFill([
                'name' => $data['name'] ?: $user->name,
                'phone' => $data['phone_mobile'] ?? $data['phone'] ?? $user->phone,
                'address' => $data['exact_address'] ?? $data['address'] ?? $user->address,
            ])->save();

            ActivityLog::log('crm', 'customer.user_linked', "Usuario cliente {$user->email} vinculado.", [
                'user_id' => $user->id,
            ]);
        }

        $this->assignCustomerRole($user);

        return $user;
    }

    private function assignCustomerRole(User $user): void
    {
        if (! Schema::hasTable('roles')) {
            throw new \RuntimeException('El catálogo RBAC no está disponible para asignar el rol de cliente.');
        }

        $role = Role::where('slug', User::ROLE_CLIENTE_TIENDA)->firstOrFail();

        // Una cuenta creada por el registro público recibe únicamente el rol
        // de cliente. Nunca se conservan permisos internos por coincidencia de correo.
        $user->syncRoles([$role->id]);

        ActivityLog::log('crm', 'customer.role_assigned', "Rol cliente asignado a {$user->email}.", [
            'user_id' => $user->id,
            'role' => User::ROLE_CLIENTE_TIENDA,
        ]);
    }

    private function assertExistingPortalUserCanBeLinked(User $user): void
    {
        $roles = $user->roleSlugs();
        $isOnlyCustomer = count($roles) === 1 && $roles[0] === User::ROLE_CLIENTE_TIENDA;

        if (! $isOnlyCustomer || ! $user->is_active) {
            throw ValidationException::withMessages([
                'email' => 'La cuenta existente requiere revisión administrativa.',
            ]);
        }
    }

    private function customerPayload(array $data, ?User $user): array
    {
        $payload = [
            'name' => $data['name'],
            'type' => 'cliente',
            'contact_info' => $data['contact_info'] ?? null,
            'email' => $data['email'] ?? $user?->email,
            'rfc_ruc' => $data['rfc_ruc'] ?? null,
            'phone_mobile' => $data['phone_mobile'] ?? $data['phone'] ?? $user?->phone,
            'phone_home' => $data['phone_home'] ?? null,
            'country' => $data['country'] ?? 'Nicaragua',
            'department' => $data['department'] ?? null,
            'municipality' => $data['municipality'] ?? null,
            'exact_address' => $data['exact_address'] ?? $data['address'] ?? $user?->address,
            'address' => $data['exact_address'] ?? $data['address'] ?? $user?->address,
            'status' => 'active',
            'email_fingerprint' => $this->emailFingerprint($data['email'] ?? null),
            'tax_id_fingerprint' => $this->taxIdFingerprint($data['rfc_ruc'] ?? null),
        ];

        if ($user && Schema::hasColumn('entities', 'user_id')) {
            $payload['user_id'] = $user->id;
        }

        return $payload;
    }

    private function findExistingCustomer(array $data, ?User $user): ?Entity
    {
        if ($user && Schema::hasColumn('entities', 'user_id')) {
            $customer = Entity::where('type', 'cliente')->where('user_id', $user->id)->first();

            if ($customer) {
                return $customer;
            }
        }

        $email = $this->normalizeEmail($data['email'] ?? null);
        $taxId = $this->normalizeTaxId($data['rfc_ruc'] ?? null);

        if ($email === null && $taxId === null) {
            return null;
        }

        $matches = Entity::query()
            ->where('type', 'cliente')
            ->where(function (Builder $query) use ($email, $taxId): void {
                if ($email !== null) {
                    $query->whereRaw('LOWER(TRIM(email)) = ?', [$email]);
                }

                if ($taxId !== null) {
                    if ($email !== null) {
                        $query->orWhereRaw('UPPER(REPLACE(REPLACE(TRIM(rfc_ruc), ?, ?), ?, ?)) = ?', ['-', '', ' ', '', $taxId]);
                    } else {
                        $query->whereRaw('UPPER(REPLACE(REPLACE(TRIM(rfc_ruc), ?, ?), ?, ?)) = ?', ['-', '', ' ', '', $taxId]);
                    }
                }
            })
            ->lockForUpdate()
            ->get();

        if ($matches->count() > 1) {
            throw ValidationException::withMessages([
                'customer' => 'Existen identidades de cliente en conflicto; se requiere revisión administrativa.',
            ]);
        }

        return $matches->first();
    }

    private function assertFingerprintsAvailable(array $payload, ?int $exceptId = null): void
    {
        $emailFingerprint = $payload['email_fingerprint'] ?? null;
        $taxIdFingerprint = $payload['tax_id_fingerprint'] ?? null;
        if ($emailFingerprint === null && $taxIdFingerprint === null) {
            return;
        }

        $exists = Entity::query()
            ->where('type', 'cliente')
            ->where(function (Builder $query) use ($emailFingerprint, $taxIdFingerprint): void {
                if ($emailFingerprint !== null) {
                    $query->where('email_fingerprint', $emailFingerprint);
                }
                if ($taxIdFingerprint !== null) {
                    if ($emailFingerprint !== null) {
                        $query->orWhere('tax_id_fingerprint', $taxIdFingerprint);
                    } else {
                        $query->where('tax_id_fingerprint', $taxIdFingerprint);
                    }
                }
            })
            ->when($exceptId, fn (Builder $query) => $query->whereKeyNot($exceptId))
            ->lockForUpdate()
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'customer' => 'Ya existe un cliente con la misma identidad.',
            ]);
        }
    }

    private function emailFingerprint(mixed $email): ?string
    {
        $email = $this->normalizeEmail($email);

        return $email === null ? null : hash('sha256', 'email:'.$email);
    }

    private function taxIdFingerprint(mixed $taxId): ?string
    {
        $taxId = $this->normalizeTaxId($taxId);

        return $taxId === null ? null : hash('sha256', 'tax:'.$taxId);
    }

    private function normalizeEmail(mixed $email): ?string
    {
        $email = mb_strtolower(trim((string) $email));

        return $email === '' ? null : $email;
    }

    private function normalizeTaxId(mixed $taxId): ?string
    {
        $taxId = strtoupper(preg_replace('/[\s-]+/', '', trim((string) $taxId)) ?? '');

        return $taxId === '' ? null : $taxId;
    }
}

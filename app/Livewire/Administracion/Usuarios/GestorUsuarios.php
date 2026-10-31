<?php

namespace App\Livewire\Administracion\Usuarios;

use App\Domain\Administracion\Usuarios\Queries\ListarRolesUsuariosQuery;
use App\Domain\Administracion\Usuarios\Queries\ListarUsuariosQuery;
use App\Models\ActivityLog;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

/** @property-read array<string, string> $roles */
#[Layout('layouts.app')]
class GestorUsuarios extends Component
{
    use WithPagination;

    // ── Lista ──────────────────────────────────────────────────────────────────
    public $search = '';

    public $activeTab = 'employees';

    // ── Modal ─────────────────────────────────────────────────────────────────
    public $showModal = false;

    public $editingId = null;

    // ── Campos del formulario ─────────────────────────────────────────────────
    public $name = '';

    public $first_name = '';

    public $last_name = '';

    public $email = '';

    public $phone = '';

    public $address = '';

    public $job_title = '';

    public $department = '';

    public $hire_date = '';

    public $identity_document = '';

    public $emergency_contact = '';

    public $notes = '';

    public $role = 'ventas';

    public array $selectedRoles = ['ventas'];

    public $password = '';

    public $is_active = true;

    // ── Modal confirmación borrar ─────────────────────────────────────────────
    public $showDeleteModal = false;

    public $deleteId = null;

    // ─── Computed helpers ──────────────────────────────────────────────────────
    public function getRolesProperty(): array
    {
        return app(ListarRolesUsuariosQuery::class)->ejecutar();
    }

    // ─── Render ───────────────────────────────────────────────────────────────
    public function render()
    {
        $employeeProfilesReady = Schema::hasTable('employee_profiles');
        $users = app(ListarUsuariosQuery::class)->ejecutar($this->search, $employeeProfilesReady);

        return view('livewire.administracion.usuarios.gestor-usuarios', [
            'users' => $users,
            'roles' => $this->roles,
            'employeeProfilesReady' => $employeeProfilesReady,
        ]);
    }

    // ─── Abrir modal crear ────────────────────────────────────────────────────
    public function openCreate()
    {
        $this->resetForm();
        $this->role = 'ventas';
        $this->selectedRoles = [$this->role];
        $this->is_active = true;
        $this->showModal = true;
    }

    // ─── Abrir modal editar ───────────────────────────────────────────────────
    public function openEdit($id)
    {
        $employeeProfilesReady = Schema::hasTable('employee_profiles');
        $userQuery = User::query();

        if ($employeeProfilesReady) {
            $userQuery->with('employeeProfile');
        }

        $user = $userQuery->findOrFail($id);
        $profile = $employeeProfilesReady ? $user->employeeProfile : null;

        // Nadie puede editar al primer admin (ID=1) excepto el mismo
        if ($user->id === 1 && auth()->id() !== 1) {
            session()->flash('error', 'No puedes editar al administrador principal.');

            return;
        }

        $this->editingId = $user->id;
        $this->name = $user->name;
        $this->first_name = $profile?->first_name ?? str($user->name)->before(' ')->toString();
        $this->last_name = $profile?->last_name ?? str($user->name)->after(' ')->toString();
        $this->email = $user->email;
        $this->phone = $profile?->phone ?? $user->phone ?? '';
        $this->address = $profile?->address ?? $user->address ?? '';
        $this->job_title = $profile?->job_title ?? '';
        $this->department = $profile?->department ?? '';
        $this->hire_date = optional($profile?->hire_date)->format('Y-m-d') ?? '';
        $this->identity_document = $profile?->identity_document ?? '';
        $this->emergency_contact = $profile?->emergency_contact ?? '';
        $this->notes = $profile?->notes ?? '';
        $this->role = $user->role;
        $this->selectedRoles = collect($user->roleSlugs())->reject(fn ($role) => $role === User::ROLE_CLIENTE_TIENDA)->values()->all();
        $this->is_active = $user->is_active;
        $this->password = ''; // no pre-llenar contraseña
        $this->showModal = true;
    }

    // ─── Guardar (crear o actualizar) ─────────────────────────────────────────
    public function save()
    {
        abort_unless(auth()->user()?->can('admin.users.manage'), 403);

        $this->validate([
            'name' => 'required|string|max:80',
            'first_name' => 'nullable|string|max:80',
            'last_name' => 'nullable|string|max:80',
            'email' => 'required|not_regex:/[\r\n]/|email|unique:users,email,'.($this->editingId ?? 'NULL'),
            'selectedRoles' => 'required|array|min:1',
            'selectedRoles.*' => 'required|in:'.implode(',', array_keys($this->roles)),
            'password' => $this->editingId ? 'nullable|min:8' : 'required|min:8',
            'phone' => 'nullable|string|max:40',
            'address' => 'nullable|string|max:1000',
            'job_title' => 'nullable|string|max:120',
            'department' => 'nullable|string|max:120',
            'hire_date' => 'nullable|date',
            'identity_document' => 'nullable|string|max:120',
            'emergency_contact' => 'nullable|string|max:255',
            'notes' => 'nullable|string|max:2000',
            'is_active' => 'boolean',
        ], [
            'name.required' => 'El nombre es obligatorio.',
            'email.required' => 'El correo es obligatorio.',
            'email.unique' => 'Este correo ya está registrado.',
            'password.required' => 'La contraseña es obligatoria para nuevos usuarios.',
            'password.min' => 'La contraseña debe tener mínimo 8 caracteres.',
        ]);

        $data = [
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone ?: null,
            'address' => $this->address ?: null,
            'is_active' => $this->is_active,
        ];

        if ($this->password) {
            $data['password'] = Hash::make($this->password);
        }

        if ($this->editingId) {
            $user = User::findOrFail($this->editingId);
            $user->update($data);
            $this->syncRoles($user);
            $this->syncEmployeeProfile($user);
            ActivityLog::log('admin', 'edit_user', "Usuario #{$this->editingId} ({$this->name}) modificado por admin.", ['user_id' => $this->editingId]);
            session()->flash('message', "Usuario {$this->name} actualizado.");
        } else {
            $user = User::create($data);
            $this->syncRoles($user);
            $this->syncEmployeeProfile($user);
            ActivityLog::log('admin', 'create_user', "Nuevo usuario {$this->name} (".implode(', ', $this->selectedRoles).') creado por admin.', ['user_id' => $user->id]);
            session()->flash('message', "Usuario {$this->name} creado.");
        }

        $this->showModal = false;
        $this->resetForm();
    }

    private function syncRoles(User $user): void
    {
        if (! Schema::hasTable('roles')) {
            return;
        }

        $roleIds = Role::whereIn('slug', $this->selectedRoles)->pluck('id')->all();
        $user->syncRoles($roleIds);
    }

    private function syncEmployeeProfile(User $user): void
    {
        if (! Schema::hasTable('employee_profiles')) {
            return;
        }

        $user->employeeProfile()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'first_name' => $this->first_name ?: str($this->name)->before(' ')->toString(),
                'last_name' => $this->last_name ?: str($this->name)->after(' ')->toString(),
                'phone' => $this->phone ?: null,
                'address' => $this->address ?: null,
                'job_title' => $this->job_title ?: null,
                'department' => $this->department ?: null,
                'hire_date' => $this->hire_date ?: null,
                'status' => $this->is_active ? 'active' : 'inactive',
                'identity_document' => $this->identity_document ?: null,
                'emergency_contact' => $this->emergency_contact ?: null,
                'notes' => $this->notes ?: null,
            ]
        );
    }

    // ─── Confirmar borrar ─────────────────────────────────────────────────────
    public function confirmDelete($id)
    {
        abort_unless(auth()->user()?->can('admin.users.manage'), 403);

        if ($id === auth()->id()) {
            session()->flash('error', 'No puedes eliminarte a ti mismo.');

            return;
        }
        $this->deleteId = $id;
        $this->showDeleteModal = true;
    }

    public function destroy()
    {
        abort_unless(auth()->user()?->can('admin.users.manage'), 403);

        $user = User::findOrFail($this->deleteId);

        if ($user->id === 1) {
            session()->flash('error', 'No se puede eliminar al administrador principal.');
            $this->showDeleteModal = false;

            return;
        }

        if ($this->userHasOperationalHistory($user)) {
            $user->forceFill(['is_active' => false])->save();
            if (Schema::hasTable('employee_profiles')) {
                $user->employeeProfile?->update(['status' => 'inactive']);
            }
            ActivityLog::log('admin', 'user.deactivated', "Usuario #{$user->id} ({$user->name}) desactivado por historial.", ['user_id' => $user->id]);
            session()->flash('message', 'Usuario desactivado. Se conserva su historial.');
        } else {
            ActivityLog::log('admin', 'user.deleted', "Usuario #{$user->id} ({$user->name}) eliminado por admin.");
            $user->delete();
            session()->flash('message', 'Usuario eliminado.');
        }

        $this->showDeleteModal = false;
        $this->deleteId = null;
    }

    // ─── Toggle activar/desactivar ────────────────────────────────────────────
    public function toggleActive($id)
    {
        abort_unless(auth()->user()?->can('admin.users.manage'), 403);

        if ($id === auth()->id()) {
            session()->flash('error', 'No puedes desactivarte a ti mismo.');

            return;
        }
        $user = User::findOrFail($id);
        $user->is_active = ! $user->is_active;
        $user->save();
        if (Schema::hasTable('employee_profiles')) {
            $user->employeeProfile?->update(['status' => $user->is_active ? 'active' : 'inactive']);
        }

        $status = $user->is_active ? 'activado' : 'desactivado';
        ActivityLog::log('admin', $user->is_active ? 'user.activated' : 'user.deactivated', "Usuario {$user->name} {$status} por admin.", ['user_id' => $id]);
        session()->flash('message', "Usuario {$user->name} {$status}.");
    }

    private function userHasOperationalHistory(User $user): bool
    {
        return $user->activityLogs()->exists()
            || $user->stockMoves()->exists()
            || $user->assignedTickets()->exists()
            || $user->ticketMessages()->exists();
    }

    private function resetForm(): void
    {
        $this->reset([
            'editingId',
            'name',
            'first_name',
            'last_name',
            'email',
            'phone',
            'address',
            'job_title',
            'department',
            'hire_date',
            'identity_document',
            'emergency_contact',
            'notes',
            'role',
            'selectedRoles',
            'password',
            'is_active',
        ]);
    }
}

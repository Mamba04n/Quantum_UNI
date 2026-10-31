<?php

namespace App\Models;

use App\Domain\Administracion\Rbac\Data\UserAccess;
use App\Domain\Administracion\Rbac\Services\UserAccessResolver;
use Illuminate\Auth\MustVerifyEmail;
use Illuminate\Contracts\Auth\MustVerifyEmail as MustVerifyEmailContract;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements MustVerifyEmailContract
{
    use HasApiTokens, HasFactory, MustVerifyEmail, Notifiable;

    // ─── Roles disponibles ─────────────────────────────────────────────────────
    const ROLE_ADMIN = 'admin';

    const ROLE_FINANZAS = 'agente_finanzas';

    const ROLE_OPS = 'operaciones';

    const ROLE_VENTAS = 'ventas';

    const ROLE_BODEGA = 'bodega';

    const ROLE_SUPPORT = 'soporte';

    const ROLE_COMPRAS = 'compras';

    /** Cliente del sitio comercial (e-commerce); no accede al panel ERP. */
    const ROLE_CLIENTE_TIENDA = 'cliente_tienda';

    public static array $roles = [
        'admin' => 'Administrador',
        'agente_finanzas' => 'Agente de Finanzas',
        'operaciones' => 'Operaciones',
        'ventas' => 'Ventas',
        'bodega' => 'Bodega',
        'soporte' => 'Soporte',
        'compras' => 'Compras',
        'cliente_tienda' => 'Cliente (tienda)',
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'phone',
        'address',
        'is_active',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'is_active' => 'boolean',
    ];

    // ─── Helpers de rol ────────────────────────────────────────────────────────

    public function isAdmin(): bool
    {
        return $this->hasRole(self::ROLE_ADMIN);
    }

    public function hasRole(string ...$roles): bool
    {
        $roles = array_filter($roles);

        if (empty($roles)) {
            return false;
        }

        return array_intersect($roles, $this->access()->roleSlugs) !== [];
    }

    public function getRoleAttribute()
    {
        $roleSlugs = $this->roleSlugs();

        return $roleSlugs[0] ?? 'viewer';
    }

    public function getRoleLabelAttribute(): string
    {
        $labels = $this->roleLabels();

        return $labels[0] ?? 'Viewer';
    }

    public function roleLabels(): array
    {
        return $this->access()->roleLabels;
    }

    public function roleSlugs(): array
    {
        return $this->access()->roleSlugs;
    }

    public function hasPermissionTo(string $permissionSlug): bool
    {
        $permissionSlug = config("rbac.legacy_aliases.{$permissionSlug}", $permissionSlug);

        $access = $this->access();

        return in_array(self::ROLE_ADMIN, $access->roleSlugs, true)
            || in_array($permissionSlug, $access->permissionSlugs, true);
    }

    public function hasAnyPermission(array $permissionSlugs): bool
    {
        foreach ($permissionSlugs as $permissionSlug) {
            if ($this->hasPermissionTo($permissionSlug)) {
                return true;
            }
        }

        return false;
    }

    // ─── Permisos por módulo ────────────────────────────────────────────────────
    // Devuelve true si el rol puede acceder al módulo indicado

    public function canAccess(string $module): bool
    {
        if ($this->hasRole(self::ROLE_CLIENTE_TIENDA) && $module !== 'tickets') {
            return false;
        }

        $permissions = config("rbac.modules.{$module}", []);

        return $this->hasAnyPermission($permissions);
    }

    // ─── Relaciones ─────────────────────────────────────────────────────────────

    public function activityLogs()
    {
        return $this->hasMany(ActivityLog::class);
    }

    /** @return HasOne<EmployeeProfile, $this> */
    public function employeeProfile(): HasOne
    {
        return $this->hasOne(EmployeeProfile::class);
    }

    protected static function booted()
    {
        static::saving(function ($user) {
            if (array_key_exists('role', $user->attributes)) {
                // Remove the role attribute so it doesn't crash the SQL insert
                unset($user->attributes['role']);
            }
        });
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class)->withTimestamps();
    }

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'permission_user')->withTimestamps();
    }

    /** @param array<int, int|string> $roleIds */
    public function syncRoles(array $roleIds): void
    {
        $this->roles()->sync($roleIds);
        app(UserAccessResolver::class)->forget($this);
    }

    /** @param array<int, int|string> $permissionIds */
    public function syncPermissions(array $permissionIds): void
    {
        $this->permissions()->sync($permissionIds);
        app(UserAccessResolver::class)->forget($this);
    }

    private function access(): UserAccess
    {
        return app(UserAccessResolver::class)->resolve($this);
    }

    public function stockMoves()
    {
        return $this->hasMany(StockMove::class);
    }

    public function customerTickets()
    {
        return $this->hasMany(Ticket::class, 'customer_id');
    }

    public function assignedTickets()
    {
        return $this->hasMany(Ticket::class, 'assigned_to');
    }

    public function ticketMessages()
    {
        return $this->hasMany(TicketMessage::class, 'sender_id');
    }

    public function internalMessages()
    {
        return $this->hasMany(InternalMessage::class, 'user_id');
    }
}

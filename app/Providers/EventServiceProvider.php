<?php

namespace App\Providers;

use App\Domain\Inventario\Events\InventoryMovementRecorded;
use App\Domain\Inventario\Listeners\LogInventoryMovement;
use App\Domain\Logistica\Events\PackageShippedByCargoTrans;
use App\Domain\Logistica\Listeners\CreateLogisticsAccountingEntry;
use App\Domain\Pedidos\OrderStatus\Listeners\TrackOrderPhaseNotificationSent;
use App\Infrastructure\Audit\AuditContext;
use App\Infrastructure\Audit\BusinessAuditRecorder;
use App\Models\User;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Illuminate\Notifications\Events\NotificationSent;
use Illuminate\Support\Facades\Event;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event to listener mappings for the application.
     *
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
        Registered::class => [
            SendEmailVerificationNotification::class,
        ],
        PackageShippedByCargoTrans::class => [
            CreateLogisticsAccountingEntry::class,
        ],
        InventoryMovementRecorded::class => [
            LogInventoryMovement::class,
        ],
        NotificationSent::class => [
            TrackOrderPhaseNotificationSent::class,
        ],
    ];

    /**
     * Register any events for your application.
     */
    public function boot(): void
    {
        foreach (['created', 'updated', 'deleted', 'restored'] as $action) {
            Event::listen('eloquent.'.$action.': App\\Models\\*', function (string $event, array $models) use ($action): void {
                app(BusinessAuditRecorder::class)->model($models[0], $action);
            });
        }

        foreach ([Login::class => 'auth.login', Logout::class => 'auth.logout', Failed::class => 'auth.failed'] as $event => $action) {
            Event::listen($event, function ($event) use ($action): void {
                app(BusinessAuditRecorder::class)->record($action, User::class, $event->user?->id, [
                    'module' => 'security', 'ip' => request()->ip(), 'path' => AuditContext::path(request()->path()),
                ], $event->user?->id);
            });
        }
    }

    /**
     * Determine if events and listeners should be automatically discovered.
     */
    public function shouldDiscoverEvents(): bool
    {
        return false;
    }
}

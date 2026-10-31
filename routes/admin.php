<?php

use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\DashboardController;
use App\Livewire\Admin\InternalChat;
use App\Livewire\Administracion\ApprovalManager;
use App\Livewire\Administracion\Auditoria\MonitorActividad;
use App\Livewire\Administracion\Compras\Documentos\FormularioDocumentoOrdenCompra;
use App\Livewire\Administracion\DispatchAuthorizationManager;
use App\Livewire\Administracion\Finanzas\GestorRevisionFinanzas;
use App\Livewire\Administracion\Indicadores\PanelKpis;
use App\Livewire\Administracion\Kardex\VisorKardex;
use App\Livewire\Administracion\Proveedores\GestorProveedores;
use App\Livewire\Administracion\Reportes\CentroExportaciones;
use App\Livewire\Administracion\Tickets\GestorTickets;
use App\Livewire\Administracion\Usuarios\GestorUsuarios;
use App\Livewire\Administracion\Ventas\Ofertas\GestorRevisionOfertas;
use App\Livewire\Administracion\Ventas\Rfq\GestorRevisionRfq;
use App\Livewire\Web\BlogManager;
use App\Livewire\Web\CatalogManager;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'erp.employee', 'no-cache'])->group(function (): void {
    Route::get('/inicio/{area?}', \App\Http\Controllers\WorkspaceController::class)->name('workspace');
    Route::get('/dashboard', DashboardController::class)->name('dashboard');
    Route::get('/audit-logs', [AuditLogController::class, 'index'])
        ->middleware('permission:admin.activity.view')->name('audit-logs.index');
    Route::get('/web/blog', BlogManager::class)->middleware('permission:blog.manage,technical_blog.manage')->name('web.blog');
    Route::get('/web/catalogos', CatalogManager::class)->middleware('permission:catalog.manage')->name('web.catalogos');
    Route::get('/shipments', fn () => abort(404))->name('shipments.index');
});

Route::middleware(['auth', 'erp.employee', 'no-cache'])->prefix('admin')->name('admin.')->group(function (): void {
    Route::get('/users', GestorUsuarios::class)->middleware('permission:admin.users.manage')->name('users');
    Route::get('/activity', MonitorActividad::class)->middleware('permission:admin.activity.view')->name('activity');
    Route::get('/kardex', VisorKardex::class)->middleware('permission:warehouse.inventory.view,tracker.view_kardex')->name('kardex');
    Route::get('/approvals', ApprovalManager::class)->middleware('permission:internal_approval.view,approval.view,order_approval.view,dispatch_authorization.view,client_offer_review.view,rfq_review.view,finance.view,full_access')->name('approvals');
    Route::get('/dispatch-authorizations', DispatchAuthorizationManager::class)->middleware('permission:dispatch_authorization.view')->name('dispatch-authorizations');
    Route::get('/offer-reviews', GestorRevisionOfertas::class)->middleware('permission:client_offer_review.view')->name('offer-reviews');
    Route::get('/finance-reviews', GestorRevisionFinanzas::class)->middleware('permission:client_offer_review.view,finance.view,internal_approval.view')->name('finance-reviews');
    Route::get('/rfq-reviews', GestorRevisionRfq::class)->middleware('permission:rfq_review.view')->name('rfq-reviews');
    Route::get('/approvals/{approval}/purchase-document', FormularioDocumentoOrdenCompra::class)->middleware('permission:purchase_order_provider.view,purchase_order_document.view')->name('approvals.purchase-document');
    Route::get('/kpis', PanelKpis::class)->middleware('permission:kpi.view')->name('kpis');
    Route::get('/exports', CentroExportaciones::class)->middleware('permission:full_access')->name('exports');
    Route::get('/chat', InternalChat::class)->name('chat');
    Route::get('/tickets', GestorTickets::class)->middleware('permission:support.ticket.create,support.ticket.reply,support.ticket.close')->name('tickets');
    Route::get('/proveedores', GestorProveedores::class)->middleware('permission:supplier.view,supplier_bid.create,supplier_bid.select_winner,purchases.order.create')->name('suppliers');
});

Route::get('profile', \App\Http\Controllers\ProfileController::class)
    ->middleware(['auth', 'erp.employee', 'no-cache', 'permission:full_access'])
    ->name('profile');

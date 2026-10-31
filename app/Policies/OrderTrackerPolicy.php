<?php

namespace App\Policies;

use App\Models\OrderTracker;
use App\Models\User;

class OrderTrackerPolicy
{
    public function view(User $user, OrderTracker $tracker): bool
    {
        $tracker->loadMissing('customer');

        return $user->can('tracker.view')
            || ($user->can('customer_tracker.view_own') && $tracker->customer?->user_id === $user->id);
    }

    public function comment(User $user, OrderTracker $tracker): bool
    {
        return $user->can('tracker.comment');
    }

    public function attachFiles(User $user, OrderTracker $tracker): bool
    {
        return $user->can('tracker.attach_files');
    }

    public function changePhase(User $user, OrderTracker $tracker): bool
    {
        return $user->can('tracker.change_phase')
            && ! in_array($tracker->operational_status, ['entregado', 'cancelado'], true);
    }

    public function reopenPhase(User $user, OrderTracker $tracker): bool
    {
        return $user->can('tracker.reopen_phase')
            && in_array($tracker->operational_status, ['entregado', 'cancelado', 'rechazado'], true);
    }

    public function viewCosts(User $user, OrderTracker $tracker): bool
    {
        return $user->can('tracker.view_costs');
    }

    public function viewSupplierQuotes(User $user, OrderTracker $tracker): bool
    {
        return $user->can('tracker.view_supplier_quotes');
    }

    public function viewInvoices(User $user, OrderTracker $tracker): bool
    {
        return $user->can('tracker.view_invoices');
    }

    public function viewKardex(User $user, OrderTracker $tracker): bool
    {
        return $user->can('tracker.view_kardex');
    }

    public function viewSensitiveDocuments(User $user, OrderTracker $tracker): bool
    {
        return $user->can('tracker.view_sensitive_documents');
    }

    public function manageSensitiveDocuments(User $user, OrderTracker $tracker): bool
    {
        return $user->can('tracker.manage_sensitive_documents');
    }
}

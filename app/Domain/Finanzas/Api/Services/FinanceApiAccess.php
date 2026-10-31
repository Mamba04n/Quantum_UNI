<?php

namespace App\Domain\Finanzas\Api\Services;

use App\Domain\Finanzas\Api\Enums\FinanceTokenAbility;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

final class FinanceApiAccess
{
    /**
     * @param  list<string>  $requested
     * @return list<string>
     */
    public function issueAbilities(User $user, array $requested): array
    {
        if (! $user->is_active || $user->hasRole(User::ROLE_CLIENTE_TIENDA) || ! $user->hasPermissionTo('finance.view')) {
            throw new AuthorizationException('La cuenta no está habilitada para la API financiera.');
        }

        $requested = array_values(array_unique($requested));

        foreach ($requested as $ability) {
            $requiredPermissions = $this->permissionsFor($ability);
            if ($requiredPermissions !== [] && ! $user->hasAnyPermission($requiredPermissions)) {
                throw new AuthorizationException("La cuenta no puede solicitar la capacidad {$ability}.");
            }
        }

        return [FinanceTokenAbility::Tokens->value, ...$requested];
    }

    /** @return list<string> */
    private function permissionsFor(string $ability): array
    {
        $documentScope = ['finance.documents.view_all', 'finance.documents.view_assigned'];

        return match ($ability) {
            FinanceTokenAbility::ReceivablesRead->value => [...$documentScope, 'cxc.view'],
            FinanceTokenAbility::PayablesRead->value => [...$documentScope, 'cxp.view'],
            FinanceTokenAbility::InvoicesRead->value => [...$documentScope, 'finance.invoice.view', 'invoice.view'],
            FinanceTokenAbility::PaymentsRead->value => [...$documentScope, 'payment.view'],
            FinanceTokenAbility::DocumentsRead->value => $documentScope,
            FinanceTokenAbility::RatesRead->value => ['finance.view'],
            default => [],
        };
    }
}

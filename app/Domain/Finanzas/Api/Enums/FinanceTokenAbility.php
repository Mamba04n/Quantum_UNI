<?php

namespace App\Domain\Finanzas\Api\Enums;

enum FinanceTokenAbility: string
{
    case Tokens = 'finance:tokens';
    case ReceivablesRead = 'finance:receivables:read';
    case PayablesRead = 'finance:payables:read';
    case InvoicesRead = 'finance:invoices:read';
    case PaymentsRead = 'finance:payments:read';
    case DocumentsRead = 'finance:documents:read';
    case RatesRead = 'finance:rates:read';

    /** @return list<string> */
    public static function requestable(): array
    {
        return array_values(array_map(
            fn (self $ability) => $ability->value,
            array_filter(self::cases(), fn (self $ability) => $ability !== self::Tokens)
        ));
    }
}

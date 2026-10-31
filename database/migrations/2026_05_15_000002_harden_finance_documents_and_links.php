<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('account_payables')) {
            Schema::table('account_payables', function (Blueprint $table) {
                $this->stringIfMissing($table, 'payable_code', 'id');
                $this->foreignIfMissing($table, 'supplier_id', 'payable_code', 'entities');
                $this->foreignIfMissing($table, 'purchase_order_id', 'supplier_id', 'purchase_orders');
                $this->foreignIfMissing($table, 'order_status_id', 'purchase_order_id', 'order_statuses');
                $this->foreignIfMissing($table, 'invoice_id', 'order_status_id', 'invoices');
                $this->stringIfMissing($table, 'currency', 'total_amount', 'NIO', 10);
                $this->stringIfMissing($table, 'support_document_path', 'status');
                $this->foreignIfMissing($table, 'created_by', 'support_document_path', 'users');
                $this->foreignIfMissing($table, 'approved_by', 'created_by', 'users');
                $this->dateTimeIfMissing($table, 'paid_at', 'approved_by');
            });
        }

        if (Schema::hasTable('account_receivables')) {
            Schema::table('account_receivables', function (Blueprint $table) {
                $this->stringIfMissing($table, 'receivable_code', 'id');
                $this->foreignIfMissing($table, 'customer_id', 'receivable_code', 'entities');
                $this->foreignIfMissing($table, 'sales_order_id', 'customer_id', 'sale_orders');
                $this->foreignIfMissing($table, 'client_offer_id', 'sales_order_id', 'client_offer_versions');
                $this->foreignIfMissing($table, 'invoice_id', 'client_offer_id', 'invoices');
                $this->foreignIfMissing($table, 'order_tracker_id', 'invoice_id', 'order_trackers');
                $this->foreignIfMissing($table, 'order_status_id', 'order_tracker_id', 'order_statuses');
                $this->stringIfMissing($table, 'currency', 'total_amount', 'NIO', 10);
                $this->stringIfMissing($table, 'support_document_path', 'status');
                $this->foreignIfMissing($table, 'created_by', 'support_document_path', 'users');
                $this->dateTimeIfMissing($table, 'collected_at', 'created_by');
            });
        }

        if (Schema::hasTable('invoices')) {
            Schema::table('invoices', function (Blueprint $table) {
                $this->stringIfMissing($table, 'invoice_type', 'invoice_number', 'customer_invoice');
                $this->foreignIfMissing($table, 'customer_id', 'entity_id', 'entities');
                $this->foreignIfMissing($table, 'supplier_id', 'customer_id', 'entities');
                $this->foreignIfMissing($table, 'sales_order_id', 'supplier_id', 'sale_orders');
                $this->foreignIfMissing($table, 'purchase_order_id', 'sales_order_id', 'purchase_orders');
                $this->foreignIfMissing($table, 'order_status_id', 'purchase_order_id', 'order_statuses');
                $this->decimalIfMissing($table, 'subtotal', 'description');
                $this->decimalIfMissing($table, 'tax_amount', 'subtotal');
                $this->decimalIfMissing($table, 'discount_amount', 'tax_amount');
                $this->decimalIfMissing($table, 'total', 'discount_amount');
                $this->stringIfMissing($table, 'currency', 'total', 'NIO', 10);
                $this->stringIfMissing($table, 'invoice_file_path', 'status');
                $this->foreignIfMissing($table, 'generated_by', 'invoice_file_path', 'users');
                $this->foreignIfMissing($table, 'uploaded_by', 'generated_by', 'users');
            });
        }

        if (Schema::hasTable('payments')) {
            Schema::table('payments', function (Blueprint $table) {
                $this->stringIfMissing($table, 'payment_code', 'id');
                $this->stringIfMissing($table, 'payment_type', 'payment_code');
                $this->foreignIfMissing($table, 'accounts_receivable_id', 'payment_type', 'account_receivables');
                $this->foreignIfMissing($table, 'accounts_payable_id', 'accounts_receivable_id', 'account_payables');
                $this->foreignIfMissing($table, 'invoice_id', 'accounts_payable_id', 'invoices');
                $this->stringIfMissing($table, 'currency', 'amount', 'NIO', 10);
                $this->stringIfMissing($table, 'payment_reference', 'payment_method');
                $this->stringIfMissing($table, 'payment_evidence_path', 'receipt_reference');
                $this->foreignIfMissing($table, 'registered_by', 'payment_evidence_path', 'users');
                $this->foreignIfMissing($table, 'approved_by', 'registered_by', 'users');
                $this->textIfMissing($table, 'notes', 'approved_by');
            });
        }
    }

    public function down(): void
    {
        // Intencionalmente no elimina columnas en rollback para evitar perder rutas de documentos financieros.
    }

    private function stringIfMissing(Blueprint $table, string $column, string $after, ?string $default = null, int $length = 255): void
    {
        $tableName = $this->tableName($table);
        if (! Schema::hasColumn($tableName, $column)) {
            $definition = $table->string($column, $length)->nullable()->after($after);
            if ($default !== null) {
                $definition->default($default);
            }
        }
    }

    private function decimalIfMissing(Blueprint $table, string $column, string $after): void
    {
        $tableName = $this->tableName($table);
        if (! Schema::hasColumn($tableName, $column)) {
            $table->decimal($column, 15, 2)->default(0)->after($after);
        }
    }

    private function dateTimeIfMissing(Blueprint $table, string $column, string $after): void
    {
        $tableName = $this->tableName($table);
        if (! Schema::hasColumn($tableName, $column)) {
            $table->dateTime($column)->nullable()->after($after);
        }
    }

    private function textIfMissing(Blueprint $table, string $column, string $after): void
    {
        $tableName = $this->tableName($table);
        if (! Schema::hasColumn($tableName, $column)) {
            $table->text($column)->nullable()->after($after);
        }
    }

    private function foreignIfMissing(Blueprint $table, string $column, string $after, string $references): void
    {
        $tableName = $this->tableName($table);
        if (! Schema::hasColumn($tableName, $column)) {
            $table->foreignId($column)->nullable()->after($after)->constrained($references)->nullOnDelete();
        }
    }

    private function tableName(Blueprint $table): string
    {
        return $table->getTable();
    }
};

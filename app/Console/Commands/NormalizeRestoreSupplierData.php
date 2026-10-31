<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use PDO;

class NormalizeRestoreSupplierData extends Command
{
    protected $signature = 'normalization:restore-supplier-data';
    protected $description = 'Restore lost supplier data from erp_legacy database into phase 18 tables';

    public function handle()
    {
        $this->info('Starting Supplier Data Restoration...');

        try {
            $legacy = new PDO('mysql:host=127.0.0.1;dbname=erp_legacy;charset=utf8mb4', 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        } catch (\Exception $e) {
            $this->error('Could not connect to erp_legacy: ' . $e->getMessage());
            return;
        }

        // 1. Supplier Brands
        $this->restorePivot($legacy, 'supplier_brands', 'brands', 'brand_entity', 'brand_id', 'name');

        // 2. Supplier Product Types
        $this->restorePivot($legacy, 'supplier_product_types', 'product_types', 'entity_product_type', 'product_type_id', 'product_type');

        // 3. Supplier Machine Types
        $this->restorePivot($legacy, 'supplier_machine_types', 'machine_types', 'entity_machine_type', 'machine_type_id', 'machine_type');

        // 4. Supplier Payment Methods
        $this->restorePivot($legacy, 'supplier_payment_methods', 'payment_methods', 'entity_payment_method', 'payment_method_id', 'payment_method');

        // 5. Supplier Bank Accounts
        $this->info('Restoring supplier_bank_accounts...');
        $stmt = $legacy->query("SELECT * FROM supplier_bank_accounts");
        $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $count = 0;

        foreach ($records as $row) {
            $exists = DB::table('entity_bank_accounts')->where([
                'entity_id' => $row['supplier_id'],
                'account_number' => $row['account_number']
            ])->exists();

            if (!$exists) {
                DB::table('entity_bank_accounts')->insert([
                    'entity_id' => $row['supplier_id'],
                    'bank_name' => $row['bank_name'],
                    'account_holder' => $row['account_holder'],
                    'account_number' => $row['account_number'],
                    'account_type' => $row['account_type'],
                    'currency' => $row['currency'] ?? 'MXN',
                    'swift' => $row['swift'],
                    'iban' => $row['iban'],
                    'country' => $row['country'],
                    'notes' => $row['notes'],
                    'is_active' => $row['is_active'],
                    'created_at' => $row['created_at'],
                    'updated_at' => $row['updated_at'],
                ]);
                $count++;
            }
        }
        $this->info("Restored $count bank accounts.");

        $this->info('Restoration completed.');
    }

    private function restorePivot(PDO $legacy, string $legacyTable, string $newCatalogTable, string $newPivotTable, string $pivotKey, string $nameColumn)
    {
        $this->info("Restoring $legacyTable...");
        $stmt = $legacy->query("SELECT * FROM $legacyTable");
        $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $count = 0;

        foreach ($records as $row) {
            $catalogName = $row[$nameColumn] ?? 'Unknown';

            // Create catalog entry
            $catalogId = DB::table($newCatalogTable)->where('name', $catalogName)->value('id');
            if (!$catalogId) {
                $catalogId = DB::table($newCatalogTable)->insertGetId([
                    'name' => $catalogName,
                    'slug' => \Illuminate\Support\Str::slug($catalogName) . '-' . uniqid(),
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            // Create Pivot
            $entityId = $row['entity_id'];
            $exists = DB::table($newPivotTable)->where([
                'entity_id' => $entityId,
                $pivotKey => $catalogId
            ])->exists();

            if (!$exists) {
                DB::table($newPivotTable)->insert([
                    'entity_id' => $entityId,
                    $pivotKey => $catalogId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $count++;
            }
        }

        $this->info("Restored $count records into $newPivotTable.");
    }
}

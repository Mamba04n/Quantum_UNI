<?php

namespace Database\Seeders;

use App\Models\Permission;
use Illuminate\Database\Seeder;

class PaymentLinksPermissionSeeder extends Seeder
{
    public function run(): void
    {
        foreach (config('rbac.permissions.finance', []) as $slug => $name) {
            if (str_starts_with($slug, 'payment_links.') || str_starts_with($slug, 'public_tracking.')) {
                Permission::firstOrCreate(['slug' => $slug], ['name' => $name, 'module' => 'finance']);
            }
        }
        // No automatic grants to employee roles; assign permissions through existing RBAC.
    }
}

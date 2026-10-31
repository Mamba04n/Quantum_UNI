<?php

namespace Tests\Feature;

use App\Support\WorkspaceDashboard;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class WorkspaceDashboardTest extends TestCase
{
    public function test_unauthorized_modules_are_not_queried(): void
    {
        $queries = [];
        DB::listen(function ($query) use (&$queries) { $queries[] = $query->sql; });
        $this->assertSame([], (new WorkspaceDashboard)->summary('finanzas', [])['series']);
        $this->assertSame([], $queries);
    }

    public function test_monthly_counts_include_only_the_selected_module_and_six_month_window(): void
    {
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');
        Schema::create('account_receivables', function (Blueprint $table) {
            $table->id();
            $table->timestamp('created_at');
        });
        DB::table('account_receivables')->insert([
            ['created_at' => now()->startOfMonth()->subMonths(6)],
            ['created_at' => now()->startOfMonth()->subMonths(5)],
            ['created_at' => now()->startOfMonth()],
            ['created_at' => now()->startOfMonth()->addMonth()],
        ]);
        $summary = (new WorkspaceDashboard)->summary('finanzas', ['finance.cxc' => ['CXC', '']]);
        $this->assertCount(1, $summary['series']);
        $this->assertSame('finance.cxc', $summary['series'][0]['route']);
        $this->assertSame(4, $summary['series'][0]['value']);
        $this->assertSame([1, 0, 0, 0, 0, 1], $summary['series'][0]['values']);
        $this->assertCount(6, $summary['months']);
    }
}

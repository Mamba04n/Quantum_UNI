<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // ERP runs MySQL/MariaDB. Triggers also cover mass SQL and FK cascades,
    // which Eloquent observers cannot reliably capture.
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            throw new RuntimeException('El feed de maestros requiere MySQL/MariaDB en el ERP.');
        }
        Schema::create('integration_master_clock', function (Blueprint $t) {
            $t->unsignedTinyInteger('id')->primary();
            $t->unsignedBigInteger('version')->default(0);
            $t->dateTime('observed_at', 6);
        });
        DB::table('integration_master_clock')->insert(['id' => 1, 'version' => 0, 'observed_at' => DB::raw('UTC_TIMESTAMP(6)')]);
        Schema::create('integration_master_versions', function (Blueprint $t) {
            $t->unsignedBigInteger('version');
            $t->string('resource', 20);
            $t->unsignedBigInteger('external_id');
            $t->dateTime('observed_at', 6);
            $t->boolean('deleted');
            $t->json('payload');
            $t->primary(['resource', 'external_id', 'version'], 'integration_master_pk');
            $t->index(['resource', 'version'], 'integration_master_page');
        });

        foreach (['INSERT' => 'NEW', 'UPDATE' => 'NEW', 'DELETE' => 'OLD'] as $event => $row) {
            $deleted = $event === 'DELETE';
            $timing = $deleted ? 'BEFORE' : 'AFTER';
            $this->trigger('entities', $event, $timing,
                $this->customer($row, $deleted).$this->assets("m.entity_id = {$row}.id", $deleted, $row));
            $this->trigger('client_machines', $event, $timing, $this->asset($row, $deleted));
            $this->trigger('employee_profiles', $event, $timing, $this->employee($row, $deleted));
        }
        // User changes are part of the employee aggregate. BEFORE DELETE captures
        // children before MySQL cascades (cascades do not invoke child triggers).
        $this->trigger('users', 'UPDATE', 'AFTER', $this->employees('p.user_id = NEW.id', false, 'NEW'));
        $this->trigger('users', 'DELETE', 'BEFORE', $this->employees('p.user_id = OLD.id', true, 'OLD'));

        // Install in maintenance mode: serialize the baseline with all writers.
        DB::transaction(function () {
            DB::statement($this->tick());
            DB::statement($this->insert('customers', 'e.id', "e.type <> 'cliente'", $this->customerJson('e'), "FROM entities e WHERE e.type = 'cliente'"));
            DB::statement($this->assets('1 = 1'));
            DB::statement($this->employees('1 = 1'));
        });
    }

    private function tick(): string
    {
        // A singleton lock serializes versions until COMMIT; AUTO_INCREMENT alone
        // can commit out of order and lose changes behind a checkpoint.
        return 'UPDATE integration_master_clock SET version = version + 1, observed_at = GREATEST(observed_at, UTC_TIMESTAMP(6)) WHERE id = 1;';
    }

    private function trigger(string $table, string $event, string $timing, string $body): void
    {
        DB::unprepared('CREATE TRIGGER cmms_'.$table.'_'.strtolower($event)." {$timing} {$event} ON {$table} FOR EACH ROW BEGIN ".$this->tick().$body.' END');
    }

    private function insert(string $resource, string $id, string $deleted, string $json, string $from = ''): string
    {
        return "INSERT INTO integration_master_versions (version, resource, external_id, observed_at, deleted, payload) SELECT (SELECT version FROM integration_master_clock WHERE id=1), '{$resource}', {$id}, (SELECT observed_at FROM integration_master_clock WHERE id=1), {$deleted}, {$json} {$from};";
    }

    private function customerJson(string $e): string
    {
        return "JSON_OBJECT('id', CAST({$e}.id AS CHAR), 'name', {$e}.name, 'active', IF({$e}.status = 'active' AND {$e}.type = 'cliente', 1, 0))";
    }

    private function customer(string $row, bool $deleted): string
    {
        $removed = $deleted ? '1' : "{$row}.type <> 'cliente'";
        // Do not disclose suppliers who have never been customers.
        $wasCustomer = $row === 'OLD' ? "{$row}.type = 'cliente'" : "({$row}.type = 'cliente' OR EXISTS (SELECT 1 FROM integration_master_versions WHERE resource='customers' AND external_id={$row}.id))";

        return $this->insert('customers', "{$row}.id", $removed, $this->customerJson($row), "WHERE {$wasCustomer}");
    }

    private function assetJson(string $m, string $e): string
    {
        return "JSON_OBJECT('id', CAST({$m}.id AS CHAR), 'customer_id', CAST({$m}.entity_id AS CHAR), 'code', {$m}.internal_code, 'name', {$m}.name, 'brand', {$m}.brand, 'model', {$m}.model, 'serial_number', {$m}.serial_number, 'location_id', NULL, 'active', IF({$e}.type = 'cliente' AND {$e}.status = 'active' AND {$m}.status <> 'fuera_de_servicio', 1, 0))";
    }

    private function asset(string $m, bool $deleted): string
    {
        return $this->insert('assets', "{$m}.id", $deleted ? '1' : "e.type <> 'cliente'", $this->assetJson($m, 'e'), "FROM entities e WHERE e.id = {$m}.entity_id AND (e.type='cliente' OR EXISTS (SELECT 1 FROM integration_master_versions WHERE resource='assets' AND external_id={$m}.id))");
    }

    private function assets(string $where, bool $deleted = false, ?string $entity = null): string
    {
        $e = $entity ?? 'e';
        $join = $entity ? '' : 'JOIN entities e ON e.id = m.entity_id';

        return $this->insert('assets', 'm.id', $deleted ? '1' : "{$e}.type <> 'cliente'", $this->assetJson('m', $e), "FROM client_machines m {$join} WHERE {$where} AND ({$e}.type='cliente' OR EXISTS (SELECT 1 FROM integration_master_versions WHERE resource='assets' AND external_id=m.id))");
    }

    private function employeeJson(string $p, string $u): string
    {
        return "JSON_OBJECT('id', CAST({$p}.id AS CHAR), 'user_id', CAST({$p}.user_id AS CHAR), 'name', {$u}.name, 'email', {$u}.email, 'job_title', {$p}.job_title, 'department', {$p}.department, 'active', IF({$u}.is_active = 1 AND {$p}.status='active', 1, 0))";
    }

    private function employee(string $p, bool $deleted): string
    {
        return $this->insert('employees', "{$p}.id", $deleted ? '1' : '0', $this->employeeJson($p, 'u'), "FROM users u WHERE u.id = {$p}.user_id");
    }

    private function employees(string $where, bool $deleted = false, ?string $user = null): string
    {
        $u = $user ?? 'u';
        $join = $user ? '' : 'JOIN users u ON u.id = p.user_id';

        return $this->insert('employees', 'p.id', $deleted ? '1' : '0', $this->employeeJson('p', $u), "FROM employee_profiles p {$join} WHERE {$where}");
    }

    public function down(): void
    {
        foreach (['entities', 'client_machines', 'employee_profiles', 'users'] as $table) {
            foreach (['insert', 'update', 'delete'] as $event) {
                DB::unprepared("DROP TRIGGER IF EXISTS cmms_{$table}_{$event}");
            }
        }
        Schema::dropIfExists('integration_master_versions');
        Schema::dropIfExists('integration_master_clock');
    }
};

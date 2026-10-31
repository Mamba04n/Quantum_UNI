<?php
require __DIR__.'/../../../vendor/autoload.php';
$app = require __DIR__.'/../../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use App\Models\AccountPayable;
use App\Models\AccountReceivable;
use App\Models\Entity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
if (!app()->environment('testing') || DB::connection()->getDatabaseName() !== 'erp_crm_testing'
    || !in_array(DB::connection()->getConfig('host'), ['localhost','127.0.0.1'], true)) throw new RuntimeException('Isolated testing database required');
Model::withoutEvents(function () {
    DB::transaction(function () {
        $client = Entity::firstOrCreate(['name'=>'UX-FINANCE-VISUAL Cliente DEMO','type'=>'cliente'],['status'=>'activo']);
        $supplier = Entity::firstOrCreate(['name'=>'UX-FINANCE-VISUAL Proveedor DEMO','type'=>'proveedor'],['status'=>'activo']);
        foreach ([1,3,2,4,3,5] as $index => $volume) {
            $month=now()->startOfMonth()->subMonths(5-$index);
            foreach ([[AccountReceivable::class,$client->id,'CXC',$volume],[AccountPayable::class,$supplier->id,'CXP',$volume+1]] as [$model,$entityId,$kind,$count]) {
                for ($i=1;$i<=$count;$i++) {
                    $model::firstOrCreate(['concept'=>'UX-FINANCE-VISUAL-'.$month->format('Ym').'-'.$kind.'-'.$i],[
                        'entity_id'=>$entityId,'total_amount'=>1200+$i*100,'paid_amount'=>0,'currency'=>'NIO','exchange_rate'=>1,
                        'due_date'=>$month->copy()->addDays(5+$i),'status'=>'pendiente','created_at'=>$month->copy()->addDay(),'updated_at'=>$month->copy()->addDay(),
                    ]);
                }
            }
        }
    });
});
echo "FINANCE_VISUAL_FIXTURES_OK\n";

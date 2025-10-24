<?php

namespace App\Console\Commands;

use App\Models\Document;
use App\Models\IndexingWorkflow;
use App\Models\IndexingWorkflowItem;
use App\Models\JiraProject;
use App\Models\Participant;
use App\Models\Tenant;
use App\Services\GraphDB\GraphDB;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use Stancl\Tenancy\Concerns\HasATenantArgument;
use Stancl\Tenancy\Concerns\HasATenantsOption;

class Reset extends Command
{
    use HasATenantArgument;

    protected $signature = 'reset';

    protected $description = 'Command description';

    public function handle()
    {
        $tenant = Tenant::findOrFail($this->argument('tenant'));
        tenancy()->initialize($tenant);

        $graphDB = app(GraphDB::class);
        $sure = $this->confirm('This will clean everything except integration-related data such as tokens, refresh tokens, etc. Are you sure?', true);
        if (!$sure) {
            return;
        }
        if (App::environment('production')) {
            $this->fail('do not run this in production');
        }
        Document::all()->each->delete();
        Participant::all()->each->delete();
        JiraProject::all()->each->delete();
        IndexingWorkflow::all()->each->delete();
        IndexingWorkflowItem::all()->each->delete();
        $graphDB->query('MATCH (n) DETACH DELETE n');

        tenancy()->end();
        DB::table('jobs')->delete();
        DB::table('failed_jobs')->delete();        
    }
}

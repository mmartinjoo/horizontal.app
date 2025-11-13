<?php

namespace App\Console\Commands;

use App\Models\GoogleChatIntegration;
use App\Models\GoogleDriveIntegration;
use App\Models\JiraIntegration;
use App\Models\LinearIntegration;
use App\Models\SlackIntegration;
use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Stancl\Tenancy\Concerns\HasATenantArgument;

class CheckRefreshTokensCommand extends Command
{
    use HasATenantArgument;
    
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'integrations:check-refresh-tokens
                            {--integration= : Check specific integration type (jira, linear, slack, google-drive, google-chat)}
                            {--show-details : Show detailed information for each integration}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check which OAuth integrations are missing refresh tokens';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $tenant = Tenant::findOrFail($this->argument('tenant'));
        tenancy()->initialize($tenant);
        
        $this->info('Checking OAuth integrations for missing refresh tokens...');
        $this->newLine();

        $integrationType = $this->option('integration');
        $showDetails = $this->option('show-details');

        $results = [];

        // Check each integration type
        if (! $integrationType || $integrationType === 'jira') {
            $results['Jira'] = $this->checkIntegration(JiraIntegration::class, 'jira_integrations', $showDetails);
        }

        if (! $integrationType || $integrationType === 'linear') {
            $results['Linear'] = $this->checkIntegration(LinearIntegration::class, 'linear_integrations', $showDetails);
        }

        if (! $integrationType || $integrationType === 'slack') {
            $results['Slack'] = $this->checkIntegration(SlackIntegration::class, 'slack_integrations', $showDetails);
        }

        if (! $integrationType || $integrationType === 'google-drive') {
            $results['Google Drive'] = $this->checkIntegration(GoogleDriveIntegration::class, 'google_drive_integrations', $showDetails);
        }

        if (! $integrationType || $integrationType === 'google-chat') {
            $results['Google Chat'] = $this->checkIntegration(GoogleChatIntegration::class, 'google_chat_integrations', $showDetails);
        }

        // Display summary
        $this->newLine();
        $this->info('═══════════════════════════════════════════════════════════');
        $this->info('                        SUMMARY                            ');
        $this->info('═══════════════════════════════════════════════════════════');
        $this->newLine();

        $headers = ['Integration', 'Total', 'With Refresh Token', 'Missing Refresh Token', 'Status'];
        $rows = [];

        foreach ($results as $name => $data) {
            $status = $data['missing'] === 0 ? '<fg=green>✓ OK</>' : '<fg=yellow>⚠ Action Needed</>';
            $rows[] = [
                $name,
                $data['total'],
                "<fg=green>{$data['with']}</>",
                $data['missing'] > 0 ? "<fg=yellow>{$data['missing']}</>" : $data['missing'],
                $status,
            ];
        }

        $this->table($headers, $rows);

        $totalMissing = array_sum(array_column($results, 'missing'));

        if ($totalMissing > 0) {
            $this->newLine();
            $this->warn("⚠ {$totalMissing} integration(s) are missing refresh tokens.");
            $this->newLine();
            $this->info('Next Steps:');
            $this->line('  1. For Google integrations: OAuth flow has been fixed. Users need to re-authorize.');
            $this->line('  2. For Slack: Enable token rotation in your Slack app settings at api.slack.com');
            $this->line('  3. Notify affected users to disconnect and re-connect their integrations');
            $this->newLine();
            $this->info('For more information, see: api/OAUTH_SETUP.md');

            Log::channel('slack')->error('Missing refresh token in ' . $tenant->company);

            return Command::FAILURE;
        }

        $this->newLine();
        $this->info('✓ All OAuth integrations have refresh tokens!');

        return Command::SUCCESS;
    }

    /**
     * Check a specific integration model for missing refresh tokens
     */
    private function checkIntegration(string $modelClass, string $tableName, bool $showDetails): array
    {
        $total = $modelClass::count();
        $withRefreshToken = $modelClass::whereNotNull('refresh_token')->count();
        $missingRefreshToken = $total - $withRefreshToken;

        $integrationName = class_basename($modelClass);
        $integrationName = str_replace('Integration', '', $integrationName);

        $this->line("Checking {$integrationName}...");

        if ($total === 0) {
            $this->line('  No integrations found');
        } else {
            $this->line("  Total: {$total}");
            $this->line("  With refresh token: <fg=green>{$withRefreshToken}</>");
            if ($missingRefreshToken > 0) {
                $this->line("  Missing refresh token: <fg=yellow>{$missingRefreshToken}</>");
            } else {
                $this->line("  Missing refresh token: {$missingRefreshToken}");
            }
        }

        if ($showDetails && $missingRefreshToken > 0) {
            $integrations = $modelClass::whereNull('refresh_token')->get();

            $this->newLine();
            $this->line('  Integrations without refresh tokens:');

            $detailHeaders = ['ID', 'User Email', 'Expires At', 'Days Until Expiry'];
            $detailRows = [];

            foreach ($integrations as $integration) {
                $expiresAt = $integration->expires_at;
                $daysUntilExpiry = $expiresAt ? now()->diffInDays($expiresAt, false) : 'N/A';

                if (is_numeric($daysUntilExpiry)) {
                    if ($daysUntilExpiry < 0) {
                        $daysUntilExpiry = '<fg=red>Expired</>';
                    } elseif ($daysUntilExpiry < 7) {
                        $daysUntilExpiry = "<fg=yellow>{$daysUntilExpiry} days</>";
                    } else {
                        $daysUntilExpiry = "{$daysUntilExpiry} days";
                    }
                }

                $detailRows[] = [
                    $integration->id,
                    $integration->user_email ?? 'N/A',
                    $expiresAt ? $expiresAt->format('Y-m-d H:i:s') : 'N/A',
                    $daysUntilExpiry,
                ];
            }

            $this->table($detailHeaders, $detailRows);
        }

        $this->newLine();

        return [
            'total' => $total,
            'with' => $withRefreshToken,
            'missing' => $missingRefreshToken,
        ];
    }
}

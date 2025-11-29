<?php

namespace App\Http\Controllers;

use App\Enums\Integration\Category;
use App\Models\GithubIntegration;
use App\Models\GithubRepository;
use App\Models\GoogleChatChannel;
use App\Models\GoogleChatIntegration;
use App\Models\GoogleDriveFolder;
use App\Models\GoogleDriveIntegration;
use App\Models\JiraIntegration;
use App\Models\JiraProject;
use App\Models\LinearIntegration;
use App\Models\LinearProject;
use App\Models\SlackChannel;
use App\Models\SlackIntegration;
use Illuminate\Http\JsonResponse;

class IntegrationController extends Controller
{
    /**
     * List all available integrations with their connection status.
     */
    public function index(): JsonResponse
    {
        $integrations = [
            Category::Communication->value => [
                [
                    'provider' => config('features.integrations.slack.slug'),
                    'name' => config('features.integrations.slack.name'),
                    'description' => 'Connect your Slack workspace and channels',
                    'connected' => SlackIntegration::query()->exists(),
                    'configured' => $this->isSlackConfigured(),
                ],
                [
                    'provider' => config('features.integrations.google_chat.slug'),
                    'name' => config('features.integrations.google_chat.name'),
                    'description' => 'Connect your Google Chat conversations and spaces',
                    'connected' => GoogleChatIntegration::query()->exists(),
                    'configured' => $this->isGoogleChatConfigured(),
                ],
            ],
            'task_management' => [
                [
                    'provider' => config('features.integrations.linear.slug'),
                    'name' => config('features.integrations.linear.name'),
                    'description' => 'Connect your Linear issues and projects',
                    'connected' => LinearIntegration::query()->exists(),
                    'configured' => $this->isLinearConfigured(),
                ],
                [
                    'provider' => config('features.integrations.jira.slug'),
                    'name' => config('features.integrations.jira.name'),
                    'description' => 'Connect your Jira issues and projects',
                    'connected' => JiraIntegration::query()->exists(),
                    'configured' => $this->isJiraConfigured(),
                ],
            ],
            'storage' => [
                [
                    'provider' => config('features.integrations.google_drive.slug'),
                    'name' => config('features.integrations.google_drive.name'),
                    'description' => 'Connect your files and folders',
                    'connected' => GoogleDriveIntegration::query()->exists(),
                    'configured' => $this->isGoogleDriveConfigured(),
                ],
            ],
            'code_repository' => [
                [
                    'provider' => config('features.integrations.github.slug'),
                    'name' => config('features.integrations.github.name'),
                    'description' => 'Connect your repositories and PRs',
                    'connected' => GithubIntegration::query()->exists(),
                    'configured' => $this->isGithubConfigured(),
                ],
            ],
        ];

        return response()->json($integrations);
    }

    private function isSlackConfigured(): bool
    {
        return SlackChannel::query()->exists();
    }

    private function isGoogleChatConfigured(): bool
    {
        return GoogleChatChannel::query()->exists();
    }

    /**
     * Check if Linear integration is configured.
     */
    private function isLinearConfigured(): bool
    {
        return LinearProject::query()->exists();
    }

    private function isJiraConfigured(): bool
    {
        return JiraProject::query()->exists();
    }

    private function isGoogleDriveConfigured(): bool
    {
        return GoogleDriveFolder::query()->exists();
    }

    private function isGithubConfigured(): bool
    {
        return GithubRepository::query()->exists();
    }
}

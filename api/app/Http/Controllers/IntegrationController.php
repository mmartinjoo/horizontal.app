<?php

namespace App\Http\Controllers;

use App\Models\GithubIntegration;
use App\Models\GoogleChatIntegration;
use App\Models\GoogleDriveIntegration;
use App\Models\JiraIntegration;
use App\Models\LinearIntegration;
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
            'communication' => [
                [
                    'provider' => 'slack',
                    'name' => 'Slack',
                    'description' => 'Connect your Slack workspace to search messages and channels',
                    'icon' => '/icons/slack.svg',
                    'connected' => SlackIntegration::query()->exists(),
                    'configured' => $this->isSlackConfigured(),
                ],
                [
                    'provider' => 'google_chat',
                    'name' => 'Google Chat',
                    'description' => 'Search your Google Chat conversations and spaces',
                    'icon' => '/icons/google-chat.svg',
                    'connected' => GoogleChatIntegration::query()->exists(),
                    'configured' => $this->isGoogleChatConfigured(),
                ],
            ],
            'task_management' => [
                [
                    'provider' => 'linear',
                    'name' => 'Linear',
                    'description' => 'Access your Linear issues and projects',
                    'icon' => '/icons/linear.svg',
                    'connected' => LinearIntegration::query()->exists(),
                    'configured' => $this->isLinearConfigured(),
                ],
                [
                    'provider' => 'jira',
                    'name' => 'Jira',
                    'description' => 'Search your Jira issues and projects',
                    'icon' => '/icons/jira.svg',
                    'connected' => JiraIntegration::query()->exists(),
                    'configured' => $this->isJiraConfigured(),
                ],
            ],
            'storage' => [
                [
                    'provider' => 'google_drive',
                    'name' => 'Google Drive',
                    'description' => 'Search files and documents from Google Drive',
                    'icon' => '/icons/google-drive.svg',
                    'connected' => GoogleDriveIntegration::query()->exists(),
                    'configured' => $this->isGoogleDriveConfigured(),
                ],
            ],
            'code_repository' => [
                [
                    'provider' => 'github',
                    'name' => 'GitHub',
                    'description' => 'Search your GitHub repositories and code',
                    'icon' => '/icons/github.svg',
                    'connected' => GithubIntegration::query()->exists(),
                    'configured' => $this->isGithubConfigured(),
                ],
            ],
        ];

        return response()->json($integrations);
    }

    /**
     * Check if Slack integration is configured with selected channels.
     */
    private function isSlackConfigured(): bool
    {
        return SlackChannel::query()->exists();
    }

    /**
     * Check if Google Chat integration is configured.
     */
    private function isGoogleChatConfigured(): bool
    {
        $integration = GoogleChatIntegration::first();

        return $integration && $integration->hasValidToken();
    }

    /**
     * Check if Linear integration is configured.
     */
    private function isLinearConfigured(): bool
    {
        $integration = LinearIntegration::first();

        return $integration && $integration->hasValidToken();
    }

    /**
     * Check if Jira integration is configured.
     */
    private function isJiraConfigured(): bool
    {
        $integration = JiraIntegration::first();

        return $integration && $integration->hasValidToken();
    }

    /**
     * Check if Google Drive integration is configured.
     */
    private function isGoogleDriveConfigured(): bool
    {
        $integration = GoogleDriveIntegration::first();

        return $integration && $integration->hasValidToken();
    }

    /**
     * Check if GitHub integration is configured.
     */
    private function isGithubConfigured(): bool
    {
        return GithubIntegration::query()->exists();
    }
}

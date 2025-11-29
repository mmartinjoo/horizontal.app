<?php

namespace App\Enums\Integration;

enum Provider: string
{
    case Slack = 'slack';
    case GoogleChat = 'google_chat';
    case Jira = 'jira';
    case Linear = 'linear';
    case GithubProjects = 'github_projects';
    case GoogleDrive = 'google_drive';
    case Github = 'github';
    case Confluence = 'confluence';

    public function name(): string
    {
        return match($this) {
            self::Slack => 'Slack',
            self::GoogleChat => 'Google Chat',
            self::Jira => 'Jira',
            self::Linear => 'Linear',
            self::GithubProjects => 'GitHub Projects',
            self::GoogleDrive => 'Google Drive',
            self::Github => 'GitHub',
            self::Confluence => 'Confluence',
        };
    }
}
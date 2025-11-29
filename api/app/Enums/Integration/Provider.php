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
}
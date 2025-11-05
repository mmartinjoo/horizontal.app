<?php

namespace App\Providers;

use App\Http\Controllers\GoogleChatIntegrationController;
use App\Http\Controllers\GoogleDriveIntegrationController;
use App\Services\GraphDB\GraphDB;
use App\Services\GraphDB\GraphDBFactory;
use App\Services\Integration\Communication\Slack\Slack;
use App\Services\Integration\CodeRepository\GitHub\GitHub;
use App\Services\Integration\CodeRepository\Github\GithubOAuth;
use App\Services\Integration\Communication\Slack\SlackOAuthService;
use App\Services\Integration\Google\GoogleChatOAuthService;
use App\Services\Integration\Google\GoogleDriveOAuthService;
use App\Services\Integration\Google\GoogleOAuthService;
use App\Services\Integration\TaskManagement\Jira\JiraOAuthService;
use App\Services\Integration\TaskManagement\Linear\LinearOAuthService;
use App\Services\KnowledgeGraph\GraphBuilder;
use App\Services\LLM\Anthropic;
use App\Services\LLM\Embedder;
use App\Services\LLM\Fireworks;
use App\Services\LLM\LLM;
use App\Services\LLM\LLMFactory;
use App\Services\LLM\OpenAI;
use App\Services\SearchEngine\SearchEngine;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        if (config('app.env') === 'production') {
            URL::forceScheme('https');
        }

        $this->app
            ->when(Anthropic::class)
            ->needs('$apiKey')
            ->give(config('services.anthropic.api_key'));

        $this->app
            ->when(Fireworks::class)
            ->needs('$apiKey')
            ->give(config('services.fireworks.api_key'));

        $this->app
            ->when(OpenAI::class)
            ->needs('$apiKey')
            ->give(config('services.openai.api_key'));

        $this->app
            ->bind(LLM::class, function () {
                return LLMFactory::create();
            });

        $this->app
            ->when(OpenAI::class)
            ->needs('$apiKey')
            ->give(config('services.openai.api_key'));

        $this->app
            ->bind(Embedder::class, function () {
                return LLMFactory::createEmbedder();
            });

        $this->app
            ->when(JiraOAuthService::class)
            ->needs('$clientId')
            ->give(config('services.jira.client_id'));

        $this->app
            ->when(JiraOAuthService::class)
            ->needs('$clientSecret')
            ->give(config('services.jira.client_secret'));

        $this->app
            ->when(JiraOAuthService::class)
            ->needs('$redirectUri')
            ->give(config('services.jira.redirect_uri'));

        $this->app
            ->when(LinearOAuthService::class)
            ->needs('$clientId')
            ->give(config('services.linear.client_id'));

        $this->app
            ->when(LinearOAuthService::class)
            ->needs('$clientSecret')
            ->give(config('services.linear.client_secret'));

        $this->app
            ->when(GoogleChatIntegrationController::class)
            ->needs(GoogleOAuthService::class)
            ->give(GoogleChatOAuthService::class);

        $this->app
            ->when(GoogleDriveIntegrationController::class)
            ->needs(GoogleOAuthService::class)
            ->give(GoogleDriveOAuthService::class);

        $this->app
            ->when(GoogleChatOAuthService::class)
            ->needs('$config')
            ->give(config('services.google_chat'));

        $this->app
            ->when(GoogleDriveOAuthService::class)
            ->needs('$config')
            ->give(config('services.google_drive'));

        $this->app
            ->when(LinearOAuthService::class)
            ->needs('$redirectUri')
            ->give(config('services.linear.redirect_uri'));

        $this->app->bind(GraphDB::class, function () {
            return GraphDBFactory::create();
        });

        $this->app
            ->when(SearchEngine::class)
            ->needs('$cosineSimilarityThreshold')
            ->give(config('search_engine.cosine_similarity_threshold'));

        $this->app
            ->when(GraphBuilder::class)
            ->needs('$baseUrl')
            ->give(config('graph_builder.base_url'));

        $this->app
            ->when(Slack::class)
            ->needs('$botUserOauthToken')
            ->give(config('services.slack.bot_user_oauth_token'));

        $this->app
            ->when(Slack::class)
            ->needs('$baseUrl')
            ->give(config('services.slack.base_url'));

        $this->app
            ->when(GitHub::class)
            ->needs('$accessToken')
            ->give(config('services.github_integration.access_token'));

        $this->app
            ->when(GitHub::class)
            ->needs('$baseUrl')
            ->give(config('services.github_integration.base_url'));

        $this->app
            ->when(GithubOAuth::class)
            ->needs('$config')
            ->give(config('services.github_integration'));

        $this->app
            ->when(SlackOAuthService::class)
            ->needs('$config')
            ->give(config('services.slack'));
    }
}

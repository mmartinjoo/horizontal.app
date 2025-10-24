<?php

namespace App\Providers;

use App\Services\GraphDB\GraphDB;
use App\Services\GraphDB\GraphDBFactory;
use App\Services\Integration\CodeRepository\GitHub\GitHub;
use App\Services\Integration\TaskManagement\Jira\JiraOAuthService;
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
            ->when(GitHub::class)
            ->needs('$accessToken')
            ->give(config('services.github.access_token'));

        $this->app
            ->when(GitHub::class)
            ->needs('$baseUrl')
            ->give(config('services.github.base_url'));
    }
}

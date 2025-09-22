<?php

namespace App\Services\Memory;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

class GraphitiService
{
    private string $baseUrl;

    public function __construct()
    {
        $this->baseUrl = config('services.graphiti.base_url', 'http://graphiti:9995');
    }

    /**
     * Add information to episodic memory
     */
    public function addMemory(string $text, int $teamId, array $context = []): ?string
    {
        try {
            $response = Http::timeout(30)->post("{$this->baseUrl}/api/memory/add", [
                'text' => $text,
                'team_id' => $teamId,
                'context' => $context,
            ]);

            if ($response->successful()) {
                return $response->json('episode_id');
            }

            Log::warning('Graphiti memory add failed', [
                'status' => $response->status(),
                'response' => $response->body(),
            ]);

            return null;
        } catch (Exception $e) {
            Log::error('Graphiti memory add error', [
                'error' => $e->getMessage(),
                'text' => $text,
                'team_id' => $teamId,
            ]);

            return null;
        }
    }

    /**
     * Search episodic memory
     */
    public function searchMemory(string $query, int $teamId, ?int $userId = null, int $limit = 10): array
    {
        try {
            $response = Http::timeout(30)->post("{$this->baseUrl}/api/memory/search", [
                'query' => $query,
                'team_id' => $teamId,
                'user_id' => $userId,
                'limit' => $limit,
            ]);

            if ($response->successful()) {
                return $response->json('results', []);
            }

            Log::warning('Graphiti memory search failed', [
                'status' => $response->status(),
                'response' => $response->body(),
            ]);

            return [];
        } catch (Exception $e) {
            Log::error('Graphiti memory search error', [
                'error' => $e->getMessage(),
                'query' => $query,
                'team_id' => $teamId,
            ]);

            return [];
        }
    }

    /**
     * Add document entity to knowledge graph
     */
    public function addDocumentEntity(array $documentData): ?string
    {
        try {
            $response = Http::timeout(30)->post("{$this->baseUrl}/api/entities/document", $documentData);

            if ($response->successful()) {
                return $response->json('entity_id');
            }

            Log::warning('Graphiti document entity add failed', [
                'status' => $response->status(),
                'response' => $response->body(),
            ]);

            return null;
        } catch (Exception $e) {
            Log::error('Graphiti document entity add error', [
                'error' => $e->getMessage(),
                'document' => $documentData,
            ]);

            return null;
        }
    }

    /**
     * Track user search patterns
     */
    public function trackSearchPattern(
        int $userId,
        string $searchQuery,
        int $teamId,
        array $clickedResults = [],
        ?string $searchContext = null
    ): ?string {
        try {
            $response = Http::timeout(30)->post("{$this->baseUrl}/api/patterns/search", [
                'user_id' => $userId,
                'search_query' => $searchQuery,
                'team_id' => $teamId,
                'clicked_results' => $clickedResults,
                'search_context' => $searchContext,
            ]);

            if ($response->successful()) {
                return $response->json('episode_id');
            }

            Log::warning('Graphiti search pattern tracking failed', [
                'status' => $response->status(),
                'response' => $response->body(),
            ]);

            return null;
        } catch (Exception $e) {
            Log::error('Graphiti search pattern tracking error', [
                'error' => $e->getMessage(),
                'user_id' => $userId,
                'query' => $searchQuery,
            ]);

            return null;
        }
    }

    /**
     * Get user's historical search patterns
     */
    public function getUserPatterns(int $userId, int $teamId, int $limit = 10): array
    {
        try {
            $response = Http::timeout(30)->get("{$this->baseUrl}/api/patterns/user/{$userId}", [
                'team_id' => $teamId,
                'limit' => $limit,
            ]);

            if ($response->successful()) {
                return $response->json('patterns', []);
            }

            Log::warning('Graphiti user patterns retrieval failed', [
                'status' => $response->status(),
                'response' => $response->body(),
            ]);

            return [];
        } catch (Exception $e) {
            Log::error('Graphiti user patterns retrieval error', [
                'error' => $e->getMessage(),
                'user_id' => $userId,
                'team_id' => $teamId,
            ]);

            return [];
        }
    }

    /**
     * Health check for Graphiti service
     */
    public function healthCheck(): bool
    {
        try {
            $response = Http::timeout(10)->get("{$this->baseUrl}/health");
            return $response->successful();
        } catch (Exception $e) {
            Log::error('Graphiti health check failed', ['error' => $e->getMessage()]);
            return false;
        }
    }
}
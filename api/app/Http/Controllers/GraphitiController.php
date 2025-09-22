<?php

namespace App\Http\Controllers;

use App\Services\Memory\GraphitiService;
use App\Services\SearchEngine\SearchEngine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class GraphitiController extends Controller
{
    public function __construct(
        private GraphitiService $graphitiService,
        private SearchEngine $searchEngine
    ) {}

    /**
     * Test basic Graphiti health check
     */
    public function healthCheck(): JsonResponse
    {
        $isHealthy = $this->graphitiService->healthCheck();

        return response()->json([
            'status' => $isHealthy ? 'healthy' : 'unhealthy',
            'service' => 'graphiti-memory',
            'timestamp' => now()->toISOString(),
        ], $isHealthy ? 200 : 503);
    }

    /**
     * Add memory to Graphiti
     */
    public function addMemory(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'text' => 'required|string|max:1000',
            'team_id' => 'required|integer',
            'context' => 'sometimes|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'messages' => $validator->errors(),
            ], 422);
        }

        $episodeId = $this->graphitiService->addMemory(
            text: $request->input('text'),
            teamId: $request->input('team_id'),
            context: $request->input('context', [])
        );

        return response()->json([
            'success' => $episodeId !== null,
            'episode_id' => $episodeId,
        ]);
    }

    /**
     * Search Graphiti memory
     */
    public function searchMemory(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'query' => 'required|string|max:500',
            'team_id' => 'required|integer',
            'user_id' => 'sometimes|integer',
            'limit' => 'sometimes|integer|min:1|max:50',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'messages' => $validator->errors(),
            ], 422);
        }

        $results = $this->graphitiService->searchMemory(
            query: $request->input('query'),
            teamId: $request->input('team_id'),
            userId: $request->input('user_id'),
            limit: $request->input('limit', 10)
        );

        return response()->json([
            'results' => $results,
            'total' => count($results),
        ]);
    }

    /**
     * Enhanced search with memory integration
     */
    public function searchWithMemory(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'question' => 'required|string|max:500',
            'team_id' => 'required|integer',
            'user_id' => 'sometimes|integer',
            'clicked_results' => 'sometimes|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'messages' => $validator->errors(),
            ], 422);
        }

        $results = $this->searchEngine->searchWithMemory(
            question: $request->input('question'),
            teamId: $request->input('team_id'),
            userId: $request->input('user_id'),
            clickedResults: $request->input('clicked_results', [])
        );

        return response()->json([
            'results' => $results->map(function ($result) {
                return [
                    'document_chunk_id' => $result->documentChunk->id,
                    'content' => $result->documentChunk->body,
                    'semantic_score' => $result->semanticScore,
                    'keyword_score' => $result->keywordScore,
                    'document' => [
                        'id' => $result->documentChunk->document->id,
                        'title' => $result->documentChunk->document->title,
                        'source_url' => $result->documentChunk->document->source_url,
                    ],
                ];
            }),
            'total' => $results->count(),
            'enhanced_with_memory' => true,
        ]);
    }

    /**
     * Track result click for learning
     */
    public function trackClick(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'document_id' => 'required|string',
            'search_query' => 'required|string|max:500',
            'user_id' => 'required|integer',
            'team_id' => 'required|integer',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'messages' => $validator->errors(),
            ], 422);
        }

        $this->searchEngine->trackResultClick(
            documentId: $request->input('document_id'),
            searchQuery: $request->input('search_query'),
            userId: $request->input('user_id'),
            teamId: $request->input('team_id')
        );

        return response()->json(['success' => true]);
    }

    /**
     * Get user's search patterns
     */
    public function getUserPatterns(Request $request, int $userId): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'team_id' => 'required|integer',
            'limit' => 'sometimes|integer|min:1|max:50',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'messages' => $validator->errors(),
            ], 422);
        }

        $patterns = $this->graphitiService->getUserPatterns(
            userId: $userId,
            teamId: $request->input('team_id'),
            limit: $request->input('limit', 10)
        );

        return response()->json([
            'user_id' => $userId,
            'patterns' => $patterns,
            'total' => count($patterns),
        ]);
    }
}
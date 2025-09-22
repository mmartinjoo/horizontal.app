<?php

namespace App\Http\Controllers;

use App\Services\Memory\GraphitiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class GraphitiDemoController extends Controller
{
    public function __construct(
        private GraphitiService $graphitiService
    ) {}

    /**
     * Run a comprehensive demo (equivalent to demo-graphiti.sh)
     */
    public function runDemo(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'team_id' => 'required|integer',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'messages' => $validator->errors(),
            ], 422);
        }

        $teamId = $request->input('team_id');
        $results = [
            'status' => 'running',
            'steps' => [],
            'summary' => []
        ];

        // Step 1: Health Check
        $healthCheck = $this->graphitiService->healthCheck();
        $results['steps'][] = [
            'step' => 1,
            'name' => 'Health Check',
            'status' => $healthCheck ? 'success' : 'failed',
            'message' => $healthCheck ? 'Graphiti service is healthy' : 'Graphiti service unavailable'
        ];

        if (!$healthCheck) {
            $results['status'] = 'failed';
            return response()->json($results, 503);
        }

        // Step 2: Add Sample Knowledge
        $sampleKnowledge = [
            'Users frequently search for authentication guides and documentation',
            'Database setup tutorials are highly valued by the team',
            'API endpoint documentation gets the most clicks',
            'React component examples are popular search topics',
            'Error handling best practices are often needed'
        ];

        $addedCount = 0;
        foreach ($sampleKnowledge as $knowledge) {
            $episodeId = $this->graphitiService->addMemory($knowledge, $teamId, ['type' => 'demo_knowledge']);
            if ($episodeId) $addedCount++;
        }

        $results['steps'][] = [
            'step' => 2,
            'name' => 'Add Knowledge',
            'status' => 'success',
            'message' => "Added {$addedCount}/" . count($sampleKnowledge) . " knowledge entries"
        ];

        // Step 3: Test Memory Search
        $testQueries = ['authentication', 'database setup', 'API docs', 'React components', 'error handling'];
        $searchResults = [];

        foreach ($testQueries as $query) {
            $memories = $this->graphitiService->searchMemory($query, $teamId, null, 3);
            $searchResults[$query] = [
                'query' => $query,
                'total_found' => count($memories),
                'top_match' => $memories[0] ?? null
            ];
        }

        $results['steps'][] = [
            'step' => 3,
            'name' => 'Memory Search Test',
            'status' => 'success',
            'results' => $searchResults
        ];

        // Step 4: Add User Patterns
        $samplePatterns = [
            ['user_id' => 1, 'query' => 'authentication', 'clicked' => ['doc_123']],
            ['user_id' => 1, 'query' => 'database migration', 'clicked' => ['doc_456']],
            ['user_id' => 2, 'query' => 'API testing', 'clicked' => ['doc_789']]
        ];

        $patternCount = 0;
        foreach ($samplePatterns as $pattern) {
            $episodeId = $this->graphitiService->trackSearchPattern(
                userId: $pattern['user_id'],
                searchQuery: $pattern['query'],
                teamId: $teamId,
                clickedResults: $pattern['clicked'],
                searchContext: 'demo'
            );
            if ($episodeId) $patternCount++;
        }

        $results['steps'][] = [
            'step' => 4,
            'name' => 'User Pattern Learning',
            'status' => 'success',
            'message' => "Recorded {$patternCount} user search patterns"
        ];

        // Step 5: Get User Patterns
        $userPatterns = [];
        foreach ([1, 2] as $userId) {
            $patterns = $this->graphitiService->getUserPatterns($userId, $teamId, 5);
            $userPatterns[$userId] = [
                'user_id' => $userId,
                'total_patterns' => count($patterns),
                'patterns' => $patterns
            ];
        }

        $results['steps'][] = [
            'step' => 5,
            'name' => 'Retrieve User Patterns',
            'status' => 'success',
            'results' => $userPatterns
        ];

        // Final Summary
        $results['status'] = 'completed';
        $results['summary'] = [
            'knowledge_added' => $addedCount,
            'patterns_recorded' => $patternCount,
            'search_tests' => count($testQueries),
            'capabilities_demonstrated' => [
                'Memory storage and retrieval',
                'Semantic search across stored knowledge',
                'User pattern tracking and learning',
                'Team-isolated data storage',
                'Real-time knowledge accumulation'
            ],
            'integration_benefits' => [
                'Search results learn from user behavior',
                'Personalized ranking based on historical patterns',
                'Continuous improvement through interaction tracking',
                'Team-scoped privacy and data isolation'
            ]
        ];

        return response()->json($results);
    }

    /**
     * View all stored memories for a team (equivalent to view-memories.sh)
     */
    public function viewMemories(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'team_id' => 'required|integer',
            'limit' => 'sometimes|integer|min:1|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'messages' => $validator->errors(),
            ], 422);
        }

        // Search with empty query to get all memories
        $memories = $this->graphitiService->searchMemory(
            query: '', // Empty query returns all memories
            teamId: $request->input('team_id'),
            userId: null,
            limit: $request->input('limit', 50)
        );

        // Group memories by type for better organization
        $organized = [
            'knowledge_entries' => [],
            'user_patterns' => [],
            'search_behaviors' => []
        ];

        foreach ($memories as $memory) {
            $content = $memory['content'] ?? '';

            if (str_contains($content, 'clicked on documents')) {
                $organized['user_patterns'][] = $memory;
            } elseif (str_contains($content, 'searched for')) {
                $organized['search_behaviors'][] = $memory;
            } else {
                $organized['knowledge_entries'][] = $memory;
            }
        }

        return response()->json([
            'team_id' => $request->input('team_id'),
            'total_memories' => count($memories),
            'organized' => $organized,
            'raw_memories' => $memories,
            'summary' => [
                'knowledge_entries' => count($organized['knowledge_entries']),
                'user_patterns' => count($organized['user_patterns']),
                'search_behaviors' => count($organized['search_behaviors']),
            ]
        ]);
    }

    /**
     * Clear all memories for a team (useful for testing)
     */
    public function clearMemories(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'team_id' => 'required|integer',
            'confirm' => 'required|boolean|accepted',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'messages' => $validator->errors(),
            ], 422);
        }

        // Note: This would require implementing a clear endpoint in the Graphiti service
        // For now, return a message about manual clearing
        return response()->json([
            'message' => 'Memory clearing not implemented in mock service',
            'suggestion' => 'Restart the Graphiti service to clear mock memories: docker compose restart graphiti'
        ]);
    }

    /**
     * Get demo status and available operations
     */
    public function getDemoInfo(): JsonResponse
    {
        return response()->json([
            'service' => 'Graphiti Demo Controller',
            'version' => '1.0.0',
            'available_operations' => [
                'POST /demo/run' => 'Run comprehensive demo with sample data',
                'GET /demo/memories' => 'View all stored memories organized by type',
                'DELETE /demo/clear' => 'Clear all memories (requires confirmation)',
                'GET /demo/info' => 'Get this information'
            ],
            'demo_features' => [
                'Knowledge entry storage and retrieval',
                'User pattern tracking and analysis',
                'Memory search with semantic scoring',
                'Team-isolated data management',
                'Comprehensive integration testing'
            ],
            'instructions' => [
                '1. Run demo: POST /demo/run with {"team_id": 1}',
                '2. View results: GET /demo/memories?team_id=1',
                '3. Clear data: DELETE /demo/clear with {"team_id": 1, "confirm": true}'
            ]
        ]);
    }
}
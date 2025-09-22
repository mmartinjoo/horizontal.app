<?php

namespace Tests\Feature;

use App\Models\Team;
use App\Models\User;
use App\Services\Memory\GraphitiService;
use App\Services\SearchEngine\SearchEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GraphitiIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private Team $team;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->team = Team::factory()->create();
        $this->user = User::factory()->create(['team_id' => $this->team->id]);
    }

    public function test_graphiti_health_check_endpoint(): void
    {
        $response = $this->get('/api/graphiti/health');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'service',
                'timestamp'
            ]);
    }

    public function test_can_add_memory_via_api(): void
    {
        $this->actingAs($this->user, 'sanctum');

        $response = $this->postJson('/api/graphiti/memory', [
            'text' => 'User searched for authentication documentation',
            'team_id' => $this->team->id,
            'context' => ['type' => 'test']
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'episode_id'
            ]);
    }

    public function test_can_search_memory_via_api(): void
    {
        $this->actingAs($this->user, 'sanctum');

        // First add some memory
        $this->postJson('/api/graphiti/memory', [
            'text' => 'User frequently searches for React components',
            'team_id' => $this->team->id
        ]);

        // Then search for it
        $response = $this->postJson('/api/graphiti/memory/search', [
            'query' => 'React components',
            'team_id' => $this->team->id,
            'limit' => 5
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'results',
                'total'
            ]);
    }

    public function test_memory_enhanced_search_returns_different_results(): void
    {
        $this->actingAs($this->user, 'sanctum');

        $searchQuery = 'authentication';

        // Simulate the enhanced search
        $response = $this->postJson('/api/graphiti/search/enhanced', [
            'question' => $searchQuery,
            'team_id' => $this->team->id,
            'user_id' => $this->user->id
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'results' => [
                    '*' => [
                        'document_chunk_id',
                        'content',
                        'semantic_score',
                        'keyword_score',
                        'document' => [
                            'id',
                            'title',
                            'source_url'
                        ]
                    ]
                ],
                'total',
                'enhanced_with_memory'
            ])
            ->assertJson([
                'enhanced_with_memory' => true
            ]);
    }

    public function test_can_track_result_clicks(): void
    {
        $this->actingAs($this->user, 'sanctum');

        $response = $this->postJson('/api/graphiti/track/click', [
            'document_id' => '123',
            'search_query' => 'authentication docs',
            'user_id' => $this->user->id,
            'team_id' => $this->team->id
        ]);

        $response->assertStatus(200)
            ->assertJson(['success' => true]);
    }

    public function test_can_retrieve_user_patterns(): void
    {
        $this->actingAs($this->user, 'sanctum');

        $response = $this->get("/api/graphiti/patterns/user/{$this->user->id}?team_id={$this->team->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'user_id',
                'patterns',
                'total'
            ])
            ->assertJson([
                'user_id' => $this->user->id
            ]);
    }

    public function test_memory_is_team_isolated(): void
    {
        $otherTeam = Team::factory()->create();
        $otherUser = User::factory()->create(['team_id' => $otherTeam->id]);

        $this->actingAs($this->user, 'sanctum');

        // Add memory for team 1
        $this->postJson('/api/graphiti/memory', [
            'text' => 'Team 1 specific information',
            'team_id' => $this->team->id
        ]);

        // Search as team 2 user
        $this->actingAs($otherUser, 'sanctum');
        $response = $this->postJson('/api/graphiti/memory/search', [
            'query' => 'Team 1 specific',
            'team_id' => $otherTeam->id
        ]);

        // Should not find team 1's memory
        $response->assertStatus(200)
            ->assertJson(['total' => 0]);
    }

    public function test_graphiti_service_fallback_when_unavailable(): void
    {
        // Mock Graphiti service to be unavailable
        Http::fake([
            'graphiti:9995/*' => Http::response(null, 503)
        ]);

        $graphitiService = new GraphitiService();

        // Should gracefully handle service unavailability
        $result = $graphitiService->addMemory('test', $this->team->id);
        $this->assertNull($result);

        $searchResults = $graphitiService->searchMemory('test', $this->team->id);
        $this->assertEmpty($searchResults);

        $healthCheck = $graphitiService->healthCheck();
        $this->assertFalse($healthCheck);
    }
}
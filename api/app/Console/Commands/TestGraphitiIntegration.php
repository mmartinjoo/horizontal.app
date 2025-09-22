<?php

namespace App\Console\Commands;

use App\Models\Team;
use App\Services\Memory\GraphitiService;
use App\Services\SearchEngine\SearchEngine;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class TestGraphitiIntegration extends Command
{
    protected $signature = 'graphiti:test {--team-id=1} {--user-id=1}';
    protected $description = 'Test Graphiti integration by comparing search results before and after memory';

    public function __construct(
        private SearchEngine $searchEngine,
        private GraphitiService $graphitiService
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $teamId = (int) $this->option('team-id');
        $userId = (int) $this->option('user-id');

        $this->info("🧪 Testing Graphiti Integration");
        $this->info("Team ID: {$teamId}, User ID: {$userId}");
        $this->newLine();

        // 1. Test Graphiti health
        if (!$this->testGraphitiHealth()) {
            return 1;
        }

        // 2. Test basic memory operations
        if (!$this->testBasicMemory($teamId)) {
            return 1;
        }

        // 3. Compare search results
        $this->compareSearchResults($teamId, $userId);

        $this->newLine();
        $this->info("✅ Graphiti integration test completed!");

        return 0;
    }

    private function testGraphitiHealth(): bool
    {
        $this->info("1. Testing Graphiti service health...");

        $isHealthy = $this->graphitiService->healthCheck();

        if ($isHealthy) {
            $this->info("   ✅ Graphiti service is healthy");
            return true;
        } else {
            $this->error("   ❌ Graphiti service is not responding");
            $this->error("   Make sure the service is running: docker compose up graphiti");
            return false;
        }
    }

    private function testBasicMemory(int $teamId): bool
    {
        $this->info("2. Testing basic memory operations...");

        // Add some test memories
        $testMemories = [
            "User frequently searches for authentication documentation",
            "Team prefers React components over Vue components",
            "Database migrations are commonly accessed documents",
            "API endpoints documentation is highly valuable",
            "Users often look for error handling examples"
        ];

        $addedMemories = 0;
        foreach ($testMemories as $memory) {
            $episodeId = $this->graphitiService->addMemory($memory, $teamId, [
                'type' => 'test_memory',
                'created_at' => now()->toISOString()
            ]);

            if ($episodeId) {
                $addedMemories++;
            }
        }

        $this->info("   ✅ Added {$addedMemories}/{count($testMemories)} test memories");

        // Test memory search
        $searchResults = $this->graphitiService->searchMemory(
            'authentication docs',
            $teamId,
            limit: 3
        );

        $this->info("   ✅ Memory search returned " . count($searchResults) . " results");

        return $addedMemories > 0;
    }

    private function compareSearchResults(int $teamId, int $userId): void
    {
        $this->info("3. Comparing search results...");

        $testQueries = [
            'authentication',
            'database setup',
            'API documentation',
            'error handling',
            'React components'
        ];

        foreach ($testQueries as $query) {
            $this->newLine();
            $this->info("🔍 Testing query: '{$query}'");

            // Simulate traditional search (without memory)
            $this->info("   📊 Traditional search results:");
            $traditionalResults = $this->simulateTraditionalSearch($query, $teamId);
            $this->displaySearchResults($traditionalResults, '      ');

            // Test memory-enhanced search
            $this->info("   🧠 Memory-enhanced search results:");
            $memoryResults = $this->searchEngine->searchWithMemory(
                question: $query,
                teamId: $teamId,
                userId: $userId,
                clickedResults: []
            );
            $this->displaySearchResults($memoryResults, '      ');

            // Compare
            $this->compareResultSets($traditionalResults, $memoryResults);

            // Simulate user clicking on a result to build memory
            if ($memoryResults->isNotEmpty()) {
                $firstResult = $memoryResults->first();
                $this->searchEngine->trackResultClick(
                    documentId: (string) $firstResult->documentChunk->document->id,
                    searchQuery: $query,
                    userId: $userId,
                    teamId: $teamId
                );
            }
        }
    }

    private function simulateTraditionalSearch(string $query, int $teamId): \Illuminate\Support\Collection
    {
        // Use the original search methods directly
        $semanticResults = $this->callPrivateMethod($this->searchEngine, 'semanticSearch', [$query]);
        $keywordResults = $this->callPrivateMethod($this->searchEngine, 'keywordSearch', [explode(' ', $query)]);

        return $this->callPrivateMethod($this->searchEngine, 'combineResults', [$semanticResults, $keywordResults]);
    }

    private function callPrivateMethod($object, $methodName, $args = [])
    {
        $reflection = new \ReflectionClass($object);
        $method = $reflection->getMethod($methodName);
        $method->setAccessible(true);

        return $method->invokeArgs($object, $args);
    }

    private function displaySearchResults($results, string $indent = ''): void
    {
        if ($results->isEmpty()) {
            $this->line("{$indent}No results found");
            return;
        }

        foreach ($results->take(3) as $index => $result) {
            $title = $result->documentChunk->document->title ?? 'Untitled';
            $semanticScore = $result->semanticScore ? round($result->semanticScore, 3) : 'N/A';
            $keywordScore = $result->keywordScore ? round($result->keywordScore, 3) : 'N/A';

            $this->line("{$indent}" . ($index + 1) . ". {$title}");
            $this->line("{$indent}   Semantic: {$semanticScore} | Keyword: {$keywordScore}");
        }

        if ($results->count() > 3) {
            $this->line("{$indent}... and " . ($results->count() - 3) . " more results");
        }
    }

    private function compareResultSets($traditional, $memoryEnhanced): void
    {
        $traditionalCount = $traditional->count();
        $memoryCount = $memoryEnhanced->count();

        if ($memoryCount > $traditionalCount) {
            $this->info("      📈 Memory search found " . ($memoryCount - $traditionalCount) . " additional results");
        } elseif ($memoryCount < $traditionalCount) {
            $this->info("      📉 Memory search refined results (" . ($traditionalCount - $memoryCount) . " fewer)");
        } else {
            $this->info("      ➡️  Same number of results, but ranking may be different");
        }

        // Check if top results are different
        $traditionalTop = $traditional->first()?->documentChunk->id;
        $memoryTop = $memoryEnhanced->first()?->documentChunk->id;

        if ($traditionalTop !== $memoryTop) {
            $this->info("      🔄 Top result changed due to memory influence");
        }
    }
}
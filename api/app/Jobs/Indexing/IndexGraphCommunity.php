<?php

namespace App\Jobs\Indexing;

use App\Enums\Indexing\WorkflowStatus;
use App\Models\IndexingWorkflowStepBucket;
use App\Models\IndexingWorkflowStepItem;
use App\Services\GraphDB\GraphDB;
use App\Services\LLM\Embedder;
use App\Services\LLM\LLM;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class IndexGraphCommunity extends IndexingStepItemJob implements ShouldQueue
{
    use Queueable;
    use Batchable;

    private ?int $createdIndexingWorkflowItemId = null;

    public function __construct(
        private string $communityID,
        // The text information from the nodes inside a community
        private string $summary,
        // The full text from the original file chunk
        private string $context,
    ) {
        $this->onQueue('indexing');
    }

    public function handle(
        LLM $llm,
        GraphDB $graphDB,
        Embedder $embedder,
    ) {
        try {
            $bucket = IndexingWorkflowStepBucket::find($this->indexingWorkflowStepBucketId);
            $indexingWorkflowItem = IndexingWorkflowStepItem::create([
                'indexing_workflow_step_bucket_id' => $bucket->id,
                'status' => WorkflowStatus::Processing->value,
                'job_id' => $this->job->payload()['uuid'],
            ]);
            $this->createdIndexingWorkflowItemId = $indexingWorkflowItem->id;
            
            $result = $llm->completion("
                ## Task: Generate Community Summary for Knowledge Graph Cluster

                You are analyzing a community (cluster) of related concepts from a knowledge graph. This community was identified through graph-based community detection, meaning these concepts are densely interconnected and likely share common themes or contexts.

                ### Input Data:

                **Extracted Concepts from Nodes/children of this community:**
                {$this->summary}

                **Source Context from the file chunk that the nodes are mentioned in:**
                {$this->context}

                ### Your Task:

                Analyze the provided concepts and their source contexts to generate a concise, meaningful summary that captures:

                1. **Core Theme**: What is the primary subject or domain this community represents?
                2. **Key Relationships**: What are the main connections or relationships between these concepts?
                3. **Contextual Purpose**: Why are these concepts grouped together? What business/technical/conceptual purpose do they serve?
                4. **Distinctive Characteristics**: What makes this community distinct from others in the knowledge graph?

                ### Guidelines for a Good Summary:

                - **Be Specific**: Avoid generic descriptions. Instead of \"business concepts,\" write \"customer acquisition strategy for B2B SaaS targeting technical decision-makers\"
                - **Identify Patterns**: Look for recurring themes, entities, or relationships across the concepts
                - **Use Domain Language**: Maintain the vocabulary and terminology present in the source material
                - **Be Actionable**: The summary should help someone quickly understand what knowledge this community contains

                ### Output Format:
                {
                    \"title\": \"A descriptive 3-7 word title for this community\",
                    \"summary\": \"A 3-5 sentence description that captures the essence of this community. Be specific about the domain, key entities, and relationships.\"
                }

                ### Example Output:
                {
                    \"title\": \"B2B SaaS Customer Acquisition Strategy\",
                    \"summary\": \"Focuses on targeting technical decision-makers (CTOs, VPs of Engineering) at small to medium-sized companies for B2B SaaS products. It encompasses go-to-market strategies, ideal customer profiles, and specific outreach tactics. Outreach tactics includes channels like email, social media, and in-person meetings. It also includes a detailed customer acquisition process and a detailed customer acquisition strategy.\"
                }

                ---

                Now, generate the summary for the community based on the provided data.
            ");

            $data = json_decode($result, true);
            $embedding = $embedder->createEmbedding($data['title'] . ' ' . $data['summary']);
            $embeddingStr = json_encode($embedding);
            $graphDB->run("
                match (c:Community { id: {$this->communityID} })
                set
                    c.name = \"{$data['title']}\",
                    c.summary = \"{$data['summary']}\",
                    c.embedding = {$embeddingStr};
            ");

            $indexingWorkflowItem->update([
                'status' => WorkflowStatus::Completed->value,
            ]);
        } catch (Throwable $e) {
            if ($this->createdIndexingWorkflowItemId) {
                $item = IndexingWorkflowStepItem::findOrFail($this->createdIndexingWorkflowItemId);
                $item->update(attributes: [
                    'status' => WorkflowStatus::Failed->value,
                    'error_message' => $e->getMessage(),
                ]); 
            }                       
            throw $e;
        }
    }
}

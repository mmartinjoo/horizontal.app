<?php

namespace App\Services\GraphDB;

use App\Services\GraphDB\Exceptions\InvalidCypherException;
use Bolt\protocol\v5\structures\Node;
use Exception;
use Illuminate\Support\Arr;

class Memgraph extends GraphDB
{
    private const BATCH_SIZE = 100;

    public function __construct(array $config)
    {
        parent::__construct($config);
    }

    public function createNode(string $label, array $attributes): ?Node
    {
        $attributesStr = $this->arrToAttributeStr($attributes);
        $rows = $this->doQuery("merge (n:$label { $attributesStr }) return n;");

        return $this->parseNode($rows);
    }

    public function createNodeWithRelation(
        string $newNodeLabel,
        array $newNodeAttributes,
        string $relation,
        string $relatedNodeLabel,
        string $relatedNodeID,
        array $relationAttributes = [],
    ): ?Node {
        $newNodeId = $newNodeAttributes['id'];
        $upsertQuery = "merge (n:$newNodeLabel { id: \"$newNodeId\" })";
        $set = $this->arrToSetStyleStr($newNodeAttributes);
        $upsertQuery .= " $set";
        $upsertQuery .= ' return n';

        $rows = $this->doQuery($upsertQuery);
        $node = Arr::get($rows, '0.n');
        if (! $node) {
            throw new InvalidCypherException('Unable to return node');
        }

        if (count($relationAttributes) === 0) {
            $rows = $this->doQuery("
                merge (r:$relatedNodeLabel { id: \"$relatedNodeID\" })
                with r
                match (n:$newNodeLabel { id: \"$newNodeId\" })
                merge (n)-[:$relation]->(r)
                return n;
            ");

            return $this->parseNode($rows);
        } else {
            $relationAttributesStr = $this->arrToAttributeStr($relationAttributes);
            $rows = $this->doQuery("
                merge (r:$relatedNodeLabel { id: \"$relatedNodeID\" })
                with r
                match (n:$newNodeLabel { id: \"$newNodeId\" })
                merge (n)-[:$relation { $relationAttributesStr }]->(r)
                return n;
            ");

            return $this->parseNode($rows);
        }
    }

    public function getNode(string $label, array $attributes): ?Node
    {
        $attributesStr = $this->arrToAttributeStr($attributes);
        $rows = $this->doQuery("match (n:$label $attributesStr) return n)");

        return $this->parseNode($rows);
    }

    public function query(string $query, string $nodeName = 'n'): ?Node
    {
        $rows = $this->doQuery($query);

        return $this->parseNode($rows, $nodeName);
    }

    public function queryMany(string $query, array $nodeNames = ['n']): array
    {
        $rows = $this->doQuery($query);

        return $this->parseNodes($rows, $nodeNames);
    }

    public function run(string $query): array
    {
        return $this->doQuery($query);
    }

    public function vectorSearch(string $indexName, array $embedding, int $n): array
    {
        $embeddingStr = json_encode($embedding);

        return $this->doQuery("
            CALL vector_search.search('$indexName', $n, $embeddingStr) YIELD * RETURN *;
        ");
    }

    public function addRelation(
        string $fromNodeLabel,
        string $fromNodeID,
        string $relation,
        string $toNodeLabel,
        string $toNodeID,
        array $relationAttributes = [],
    ): void {
        if (count($relationAttributes) === 0) {
            $this->doQuery("
                match (n1:$fromNodeLabel { id: \"$fromNodeID\" }), (n2:$toNodeLabel { id: \"$toNodeID\" })
                merge (n1)-[:$relation]->(n2)
            ");
        } else {
            $relationAttributesStr = $this->arrToAttributeStr($relationAttributes);
            $this->doQuery("
                match (n1:$fromNodeLabel { id: \"$fromNodeID\" }), (n2:$toNodeLabel { id: \"$toNodeID\" })
                merge (n1)-[:$relation { $relationAttributesStr }]->(n2)
            ");
        }
    }

    private function doQuery(string $query): array
    {
        $all = [];
        $runResponse = $this->protocol->run($query)->getResponse();
        if ($runResponse->signature != \Bolt\enum\Signature::SUCCESS) {
            throw new Exception(implode(' ', $runResponse->content));
        }
        $content = $runResponse->content;

        // Pull results in batches to handle large result sets
        while (true) {
            $hasMore = false;
            foreach ($this->protocol->pull(['n' => self::BATCH_SIZE])->getResponses() as $res) {
                if ($res->signature == \Bolt\enum\Signature::SUCCESS) {
                    // Check if there are more results to pull
                    $hasMore = $res->content['has_more'] ?? false;
                    if (! $hasMore) {
                        break 2; // Exit both loops - we're done
                    }
                    break; // Exit inner loop to pull next batch
                }
                if ($res->signature == \Bolt\enum\Signature::IGNORED || $res->signature == \Bolt\enum\Signature::FAILURE) {
                    throw new Exception('Error while executing query: '.json_encode($res->content));
                }
                // RECORD signature - add to results
                $all[] = $res->content;
            }

            if (! $hasMore) {
                break;
            }
        }

        return ! empty($all) ? array_map(function ($element) use ($content) {
            return array_combine($content['fields'], $element);
        }, $all) : [];
    }
}

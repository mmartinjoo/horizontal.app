<?php

namespace App\Services\GraphDB;

use App\Services\GraphDB\Exceptions\UnableToConnect;
use Bolt\Bolt;
use Bolt\connection\Socket;
use Bolt\protocol\AProtocol;
use Bolt\protocol\Response;
use Bolt\protocol\v5\structures\Node;
use Illuminate\Support\Arr;

abstract class GraphDB
{
    protected AProtocol $protocol;

    public abstract function createNode(string $label, array $attributes): ?Node;
    public abstract function createNodeWithRelation(
        string $newNodeLabel,
        array $newNodeAttributes,
        string $relation,
        string $relatedNodeLabel,
        string $relatedNodeID,
        array $relationAttributes = [],
    ): ?Node;
    abstract public function getNode(string $label, array $attributes): ?Node;
    abstract public function query(string $query): ?Node;
    /** @return array<Node> */
    abstract public function queryMany(string $query, array $nodeNames = ['n']): array;
    abstract public function vectorSearch(string $indexName, array $embedding, int $n): array;
    abstract public function addRelation(
        string $fromNodeLabel,
        string $fromNodeID,
        string $relation,
        string $toNodeLabel,
        string $toNodeID,
        array $relationAttributes = [],
    ): void;

    abstract public function run(string $query): array;

    public function __construct(array $config)
    {
        $conn = new Socket();
        $bolt = new Bolt($conn);
        $bolt->setProtocolVersions(5.2);
        $this->protocol = $bolt->build();
        $this->protocol->hello()->getResponse();
        /** @var Response $res */
        $res = $this->protocol->logon([
            'scheme' => $config['scheme'],
            'principal' => 'user',
            'credentials' => $config['password'],
        ])->getResponse();

        if ($res->signature->name !== 'SUCCESS') {
            throw new UnableToConnect('Unable to connect to GraphDB. config: ' . json_encode($config) . ', status: ' . json_encode($res->content));
        }
    }

    protected function arrToAttributeStr(array $attributes): string
    {
        $attributesStr = "";
        foreach ($attributes as $key => $value) {
            if (is_array($value)) {
                $value = json_encode($value);
            }
            // Vector index has to be a list so omit the "" signs
            if ($key === 'embedding') {
                $attributesStr .= "$key: $value, ";
                continue;
            }
            $attributesStr .= "$key: \"$value\", ";
        }
        return rtrim($attributesStr, ", ");
    }

    protected function arrToSetStyleStr(array $attributes): string
    {
        $attributesStr = "set ";
        foreach ($attributes as $key => $value) {
            if (is_array($value)) {
                $value = json_encode($value);
            }
            if ($key === 'embedding') {
                $attributesStr .= "n.$key = $value, ";
                continue;
            }
            $attributesStr .= "n.$key = \"$value\", ";
        }
        return rtrim($attributesStr, ", ");
    }

    protected function parseNode(array $rows, string $nodeName = 'n'): ?Node
    {
        return Arr::get($rows, '0.' . $nodeName);
    }

    /**
     * @return array<Node>
     */
    protected function parseNodes(array $rows, array $nodeNames = ['n']): array
    {
        /** @var array<Node> $nodes */
        $nodes = [];
        foreach ($rows as $row) {
            foreach ($nodeNames as $nodeName) {
                if (!array_key_exists($nodeName, $row)) {
                    continue;
                }
            }
            foreach ($nodeNames as $nodeName) {
                $nodes[] = $row[$nodeName];
            }
        }
        return $nodes;
    }
}

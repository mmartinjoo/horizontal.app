<?php

namespace App\Services\SearchEngine\DataTransferObjects;

use Bolt\protocol\v1\structures\Path as BoltPath;
use Bolt\protocol\v1\structures\Node;

class Path
{
    public string $pathString;

    public function __construct(public BoltPath $path)
    {
        $this->pathString = $this->toString();
    }

    /**
     * @param array<BoltPath> $boltPaths
     * @return array<self>
     */
    public static function fromArray(array $boltPaths): array
    {
        $paths = [];
        foreach ($boltPaths as $path) {
            $paths[] = new self($path);
        }
        return $paths;
    }

    /**
     * @see https://www.neo4j.com/docs/bolt/current/bolt/structure-semantics/#structure-path
     */
    public function toString(): string
    {
        $pathStr = "(" . $this->nodeTitle($this->path->nodes[0]) . ":" . $this->path->nodes[0]->labels[0] . ")";
        for ($i = 0; $i < count($this->path->ids); $i++) {
            $j = $i + 1;
            // It refers to a relation
            if ($j % 2 !== 0) {
                // Negative means an outbound relation
                $inverse = $this->path->ids[$i] < 0;
                $relationIdx = abs($this->path->ids[$i]) - 1;

                if ($inverse) {
                    $pathStr .= "<-[:{$this->path->rels[$relationIdx]->type}]-";
                } else {
                    $pathStr .= "-[:{$this->path->rels[$relationIdx]->type}]->";
                }
            } else {    // It refers to a node
                $nodeIdx = $this->path->ids[$i];
                $pathStr .= "(" . $this->nodeTitle($this->path->nodes[$nodeIdx]) . ":" . $this->path->nodes[$nodeIdx]->labels[0] . ")";
            }
        }
        return $pathStr;
    }

    public function nodeTitle(Node $node): string
    {
        return $node->properties['name'] ?? $node->properties['id'] ?? $node->id;
    }
}

<?php

namespace App\Services\Integration\Storage\DataTransferObjects;

use League\Flysystem\DirectoryAttributes;

class Folder
{
    public function __construct(
        public string $id,
        public string $path,
    ) {}

    public static function fromFlysystem(DirectoryAttributes $folder): self
    {
        return new static(
            id: $folder->extraMetadata()['id'],
            path: $folder->path(),
        );
    }
}
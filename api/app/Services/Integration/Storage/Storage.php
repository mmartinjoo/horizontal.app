<?php

namespace App\Services\Integration\Storage;

use App\Services\Integration\Storage\DataTransferObjects\File;

interface Storage
{
    public function getRevisionAuthors(File $file): array;
    public function getComments(File $file): array;
}
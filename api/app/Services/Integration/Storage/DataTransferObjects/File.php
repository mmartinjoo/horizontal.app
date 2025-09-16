<?php

namespace App\Services\Integration\Storage\DataTransferObjects;

use Carbon\Carbon;
use Generator;
use Illuminate\Support\Str;
use JsonSerializable;
use League\Flysystem\DirectoryListing;
use League\Flysystem\FileAttributes;

class File implements JsonSerializable
{
    protected Carbon $createdAt;
    protected Carbon $updatedAt;
    protected Carbon $viewedAt;
    protected Carbon $lastUsedAt;
    /** @var array[string] $owners  */
    protected array $owners = [];
    protected string $sharingUser;

    public function __construct(private FileAttributes $file)
    {
        $this->createdAt = Carbon::parse("1900-01-01 00:00:00");
        $this->updatedAt = Carbon::createFromTimestamp($file->lastModified());
        $this->viewedAt = Carbon::parse("1900-01-01 00:00:00");
        $this->lastUsedAt = Carbon::parse("1900-01-01 00:00:00");

        $this->sharingUser = "";
    }

    public function setCreatedAt(string|null $createdAt): void
    {
        $this->createdAt = Carbon::parse($createdAt ?? "1900-01-01 00:00:00");
        $this->updateLastUsedAt();
    }

    public function setUpdatedAt(string|null $updatedAt): void
    {
        $this->updatedAt = Carbon::parse($updatedAt ?? "1900-01-01 00:00:00");
        $this->updateLastUsedAt();
    }

    public function setViewedAt(string|null $viewedAt): void
    {
        $this->viewedAt = Carbon::parse($viewedAt ?? "1900-01-01 00:00:00");
        $this->updateLastUsedAt();
    }

    public function getUpdatedAt(): Carbon
    {
        return $this->updatedAt;
    }

    public function updateLastUsedAt()
    {
        $this->lastUsedAt = $this->viewedAt->max($this->createdAt)->max($this->updatedAt);
    }

    public function addOwner(string $owner): void
    {
        $this->owners[] = $owner;
    }

    /**
     * @return array[string]
     */
    public function getOwners(): array
    {
        return $this->owners;
    }

    public function setSharingUser(string $sharingUser): void
    {
        $this->sharingUser = $sharingUser;
    }

    public function getSharingUser(): string
    {
        return $this->sharingUser;
    }

    public static function fromDirectoryListing(DirectoryListing $listing): Generator
    {
        foreach ($listing as $file) {
            if (!$file instanceof FileAttributes) {
                continue;
            }
            yield new self($file);
        }
    }

    public function extraMetadata(): array
    {
        return $this->file->extraMetadata();
    }

    public function isFile(): bool
    {
        return $this->file->isFile();
    }

    public function fileSize(): int|null
    {
        return $this->file->fileSize();
    }

    public function path(): string
    {
        return $this->file->path();
    }

    public function mimeType(): string
    {
        return $this->file->mimeType();
    }

    public function isMediaFile(): bool
    {
        return Str::startsWith($this->file->mimeType(), 'image')
            || Str::startsWith($this->file->mimeType(), 'audio')
            || Str::startsWith($this->file->mimeType(), 'video');
    }

    public function isZipFile(): bool
    {
        return Str::contains($this->file->mimeType(), 'zip');
    }

    public function wasUsedIn(int $days): bool
    {
        return $this->lastUsedAt->gt(now()->subDays($days));
    }

    public function isSmallerThan(int $sizeMB): bool
    {
        return $this->file->fileSize() < $sizeMB * 1024 * 1024;
    }

    public function jsonSerialize(): mixed
    {
        return [
            'created_at' => $this->createdAt->toIso8601String(),
            'updated_at' => $this->updatedAt->toIso8601String(),
            'viewed_at' => $this->viewedAt->toIso8601String(),
            'last_used_at' => $this->lastUsedAt->toIso8601String(),
            'file' => $this->file,
        ];
    }
}

<?php

namespace App\Services\Integration\Storage\GoogleDrive;

use App\Exceptions\Storage\FileDownloadException;
use App\Services\Indexing\FilePrioritizer;
use App\Services\Integration\Storage\DataTransferObjects\File;
use App\Services\Integration\Storage\Storage as StorageIntegration;
use Exception;
use Google\Client;
use Google\Service\Drive;
use Google\Service\Drive\DriveFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\LazyCollection;
use League\Flysystem\Filesystem;
use Masbug\Flysystem\GoogleDriveAdapter;
use League\Flysystem\FileAttributes;

class GoogleDrive implements StorageIntegration
{
    private Filesystem $fs;
    private Drive $drive;

    private const GOOGLE_NATIVE_TYPES = [
        'application/vnd.google-apps.document',
        'application/vnd.google-apps.spreadsheet',
        'application/vnd.google-apps.presentation',
        'application/vnd.google-apps.form',
    ];

    private const EXPORT_FORMATS = [
        'application/vnd.google-apps.document' => 'text/plain',
        'application/vnd.google-apps.spreadsheet' => 'text/csv',
        'application/vnd.google-apps.presentation' => 'text/plain',
        'application/vnd.google-apps.form' => 'text/plain',
    ];

    public function __construct(private FilePrioritizer $prioritizer)
    {
        $client = new Client();
        $client->setClientId(config('services.google_drive.client_id'));;
        $client->setClientSecret(config('services.google_drive.client_secret'));;
        $client->refreshToken(config('services.google_drive.refresh_token'));;
        $client->setApplicationName('Horizontal');

        $this->drive = new Drive($client);
        $adapter = new GoogleDriveAdapter($this->drive);
        $this->fs = new Filesystem($adapter);
    }

    /**
     * @return LazyCollection<File>
     */
    public function listDirectoryContents(string $directory = ''): LazyCollection
    {
        return LazyCollection::make(function () use ($directory) {
            $listing = $this->fs->listContents($directory, true);
            foreach ($listing as $listingItem) {
                if (!$listingItem instanceof FileAttributes) {
                    continue;
                }

                $file = new File($listingItem);

                $data = $this->getMetaData($file);
                $file->setCreatedAt($data->createdTime);
                $file->setUpdatedAt($data->modifiedTime);
                $file->setViewedAt($data->viewedByMeTime);

                foreach ($data->getOwners() as $owner) {
                    $file->addOwner($owner->displayName);
                }

                $sharingUser = $data->getSharingUser();
                if ($sharingUser) {
                    $file->setSharingUser($sharingUser->displayName);;
                }

                yield $file;
            }
        });
    }

    public function downloadFile(File $file)
    {
        try {
            if ($this->isGoogleNativeFile($file)) {
                $content = $this->exportGoogleNativeFile($file);
                $result = Storage::put($file->path(), $content);
            } else {
                $stream = $this->fs->readStream($file->path());
                $result = Storage::writeStream($file->path(), $stream);
            }

            if (!$result) {
                throw new FileDownloadException("Failed to write file to storage: " . json_encode($file));
            }
        } catch (Exception $e) {
            throw FileDownloadException::wrap($e);
        }
    }

    public function getRevisionAuthors(File $file): array
    {
        $revisions = $this->drive->revisions->listRevisions($file->extraMetadata()['id'], [
            'fields' => 'revisions(id,modifiedTime,lastModifyingUser,size,mimeType,keepForever,published)',
            'pageSize' => 100,
        ]);
        $authors = [];
        foreach ($revisions as $revision) {
            $authors[$revision->lastModifyingUser->emailAddress] = $revision->lastModifyingUser->displayName;
        }
        return $authors;
    }

    public function getComments(File $file): array
    {
        $comments = $this->drive->comments->listComments($file->extraMetadata()['id'], [
            'fields' => 'comments(id,createdTime,modifiedTime,author(displayName),content,replies)',
            'pageSize' => 100,
        ]);

        $result = [];
        foreach ($comments->getComments() as $comment) {
            $result[] = [
                'id' => $comment->getId(),
                'content' => $comment->getContent(),
                'created_at' => $comment->getCreatedTime(),
                'author' => $comment->getAuthor()->getDisplayName(),
            ];
            foreach ($comment->getReplies() as $reply) {
                $result[] = [
                    'id' => $reply->getId(),
                    'content' => $reply->getContent(),
                    'created_at' => $reply->getCreatedTime(),
                    'author' => $reply->getAuthor()->getDisplayName(),
                ];
            }
        }
        return $result;
    }

    private function isGoogleNativeFile(File $file): bool
    {
        return in_array($file->mimeType(), self::GOOGLE_NATIVE_TYPES);
    }

    private function exportGoogleNativeFile(File $file): string
    {
        if (!isset(self::EXPORT_FORMATS[$file->mimeType()])) {
            throw new FileDownloadException("Unsupported Google native file type: {$file->mimeType()}");
        }

        $exportFormat = self::EXPORT_FORMATS[$file->mimeType()];;

        try {
            $response = $this->drive->files->export($file->extraMetadata()['id'], $exportFormat, [
                'alt' => 'media'
            ]);

            return $response->getBody()->getContents();
        } catch (Exception $e) {
            throw FileDownloadException::wrap($e);
        }
    }

    private function getMetaData(File $file): DriveFile
    {
        $fields = 'modifiedTime,createdTime,viewedByMeTime,owners,sharingUser';
        return $this->drive->files->get($file->extraMetadata()['id'], ['fields' => $fields]);
    }
}

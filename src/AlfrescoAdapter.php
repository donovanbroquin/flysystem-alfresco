<?php

namespace Donovanbroquin\FlysystemAlfresco;

use Carbon\Carbon;
use Generator;
use League\Flysystem\{Config, DirectoryAttributes, FileAttributes, FilesystemAdapter, StorageAttributes};

class AlfrescoAdapter implements FilesystemAdapter
{
    protected AlfrescoClient $client;

    public function __construct(array $config)
    {
        $this->client = new AlfrescoClient(config: $config);
    }

    public function fileExists(string $path): bool
    {
        return $this->client->nodeExists(path: $path);
    }

    public function directoryExists(string $path): bool
    {
        return $this->fileExists(path: $path);
    }

    public function write(string $path, string $contents, Config $config): void
    {
        $node = $this->client->writeNode(path: $path, contents: $contents);

        $this->client->updateNodeContent(nodeId: $node->id, contents: $contents);
    }

    public function writeStream(string $path, $contents, Config $config): void
    {
        $this->client->writeNode(path: $path, contents: $contents);
    }

    public function read(string $path): string
    {
        $object = $this->readStream(path: $path);

        $contents = stream_get_contents($object);
        fclose($object);

        unset($object);

        return $contents;
    }

    public function readStream(string $path)
    {
        return $this->client->getNodeContent(path: $path);
    }

    public function delete(string $path): void
    {
        $this->client->deleteNode(path: $path);
    }

    public function deleteDirectory(string $path): void
    {
        $this->delete(path: $path);
    }

    public function createDirectory(string $path, Config $config): void
    {
        $this->client->writeNode(path: $path, nodeType: 'cm:folder');
    }

    public function setVisibility(string $path, string $visibility): void
    {
        throw new \LogicException('Visibility management not implemented in Alfresco.');
    }

    public function visibility(string $path): FileAttributes
    {
        $node = $this->client->findNode(path: $path);

        return new FileAttributes(
            path: $path,
            visibility: ! is_null($node)
        );
    }

    public function mimeType(string $path): FileAttributes
    {
        $node = $this->client->findNode(path: $path);

        return new FileAttributes(
            path: $path,
            mimeType: $node->content->mimeType
        );
    }

    public function lastModified(string $path): FileAttributes
    {
        $node = $this->client->findNode(path: $path);

        return new FileAttributes(
            path: $path,
            lastModified: Carbon::parse($node->content->mimeType)
                ->getTimestamp()
        );
    }

    public function fileSize(string $path): FileAttributes
    {
        $node = $this->client->findNode(path: $path);

        return new FileAttributes(
            path: $path,
            fileSize: $node->content->sizeInBytes
        );
    }

    public function listContents(string $path, bool $deep): iterable
    {
        $nodeId = $this->client->findNodeId(path: $path, type: 'cm:folder');

        foreach($this->iterateChildren(nodeId: $nodeId) as $entry) {
            $node = $this->normalizeResponse(entry: $entry->entry, rootPath: $path);

            yield $node;

            if ($deep && $entry->entry->isFolder) {
                $subFolderPath = $path . '/' . $entry->entry->name;
                yield from $this->listContents($subFolderPath, $deep);
            }
        };
    }

    protected function iterateChildren(string $nodeId): Generator
    {
        $skipCount = 0;

        $res = $this->client->getNodeChildren(nodeId: $nodeId, maxItems: 100);

        yield from $res->list->entries;

        $skipCount += count($res->list->entries);

        while ($res->list->pagination->hasMoreItems) {
            $res = $this->client->getNodeChildren(
                nodeId: $nodeId,
                skipCount: $skipCount,
                maxItems: 1
            );

            yield from $res->list->entries;

            $skipCount += count($res->list->entries);
        }
    }


    protected function normalizeResponse($entry, string $rootPath): StorageAttributes
    {
        $timestamp = strtotime($entry->modifiedAt);
        $path = strstr($entry->path->name, "/$rootPath") . '/' . $entry->name;

        if ($entry->isFolder) {

            return new DirectoryAttributes(
                $path,
                null,
                $timestamp
            );
        }

        return new FileAttributes(
            $path,
            $entry->content->sizeInBytes,
            null,
            $timestamp,
            $entry->content->mimeType
        );
    }

    public function move(string $source, string $destination, Config $config): void
    {
        $contents = $this->read(path: $source);

        $this->writeStream($destination, $contents, $config);
        $this->delete($source);
    }

    public function copy(string $source, string $destination, Config $config): void
    {
        $contents = $this->read(path: $source);

        $this->writeStream($destination, $contents, $config);
    }
}

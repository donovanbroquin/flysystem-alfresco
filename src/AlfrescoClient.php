<?php

namespace Donovanbroquin\FlysystemAlfresco;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\{ClientException, ConnectException, ServerException, TooManyRedirectsException};
use GuzzleHttp\Psr7\StreamWrapper;
use Psr\Http\Message\{ResponseInterface, StreamInterface};

class AlfrescoClient
{
    protected Client $client;

    protected string $url = '';

    protected string $endpoint = 'alfresco/api/-default-/public';

    protected string $version = 'versions/1';

    protected string $site;

    public function __construct(array $config)
    {
        $this->url = $config['url'];
        $this->site = $config['site'];
        $this->client = new Client([
            'base_uri' => $this->url,
            'auth' => [
                $config['username'],
                $config['password'],
            ],
        ]);
    }

    public function writeNode(string $path, $contents = null, string $nodeType = 'cm:content')
    {
        $exists = $this->nodeExists($path);
        [
            'fileName' => $fileName,
            'relativePath' => $relativePath
        ] = $this->normalizePathAndFileName(path: $path);

        if ($exists) {
            $nodeId = $this->findNodeId(path: $path);
            $res = $this->updateNodeContentRequest(
                nodeId: $nodeId,
                contents: $contents
            );
        } else {
            $libraryId = $this->findDocumentLibraryId();

            $res = $this->writeNodeRequest(
                nodeId: $libraryId,
                contents: $contents,
                fileName: $fileName,
                relativePath: $relativePath,
                nodeType: $nodeType,
                multipart: ! is_string($contents)
            );
        }

        return $this->getEntry($res);
    }

    protected function writeNodeRequest(
        string $nodeId,
        mixed $contents,
        string $fileName,
        string $relativePath,
        string $nodeType = 'cm:content',
        bool $multipart = true,
    ): StreamInterface {
        $arguments = [
            'nodeId' => $nodeId,
            'contents' => $contents,
            'fileName' => $fileName,
            'relativePath' => $relativePath,
            'nodeType' => $nodeType,
        ];

        try {
            return $this->client
                ->request(
                    method: 'POST',
                    uri: $this->getApiUrl(route: "nodes/$nodeId/children"),
                    options: $this->resolveWriteBody(multipart: $multipart, arguments: $arguments)
                )
                ->getBody();
        } catch (ClientException $e) {
            throw new \RuntimeException('Failed to write node', $e->getCode(), $e);
        } catch (ServerException $e) {
            throw new \RuntimeException('Alfresco server error', $e->getCode(), $e);
        } catch (ConnectException $e) {
            throw new \RuntimeException('Cannot connect to server', $e->getCode(), $e);
        } catch (TooManyRedirectsException $e) {
            throw new \RuntimeException('Too many redirect', $e->getCode(), $e);
        }
    }

    public function updateNodeContent(string $nodeId, string $contents): \stdClass
    {
        return $this->getEntry(
            $this->updateNodeContentRequest(nodeId: $nodeId, contents: $contents)
        );
    }

    protected function updateNodeContentRequest(string $nodeId, string $contents): StreamInterface
    {
        try {

            return $this->client
                ->request(
                    method: 'PUT',
                    uri: $this->getApiUrl(route: "nodes/$nodeId/content"),
                    options: [
                        'body' => $contents,
                    ]
                )
                ->getBody();
        } catch (ClientException $e) {
            throw new \RuntimeException('Failed to update node', $e->getCode(), $e);
        } catch (ServerException $e) {
            throw new \RuntimeException('Alfresco server error', $e->getCode(), $e);
        } catch (ConnectException $e) {
            throw new \RuntimeException('Cannot connect to server', $e->getCode(), $e);
        } catch (TooManyRedirectsException $e) {
            throw new \RuntimeException('Too many redirect', $e->getCode(), $e);
        }
    }

    public function findDocumentLibraryId(): string
    {
        return $this->getEntryId($this->findDocumentLibraryRequest());
    }

    public function findNode(string $path, string $type = 'cm:content'): ?\stdClass
    {
        $res = $this->findNodeRequest(path: $path, type: $type);

        if ($res) {
            return $this->getEntry($res);
        }

        return null;
    }

    public function nodeExists(string $path, string $type = 'cm:content'): bool
    {
        return json_decode($this->findNodeRequest(path: $path, type: $type))
                ->list
                ->pagination
                ->count > 0;
    }

    public function findNodeId(string $path, string $type = 'cm:content'): string
    {
        return $this->getEntryId($this->findNodeRequest(path: $path, type: $type));
    }

    public function getNodeContent(string $path)
    {
        $nodeId = $this->findNodeId(path: $path);

        return StreamWrapper::getResource(
            $this->getNodeContentRequest(
                nodeId: $nodeId
            )
                ->getBody()
        );
    }

    public function deleteNode(string $path): ResponseInterface
    {
        $nodeId = $this->findNodeId(path: $path);

        return $this->deleteNodeRequest(nodeId: $nodeId);
    }

    protected function getNodeContentRequest(string $nodeId): ResponseInterface
    {
        try {

            return $this->client
                ->request(
                    method: 'GET',
                    uri: $this->getApiUrl(route: "nodes/$nodeId/content"),
                    options: [
                        'stream' => true,
                    ]
                );
        } catch (ClientException $e) {
            throw new \RuntimeException('Failed to get node', $e->getCode(), $e);
        } catch (ServerException $e) {
            throw new \RuntimeException('Alfresco server error', $e->getCode(), $e);
        } catch (ConnectException $e) {
            throw new \RuntimeException('Cannot connect to server', $e->getCode(), $e);
        } catch (TooManyRedirectsException $e) {
            throw new \RuntimeException('Too many redirect', $e->getCode(), $e);
        }
    }

    protected function deleteNodeRequest(string $nodeId): ResponseInterface
    {
        try {

            return $this->client
                ->request(
                    method: 'DELETE',
                    uri: $this->getApiUrl(route: "nodes/$nodeId"),
                );
        } catch (ClientException $e) {
            throw new \RuntimeException('Failed to delete node', $e->getCode(), $e);
        } catch (ServerException $e) {
            throw new \RuntimeException('Alfresco server error', $e->getCode(), $e);
        } catch (ConnectException $e) {
            throw new \RuntimeException('Cannot connect to server', $e->getCode(), $e);
        } catch (TooManyRedirectsException $e) {
            throw new \RuntimeException('Too many redirect', $e->getCode(), $e);
        }
    }

    protected function findDocumentLibraryRequest(): StreamInterface
    {
        return $this->client
            ->request(
                method: 'POST',
                uri: $this->getApiUrl(route: 'search', api: 'search'),
                options: [
                    'json' => $this->aftsQuery(type: 'cm:folder'),
                ]
            )
            ->getBody();
    }

    protected function findNodeRequest(string $path, string $type = 'cm:content'): StreamInterface
    {
        return $this->client
            ->request(
                method: 'POST',
                uri: $this->getApiUrl(route: 'search', api: 'search'),
                options: [
                    'json' => $this->aftsQuery(path: $path, type: $type),
                ]
            )
            ->getBody();
    }

    protected function aftsPath(string $path = ''): string
    {
        $basePath = "/app:/st:sites/cm:$this->site/cm:documentLibrary";
        $exp = explode('/', $path);

        if ($exp[0] !== '') {
            foreach ($exp as $idx => $part) {
                $exp[$idx] = "cm:$part";
            }

            $path = implode('/', $exp);

            return "$basePath/$path";
        }

        return $basePath;
    }

    protected function aftsQuery(string $path = '', string $type = 'cm:content'): array
    {
        $aftsPath = $this->aftsPath(path: $path);

        return [
            'query' => [
                'language' => 'afts',
                'query' => "PATH:'$aftsPath' AND TYPE:'$type'",
            ],
        ];
    }

    protected function getApiUrl(string $route, string $api = 'alfresco'): string
    {
        return "$this->url/$this->endpoint/$api/$this->version/$route";
    }

    protected function getEntry(StreamInterface $stream)
    {
        $decoded = json_decode($stream);

        if (property_exists(object_or_class: $decoded, property: 'list')) {
            try {
                return $decoded->list
                    ->entries[0]
                    ->entry;
            } catch (\Exception $e) {
                throw new \RuntimeException('Failed to get entry', $e);
            }

        }

        return $decoded->entry;
    }

    protected function getEntryId(StreamInterface $stream): string
    {
        return $this->getEntry($stream)->id;
    }

    protected function normalizePathAndFileName(string $path): array
    {
        $explodedPath = explode(separator: '/', string: $path);

        $fileName = array_pop($explodedPath);
        $relativePath = implode('/', $explodedPath);

        return [
            'fileName' => $fileName,
            'relativePath' => $relativePath,
        ];
    }

    protected function resolveWriteBody(bool $multipart, array $arguments): array
    {
        [
            'fileName' => $fileName,
            'relativePath' => $relativePath,
            'contents' => $contents,
            'nodeType' => $nodeType,
        ] = $arguments;

        if ($multipart) {
            return [
                'multipart' => [
                    [
                        'name' => 'fileData',
                        'contents' => $contents,
                        'filename' => $fileName,
                    ],
                    [
                        'name' => 'name',
                        'contents' => $fileName,
                    ],
                    [
                        'name' => 'relativePath',
                        'contents' => $relativePath,
                    ],
                    [
                        'name' => 'nodeType',
                        'contents' => $nodeType,
                    ],
                ],
            ];
        }

        return [
            'json' => [
                'name' => $fileName,
                'relativePath' => $relativePath,
                'nodeType' => $nodeType,
            ],
        ];
    }

    public function getNodeChildren(string $nodeId, int $skipCount = 0, int $maxItems = 100)
    {
        return json_decode(
            $this->getNodeChildrenRequest(nodeId: $nodeId, skipCount: $skipCount, maxItems: $maxItems)
        );
    }

    protected function getNodeChildrenRequest(string $nodeId, int $skipCount = 0, int $maxItems = 100): StreamInterface
    {
        try {
            return $this->client
                ->request(
                    method: 'GET',
                    uri: $this->getApiUrl(route: "nodes/$nodeId/children"),
                    options: [
                        'query' => [
                            'maxItems' => $maxItems,
                            'skipCount' => $skipCount,
                            'include' => 'path',
                        ],
                    ]
                )
                ->getBody();
        } catch (ClientException $e) {
            throw new \RuntimeException('Failed to get node children', $e->getCode(), $e);
        } catch (ServerException $e) {
            throw new \RuntimeException('Alfresco server error', $e->getCode(), $e);
        } catch (ConnectException $e) {
            throw new \RuntimeException('Cannot connect to server', $e->getCode(), $e);
        } catch (TooManyRedirectsException $e) {
            throw new \RuntimeException('Too many redirect', $e->getCode(), $e);
        }
    }
}

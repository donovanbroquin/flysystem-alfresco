<?php

namespace Donovanbroquin\FlysystemAlfresco;

use GuzzleHttp\Client;
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
        $libraryId = $this->findDocumentLibraryId();
        [
            'fileName' => $fileName,
            'relativePath' => $relativePath
        ] = $this->normalizePathAndFileName(path: $path);

        if($exists) {
            $nodeId = $this->findNodeId(path: $path);
            $res = $this->updateNodeContentRequest(
                nodeId: $nodeId,
                contents: stream_get_contents($contents)
            );
        } else {
            $res = $this->writeNodeRequest(
                nodeId: $libraryId,
                contents: $contents,
                fileName: $fileName,
                relativePath: $relativePath,
                nodeType: $nodeType,
                multipart: !is_string($contents)
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
    ): StreamInterface
    {
        $arguments = [
            'nodeId' => $nodeId,
            'contents' => $contents,
            'fileName' => $fileName,
            'relativePath' => $relativePath,
            'nodeType' => $nodeType,
        ];

        return $this->client
            ->request(
                method: 'POST',
                uri: $this->getApiUrl(route: "nodes/$nodeId/children"),
                options: $this->resolveWriteBody(multipart: $multipart, arguments: $arguments)
            )
            ->getBody();
    }

    public function updateNodeContent(string $nodeId, string $contents): \stdClass
    {
        return $this->getEntry(
            $this->updateNodeContentRequest(nodeId: $nodeId, contents: $contents)
        );
    }

    protected function updateNodeContentRequest(string $nodeId, string $contents): StreamInterface
    {
        return $this->client
            ->request(
                method: 'PUT',
                uri: $this->getApiUrl(route: "nodes/$nodeId/content"),
                options: [
                    'body' => $contents
                ]
            )
            ->getBody();
    }

    public function findDocumentLibraryId(): string
    {
        return $this->getEntryId($this->findDocumentLibraryRequest());
    }

    public function findNode(string $path): \stdClass
    {
        return $this->getEntry($this->findNodeRequest(path: $path));
    }

    public function nodeExists(string $path): bool
    {
        return json_decode($this->findNodeRequest(path: $path))
                ->list
                ->pagination
                ->count > 0;
    }

    public function findNodeId(string $path): string
    {
        return $this->getEntryId($this->findNodeRequest($path));
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
        return $this->client
            ->request(
                method: 'GET',
                uri: $this->getApiUrl(route: "nodes/$nodeId/content"),
                options: [
                    'stream' => true,
                ]
            );
    }

    protected function deleteNodeRequest(string $nodeId): ResponseInterface
    {
        return $this->client
            ->request(
                method: 'DELETE',
                uri: $this->getApiUrl(route: "nodes/$nodeId"),
            );
    }

    protected function findDocumentLibraryRequest(): StreamInterface
    {
        return $this->client
            ->request(
                method: 'POST',
                uri: $this->getApiUrl(route: 'search', api: 'search'),
                options: [
                    'json' => $this->aftsQuery(type: 'cm:folder')
                ]
            )
                ->getBody();
    }

    protected function findNodeRequest(string $path): StreamInterface
    {
        return $this->client
            ->request(
                method: 'POST',
                uri: $this->getApiUrl(route: 'search', api: 'search'),
                options: [
                    'json' => $this->aftsQuery($path)
                ]
            )
            ->getBody();
    }

    protected function aftsPath(string $path = ''): string
    {
        $basePath = "/app:/st:sites/cm:$this->site/cm:documentLibrary";
        $exp = explode('/', $path);

        if($exp[0] !== '') {
            foreach($exp as $idx => $part) {
                $exp[$idx] = "cm:$part";
            }

            $path = join('/', $exp);

            return "$basePath/$path";
        }

        return $basePath;
    }

    protected function aftsQuery(string $path = '', string $type = 'cm:content'): array
    {
        $aftsPath = $this->aftsPath(path: $path);

        return [
            "query" => [
                "language" => "afts",
                "query" => "PATH:'$aftsPath' AND TYPE:'$type'"
            ]
        ];
    }

    protected function getApiUrl(string $route, string $api = 'alfresco'): string
    {
        return "$this->url/$this->endpoint/$api/$this->version/$route";
    }

    protected function getEntry(StreamInterface $stream)
    {
        $decoded = json_decode($stream);

        if(property_exists(object_or_class: $decoded, property: 'list')) {
            return $decoded->list
                ->entries[0]
                ->entry;
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
        $relativePath = join('/', $explodedPath);

        return [
            'fileName' => $fileName,
            'relativePath' => $relativePath
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

        if($multipart) {
            return [
                'multipart' => [
                    [
                        'name'     => 'fileData',
                        'contents' => $contents,
                        'filename' => $fileName
                    ],
                    [
                        'name'     => 'name',
                        'contents' => $fileName
                    ],
                    [
                        'name'     => 'relativePath',
                        'contents' => $relativePath
                    ],
                    [
                        'name'     => 'nodeType',
                        'contents' => $nodeType
                    ]
                ]
            ];
        }

        return [
            'json' => [
                'name' => $fileName,
                'relativePath' => $relativePath,
                'nodeType' => $nodeType
            ]
        ];
    }
}

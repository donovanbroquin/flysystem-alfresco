<?php

use Donovanbroquin\FlysystemAlfresco\AlfrescoClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Psr7\{Response};
use GuzzleHttp\{Client, HandlerStack};

beforeEach(function (): void {
    $this->mock = new MockHandler;

    $handlerStack = HandlerStack::create($this->mock);
    $this->client = new Client(['handler' => $handlerStack]);

    // Inject mock client in AlfrescoClient
    $this->alfrescoClient = new AlfrescoClient([
        'url' => 'http://alfresco.test',
        'site' => 'internal',
        'username' => 'internal',
        'password' => 'internal',
    ]);

    // Replace actual client with mock client
    $reflection = new ReflectionClass($this->alfrescoClient);
    $clientProperty = $reflection->getProperty('client');
    $clientProperty->setAccessible(true);
    $clientProperty->setValue($this->alfrescoClient, $this->client);
});

it('finds document library ID', function (): void {
    $this->mock->append(new Response(200, [], json_encode([
        'entry' => ['id' => 'mock-node-id'],
    ])));
    $libraryId = $this->alfrescoClient->findDocumentLibraryId();

    expect($libraryId)->toBe('mock-node-id');
});

it('finds node by path', function (): void {
    $this->mock->append(new Response(200, [], json_encode([
        'entry' => ['id' => 'mock-node-id'],
    ])));
    $path = 'mock/path/to/node';
    $node = $this->alfrescoClient->findNode($path);

    expect($node->id)->toBe('mock-node-id');
});

it('checks if a node exists by path', function (): void {
    $this->mock->append(new Response(200, [], json_encode([
        'list' => ['pagination' => ['count' => 1]],
        'entry' => ['id' => 'mock-node-id'],
    ])));
    $path = 'mock/path/to/node';
    $exists = $this->alfrescoClient->nodeExists($path);

    expect($exists)->toBeTrue();
});

it('replace existing node on write', function (): void {
    $this->mock->append(
        new Response(200, [], json_encode([
            'list' => [
                'pagination' => ['count' => 1],
                'entries' => [['entry' => ['id' => 'search-node-id']]],
            ],
        ])),
        new Response(200, [], json_encode([
            'list' => [
                'pagination' => ['count' => 1],
                'entries' => [['entry' => ['id' => 'search-node-id']]],
            ],
        ])),
        new Response(201, [], json_encode([
            'entry' => ['id' => 'new-mock-node-id'],
        ]))
    );

    $path = 'mock/path/to/new-node.txt';
    $node = $this->alfrescoClient->writeNode($path, 'hello world');

    expect($node->id)->toBe('new-mock-node-id');
});

it('write node if not exists', function (): void {
    $this->mock->append(
        new Response(200, [], json_encode([
            'list' => [
                'pagination' => ['count' => 1],
                'entries' => [],
            ],
        ])),
        new Response(200, [], json_encode([
            'list' => [
                'pagination' => ['count' => 1],
                'entries' => [['entry' => ['id' => 'library-node-id']]],
            ],
        ])),
        new Response(201, [], json_encode([
            'entry' => ['id' => 'new-mock-node-id'],
        ]))
    );

    $path = 'mock/path/to/new-node.txt';
    $node = $this->alfrescoClient->writeNode($path, 'hello world');

    expect($node->id)->toBe('new-mock-node-id');
});

it('updates an existing node', function (): void {
    $this->mock->append(new Response(200, [], json_encode([
        'entry' => ['id' => 'mock-node-id'],
    ])));

    $nodeId = 'mock-node-id';
    $contents = 'Updated contents';
    $node = $this->alfrescoClient->updateNodeContent($nodeId, $contents);

    expect($node->id)->toBe('mock-node-id');
});

it('deletes a node by path', function (): void {
    $this->mock->append(
        new Response(200, [], json_encode([
            'list' => [
                'pagination' => ['count' => 1],
                'entries' => [['entry' => ['id' => 'search-node-id']]],
            ],
        ])),
        new Response(204),
    );
    $path = 'mock/path/to/node.txt';
    $response = $this->alfrescoClient->deleteNode($path);

    expect($response->getStatusCode())->toBe(204);
});

test('relative path with file name should be split', function (): void {
    $method = setClientMethodAsPublic(client: $this->alfrescoClient, method: 'normalizePathAndFileName');

    $result = $method->invokeArgs($this->alfrescoClient, ['customers/invoices/invoice2.pdf']);

    expect($result['fileName'])->toBe('invoice2.pdf')
        ->and($result['relativePath'])->toBe('customers/invoices');
});

test('ensure API url is correctly formatted', function (): void {
    $method = setClientMethodAsPublic(client: $this->alfrescoClient, method: 'getApiUrl');

    $alfrescoApi = $method->invokeArgs($this->alfrescoClient, ['nodes/node-id/content']);
    expect($alfrescoApi)
        ->toBe('http://alfresco.test/alfresco/api/-default-/public/alfresco/versions/1/nodes/node-id/content');

    $searchApi = $method->invokeArgs($this->alfrescoClient, ['search', 'search']);
    expect($searchApi)
        ->toBe('http://alfresco.test/alfresco/api/-default-/public/search/versions/1/search');
});

test('ensure afts path is correctly formatted', function (): void {
    $method = setClientMethodAsPublic(client: $this->alfrescoClient, method: 'aftsPath');

    $documentLibraryPath = $method->invokeArgs($this->alfrescoClient, []);
    expect($documentLibraryPath)
        ->toBe('/app:/st:sites/cm:internal/cm:documentLibrary');

    $basicPath = $method->invokeArgs($this->alfrescoClient, ['invoice.pdf']);
    expect($basicPath)
        ->toBe('/app:/st:sites/cm:internal/cm:documentLibrary/cm:invoice.pdf');

    $nestedPath = $method->invokeArgs($this->alfrescoClient, ['customers/invoices/invoice2.pdf']);
    expect($nestedPath)
        ->toBe('/app:/st:sites/cm:internal/cm:documentLibrary/cm:customers/cm:invoices/cm:invoice2.pdf');
});

test('ensure afts query is correctly formatted', function (): void {
    $method = setClientMethodAsPublic(client: $this->alfrescoClient, method: 'aftsQuery');

    $contentNode = $method->invokeArgs($this->alfrescoClient, ['invoice.pdf']);
    expect($contentNode)
        ->toBe([
            'query' => [
                'language' => 'afts',
                'query' => "PATH:'/app:/st:sites/cm:internal/cm:documentLibrary/cm:invoice.pdf' AND TYPE:'cm:content'",
            ],
        ]);

    $folderNode = $method->invokeArgs($this->alfrescoClient, ['customer', 'cm:folder']);
    expect($folderNode)
        ->toBe([
            'query' => [
                'language' => 'afts',
                'query' => "PATH:'/app:/st:sites/cm:internal/cm:documentLibrary/cm:customer' AND TYPE:'cm:folder'",
            ],
        ]);
});

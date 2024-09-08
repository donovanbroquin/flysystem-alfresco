<?php

use Donovanbroquin\FlysystemAlfresco\AlfrescoClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Psr7\{Request, Response, Utils};
use GuzzleHttp\{Client,
    Exception\ClientException,
    Exception\ConnectException,
    Exception\ServerException,
    Exception\TooManyRedirectsException,
    HandlerStack};

beforeEach(function (): void {
    $this->mock = new MockHandler;

    $handlerStack = HandlerStack::create($this->mock);
    $this->client = new Client(['handler' => $handlerStack]);

    $this->alfrescoClient = new AlfrescoClient([
        'url' => 'http://alfresco.test',
        'site' => 'internal',
        'username' => 'internal',
        'password' => 'internal',
    ]);

    $reflection = new ReflectionClass($this->alfrescoClient);
    $clientProperty = $reflection->getProperty('client');
    $clientProperty->setAccessible(true);
    $clientProperty->setValue($this->alfrescoClient, $this->client);
});

afterEach(function () {
    Mockery::close();
});

it('finds document library ID', function (): void {
    $this->mock->append(new Response(200, [], json_encode([
        'entry' => ['id' => 'mock-node-id'],
    ])));
    $libraryId = $this->alfrescoClient->findDocumentLibraryId();

    expect($libraryId)->toBe('mock-node-id');
});

it('finds node by path', function (): void {
    $this->mock->append(
        new Response(200, [], json_encode([
            'entry' => ['id' => 'mock-node-id'],
        ]))
    );
    $path = 'mock/path/to/node';
    $node = $this->alfrescoClient->findNode($path);

    expect($node->id)->toBe('mock-node-id');
});

test('throws an exception when ClientException occurs on get node request', function (): void {
    $this->mock->append(
        new ClientException(
            'Failed to get node',
            new Request('GET', 'nodes/mock-node-id/children'),
            new Response(400)
        )
    );

    $path = 'mock/path/to/new-node.txt';
    $this->alfrescoClient->writeNode($path, 'hello world');

})->throws(\RuntimeException::class, 'Failed to get node');

test('throws an exception when ServerException occurs on get node request', function (): void {
    $this->mock->append(
        new ServerException(
            'Alfresco server error',
            new Request('GET', 'nodes/mock-node-id/children'),
            new Response(500)
        )
    );

    $path = 'mock/path/to/new-node.txt';
    $this->alfrescoClient->writeNode($path, 'hello world');
})->throws(\RuntimeException::class, 'Alfresco server error');

test('throws an exception when ConnectException occurs on get node request', function (): void {
    $this->mock->append(
        new ConnectException(
            'Cannot connect to server',
            new Request('GET', 'nodes/mock-node-id/children')
        )
    );

    $path = 'mock/path/to/new-node.txt';
    $this->alfrescoClient->writeNode($path, 'hello world');
})->throws(\RuntimeException::class, 'Cannot connect to server');

test('throws an exception when TooManyRedirectsException occurs on get node request', function (): void {
    $this->mock->append(
        new TooManyRedirectsException(
            'Too many redirects occurred',
            new Request('GET', 'nodes/mock-node-id/children'),
            new Response(301)
        )
    );

    $path = 'mock/path/to/new-node.txt';
    $this->alfrescoClient->writeNode($path, 'hello world');
})->throws(\RuntimeException::class, 'Too many redirect');

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

test('throws an exception when ClientException occurs on write node request', function (): void {
    $this->mock->append(
        new ClientException(
            'Failed to write node',
            new Request('POST', 'nodes/mock-node-id/children'),
            new Response(400)
        )
    );

    $path = 'mock/path/to/new-node.txt';
    $this->alfrescoClient->writeNode($path, 'hello world');

})->throws(\RuntimeException::class, 'Failed to write node');

test('throws an exception when ServerException occurs on write node request', function (): void {
    $this->mock->append(
        new ServerException(
            'Alfresco server error',
            new Request('POST', 'nodes/mock-node-id/children'),
            new Response(500)
        )
    );

    $path = 'mock/path/to/new-node.txt';
    $this->alfrescoClient->writeNode($path, 'hello world');
})->throws(\RuntimeException::class, 'Alfresco server error');

test('throws an exception when ConnectException occurs on write node request', function (): void {
    $this->mock->append(
        new ConnectException(
            'Cannot connect to server',
            new Request('POST', 'nodes/mock-node-id/children')
        )
    );

    $path = 'mock/path/to/new-node.txt';
    $this->alfrescoClient->writeNode($path, 'hello world');
})->throws(\RuntimeException::class, 'Cannot connect to server');

test('throws an exception when TooManyRedirectsException  occurs on write node request', function (): void {
    $this->mock->append(
        new TooManyRedirectsException(
            'Too many redirects occurred',
            new Request('POST', 'nodes/mock-node-id/children'),
            new Response(301)
        )
    );

    $path = 'mock/path/to/new-node.txt';
    $this->alfrescoClient->writeNode($path, 'hello world');
})->throws(\RuntimeException::class, 'Too many redirect');

it('updates an existing node', function (): void {
    $this->mock->append(new Response(200, [], json_encode([
        'entry' => ['id' => 'mock-node-id'],
    ])));

    $nodeId = 'mock-node-id';
    $contents = 'Updated contents';
    $node = $this->alfrescoClient->updateNodeContent($nodeId, $contents);

    expect($node->id)->toBe('mock-node-id');
});

test('throws an exception when ClientException occurs on update node request', function (): void {
    $this->mock->append(
        new ClientException(
            'Failed to update node',
            new Request('PUT', 'nodes/mock-node-id/children'),
            new Response(400)
        )
    );

    $path = 'mock/path/to/new-node.txt';
    $this->alfrescoClient->writeNode($path, 'hello world');

})->throws(\RuntimeException::class, 'Failed to update node');

test('throws an exception when ServerException occurs on update node request', function (): void {
    $this->mock->append(
        new ServerException(
            'Alfresco server error',
            new Request('PUT', 'nodes/mock-node-id/children'),
            new Response(500)
        )
    );

    $path = 'mock/path/to/new-node.txt';
    $this->alfrescoClient->writeNode($path, 'hello world');
})->throws(\RuntimeException::class, 'Alfresco server error');

test('throws an exception when ConnectException occurs on update node request', function (): void {
    $this->mock->append(
        new ConnectException(
            'Cannot connect to server',
            new Request('PUT', 'nodes/mock-node-id/children')
        )
    );

    $path = 'mock/path/to/new-node.txt';
    $this->alfrescoClient->writeNode($path, 'hello world');
})->throws(\RuntimeException::class, 'Cannot connect to server');

test('throws an exception when TooManyRedirectsException occurs on update node request', function (): void {
    $this->mock->append(
        new TooManyRedirectsException(
            'Too many redirects occurred',
            new Request('PUT', 'nodes/mock-node-id/children'),
            new Response(301)
        )
    );

    $path = 'mock/path/to/new-node.txt';
    $this->alfrescoClient->writeNode($path, 'hello world');
})->throws(\RuntimeException::class, 'Too many redirect');

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

test('throws an exception when ClientException occurs on delete node request', function (): void {
    $this->mock->append(
        new ClientException(
            'Failed to delete node',
            new Request('DELETE', 'nodes/mock-node-id/children'),
            new Response(400)
        )
    );

    $path = 'mock/path/to/new-node.txt';
    $this->alfrescoClient->writeNode($path, 'hello world');

})->throws(\RuntimeException::class, 'Failed to delete node');

test('throws an exception when ServerException occurs on delete node request', function (): void {
    $this->mock->append(
        new ServerException(
            'Alfresco server error',
            new Request('DELETE', 'nodes/mock-node-id/children'),
            new Response(500)
        )
    );

    $path = 'mock/path/to/new-node.txt';
    $this->alfrescoClient->writeNode($path, 'hello world');
})->throws(\RuntimeException::class, 'Alfresco server error');

test('throws an exception when ConnectException occurs on delete node request', function (): void {
    $this->mock->append(
        new ConnectException(
            'Cannot connect to server',
            new Request('DELETE', 'nodes/mock-node-id/children')
        )
    );

    $path = 'mock/path/to/new-node.txt';
    $this->alfrescoClient->writeNode($path, 'hello world');
})->throws(\RuntimeException::class, 'Cannot connect to server');

test('throws an exception when TooManyRedirectsException occurs on delete node request', function (): void {
    $this->mock->append(
        new TooManyRedirectsException(
            'Too many redirects occurred',
            new Request('DELETE', 'nodes/mock-node-id/children'),
            new Response(301)
        )
    );

    $path = 'mock/path/to/new-node.txt';
    $this->alfrescoClient->writeNode($path, 'hello world');
})->throws(\RuntimeException::class, 'Too many redirect');

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

test('ensure write request is correctly formatted', function (): void {
    $method = setClientMethodAsPublic(client: $this->alfrescoClient, method: 'resolveWriteBody');

    $arguments = [
        'fileName' => 'file-one.txt',
        'relativePath' => '/',
        'contents' => 'content-one',
        'nodeType' => 'cm:content',
    ];

    // Multipart
    $body = $method->invokeArgs($this->alfrescoClient, [true, $arguments]);
    expect($body)->toBe([
        'multipart' => [
            [
                'name' => 'fileData',
                'contents' => 'content-one',
                'filename' => 'file-one.txt',
            ],
            [
                'name' => 'name',
                'contents' => 'file-one.txt',
            ],
            [
                'name' => 'relativePath',
                'contents' => '/',
            ],
            [
                'name' => 'nodeType',
                'contents' => 'cm:content',
            ],
        ],
    ]);

    // JSON
    $body = $method->invokeArgs($this->alfrescoClient, [false, $arguments]);
    expect($body)->toBe([
        'json' => [
            'name' => 'file-one.txt',
            'relativePath' => '/',
            'nodeType' => 'cm:content',
        ],
    ]);
});

test('ensure the first entry in search is returned', function (): void {
    $method = setClientMethodAsPublic(client: $this->alfrescoClient, method: 'getEntry');
    $stream = Utils::streamFor(json_encode([
        'list' => [
            'entries' => [
                [
                    'entry' => [
                        'name' => 'node.txt',
                        'id' => 'search-node-id',
                    ],
                ],
            ],
        ],
    ]));

    $res = $method->invokeArgs($this->alfrescoClient, [$stream]);
    $resAsClass = new stdClass;
    $resAsClass->name = 'node.txt';
    $resAsClass->id = 'search-node-id';

    expect($res->id)->toBe($resAsClass->id)
        ->and($res->name)->toBe($resAsClass->name);
});

it('must return the entry id', function (): void {
    $method = setClientMethodAsPublic(client: $this->alfrescoClient, method: 'getEntryId');
    $stream = Utils::streamFor(json_encode([
        'list' => [
            'entries' => [
                [
                    'entry' => [
                        'id' => 'search-node-id',
                    ],
                ],
            ],
        ],
    ]));

    $res = $method->invokeArgs($this->alfrescoClient, [$stream]);
    $resAsClass = new stdClass;
    $resAsClass->id = 'search-node-id';

    expect($res)->toBe($resAsClass->id);
});

it('successfully retrieves node children', function (): void {
    $response = json_encode([
        'list' => [
            'entries' => [
                ['entry' => ['name' => 'file1.txt', 'isFolder' => false]],
                ['entry' => ['name' => 'folder1', 'isFolder' => true]],
            ],
            'pagination' => [
                'hasMoreItems' => false,
            ],
        ],
    ]);

    $this->mock->append(new Response(200, [], $response));

    $nodeId = 'mock-node-id';
    $result = $this->alfrescoClient->getNodeChildren($nodeId);

    expect($result->list->entries)->toHaveCount(2);
    expect($result->list->entries[0]->entry->name)->toBe('file1.txt');
});

// Test handling of ClientException
test('throws an exception when ClientException occurs on getNodeChildren request', function (): void {
    $this->mock->append(
        new ClientException(
            'Failed to get node children',
            new Request('GET', 'nodes/mock-node-id/children'),
            new Response(400)
        )
    );

    $nodeId = 'mock-node-id';
    $this->alfrescoClient->getNodeChildren($nodeId);
})->throws(\RuntimeException::class, 'Failed to get node children');

// Test handling of ServerException
test('throws an exception when ServerException occurs on getNodeChildren request', function (): void {
    $this->mock->append(
        new ServerException(
            'Alfresco server error',
            new Request('GET', 'nodes/mock-node-id/children'),
            new Response(500)
        )
    );

    $nodeId = 'mock-node-id';
    $this->alfrescoClient->getNodeChildren($nodeId);
})->throws(\RuntimeException::class, 'Alfresco server error');

// Test handling of ConnectException
test('throws an exception when ConnectException occurs on getNodeChildren request', function (): void {
    $this->mock->append(
        new ConnectException(
            'Cannot connect to server',
            new Request('GET', 'nodes/mock-node-id/children')
        )
    );

    $nodeId = 'mock-node-id';
    $this->alfrescoClient->getNodeChildren($nodeId);
})->throws(\RuntimeException::class, 'Cannot connect to server');

// Test handling of TooManyRedirectsException
test('throws an exception when TooManyRedirectsException occurs on getNodeChildren request', function (): void {
    $this->mock->append(
        new TooManyRedirectsException(
            'Too many redirects occurred',
            new Request('GET', 'nodes/mock-node-id/children'),
            new Response(301)
        )
    );

    $nodeId = 'mock-node-id';
    $this->alfrescoClient->getNodeChildren($nodeId);
})->throws(\RuntimeException::class, 'Too many redirect');

<?php

use Carbon\Carbon;
use Donovanbroquin\FlysystemAlfresco\{AlfrescoAdapter, AlfrescoClient};
use League\Flysystem\{Config, DirectoryAttributes, FileAttributes};

beforeEach(function (): void {
    $this->alfrescoClient = Mockery::mock(AlfrescoClient::class);
    $this->config = new Config;
    $this->adapter = new AlfrescoAdapter([
        'url' => 'http://mock-alfresco-url',
        'site' => 'mock-site',
        'username' => 'mock-user',
        'password' => 'mock-password',
    ]);

    $reflection = new ReflectionClass($this->adapter);
    $clientProperty = $reflection->getProperty('client');
    $clientProperty->setAccessible(true);
    $clientProperty->setValue($this->adapter, $this->alfrescoClient);
});

it('checks if a file exists', function (): void {
    $this->alfrescoClient->shouldReceive('nodeExists')
        ->with('path/to/file.txt')
        ->andReturn(true);

    $exists = $this->adapter->fileExists('path/to/file.txt');

    expect($exists)->toBeTrue();
});

it('checks if a directory exists', function (): void {
    $this->alfrescoClient->shouldReceive('nodeExists')
        ->with('path/to/directory')
        ->andReturn(true);

    $exists = $this->adapter->directoryExists('path/to/directory');

    expect($exists)->toBeTrue();
});

it('writes a file', function (): void {
    $nodeMock = (object) ['id' => 'mock-node-id'];

    $this->alfrescoClient->shouldReceive('writeNode')
        ->with('path/to/file.txt', 'file contents')
        ->andReturn($nodeMock);

    $this->alfrescoClient->shouldReceive('updateNodeContent')
        ->with('mock-node-id', 'file contents');

    $this->adapter->write('path/to/file.txt', 'file contents', $this->config);
})->doesNotPerformAssertions();

it('writes a stream', function (): void {
    $resource = tmpfile();
    fwrite($resource, 'stream contents');
    fseek($resource, 0);

    $this->alfrescoClient->shouldReceive('writeNode')
        ->with('path/to/file.txt', $resource);

    $this->adapter->writeStream('path/to/file.txt', $resource, $this->config);

    fclose($resource);
})->doesNotPerformAssertions();

it('reads a file', function (): void {
    $stream = tmpfile();
    fwrite($stream, 'file contents');
    fseek($stream, 0);

    $this->alfrescoClient->shouldReceive('getNodeContent')
        ->with('path/to/file.txt')
        ->andReturn($stream);

    $contents = $this->adapter->read('path/to/file.txt');

    expect($contents)->toBe('file contents');
});

it('reads a stream', function (): void {
    $stream = tmpfile();
    fwrite($stream, 'stream contents');
    fseek($stream, 0);

    $this->alfrescoClient->shouldReceive('getNodeContent')
        ->with('path/to/file.txt')
        ->andReturn($stream);

    $returnedStream = $this->adapter->readStream('path/to/file.txt');

    expect($returnedStream)->toBe($stream);

    fclose($stream);
});

it('deletes a file', function (): void {
    $this->alfrescoClient->shouldReceive('deleteNode')
        ->with('path/to/file.txt');

    $this->adapter->delete('path/to/file.txt');
})->doesNotPerformAssertions();

it('deletes a directory', function (): void {
    $this->alfrescoClient->shouldReceive('deleteNode')
        ->with('path/to/directory');

    $this->adapter->deleteDirectory('path/to/directory');
})->doesNotPerformAssertions();

it('creates a directory', function (): void {
    $this->alfrescoClient->shouldReceive('writeNode')
        ->with('path/to/directory', null, 'cm:folder');

    $this->adapter->createDirectory('path/to/directory', $this->config);
})->doesNotPerformAssertions();

it('returns file visibility', function (): void {
    $nodeMock = (object) ['content' => (object) ['mimeType' => 'text/plain']];

    $this->alfrescoClient->shouldReceive('findNode')
        ->with('path/to/file.txt')
        ->andReturn($nodeMock);

    $attributes = $this->adapter->visibility('path/to/file.txt');

    expect($attributes)->toBeInstanceOf(FileAttributes::class)
        ->and($attributes->visibility())->toEqual(1);
});

it('retrieves mime type', function (): void {
    $nodeMock = (object) ['content' => (object) ['mimeType' => 'text/plain']];

    $this->alfrescoClient->shouldReceive('findNode')
        ->with('path/to/file.txt')
        ->andReturn($nodeMock);

    $attributes = $this->adapter->mimeType('path/to/file.txt');

    expect($attributes->mimeType())->toBe('text/plain');
});

it('retrieves last modified time', function (): void {
    $nodeMock = (object) ['content' => (object) ['mimeType' => '2024-09-01T12:00:00']];

    $this->alfrescoClient->shouldReceive('findNode')
        ->with('path/to/file.txt')
        ->andReturn($nodeMock);

    $attributes = $this->adapter->lastModified('path/to/file.txt');

    $timestamp = Carbon::parse('2024-09-01T12:00:00')->getTimestamp();
    expect($attributes->lastModified())->toBe($timestamp);
});

it('retrieves file size', function (): void {
    $nodeMock = (object) ['content' => (object) ['sizeInBytes' => 123456]];

    $this->alfrescoClient->shouldReceive('findNode')
        ->with('path/to/file.txt')
        ->andReturn($nodeMock);

    $attributes = $this->adapter->fileSize('path/to/file.txt');

    expect($attributes->fileSize())->toBe(123456);
});

it('lists contents without recursion', function (): void {
    $nodeId = 'mock-node-id';
    $entries = [
        (object) [
            'entry' => (object) [
                'isFile' => true,
                'isFolder' => false,
                'name' => 'file1.txt',
                'path' => (object) [
                    'name' => '/root/library/path/to/directory'
                ],
                'modifiedAt' => '2024-09-01T12:00:00',
                'content' => (object) [
                    'sizeInBytes' => 123,
                    'mimeType' => 'text/plain'
                ]
            ]
        ],
        (object) [
            'entry' => (object) [
                'isFile' => true,
                'isFolder' => false,
                'name' => 'file2.txt',
                'path' => (object) [
                    'name' => '/root/library/path/to/directory'
                ],
                'modifiedAt' => '2024-09-01T12:00:00',
                'content' => (object) [
                    'sizeInBytes' => 123,
                    'mimeType' => 'text/plain'
                ]
            ]
        ]
    ];

    $this->alfrescoClient->shouldReceive('findNodeId')
        ->with('path/to/directory', 'cm:folder')
        ->andReturn($nodeId);

    $this->alfrescoClient->shouldReceive('getNodeChildren')
        ->with($nodeId, 0, 100)
        ->andReturn((object) ['list' => (object) ['entries' => $entries, 'pagination' => (object) ['hasMoreItems' => false]]]);

    $contents = iterator_to_array($this->adapter->listContents('path/to/directory', false));

    expect($contents)->toHaveCount(2)
        ->and($contents[0]->path())->toBe('path/to/directory/file1.txt')
        ->and($contents[1]->path())->toBe('path/to/directory/file2.txt');
});

it('lists contents with recursion', function (): void {
    $rootNodeId = 'root-node-id';
    $subDirNodeId = 'sub-dir-node-id';

    $entries = [
        (object) [
            'entry' => (object) [
                'isFile' => false,
                'isFolder' => true,
                'name' => 'subdir',
                'path' => (object) [
                    'name' => '/root/library/path/to'
                ],
                'modifiedAt' => '2024-09-01T12:00:00'
            ]
        ],
        (object) [
            'entry' => (object) [
                'isFile' => true,
                'isFolder' => false,
                'name' => 'file1.txt',
                'path' => (object) [
                    'name' => '/root/library/path/to'
                ],
                'modifiedAt' => '2024-09-01T12:00:00',
                'content' => (object) [
                    'sizeInBytes' => 123,
                    'mimeType' => 'text/plain'
                ]
            ]
        ]
    ];

    $subDirEntries = [
        (object) [
            'entry' => (object) [
                'isFile' => true,
                'isFolder' => false,
                'name' => 'subfile.txt',
                'path' => (object) [
                    'name' => '/root/library/path/to/subdir'
                ],
                'modifiedAt' => '2024-09-01T12:00:00',
                'content' => (object) [
                    'sizeInBytes' => 456,
                    'mimeType' => 'text/plain'
                ]
            ]
        ]
    ];

    $this->alfrescoClient->shouldReceive('findNodeId')
        ->with('path/to', 'cm:folder')
        ->andReturn($rootNodeId);

    $this->alfrescoClient->shouldReceive('findNodeId')
        ->with('path/to/subdir', 'cm:folder')
        ->andReturn($subDirNodeId);

    $this->alfrescoClient->shouldReceive('getNodeChildren')
        ->with($rootNodeId, 0, 100)
        ->andReturn((object) ['list' => (object) ['entries' => $entries, 'pagination' => (object) ['hasMoreItems' => false]]]);

    $this->alfrescoClient->shouldReceive('getNodeChildren')
        ->with($subDirNodeId, 0, 100)
        ->andReturn((object) ['list' => (object) ['entries' => $subDirEntries, 'pagination' => (object) ['hasMoreItems' => false]]]);

    $contents = iterator_to_array($this->adapter->listContents('path/to', true));

    expect($contents)->toHaveCount(2)
        ->and($contents[0]->path())->toBe('path/to/subdir/subfile.txt')
        ->and($contents[1]->path())->toBe('path/to/file1.txt');
});

it('normalizes file entry response', function (): void {
    $method = setClientMethodAsPublic(client: $this->adapter, method: 'normalizeResponse');
    $entry = (object) [
        'name' => 'file.txt',
        'modifiedAt' => '2024-09-01T12:00:00',
        'path' => (object) ['name' => 'path'],
        'isFile' => true,
        'isFolder' => false,
        'content' => (object) ['sizeInBytes' => 123, 'mimeType' => 'text/plain']
    ];
    $rootPath = 'path';

    $normalized = $method->invokeArgs($this->adapter, [$entry, $rootPath]);

    expect($normalized)->toBeInstanceOf(FileAttributes::class)
        ->and($normalized->path())->toBe('file.txt')
        ->and($normalized->fileSize())->toBe(123)
        ->and($normalized->mimeType())->toBe('text/plain');
});

it('normalizes folder entry response', function (): void {
    $method = setClientMethodAsPublic(client: $this->adapter, method: 'normalizeResponse');
    $entry = (object) [
        'name' => 'folder',
        'modifiedAt' => '2024-09-01T12:00:00',
        'path' => (object) ['name' => 'path'],
        'isFolder' => true
    ];
    $rootPath = 'path';

    $normalized = $method->invokeArgs($this->adapter, [$entry, $rootPath]);

    expect($normalized)->toBeInstanceOf(DirectoryAttributes::class)
        ->and($normalized->path())->toBe('folder');
});
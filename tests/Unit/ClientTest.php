<?php

use Donovanbroquin\FlysystemAlfresco\AlfrescoClient;

function buildClient(): AlfrescoClient
{
    $client = new AlfrescoClient([
        'url' => 'http://localhost',
        'site' => 'internal',
        'username' => 'testing',
        'password' => 'testing',
    ]);

    return $client;
}

test('relative path with file name should be split', function () {
    $client = buildClient();

    $reflection = new ReflectionClass($client);
    $method = $reflection->getMethod('normalizePathAndFileName');
    $method->setAccessible(true);

    $result = $method->invokeArgs($client, ['customers/invoices/invoice2.pdf']);

    expect($result['fileName'])->toBe('invoice2.pdf')
        ->and($result['relativePath'])->toBe('customers/invoices');
});

test('ensure API url is correctly formatted', function () {
    $client = buildClient();

    $reflection = new ReflectionClass($client);
    $method = $reflection->getMethod('getApiUrl');
    $method->setAccessible(true);

    $alfrescoApi = $method->invokeArgs($client, ['nodes/node-id/content']);
    expect($alfrescoApi)
        ->toBe('http://localhost/alfresco/api/-default-/public/alfresco/versions/1/nodes/node-id/content');

    $searchApi = $method->invokeArgs($client, ['search', 'search']);
    expect($searchApi)
        ->toBe('http://localhost/alfresco/api/-default-/public/search/versions/1/search');
});

test('ensure afts path is correctly formatted', function () {
    $client = buildClient();

    $reflection = new ReflectionClass($client);
    $method = $reflection->getMethod('aftsPath');
    $method->setAccessible(true);

    $documentLibraryPath = $method->invokeArgs($client, []);
    expect($documentLibraryPath)
        ->toBe('/app:/st:sites/cm:internal/cm:documentLibrary');

    $basicPath = $method->invokeArgs($client, ['invoice.pdf']);
    expect($basicPath)
        ->toBe('/app:/st:sites/cm:internal/cm:documentLibrary/cm:invoice.pdf');

    $nestedPath = $method->invokeArgs($client, ['customers/invoices/invoice2.pdf']);
    expect($nestedPath)
        ->toBe('/app:/st:sites/cm:internal/cm:documentLibrary/cm:customers/cm:invoices/cm:invoice2.pdf');
});

test('ensure afts query is correctly formatted', function () {
    $client = buildClient();

    $reflection = new ReflectionClass($client);
    $method = $reflection->getMethod('aftsQuery');
    $method->setAccessible(true);

    $contentNode = $method->invokeArgs($client, ['invoice.pdf']);
    expect($contentNode)
        ->toBe([
            'query' => [
                'language' => 'afts',
                'query' => "PATH:'/app:/st:sites/cm:internal/cm:documentLibrary/cm:invoice.pdf' AND TYPE:'cm:content'",
            ],
        ]);

    $folderNode = $method->invokeArgs($client, ['customer', 'cm:folder']);
    expect($folderNode)
        ->toBe([
            'query' => [
                'language' => 'afts',
                'query' => "PATH:'/app:/st:sites/cm:internal/cm:documentLibrary/cm:customer' AND TYPE:'cm:folder'",
            ],
        ]);
});

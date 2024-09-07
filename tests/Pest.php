<?php

function setClientMethodAsPublic(object $client, string $method): ReflectionMethod
{
    $reflection = new ReflectionClass($client);
    $method = $reflection->getMethod($method);
    $method->setAccessible(true);

    return $method;
}

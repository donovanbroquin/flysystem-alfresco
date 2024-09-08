# Flysystem adapter for Alfresco
![indicate if the package pass tests](https://github.com/donovanbroquin/flysystem-alfresco/actions/workflows/run_test.yml/badge.svg)

A [flysystem](https://flysystem.thephpleague.com/docs/) v3 adapter to use [Alfresco](https://www.hyland.com/fr/products/alfresco-platform) sites functionality.

## Install
> This package is in development stage and not registered in Composer for now

## Usage
### Laravel
This package can be used as a Laravel flysystem disk.

```php
# app/Providers/AppServiceProvider.php

use Donovanbroquin\FlysystemAlfresco\AlfrescoAdapter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Filesystem\FilesystemAdapter;
use League\Flysystem\Filesystem;

public function boot(): void
{
    Storage::extend('alfresco', function (Application $app, array $config) {
        $adapter = new AlfrescoAdapter($config);
 
        return new FilesystemAdapter(
            new Filesystem($adapter, $config),
                $adapter,
                $config
        );
    });
}
```

```php
# config/filesystems.php

return [
    'disks' => [
        // ...

        'alfresco' => [
            'driver' => 'alfresco',
            'url' => 'https://alfresco.xyz',
            'site' => 'internal',
            'username' => 'username',
            'password' => 'password'
        ]
    ]
]
```

```php
Storage::disk('alfresco')->put('test.txt', 'Hello world');
```

## Development
### Test
This package uses [Pest](https://pestphp.com) as test runner.

You can launch it with the following command

```shell
vendor/bin/pest
```

### Environment
A development environment is present with the dockerfile and dev container with the package’s needs.

#### Dockerfile directly
```shell
docker build -t flysystem-alfresco:latest .
docker run -d -v $(pwd):/var/package -it flysystem-alfresco:latest
```

> Remember that any PHP command, including Composer, must be run from the container.

#### Dev Container
Open the package with your editor. It should ask you to re-open it with Dev Container.

> Works at least with Visual Studio Code and PHPStorm.
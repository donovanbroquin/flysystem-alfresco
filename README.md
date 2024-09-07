# Flysystem adapter for Alfresco
![indicate if the package pass tests](https://github.com/donovanbroquin/flysystem-alfresco/actions/workflows/run_test.yml/badge.svg)

## Install
> This package is in development stage and not registered in Composer for now

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
# City-of-Helsinki/helsinki-paatokset

This is a repository which will create a new Drupal 9 project for you and setup Docker based development
environment with Stonehenge. It creates the basics for the Helsinki Päätökset site

## Includes

- Drupal 9.0.x
- Drush 10.x
- Docker setup for development, see [docker-compose.yml](docker-compose.yml)
- [druidfi/tools](https://github.com/druidfi/tools)
- Web root is `/public`
- Configuration is in `/conf/cmi`
- Custom modules are created in `/public/modules/custom`

## Requirements

- PHP and Composer
- [Docker and Stonehenge](https://github.com/druidfi/guidelines/blob/master/docs/local_dev_env.md)

## Create a new project

### 1. using Composer

If you have PHP and Composer installed on your host (recommended):

```
$ composer create-project City-of-Helsinki/helsinki-paatokset:dev-main yoursite --no-interaction \
    --repository https://repository.drupal.hel.ninja/
```

Or using Docker image:

```
mkdir yoursite && cd yoursite && \
docker run --rm -it -v $PWD:/app --env COMPOSER_MEMORY_LIMIT=-1 \
    druidfi/drupal:7.4-web \
    composer create-project City-of-Helsinki/helsinki-paatokset:dev-main . --no-interaction \
    --repository https://repository.drupal.hel.ninja/
```

## Get started

Now you need to have Stonehenge up & running.

Start the development environment, build development codebase and install empty site with minimal profile:

```
$ make new
```

Now your site can can be accessed from https://yoursite.docker.sh

## More information

More infromation can be found at https://github.com/City-of-Helsinki/drupal-helfi-platform. It is the basis for this site



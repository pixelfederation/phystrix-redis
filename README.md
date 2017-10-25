# Phystrix Redis State Storage

Redis storage implementation for Phystrix (https://github.com/upwork/phystrix)
It uses the [predis/predis](https://github.com/nrk/predis) package.

## Usage

Require redis state storage library:

```bash
composer require pixelfederation/phystrix-redis
```

Create new instance of `\PixelFederation\Phystrix\Storage\RedisStateStorage` and inject to `\Odesk\Phystrix\CommandMetricsFactory` and `\Odesk\Phystrix\CircuitBreakerFactory`

## Usage with Symfony Framework

Install [Redis Bundle](https://github.com/snc/SncRedisBundle)

Install [Phystrix Bundle](https://github.com/pixelfederation/phystrix-bundle) and override state storage configuration:

```yaml
# services.yml
services:
    PixelFederation\Phystrix\Storage\RedisStateStorage:
        arguments:
            - '@snc_redis.cache'

    phystrix.state_storage:
        alias: PixelFederation\Phystrix\Storage\RedisStateStorage
```
# Managing the dependencies

The dependencies of this project are managed by:

- [Composer](https://getcomposer.org/) for the backend (see [`composer.json`](/composer.json));
- [NPM](https://www.npmjs.com/) for the frontend (see [`package.json`](/package.json));

The general philosophy about the dependencies is to limit them as much as possible.
Dependencies must be added to relieve a pain.

## Reminder about versions

Versions are usually using the [semver](https://semver.org) standard.
They are formatted as `major.minor.patch` where:

- `major` is a number incremented when incompatible API changes are made;
- `minor` is a number incremented when a new backwards compatible functionnality is added;
- `patch` is a number incremented when backwards bug fixes are made.

Be careful with versions `0.x.y`, the minor number is often considered as major.

## General advices

- Always check the changelog of the dependencies that you are updating;
- It should be fine to update the patch and minor versions in a batch;
- When upgrading to major versions, **always upgrade one dependency at a time;**
- Always restart the Docker containers after an update and verify the application is not broken.

## Upgrade the Composer dependencies

Check the outdated dependencies with:

```console
$ ./docker/bin/composer outdated
```

Update with:

```console
$ ./docker/bin/composer update
```

For major versions upgrade, please update the requirements in the file `composer.json` and run the previous command.

After that, [run the linters and the tests](/docs/developers/tests.md) to check everything is fine.

## Upgrade the NPM dependencies

Check the outdated dependencies with:

```console
$ ./docker/bin/npm outdated
```

Update with:

```console
$ ./docker/bin/npm update
```

For major versions upgrade, please update the requirements in the file `package.json` and run the previous command.

After that, [run the linters and the tests](/docs/developers/tests.md) to check everything is fine.

## Follow the Web feeds

It is recommended to follow the Web feeds of the main dependencies in an aggregator to be notified about new releases.

List of feeds for Pollaris:

- [PHP](https://www.php.net/releases/feed.php) ([news](https://www.php.net/))
- [Symfony](https://github.com/symfony/symfony/releases.atom) ([releases](https://github.com/symfony/symfony/releases))
- [Turbo](https://github.com/hotwired/turbo/releases.atom) ([releases](https://github.com/hotwired/turbo/releases))
- [Stimulus](https://github.com/hotwired/stimulus/releases.atom) ([releases](https://github.com/hotwired/stimulus/releases))
- [esbuild](https://github.com/evanw/esbuild/releases.atom) ([releases](https://github.com/evanw/esbuild/releases))
- [Foundry](https://github.com/zenstruck/foundry/releases.atom) ([releases](https://github.com/zenstruck/foundry/releases))
- [PHPUnit](https://github.com/sebastianbergmann/phpunit/releases.atom) ([releases](https://github.com/sebastianbergmann/phpunit/releases))
- [PHPStan](https://github.com/phpstan/phpstan/releases.atom) ([releases](https://github.com/phpstan/phpstan/releases))
- [Rector](https://github.com/rectorphp/rector/releases.atom) ([releases](https://github.com/rectorphp/rector/releases))
- [PHP\_CodeSniffer](https://github.com/squizlabs/PHP_CodeSniffer/releases.atom) ([releases](https://github.com/squizlabs/PHP_CodeSniffer/releases))

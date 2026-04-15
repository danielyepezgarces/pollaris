# Running the tests and the linters

The linters and the tests are executed on the CI, so you'll have to make sure they pass before we merge your merge request.

## Running the tests

You can execute the tests with:

```console
$ make test
```

Execute the tests of a specific file with the `FILE=` parameter:

```console
$ make test FILE=tests/path/to/file.php
```

Filter tests with the `FILTER=` parameter (it takes a function name, or a part of it):

```console
$ make test FILE=tests/path/to/file.php FILTER=testSomePattern
```

## Code coverage

The previous command generates code coverage under the folder `coverage/`.
To disable code coverage, run the command:

```console
$ make test COVERAGE=
```

## Running the linters

Execute the linters with:

```console
$ make lint
```

You can run a specific linter with:

```console
$ make lint LINTER=phpstan
$ make lint LINTER=rector
$ make lint LINTER=phpcs
$ make lint LINTER=container
```

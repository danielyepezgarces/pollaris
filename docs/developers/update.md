# Updating the development environment

Pull the changes with Git:

```console
$ git pull
```

If dependencies have been added or updated, install them:

```console
$ make install
```

Execute the migrations:

```console
$ make db-migrate
```

Sometimes, you may also have to pull or rebuild the Docker images:

```console
$ make docker-build
$ make docker-pull
```

Remember to restart the containers then.

If you encounter any problem with the Docker containers, you can clean them:

```console
$ make docker-clean
```

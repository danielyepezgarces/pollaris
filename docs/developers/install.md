# Setup the development environment

## Setup Docker

The development environment is managed with Docker by default.

First, make sure to install [Docker Engine](https://docs.docker.com/engine/install/).
The `docker` command must be executable by your normal user.

## Install the project

Clone the repository:

```console
$ git clone git@framagit.org:pollaris/pollaris.git
$ # or git clone https://framagit.org/pollaris/pollaris.git
```

Install the dependencies:

```console
$ make install
```

Start the development server:

```console
$ make docker-start
```

Setup the database:

```console
$ make db-setup
```

Open [localhost:8000](http://localhost:8000).

You can run Nginx on a specific port:

```console
$ make docker-start PORT=8080
```

A note about the `make` commands: they might feel magic, but they are not!
They are just shortcuts for common commands.
If you want to know what they do, you can open the [Makefile](/Makefile) and locates the command that you are interested in.

## Working in the Docker containers

As the environment runs in Docker, you cannot run the `php` (or the others) directly.
There are few scripts to allow to execute commands in the Docker containers easily:

```console
$ ./docker/bin/php
$ ./docker/bin/composer
$ ./docker/bin/npm
$ ./docker/bin/psql
```

## Reset the database

When developing, you may want to reset the database.
You can do it with the following command:

```console
$ make db-reset FORCE=true
```

You need to pass the `FORCE` argument, or the command will not be executed.

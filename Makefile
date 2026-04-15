# This file is part of Pollaris.
# Copyright 2024-2026 Marien Fressinaud
# SPDX-License-Identifier: AGPL-3.0-or-later

.DEFAULT_GOAL := help

USER = $(shell id -u):$(shell id -g)

DOCKER_COMPOSE = docker compose -f docker/development/docker-compose.yml

ifdef NODOCKER
	PHP = php
	COMPOSER = composer
	CONSOLE = php bin/console
	NPM = npm
else
	PHP = ./docker/bin/php
	COMPOSER = ./docker/bin/composer
	CONSOLE = ./docker/bin/php bin/console
	NPM = ./docker/bin/npm
endif

.PHONY: docker-start
docker-start: PORT ?= 8000
docker-start: PORT_MAILPIT ?= 8025
docker-start: ## Start a development server (can take a PORT and PORT_MAILPIT arguments)
	@echo "Running webserver on http://localhost:$(PORT)"
	$(DOCKER_COMPOSE) up

.PHONY: docker-build
docker-build: ## Rebuild the Docker development images
	$(DOCKER_COMPOSE) build --pull

.PHONY: docker-pull
docker-pull: ## Pull the Docker images from the Docker Hub
	$(DOCKER_COMPOSE) pull --ignore-buildable

.PHONY: docker-clean
docker-clean: ## Clean the Docker stuff
	$(DOCKER_COMPOSE) down -v

.PHONY: install
install: INSTALLER ?= all
install: ## Install the dependencies (can take an INSTALLER argument)
ifeq ($(INSTALLER), $(filter $(INSTALLER), all composer))
	$(COMPOSER) install
endif
ifeq ($(INSTALLER), $(filter $(INSTALLER), all npm))
	$(NPM) install
endif

.PHONY: db-setup
db-setup: ## Setup the database
	$(CONSOLE) doctrine:database:create
	$(CONSOLE) doctrine:migrations:migrate --no-interaction

.PHONY: db-migrate
db-migrate: ## Migrate the database
	$(CONSOLE) doctrine:migrations:migrate --no-interaction

.PHONY: db-rollback
db-rollback: ## Rollback the database to the previous version
	$(CONSOLE) doctrine:migrations:migrate --no-interaction prev

.PHONY: db-reset
db-reset: ## Reset the database (take a FORCE argument)
ifndef FORCE
	$(error Please run the operation with FORCE=true)
endif
ifndef NODOCKER
	$(DOCKER_COMPOSE) stop worker
endif
	$(CONSOLE) doctrine:database:drop --force --if-exists
	$(CONSOLE) doctrine:database:create
	$(CONSOLE) doctrine:migrations:migrate --no-interaction
	$(CONSOLE) cache:clear
ifndef NODOCKER
	$(DOCKER_COMPOSE) start worker
endif

.PHONY: migration
migration: ## Generate a database migration from entities changes
	$(CONSOLE) make:migration --formatted

.PHONY: translations
translations: ## Update the translations from the code
	$(CONSOLE) translation:extract --format=yaml --force --clean --sort=asc en_GB
	$(CONSOLE) translation:extract --format=yaml --force --clean --sort=asc fr_FR

.PHONY: icons
icons: ## Build the icons asset
	$(NPM) run build:icons

.PHONY: test
test: FILE ?= ./tests
ifdef FILTER
test: override FILTER := --filter=$(FILTER)
endif
test: COVERAGE ?= --coverage-html ./coverage
test: ## Run the test suite (can take FILE, FILTER and COVERAGE arguments)
	$(PHP) ./vendor/bin/phpunit \
		-c .phpunit.xml.dist \
		$(COVERAGE) \
		$(FILTER) \
		$(FILE)

.PHONY: lint
lint: LINTER ?= all
lint: ## Execute the linters (can take a LINTER argument)
ifeq ($(LINTER), $(filter $(LINTER), all phpstan))
	$(PHP) vendor/bin/phpstan analyse --memory-limit 512M -c .phpstan.neon
endif
ifeq ($(LINTER), $(filter $(LINTER), all rector))
	$(PHP) vendor/bin/rector process --dry-run --config .rector.php
endif
ifeq ($(LINTER), $(filter $(LINTER), all phpcs))
	$(PHP) vendor/bin/phpcs
endif
ifeq ($(LINTER), $(filter $(LINTER), all container))
	$(CONSOLE) lint:container
endif

.PHONY: lint-fix
lint-fix: LINTER ?= all
lint-fix: ## Fix the errors detected by the linters (can take a LINTER argument)
ifeq ($(LINTER), $(filter $(LINTER), all rector))
	$(PHP) vendor/bin/rector process --config .rector.php
endif
ifeq ($(LINTER), $(filter $(LINTER), all phpcs))
	$(PHP) vendor/bin/phpcbf
endif

.PHONY: release
release: ## Release a new version (take a VERSION argument)
ifndef VERSION
	$(error You need to provide a "VERSION" argument)
endif
	echo $(VERSION) > VERSION.txt
	rm -rf public/assets
	$(NPM) run build
	$(EDITOR) CHANGELOG.md
	git add .
	git commit -m "release: Publish version $(VERSION)"
	git tag -a $(VERSION) -m "Release version $(VERSION)"

.PHONY: help
help:
	@grep -h -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | sort | awk 'BEGIN {FS = ":.*?## "}; {printf "\033[36m%-30s\033[0m %s\n", $$1, $$2}'

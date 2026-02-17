COMPOSE := docker compose

.PHONY: help up down rebuild logs bash composer-install migrate seed test test-stable test-circulation-stable artisan

help:
	@echo "Makefile targets:"
	@echo "  make up                # Start containers (detached)"
	@echo "  make down              # Stop and remove containers and volumes"
	@echo "  make rebuild           # Rebuild and start containers"
	@echo "  make logs              # Follow container logs"
	@echo "  make bash              # Get shell in app container"
	@echo "  make composer-install  # Run composer install in app container"
	@echo "  make migrate           # Run artisan migrate --force"
	@echo "  make seed              # Run artisan db:seed"
	@echo "  make test              # Run test suite"
	@echo "  make test-stable       # Recreate testing DB + run full suite sequentially"
	@echo "  make test-circulation-stable # Recreate testing DB + run circulation smoke suite"
	@echo "  make artisan cmd='route:list'  # Run artisan command"

up:
	$(COMPOSE) up -d --build

down:
	$(COMPOSE) down -v

rebuild:
	$(COMPOSE) up -d --build --remove-orphans

logs:
	$(COMPOSE) logs -f

bash:
	$(COMPOSE) exec app bash

composer-install:
	$(COMPOSE) run --rm app composer install --no-interaction --prefer-dist

migrate:
	$(COMPOSE) exec app php artisan migrate --force

seed:
	$(COMPOSE) exec app php artisan db:seed

test:
	$(COMPOSE) exec app composer test

test-stable:
	$(COMPOSE) exec app composer run test:stable

test-circulation-stable:
	$(COMPOSE) exec app composer run test:circulation:stable

artisan:
	@if [ -z "$(cmd)" ]; then \
		echo "Usage: make artisan cmd='migrate'"; exit 1; \
	fi
	$(COMPOSE) exec app php artisan $(cmd)

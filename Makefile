# eShop Docker/Laravel Development Shortcuts

.PHONY: up down restart ps shell migrate migrate-fresh seed seed-products swagger-gen test test-api test-single queue-work queue-work-bg import-products clear-cache

# --- Docker Container Management ---

# Start all Docker containers in background
up:
	docker compose up -d

# Stop all Docker containers
down:
	docker compose down

# Restart Nginx and PHP containers
restart:
	docker compose restart web php

# View active containers status
ps:
	docker compose ps

# Enter the PHP container interactive bash shell
shell:
	docker compose exec php bash


# --- Laravel Database Operations ---

# Run standard pending migrations
migrate:
	docker compose exec php php artisan migrate

# Reset database and seed all initial data
migrate-fresh:
	docker compose exec php php artisan migrate:fresh --seed

# Run general database seeders
seed:
	docker compose exec php php artisan db:seed

# Seed only the Product catalog data
seed-products:
	docker compose exec php php artisan db:seed --class=ProductSeeder


# --- Swagger API Documentation ---

# Regenerate Swagger OpenAPI JSON and HTML documentation
swagger-gen:
	docker compose exec php php artisan l5-swagger:generate


# --- Testing Suites ---

# Run all PHPUnit feature and unit tests
test:
	docker compose exec php php artisan test

# Run only the API feature tests
test-api:
	docker compose exec php php artisan test tests/Feature/Api

# Run a single test file (Usage: make test-single file=tests/Feature/Api/AuthControllerTest.php)
test-single:
	@if [ -z "$(file)" ]; then \
		echo "Error: Please specify the test file path using 'file=path/to/test.php'"; \
		exit 1; \
	fi
	docker compose exec php php artisan test $(file)


# --- RabbitMQ Queue Management ---

# Start RabbitMQ queue worker (foreground)
queue-work:
	docker compose exec php php artisan queue:work rabbitmq

# Start RabbitMQ queue worker (background/async)
queue-work-bg:
	docker compose exec -d php php artisan queue:work rabbitmq


# --- System Utilities ---

# Clear all Laravel caches (config, cache, route, view)
clear-cache:
	docker compose exec php php artisan optimize:clear


# --- Product Import Commands ---

# Bulk import products from CSV files (Usage: make import-products files="path/to/file.csv")
import-products:
	@if [ -z "$(files)" ]; then \
		echo "Error: Please specify the CSV file path(s) using 'files=\"path1.csv path2.csv\"'"; \
		exit 1; \
	fi
	docker compose exec php php artisan products:import $(files)

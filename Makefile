.PHONY: generate-proto install test clean docker-build docker-up docker-down docker-shell docker-generate docker-test

# Генерация proto файлов
generate-proto:
	@echo "Generating PHP classes from proto files..."
	@./generate-proto.sh

# Установка зависимостей
install:
	@echo "Installing dependencies..."
	@composer install

# Запуск тестов
test:
	@echo "Running tests..."
	@composer test

# Очистка
clean:
	@echo "Cleaning generated files..."
	@rm -rf src/Generated/
	@rm -rf vendor/
	@rm -f composer.lock

# Обновление зависимостей
update:
	@echo "Updating dependencies..."
	@composer update

# Docker команды

# Сборка Docker образа для разработки
docker-build:
	@echo "Building PHP dev Docker image..."
	@docker-compose build php-dev

# Запуск контейнеров
docker-up:
	@echo "Starting Docker containers..."
	@docker-compose up -d

# Запуск с Auth Service
docker-up-full:
	@echo "Starting Docker containers with Auth Service..."
	@docker-compose --profile with-service up -d

# Остановка контейнеров
docker-down:
	@echo "Stopping Docker containers..."
	@docker-compose down

# Вход в контейнер PHP dev
docker-shell:
	@echo "Entering PHP dev container..."
	@docker-compose exec php-dev bash

# Генерация proto классов в Docker
docker-generate:
	@echo "Generating proto classes in Docker..."
	@docker-compose exec php-dev make generate-proto

# Запуск тестов в Docker
docker-test:
	@echo "Running tests in Docker..."
	@docker-compose exec php-dev make test

# Установка зависимостей в Docker
docker-install:
	@echo "Installing dependencies in Docker..."
	@docker-compose exec php-dev composer install


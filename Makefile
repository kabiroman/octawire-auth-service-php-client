.PHONY: install test clean update docker-build docker-up docker-down docker-shell docker-test docker-install proto-note

# Информация о proto-моделях (TCP клиент не генерирует код)
proto-note:
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
	@docker compose build php-dev

# Запуск контейнеров
docker-up:
	@echo "Starting Docker containers..."
	@docker compose up -d

# Запуск с Auth Service
docker-up-full:
	@echo "Starting Docker containers with Auth Service..."
	@docker compose --profile with-service up -d

# Остановка контейнеров
docker-down:
	@echo "Stopping Docker containers..."
	@docker compose down

# Вход в контейнер PHP dev
docker-shell:
	@echo "Entering PHP dev container..."
	@docker compose exec php-dev bash

# Запуск тестов в Docker
docker-test:
	@echo "Running tests in Docker..."
	@docker compose exec php-dev make test

# Установка зависимостей в Docker
docker-install:
	@echo "Installing dependencies in Docker..."
	@docker compose exec php-dev composer install


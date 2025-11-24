.PHONY: generate-proto install test clean

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


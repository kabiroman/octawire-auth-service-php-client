# Docker окружение для разработки PHP клиента

Этот Docker Compose файл предоставляет изолированное окружение для разработки PHP клиента с всеми необходимыми зависимостями.

## Что включено

- **PHP 8.1** с gRPC и protobuf extensions
- **protoc** и **grpc_php_plugin** для генерации proto классов
- **Composer** для управления зависимостями
- **Redis** для тестирования кэширования (опционально)
- **Auth Service** для тестирования (опционально, через profile)

## Быстрый старт

### 1. Сборка образа

```bash
make docker-build
```

Или напрямую:

```bash
docker-compose build php-dev
```

**⚠️ Важно:** Первая сборка может занять **20-30 минут**, так как:
- `pecl install grpc` компилирует gRPC extension из исходников (~15-20 минут)
- Компиляция `grpc_php_plugin` из исходников gRPC (~5-10 минут)

**Что делать если сборка долго идет:**
1. **Подождать** - это нормально для первой сборки. Компиляция gRPC из исходников занимает время.
2. **Не прерывать** - если процесс идет (видно вывод компиляции), лучше дождаться завершения.
3. **Использовать кэш** - последующие сборки будут быстрее благодаря Docker layer cache.
4. **Альтернатива** - можно использовать локальную установку gRPC extension (см. основной README.md)

**Текущий статус:** Если видите вывод компиляции (g++ команды), процесс идет нормально.

### 2. Запуск контейнеров

```bash
# Только PHP dev контейнер и Redis
make docker-up

# С Auth Service (если нужно тестировать с реальным сервисом)
make docker-up-full
```

### 3. Вход в контейнер

```bash
make docker-shell
```

Или:

```bash
docker-compose exec php-dev bash
```

### 4. Генерация proto классов

Внутри контейнера или снаружи:

```bash
# Внутри контейнера
make docker-generate

# Или снаружи
docker-compose exec php-dev make generate-proto
```

### 5. Установка зависимостей

```bash
make docker-install
```

### 6. Запуск тестов

```bash
make docker-test
```

## Доступные команды

### Make команды

- `make docker-build` - Сборка Docker образа
- `make docker-up` - Запуск контейнеров (PHP dev + Redis)
- `make docker-up-full` - Запуск с Auth Service
- `make docker-down` - Остановка контейнеров
- `make docker-shell` - Вход в контейнер PHP dev
- `make docker-generate` - Генерация proto классов
- `make docker-test` - Запуск тестов
- `make docker-install` - Установка зависимостей

### Docker Compose команды

```bash
# Запуск только PHP dev и Redis
docker-compose up -d

# Запуск с Auth Service
docker-compose --profile with-service up -d

# Просмотр логов
docker-compose logs -f php-dev

# Остановка
docker-compose down

# Пересборка образа
docker-compose build --no-cache php-dev
```

## Структура volumes

- `.` → `/app` - Код PHP клиента
- `../..` → `/workspace` - Проект auth-service (для доступа к proto файлам)
- `composer-cache` - Кэш Composer (persistent volume)

## Использование

### Генерация proto классов

```bash
# В контейнере
cd /app
./generate-proto.sh

# Или через make
make generate-proto
```

### Запуск тестов

```bash
# В контейнере
composer test

# Или через make
make test
```

### Запуск примеров

```bash
# В контейнере
php examples/basic.php
```

### Подключение к Auth Service

Если Auth Service запущен локально (не в Docker), он доступен по адресу `host.docker.internal:50051` (на macOS/Windows) или через сеть Docker.

Если Auth Service запущен в Docker (через `--profile with-service`), он доступен по адресу `auth-service:50051` из контейнера PHP dev.

## Troubleshooting

### Ошибка при сборке grpc_php_plugin

Если сборка падает, попробуйте:

```bash
docker-compose build --no-cache php-dev
```

### Проблемы с правами доступа

Если возникают проблемы с правами на файлы:

```bash
# Внутри контейнера
chown -R $(id -u):$(id -g) /app
```

### Очистка

```bash
# Остановить и удалить контейнеры
docker-compose down

# Удалить volumes (включая composer cache)
docker-compose down -v

# Удалить образ
docker rmi octawire-php-client-dev
```

## Альтернатива: использование локального окружения

Если вы предпочитаете работать локально (без Docker), вам нужно:

1. Установить PHP 8.1+ с gRPC extension
2. Установить protoc
3. Скомпилировать grpc_php_plugin (см. основной README.md)

Docker окружение избавляет от необходимости выполнять эти шаги вручную.


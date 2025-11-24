# PHP Client для Auth Service

PHP клиент для работы с Auth Service (v0.9.1) через gRPC.

**Репозиторий:** [https://github.com/kabiroman/octawire-auth-service-php-client](https://github.com/kabiroman/octawire-auth-service-php-client)

## Требования

- **PHP 8.1+** (минимальная версия, обязательное требование)
- gRPC extension для PHP
- Composer
- protoc и grpc_php_plugin (для генерации proto классов)

## Установка

### Вариант 1: Docker (рекомендуется для разработки)

Используйте Docker окружение для изоляции всех зависимостей:

```bash
# Сборка образа
make docker-build

# Запуск контейнеров
make docker-up

# Вход в контейнер
make docker-shell

# Генерация proto классов
make docker-generate
```

Подробнее см. [README.docker.md](./README.docker.md)

### Вариант 2: Локальная установка

```bash
composer require kabiroman/octawire-auth-service-php-client
```

## Генерация proto классов

Перед использованием клиента необходимо сгенерировать PHP классы из proto файлов:

```bash
# Убедитесь, что установлены protoc и grpc_php_plugin
# Затем выполните:
make generate-proto

# Или напрямую:
./generate-proto.sh
```

Сгенерированные классы будут находиться в `src/Generated/`.

## Быстрый старт

```php
<?php

use Kabiroman\Octawire\AuthService\Client\Config;
use Kabiroman\Octawire\AuthService\Client\AuthClient;

require_once __DIR__ . '/vendor/autoload.php';

// Создаем конфигурацию
$config = new Config([
    'address' => 'localhost:50051',
    'project_id' => 'your-project-id',
]);

// Создаем клиент
$client = new AuthClient($config);

// Выдаем токен
$response = $client->issueToken([
    'user_id' => 'user-123',
    'claims' => ['role' => 'admin'],
    'access_token_ttl' => 3600,
    'refresh_token_ttl' => 86400,
]);

echo "Access Token: " . $response->getAccessToken() . "\n";
```

## Конфигурация

### Базовая конфигурация

```php
$config = new Config([
    'address' => 'localhost:50051',
    'project_id' => 'default-project-id',
    'api_key' => 'your-api-key', // Опционально
]);
```

### TLS/mTLS конфигурация

```php
$config = new Config([
    'address' => 'auth-service.example.com:50051',
    'tls' => [
        'enabled' => true,
        'ca_file' => '/path/to/ca.crt',
        'cert_file' => '/path/to/client.crt', // Для mTLS
        'key_file' => '/path/to/client.key', // Для mTLS
        'server_name' => 'auth-service.example.com',
    ],
]);
```

### Retry конфигурация

```php
$config = new Config([
    'address' => 'localhost:50051',
    'retry' => [
        'max_attempts' => 3,
        'initial_backoff' => 0.1, // секунды
        'max_backoff' => 5.0, // секунды
    ],
]);
```

### Кэш ключей

```php
$config = new Config([
    'address' => 'localhost:50051',
    'key_cache' => [
        'ttl' => 3600, // секунды
        'max_size' => 100, // Максимальное количество проектов в кэше
        'driver' => 'memory', // 'memory' или 'redis'
    ],
]);
```

## Использование

### JWT Service методы

#### IssueToken - Выдача токена

```php
$response = $client->issueToken([
    'user_id' => 'user-123',
    'claims' => ['role' => 'admin'],
    'access_token_ttl' => 3600,
    'refresh_token_ttl' => 86400,
]);
```

#### ValidateToken - Валидация токена

```php
$response = $client->validateToken([
    'token' => 'jwt-token',
    'check_blacklist' => true,
]);

if ($response->isValid()) {
    $claims = $response->getClaims();
    // ...
}
```

#### RefreshToken - Обновление токена

```php
$response = $client->refreshToken([
    'refresh_token' => 'refresh-token',
]);
```

#### GetPublicKey - Получение публичного ключа (с кэшированием)

```php
$response = $client->getPublicKey([
    'project_id' => 'project-id',
    'key_id' => 'key-id', // Опционально
]);
```

Метод автоматически кэширует ключи и использует кэш при повторных запросах.

### API Key Service методы

#### CreateAPIKey - Создание API ключа

```php
$response = $client->createAPIKey([
    'project_id' => 'project-id',
    'name' => 'My API Key',
    'scopes' => ['read', 'write'],
    'ttl' => 86400 * 30, // 30 дней
]);
```

#### ValidateAPIKey - Валидация API ключа

```php
$response = $client->validateAPIKey([
    'api_key' => 'api-key',
    'required_scopes' => ['read'],
]);
```

#### ListAPIKeys - Список API ключей

```php
$response = $client->listAPIKeys([
    'project_id' => 'project-id',
    'user_id' => 'user-id', // Опционально
    'page' => 1,
    'page_size' => 10,
]);
```

#### RevokeAPIKey - Отзыв API ключа

```php
$response = $client->revokeAPIKey([
    'key_id' => 'key-id',
    'project_id' => 'project-id',
]);
```

## Кэширование публичных ключей

Клиент автоматически кэширует публичные ключи для оптимизации производительности. Кэш поддерживает **graceful key rotation** - хранение нескольких активных ключей одновременно.

### Graceful Key Rotation

При ротации ключей сервер возвращает список всех активных ключей в поле `active_keys`. Клиент автоматически кэширует все активные ключи, что позволяет валидировать токены, подписанные как старыми, так и новыми ключами во время ротации.

**Преимущества:**
- Нет простоя при ротации
- Токены, подписанные старым ключом, остаются валидными
- Плавный переход на новый ключ
- Автоматическая очистка устаревших ключей

**Рекомендации по использованию:**
- Кэшировать все ключи из `active_keys`
- Проверять `cache_until` и периодически обновлять список ключей
- При валидации проверять подпись всеми активными ключами (по `key_id` из токена)

### Управление кэшем

```php
// Получить кэш
$keyCache = $client->getKeyCache();

// Получить все активные ключи для проекта
$activeKeys = $keyCache->getAllActive('project-id');

// Инвалидировать кэш для проекта
$keyCache->invalidate('project-id');

// Очистить весь кэш
$keyCache->clear();

// Очистить истекшие ключи
$keyCache->cleanupExpired();
```

## Retry логика

Клиент автоматически повторяет запросы при временных ошибках (Unavailable, DeadlineExceeded, ResourceExhausted) с экспоненциальным backoff и jitter.

Настройки retry:

```php
$config = new Config([
    'retry' => [
        'max_attempts' => 3,                      // Максимум попыток
        'initial_backoff' => 0.1,                 // Начальная задержка (секунды)
        'max_backoff' => 5.0,                     // Максимальная задержка (секунды)
    ],
]);
```

## Обработка ошибок

Клиент использует кастомные исключения для обработки ошибок:

```php
use Kabiroman\Octawire\AuthService\Client\Exception\InvalidTokenException;
use Kabiroman\Octawire\AuthService\Client\Exception\TokenExpiredException;
use Kabiroman\Octawire\AuthService\Client\Exception\TokenRevokedException;
use Kabiroman\Octawire\AuthService\Client\Exception\ConnectionException;
use Kabiroman\Octawire\AuthService\Client\Exception\RateLimitException;

try {
    $response = $client->validateToken(['token' => $token]);
} catch (InvalidTokenException $e) {
    // Токен невалиден
} catch (TokenExpiredException $e) {
    // Токен истек
} catch (TokenRevokedException $e) {
    // Токен отозван
} catch (ConnectionException $e) {
    // Ошибка подключения
} catch (RateLimitException $e) {
    // Превышен лимит запросов
    echo "Limit: " . $e->getLimit() . "\n";
    echo "Remaining: " . $e->getRemaining() . "\n";
}
```

## Работа с несколькими проектами

Клиент поддерживает работу с несколькими проектами. Вы можете указать `project_id` в конфигурации (для всех запросов) или в каждом запросе отдельно:

```php
// Дефолтный проект из конфигурации
$config = new Config([
    'address' => 'localhost:50051',
    'project_id' => 'default-project-id',
]);
$client = new AuthClient($config);

// Использование дефолтного проекта
$response = $client->issueToken([
    'user_id' => 'user-123',
    // project_id не указан, используется из конфигурации
]);

// Использование другого проекта
$response = $client->issueToken([
    'user_id' => 'user-123',
    'project_id' => 'another-project-id',
]);
```

## Примеры

Полные примеры использования находятся в директории `examples/`:

- `examples/basic.php` - базовое использование
- `examples/tls.php` - использование с TLS/mTLS
- `examples/caching.php` - демонстрация кэширования ключей и graceful ротации
- `examples/multiproject.php` - работа с несколькими проектами

## Тестирование

```bash
# Установить зависимости
composer install

# Запустить тесты
composer test

# Или напрямую
vendor/bin/phpunit
```

## Особенности реализации

- **PHP 8.1+** - использование типизированных свойств и возвращаемых значений
- **Readonly свойства** - где возможно (PHP 8.2+)
- **PSR-4 автозагрузка** - стандартная структура namespace
- **In-memory кэш** - для PHP-FPM (локальный кэш на каждый процесс)
- **Redis кэш** - опционально, для долгоживущих процессов
- **Обработка ошибок** - через исключения (try/catch)

## Лицензия

MIT License

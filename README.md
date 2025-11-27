# PHP Client для Auth Service

PHP клиент для работы с Auth Service (v0.9.1) через JATP (TCP/JSON протокол).

**Репозиторий:** [https://github.com/kabiroman/octawire-auth-service-php-client](https://github.com/kabiroman/octawire-auth-service-php-client)

**Соответствие спецификации:** Клиент полностью соответствует спецификации JATP_METHODS_1.0.json и обрабатывает все коды ошибок, определенные в спецификации.

## Требования

- **PHP 8.1+** (минимальная версия, обязательное требование)
- **ext-json** (стандартное расширение PHP)
- **ext-sockets** (стандартное расширение PHP)
- Composer

> **Важно:** Клиент использует TCP/JSON транспорт (JATP протокол), **не требует gRPC extension**.

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

> **Примечание:** Генерация proto классов больше не требуется, так как клиент использует TCP/JSON транспорт и работает напрямую с JSON.

## Быстрый старт

```php
<?php

use Kabiroman\Octawire\AuthService\Client\Config;
use Kabiroman\Octawire\AuthService\Client\AuthClient;

require_once __DIR__ . '/vendor/autoload.php';

// Создаем конфигурацию для TCP (JATP)
$config = new Config([
    'transport' => 'tcp',
    'address' => 'localhost:50052', // TCP port (по умолчанию 50052)
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

echo "Access Token: " . ($response['access_token'] ?? 'N/A') . "\n";
```

## Конфигурация

### Базовая конфигурация

```php
$config = new Config([
    'transport' => 'tcp',
    'address' => 'localhost:50052', // TCP port
    'project_id' => 'default-project-id',
    'api_key' => 'your-api-key', // Опционально (для JWT аутентификации)
]);
```

### TCP конфигурация с TLS/mTLS

```php
$config = new Config([
    'transport' => 'tcp',
    'tcp' => [
        'host' => 'auth-service.example.com',
        'port' => 50052,
        'tls' => [
            'enabled' => true,
            'ca_file' => '/path/to/ca.crt',
            'cert_file' => '/path/to/client.crt', // Для mTLS
            'key_file' => '/path/to/client.key', // Для mTLS
            'server_name' => 'auth-service.example.com',
        ],
        'persistent' => true, // Переиспользование соединений
    ],
    'project_id' => 'default-project-id',
]);
```

### Retry конфигурация

```php
$config = new Config([
    'transport' => 'tcp',
    'address' => 'localhost:50052',
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
    'transport' => 'tcp',
    'address' => 'localhost:50052',
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

## Service Authentication

Service Authentication используется для межсервисной аутентификации при вызове `IssueServiceToken`. Это дополнительный слой защиты поверх TLS/mTLS.

### Настройка

Service secret можно указать в конфигурации или передать при вызове метода:

```php
// Вариант 1: В конфигурации
$config = new Config([
    'transport' => 'tcp',
    'tcp' => [
        'host' => 'auth-service.example.com',
        'port' => 50052,
        'tls' => [
            'enabled' => true,
            'ca_file' => '/path/to/ca.crt',
        ],
    ],
    'project_id' => 'default-project-id',
    'service_secret' => 'identity-service-secret-abc123def456', // Опционально
]);

// Вариант 2: При вызове метода
$request = new IssueServiceTokenRequest(
    sourceService: 'identity-service',
    targetService: 'gateway-service',
    ttl: 3600,
);
$response = $client->issueServiceToken($request, 'identity-service-secret-abc123def456');
```

### Использование

```php
use Kabiroman\Octawire\AuthService\Client\Request\JWT\IssueServiceTokenRequest;

try {
    $request = new IssueServiceTokenRequest(
        sourceService: 'identity-service',
        targetService: 'gateway-service',
        userId: 'service-user', // Опционально
        claims: ['service' => 'identity-service'], // Опционально
        ttl: 3600, // Опционально
    );
    
    // Service secret можно передать как параметр или использовать из конфигурации
    $response = $client->issueServiceToken($request, 'identity-service-secret-abc123def456');
    
    echo "Service Token: " . substr($response->accessToken, 0, 50) . "...\n";
} catch (AuthException $e) {
    // Обработка AUTH_FAILED ошибки для service authentication
    if ($e->getErrorCode() === 'AUTH_FAILED') {
        error_log("Invalid service credentials: " . $e->getMessage());
        return;
    }
    error_log("Failed to issue service token: " . $e->getMessage());
}
```

### Обработка ошибок

При неудачной валидации service credentials сервер возвращает ошибку `AUTH_FAILED`:

```php
try {
    $response = $client->issueServiceToken($request, $serviceSecret);
} catch (AuthException $e) {
    if ($e->getErrorCode() === 'AUTH_FAILED') {
        // Неверный service_name или service_secret
        error_log("Service authentication failed: " . $e->getMessage());
    }
}
```

### Безопасное хранение секретов

**Важно:**
- Не храните `service_secret` в коде или конфигурациях
- Используйте переменные окружения и secrets manager
- Ротируйте секреты и используйте разные значения для окружений
- Отзывайте скомпрометированные секреты немедленно

**Рекомендации:**
```php
// Используйте переменные окружения
$config = new Config([
    'transport' => 'tcp',
    'tcp' => [
        'host' => 'auth-service.example.com',
        'port' => 50052,
    ],
    'project_id' => 'default-project-id',
    'service_secret' => $_ENV['IDENTITY_SERVICE_SECRET'] ?? null,
]);
```

### Кейсы подключения

Клиент поддерживает 4 кейса подключения согласно конфигурациям сервиса:

1. **PROD + service_auth=false**: TLS обязателен (tcp.tls.enabled=true, tcp.tls.required=true)
2. **PROD + service_auth=true**: TLS обязателен + service authentication
3. **DEV + service_auth=false**: TLS опционален (tcp.tls.enabled=false)
4. **DEV + service_auth=true**: TLS опционален + service authentication

Примеры для каждого кейса см. в разделе [Примеры подключения](#примеры-подключения).

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

Клиент автоматически повторяет запросы при временных ошибках (connection errors, timeouts, ERROR_INTERNAL) с экспоненциальным backoff и jitter.

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
    'transport' => 'tcp',
    'address' => 'localhost:50052',
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

## Примеры подключения

Клиент поддерживает различные сценарии подключения в зависимости от окружения и настроек сервиса:

### 1. PROD + service_auth=false (TLS обязателен, без service auth)

```php
$config = new Config([
    'transport' => 'tcp',
    'tcp' => [
        'host' => 'auth-service.example.com',
        'port' => 50052,
        'tls' => [
            'enabled' => true,
            'required' => true,
            'ca_file' => '/path/to/ca.crt',
            'server_name' => 'auth-service.example.com',
        ],
    ],
    'project_id' => 'default-project-id',
]);
```

### 2. PROD + service_auth=true (TLS обязателен, с service auth)

```php
$config = new Config([
    'transport' => 'tcp',
    'tcp' => [
        'host' => 'auth-service.example.com',
        'port' => 50052,
        'tls' => [
            'enabled' => true,
            'required' => true,
            'ca_file' => '/path/to/ca.crt',
            'cert_file' => '/path/to/client.crt', // для mTLS
            'key_file' => '/path/to/client.key', // для mTLS
            'server_name' => 'auth-service.example.com',
        ],
    ],
    'project_id' => 'default-project-id',
    'service_secret' => $_ENV['IDENTITY_SERVICE_SECRET'], // для service authentication
]);
```

### 3. DEV + service_auth=false (TLS опционален, без service auth)

```php
$config = new Config([
    'transport' => 'tcp',
    'tcp' => [
        'host' => 'localhost',
        'port' => 50052,
        'tls' => [
            'enabled' => false, // TLS опционален в DEV
        ],
    ],
    'project_id' => 'default-project-id',
]);
```

### 4. DEV + service_auth=true (TLS опционален, с service auth)

```php
$config = new Config([
    'transport' => 'tcp',
    'tcp' => [
        'host' => 'localhost',
        'port' => 50052,
        'tls' => [
            'enabled' => false, // TLS опционален в DEV
        ],
    ],
    'project_id' => 'default-project-id',
    'service_secret' => 'dev-service-secret-abc123', // для service authentication
]);
```

## Примеры

Полные примеры использования находятся в директории `examples/`:

- `examples/basic.php` - базовое использование
- `examples/tcp.php` - полный пример использования TCP/JATP транспорта
- `examples/tls.php` - использование с TLS/mTLS (обновлен для tcp.tls формата)
- `examples/caching.php` - демонстрация кэширования ключей и graceful ротации
- `examples/multiproject.php` - работа с несколькими проектами
- `examples/connection-scenarios.php` - примеры для всех 4 кейсов подключения

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

- **TCP/JSON транспорт** - использует стандартные PHP расширения (sockets, json), не требует gRPC
- **JATP протокол** - JSON-over-TCP с newline-delimited форматом
- **PHP 8.1+** - использование типизированных свойств и возвращаемых значений
- **Readonly свойства** - где возможно (PHP 8.2+)
- **PSR-4 автозагрузка** - стандартная структура namespace
- **Persistent connections** - переиспользование TCP соединений для производительности
- **In-memory кэш** - для PHP-FPM (локальный кэш на каждый процесс)
- **Redis кэш** - опционально, для долгоживущих процессов
- **Обработка ошибок** - через исключения (try/catch) с маппингом JATP error codes
- **Retry логика** - автоматический retry с exponential backoff для временных ошибок

## Лицензия

MIT License

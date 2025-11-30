# PHP Client для Auth Service

PHP клиент для работы с Auth Service (v1.0) через JATP (TCP/JSON протокол).

**Репозиторий:** [https://github.com/kabiroman/octawire-auth-service-php-client](https://github.com/kabiroman/octawire-auth-service-php-client)

**Соответствие спецификации:** Клиент полностью соответствует спецификации JATP_METHODS_1.0 и обрабатывает все коды ошибок, определенные в спецификации.

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
use Kabiroman\Octawire\AuthService\Client\Request\JWT\IssueTokenRequest;

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
$request = new IssueTokenRequest(
    userId: 'user-123',
    projectId: 'your-project-id', // Обязательное поле (v0.9.3+)
    claims: ['role' => 'admin'],
    accessTokenTtl: 3600,
    refreshTokenTtl: 86400,
);
$response = $client->issueToken($request);

echo "Access Token: " . $response->accessToken . "\n";
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

## Типы аутентификации (v1.0+)

### Публичные методы

Следующие методы не требуют аутентификации:
- `IssueToken` - выдача токенов
- `RefreshToken` - обновление токенов
- `GetPublicKey` - получение публичного ключа
- `HealthCheck` - проверка здоровья сервиса

### Методы с опциональной service authentication (v1.0+)

Следующие методы поддерживают опциональную service authentication:
- `IssueServiceToken` - service auth опциональна (рекомендуется для production)
- `ValidateToken` - service auth опциональна, или публичный (без аутентификации, особенно для localhost)
- `ParseToken` - service auth опциональна, или публичный (без аутентификации, особенно для localhost)
- `ExtractClaims` - service auth опциональна, или публичный (без аутентификации, особенно для localhost)
- `ValidateBatch` - service auth опциональна, или публичный (без аутентификации, особенно для localhost)

**Важно (v1.0+):** Service authentication теперь опциональна для этих методов. Если `service_auth.enabled = true` на сервере, service authentication доступна но не обязательна (рекомендуется для production).

### Методы, требующие JWT токен

Следующие методы требуют JWT токен:
- `RevokeToken` - требует JWT (user revoking their own token)
- Все методы `APIKeyService.*` - требуют JWT (key management operations)

## Использование

### JWT Service методы

#### IssueToken - Выдача токена

```php
use Kabiroman\Octawire\AuthService\Client\Request\JWT\IssueTokenRequest;

$request = new IssueTokenRequest(
    userId: 'user-123',
    projectId: 'your-project-id', // Обязательное поле (v0.9.3+)
    claims: ['role' => 'admin'],
    accessTokenTtl: 3600,
    refreshTokenTtl: 86400,
);
$response = $client->issueToken($request);
```

#### ValidateToken - Валидация токена

**Authentication опциональна (v1.0+)** - можно использовать service auth или работать без аутентификации (особенно для localhost):

```php
use Kabiroman\Octawire\AuthService\Client\Request\JWT\ValidateTokenRequest;

// Вариант 1: С service authentication (рекомендуется для production)
$request = new ValidateTokenRequest(
    token: 'jwt-token',
    projectId: 'your-project-id', // Обязательное поле (v0.9.3+)
    checkBlacklist: true,
);
$response = $client->validateToken(
    $request,
    serviceName: 'gateway-service', // Опционально
    serviceSecret: 'gateway-service-secret' // Опционально
);

// Вариант 2: Без аутентификации (для localhost или если service_auth.enabled = false)
$response = $client->validateToken($request);

if ($response->valid) {
    $claims = $response->claims;
    // ...
}
```

**Примечание:** Токен в поле `token` - это токен, который валидируется, а не токен для аутентификации запроса.

#### RefreshToken - Обновление токена

```php
use Kabiroman\Octawire\AuthService\Client\Request\JWT\RefreshTokenRequest;

$request = new RefreshTokenRequest(
    refreshToken: 'refresh-token',
    projectId: 'your-project-id', // Обязательное поле (v0.9.3+)
);
$response = $client->refreshToken($request);
```

#### GetPublicKey - Получение публичного ключа (с кэшированием)

```php
use Kabiroman\Octawire\AuthService\Client\Request\JWT\GetPublicKeyRequest;

$request = new GetPublicKeyRequest(
    projectId: 'project-id',
    keyId: 'key-id', // Опционально
);

$response = $client->getPublicKey($request);
```

Метод автоматически кэширует ключи и использует кэш при повторных запросах.

### API Key Service методы

**Все методы APIKeyService требуют JWT токен** (JWTToken должен быть передан при вызове метода).

#### CreateAPIKey - Создание API ключа

```php
use Kabiroman\Octawire\AuthService\Client\Request\APIKey\CreateAPIKeyRequest;

$request = new CreateAPIKeyRequest(
    projectId: 'project-id',
    name: 'My API Key',
    scopes: ['read', 'write'],
    ttl: 86400 * 30, // 30 дней
);

$jwtToken = 'user-jwt-token'; // JWT токен пользователя
$response = $client->createAPIKey($request, jwtToken: $jwtToken);
```

#### ValidateAPIKey - Валидация API ключа

```php
use Kabiroman\Octawire\AuthService\Client\Request\APIKey\ValidateAPIKeyRequest;

$request = new ValidateAPIKeyRequest(
    apiKey: 'api-key',
    requiredScopes: ['read'],
);

$jwtToken = 'user-jwt-token';
$response = $client->validateAPIKey($request, jwtToken: $jwtToken);
```

#### ListAPIKeys - Список API ключей

```php
use Kabiroman\Octawire\AuthService\Client\Request\APIKey\ListAPIKeysRequest;

$request = new ListAPIKeysRequest(
    projectId: 'project-id',
    userId: 'user-id', // Опционально
    page: 1,
    pageSize: 10,
);

$jwtToken = 'user-jwt-token';
$response = $client->listAPIKeys($request, jwtToken: $jwtToken);
```

#### RevokeAPIKey - Отзыв API ключа

```php
use Kabiroman\Octawire\AuthService\Client\Request\APIKey\RevokeAPIKeyRequest;

$request = new RevokeAPIKeyRequest(
    keyId: 'key-id',
    projectId: 'project-id',
);

$jwtToken = 'user-jwt-token';
$response = $client->revokeAPIKey($request, jwtToken: $jwtToken);
```

#### ParseToken - Парсинг токена без валидации

**Authentication опциональна (v1.0+)**:

```php
use Kabiroman\Octawire\AuthService\Client\Request\JWT\ParseTokenRequest;

$request = new ParseTokenRequest(
    token: 'jwt-token',
    projectId: 'your-project-id',
);

// Без аутентификации или с опциональной service auth
$response = $client->parseToken($request, serviceName: 'gateway-service', serviceSecret: 'secret');
```

#### ExtractClaims - Извлечение claims из токена

**Authentication опциональна (v1.0+)**:

```php
use Kabiroman\Octawire\AuthService\Client\Request\JWT\ExtractClaimsRequest;

$request = new ExtractClaimsRequest(
    token: 'jwt-token',
    projectId: 'your-project-id',
    claimKeys: ['user_id', 'role', 'email'],
);

// Без аутентификации или с опциональной service auth
$response = $client->extractClaims($request, serviceName: 'gateway-service', serviceSecret: 'secret');
```

#### ValidateBatch - Пакетная валидация токенов

**Authentication опциональна (v1.0+)**:

```php
use Kabiroman\Octawire\AuthService\Client\Request\JWT\ValidateBatchRequest;

$request = new ValidateBatchRequest(
    tokens: ['token1', 'token2', 'token3'],
    checkBlacklist: true,
);

// Без аутентификации или с опциональной service auth
$response = $client->validateBatch($request, serviceName: 'gateway-service', serviceSecret: 'secret');
```

#### IssueServiceToken - Выдача межсервисного токена

**Service authentication опциональна (v1.0+)**:

```php
use Kabiroman\Octawire\AuthService\Client\Request\JWT\IssueServiceTokenRequest;

$request = new IssueServiceTokenRequest(
    sourceService: 'identity-service',
    targetService: 'gateway-service',
    projectId: 'your-project-id',
    ttl: 3600,
);

// С service auth (рекомендуется для production)
$response = $client->issueServiceToken($request, serviceSecret: 'identity-service-secret');

// Или без service auth (для localhost)
$response = $client->issueServiceToken($request);
```

**Примечание (v1.0+):** Service authentication теперь опциональна. Если `service_auth.enabled = true` на сервере, service authentication доступна но не обязательна (рекомендуется для production).

#### RevokeToken - Отзыв токена

**Требует JWT токен**:

```php
use Kabiroman\Octawire\AuthService\Client\Request\JWT\RevokeTokenRequest;

$request = new RevokeTokenRequest(
    token: 'jwt-token-to-revoke',
    projectId: 'your-project-id',
);

// Требуется JWT токен для аутентификации
$jwtToken = 'user-jwt-token'; // JWT токен пользователя
$response = $client->revokeToken($request, jwtToken: $jwtToken);
```

## Service Authentication

Service Authentication используется для межсервисной аутентификации. В v1.0+ она опциональна для методов `IssueServiceToken`, `ValidateToken`, `ParseToken`, `ExtractClaims`, `ValidateBatch`.

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
    projectId: 'default-project-id', // Обязательное поле (v0.9.3+)
    targetService: 'gateway-service',
    ttl: 3600,
);
$response = $client->issueServiceToken($request, 'identity-service-secret-abc123def456');
```

### Использование

**Важно (v1.0+):** Service authentication теперь опциональна. Вы можете вызывать методы без service auth, особенно для localhost соединений.

```php
use Kabiroman\Octawire\AuthService\Client\Request\JWT\IssueServiceTokenRequest;

try {
    $request = new IssueServiceTokenRequest(
        sourceService: 'identity-service',
        projectId: 'default-project-id',
        targetService: 'gateway-service',
        userId: 'service-user', // Опционально
        claims: ['service' => 'identity-service'], // Опционально
        ttl: 3600, // Опционально
    );
    
    // Вариант 1: С service auth (рекомендуется для production)
    $response = $client->issueServiceToken($request, serviceSecret: 'identity-service-secret-abc123def456');
    
    // Вариант 2: Без service auth (для localhost или если service_auth.enabled = false)
    $response = $client->issueServiceToken($request);
    
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

## JWT Authentication

Для методов, требующих JWT аутентификации (RevokeToken и все методы APIKeyService), требуется JWT токен. JWT токен передается при вызове метода как параметр:

```php
// Получить JWT токен через IssueToken
$issueRequest = new IssueTokenRequest(
    userId: 'user-123',
    projectId: 'project-id',
);
$issueResponse = $client->issueToken($issueRequest);
$jwtToken = $issueResponse->accessToken;

// Использовать JWT токен для методов требующих JWT
$revokeRequest = new RevokeTokenRequest(
    token: 'token-to-revoke',
    projectId: 'project-id',
);
$client->revokeToken($revokeRequest, jwtToken: $jwtToken);
```

**Важно:** Для методов с опциональной аутентификацией (ValidateToken, ParseToken, ExtractClaims, ValidateBatch) можно использовать service auth или работать без аутентификации (особенно для localhost или если service_auth.enabled = false).

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

use Kabiroman\Octawire\AuthService\Client\Request\JWT\ValidateTokenRequest;

try {
    $request = new ValidateTokenRequest(
        token: $token,
        projectId: 'your-project-id', // Обязательное поле (v0.9.3+)
    );
    $response = $client->validateToken($request);
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

> **Важно (v0.9.3+):** `project_id` теперь обязателен для всех токен-методов. Вы должны явно указывать `projectId` в каждом Request классе.

Клиент поддерживает работу с несколькими проектами. Вы должны указать `projectId` в каждом Request:

```php
use Kabiroman\Octawire\AuthService\Client\Request\JWT\IssueTokenRequest;

$config = new Config([
    'transport' => 'tcp',
    'address' => 'localhost:50052',
]);
$client = new AuthClient($config);

// Использование проекта по умолчанию
$request = new IssueTokenRequest(
    userId: 'user-123',
    projectId: 'default-project-id', // Обязательное поле (v0.9.3+)
);
$response = $client->issueToken($request);

// Использование другого проекта
$request = new IssueTokenRequest(
    userId: 'user-123',
    projectId: 'another-project-id', // Обязательное поле (v0.9.3+)
);
$response = $client->issueToken($request);
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

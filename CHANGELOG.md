# История изменений

Все заметные изменения в этом проекте будут документироваться в этом файле.

Формат основан на [Keep a Changelog](https://keepachangelog.com/ru/1.0.0/),
и этот проект придерживается [Semantic Versioning](https://semver.org/lang/ru/).

## [Unreleased]

## [0.9.4] - 2025-12-01

### BREAKING CHANGES
- **HealthCheckResponse**: Поле `healthy` (bool) заменено на `status` (string)
  - Возможные значения Status: `"healthy"`, `"degraded"`, `"unhealthy"`
  - Добавлено поле `timestamp` (int) с Unix timestamp проверки
  - Добавлены хелпер-методы `isHealthy()` и `isOperational()`
  - Миграция: `$response->healthy` → `$response->status === 'healthy'` или `$response->isHealthy()`
- **JATPProtocol**: Все envelope поля JATP протокола конвертированы на camelCase:
  - `protocol_version` → `protocolVersion`
  - `request_id` → `requestId`
  - `jwt_token` → `jwtToken`
  - `service_name` → `serviceName`
  - `service_secret` → `serviceSecret`
- **Все Request классы**: Payload поля конвертированы на camelCase согласно спецификации JATP v1.0:
  - `user_id` → `userId`, `project_id` → `projectId`
  - `access_token_ttl` → `accessTokenTtl`, `refresh_token_ttl` → `refreshTokenTtl`
  - `device_id` → `deviceId`, `token_type` → `tokenType`
  - `check_blacklist` → `checkBlacklist`, `claim_keys` → `claimKeys`
  - `source_service` → `sourceService`, `target_service` → `targetService`
  - `api_key` → `apiKey`, `key_id` → `keyId`
  - `allowed_ips` → `allowedIps`, `required_scopes` → `requiredScopes`
  - `page_size` → `pageSize`
- **ValidateBatchRequest**: Добавлено обязательное поле `projectId`
- **ValidateAPIKeyRequest**: Добавлено обязательное поле `projectId`

### Изменено
- Обновлен для соответствия Auth Service Protocol v1.0 (JATP) спецификации
- Service authentication теперь опциональна для методов `IssueServiceToken`, `ValidateToken`, `ParseToken`, `ExtractClaims`, `ValidateBatch`
- Методы `ValidateToken`, `ParseToken`, `ExtractClaims`, `ValidateBatch` больше не требуют JWT токен - используют опциональную service auth или работают как публичные методы

### Добавлено
- Константа `Version::VERSION` для программного доступа к версии клиента
- **IssueTokenResponse / RefreshTokenResponse**: добавлены поля `expiresIn` и `refreshExpiresIn` (TTL в секундах)
- **TokenClaims**: добавлены поля `jwtId` и `keyId` согласно спецификации
- **HealthCheckResponse**: константы `STATUS_HEALTHY`, `STATUS_DEGRADED`, `STATUS_UNHEALTHY`
- Полная поддержка JATP Protocol v1.0:
  * Опциональная service authentication для методов валидации
  * Публичный доступ к методам валидации без аутентификации (для localhost)
  * Правильная обработка JWT токена только для методов, требующих JWT (RevokeToken, APIKeyService)
  * camelCase именование всех полей согласно спецификации
- Рефакторинг интеграционных тестов:
  * Переиспользуемые функции для тестирования различных сценариев
  * Тест `testAllScenariosV1()` покрывающий 4 сценария (TLS/no-TLS × auth/no-auth)
  * Автоматическое определение TLS требований сервера
  * Graceful skip для неподдерживаемых сценариев
- Обновлена документация TESTING.md:
  * Добавлен раздел о юнит-тестах с полным списком покрытия
  * Детальные инструкции по настройке и запуску интеграционных тестов
  * Инструкции по тестированию различных сценариев через Docker Compose

### Исправлено
- Убрано автоматическое использование JWT токена из `config->apiKey` для методов `ValidateToken`, `ParseToken`, `ExtractClaims`, `ValidateBatch`
- Исправлена логика передачи service auth - теперь опциональна согласно v1.0
- Убрана обязательная валидация `serviceSecret` для метода `IssueServiceToken` (теперь опциональна)

### Тестирование
- Рефакторинг интеграционных тестов для поддержки 4 различных сценариев
- Улучшена обработка JWT токенов для методов требующих JWT
- Добавлены юнит-тесты для проверки опциональной service auth
- Обновлены примеры для соответствия v1.0 спецификации
- Обновлены unit-тесты для camelCase payload

### Соответствие спецификациям
- Полное соответствие спецификации JATP_METHODS_1.0.json
- Полное соответствие спецификации JATP_1.0.md по camelCase именованию
- Соответствие требованиям Auth Service Protocol v1.0 по опциональной service authentication
- Корректная обработка JWT аутентификации только для требуемых методов

### Миграция с 0.9.3

```php
// HealthCheckResponse - BREAKING CHANGE
// Было:
if ($response->healthy) { ... }

// Стало:
if ($response->isHealthy()) { ... }
// или
if ($response->status === 'healthy') { ... }

// Новые поля в Response
$response->expiresIn;        // TTL access токена в секундах
$response->refreshExpiresIn; // TTL refresh токена в секундах
$response->timestamp;        // Unix timestamp проверки (HealthCheck)

// ValidateBatchRequest теперь требует projectId
// Было:
$request = new ValidateBatchRequest(tokens: [...], checkBlacklist: true);

// Стало:
$request = new ValidateBatchRequest(tokens: [...], projectId: 'project-uuid', checkBlacklist: true);

// ValidateAPIKeyRequest теперь требует projectId
// Было:
$request = new ValidateAPIKeyRequest(apiKey: 'key');

// Стало:
$request = new ValidateAPIKeyRequest(apiKey: 'key', projectId: 'project-uuid');
```

## [0.9.3] - 2025-11-27

### Изменено
- **BREAKING**: `project_id` теперь обязателен для всех токен-методов (v0.9.3+)
  - `IssueTokenRequest`: `projectId` теперь обязательное поле (не nullable)
  - `IssueServiceTokenRequest`: `projectId` теперь обязательное поле (не nullable)
  - `ValidateTokenRequest`: добавлено обязательное поле `projectId`
  - `RefreshTokenRequest`: добавлено обязательное поле `projectId`
  - `ParseTokenRequest`: добавлено обязательное поле `projectId`
  - `ExtractClaimsRequest`: добавлено обязательное поле `projectId`
  - `RevokeTokenRequest`: добавлено обязательное поле `projectId`
- Удалена логика автоматического добавления `project_id` из конфигурации в `AuthClient`
- Обновлены все методы `AuthClient` для соответствия новой спецификации
- Обновлены все примеры для включения обязательного `projectId` в Request классах
- Обновлены тесты для использования обязательного `projectId`

### Соответствие спецификациям
- Полное соответствие `JATP_METHODS_1.0.json` по обязательности `project_id` для всех токен-методов

## [0.9.3] - 2025-11-27

### Добавлено
- **Service Authentication поддержка:**
  - Добавлен `service_secret` параметр в `Config` класс для межсервисной аутентификации
  - Улучшен метод `issueServiceToken` с валидацией параметров и поддержкой service secret из конфигурации
  - Добавлена обработка ошибки `AUTH_FAILED` (403) для service authentication failures
  - Обновлен пример `examples/tcp.php` с правильным использованием `IssueServiceTokenRequest` и обработкой ошибок
- **Полная поддержка всех JATP error codes:**
  - Добавлена обработка всех кодов ошибок из спецификации `JATP_METHODS_1.0.json`:
    * `AUTH_FAILED` - для service authentication failures
    * `ERROR_EXPIRED_TOKEN`, `ERROR_INVALID_TOKEN`, `ERROR_INVALID_SIGNATURE`
    * `ERROR_INVALID_ISSUER`, `ERROR_INVALID_AUDIENCE`
    * `ERROR_TOKEN_REVOKED`, `ERROR_REFRESH_TOKEN_INVALID`, `ERROR_REFRESH_TOKEN_EXPIRED`
    * `ERROR_INVALID_USER_ID`
  - Улучшена логика определения типа ошибки по содержимому сообщения
- **Расширенная документация:**
  - Добавлен раздел "Service Authentication" в README.md с примерами использования
  - Добавлен раздел "Примеры подключения" с описанием всех 4 кейсов (PROD/DEV + service_auth=true/false)
  - Обновлен пример `examples/tls.php` для использования `tcp.tls` формата вместо legacy `tls`
  - Добавлен новый пример `examples/connection-scenarios.php` с примерами для всех 4 сценариев подключения
- **Комплексное тестирование:**
  - Добавлен `ErrorHandlerTest` с 23 тестами для всех кодов ошибок
  - Добавлены тесты для `issueServiceToken` валидации и service secret из конфигурации
  - Добавлены тесты для `Config` с `service_secret` параметром
  - Добавлен интеграционный тест `test-all-scenarios.php` для тестирования всех 4 сценариев подключения
  - Всего 36 тестов (было 13), все проходят успешно

### Изменено
- Обновлен `ErrorHandler` для полного соответствия спецификации JATP_METHODS_1.0.json
- Улучшена валидация в `issueServiceToken` метод: добавлена проверка обязательных параметров
- Обновлена документация README.md с информацией о соответствии спецификации

### Исправлено
- Исправлена обработка ошибок: добавлена поддержка всех кодов ошибок из спецификации
- Улучшена логика определения типа ошибки по содержимому сообщения для лучшей обработки ошибок без JATP error structure

### Технические детали
- Полное соответствие спецификации `JATP_METHODS_1.0.json`
- Service secret может быть указан в конфигурации или передан как параметр метода
- Приоритет: параметр метода > конфигурация
- Все коды ошибок маппятся в соответствующие типы исключений

### Результаты тестирования
- ✅ 36 unit-тестов проходят успешно (было 13)
- ✅ Все 4 сценария подключения протестированы (PROD/DEV + service_auth=true/false)
- ✅ Service authentication работает корректно
- ✅ Все коды ошибок обрабатываются правильно
- ✅ Health check, IssueToken, IssueServiceToken работают во всех сценариях

## [0.9.2] - 2025-11-26

### Исправлено
- Исправлено использование TLS схемы в `TCPConnection`: теперь используется `tls://` вместо `tcp://` когда TLS включен, что позволяет корректно устанавливать TLS соединения с Auth Service
- Улучшена обработка `customClaims` в `TokenClaims::fromArray`: добавлен `jwtId` (и варианты `jwt_id`, `jti`) в список стандартных ключей, чтобы предотвратить его попадание в `customClaims`
- Исправлена обработка вложенных `customClaims` и `custom_claims` в `TokenClaims::fromArray` для корректного извлечения кастомных claims из токенов

### Технические детали
- Изменение в `TCPConnection::connect()`: использование `tls://` схемы для stream_socket_client при включенном TLS
- Обновление логики фильтрации стандартных ключей в `TokenClaims::fromArray` для поддержки JWT ID

## [0.9.1] - 2025-11-25

### Добавлено
- Полная реализация PHP клиента для Auth Service через TCP/JATP протокол
- Основной класс `AuthClient` с поддержкой всех методов JWTService и APIKeyService
- TCP транспорт через `TCPConnection` с поддержкой TLS/mTLS
- JATP протокол через `JATPProtocol` и `JATPClient` для сериализации/десериализации JSON
- Класс `KeyCache` для кэширования публичных ключей с поддержкой graceful ротации
- Класс `RetryHandler` с экспоненциальным backoff и jitter для TCP ошибок
- Класс `TLSConfig` для поддержки TLS/mTLS конфигурации
- Класс `Config` для конфигурации клиента с поддержкой TCP транспорта
- Кастомные исключения: `AuthException`, `ConnectionException`, `InvalidTokenException`, `TokenExpiredException`, `TokenRevokedException`, `RateLimitException`
- Модели данных: `PublicKeyInfo`, `TokenClaims`, `APIKeyInfo`
- **Типизированные DTO классы для всех JATP методов:**
  - 14 Request DTO классов для всех методов (`IssueTokenRequest`, `ValidateTokenRequest`, `CreateAPIKeyRequest` и др.)
  - 14 Response DTO классов для всех методов (`IssueTokenResponse`, `ValidateTokenResponse`, `CreateAPIKeyResponse` и др.)
  - Полная типизация согласно спецификации `JATP_METHODS_1.0.json`
- Unit-тесты для `KeyCache` и `RetryHandler`
- Интеграционный тест: `test_jatp_full_dto.php` (полный набор из 14 методов с типизированными DTO)
- Smoke test: `test_client.php` для проверки компонентов без запущенного сервиса
- Примеры использования: `basic.php`, `tcp.php`, `tls.php`, `caching.php`, `multiproject.php`
- Docker окружение для разработки (`docker-compose.yml`, `Dockerfile.dev`)
- Документация: `README.md`, `README.docker.md`, `TESTING.md` с результатами тестирования
- Требование PHP 8.1+ (минимальная версия)

### Изменено
- Заменен gRPC транспорт на TCP/JSON (JATP протокол)
- Удалена зависимость от `grpc/grpc` PHP extension
- Обновлена обработка ошибок для работы с JATP error codes
- Обновлен `RetryHandler` для TCP-специфичных ошибок (connection refused, timeouts)
- Обновлен `ErrorHandler` для маппинга JATP error codes в PHP исключения
- **BREAKING CHANGE: Рефакторинг `AuthClient` на типизированные DTO:**
  - Все методы `AuthClient` теперь принимают типизированные Request DTO вместо массивов
  - Все методы возвращают типизированные Response DTO вместо массивов
  - Удалена обратная совместимость с массив-основанным API
  - Автоматическое добавление `project_id` только для методов, которые его принимают согласно спецификации
- Обновлены модели данных для поддержки как camelCase (protobuf), так и snake_case (JSON) форматов полей
- Обновлены unit-тесты для работы с новым типизированным API
- Консолидированы тестовые файлы: удалены устаревшие `test_jatp.php` и `test_jatp_full.php`, оставлен только `test_jatp_full_dto.php`

### Исправлено
- Исправлена совместимость с PHP 8+ (замена `resource` type hint на PHPDoc)
- Исправлена обработка пустых payload (конвертация массивов в объекты для protobuf)
- Исправлена поддержка mixed типов для payload (массивы и объекты)
- Исправлена логика кэширования публичных ключей в `getPublicKey` метод

### Технические детали
- Использование PSR-4 автозагрузки
- Типизированные свойства и возвращаемые значения (PHP 8.1+)
- Readonly свойства где возможно (PHP 8.2+)
- Поддержка graceful key rotation (несколько активных ключей одновременно)
- In-memory кэширование ключей с TTL
- Обработка ошибок через исключения
- TCP/JSON транспорт через стандартные PHP расширения (`ext-sockets`, `ext-json`)
- UUID v7 генерация для request_id (через `ramsey/uuid`)
- Поддержка persistent connections для производительности

### Результаты тестирования
- ✅ 14 из 14 методов успешно протестированы с типизированными DTO
- ✅ Все публичные методы (HealthCheck, GetPublicKey) работают
- ✅ Все JWT Service методы (10 методов) протестированы и работают
- ✅ Все API Key Service методы (4 метода) протестированы и работают
- ✅ Service authentication настроена и работает
- ✅ Batch операции (ValidateBatch) работают корректно
- ✅ Все unit-тесты (13 тестов) проходят успешно

### Известные ограничения
- Полное тестирование требует запущенного Auth Service с настроенным `service_auth`
- API Key Service методы требуют `project_id` в конфигурации для использования

### Миграция с предыдущих версий
**ВАЖНО:** Эта версия содержит breaking changes. При обновлении необходимо:

1. Заменить все вызовы методов `AuthClient` с массивами на типизированные DTO:
   ```php
   // Было (устарело):
   $response = $client->issueToken(['user_id' => '123', 'project_id' => 'test']);
   
   // Стало:
   $request = new IssueTokenRequest(userId: '123', projectId: 'test');
   $response = $client->issueToken($request);
   ```

2. Все методы теперь возвращают типизированные Response DTO вместо массивов:
   ```php
   // Было:
   $accessToken = $response['access_token'];
   
   // Стало:
   $accessToken = $response->accessToken;
   ```

3. Полный список DTO классов см. в `src/Request/` и `src/Response/` директориях

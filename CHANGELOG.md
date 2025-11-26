# История изменений

Все заметные изменения в этом проекте будут документироваться в этом файле.

Формат основан на [Keep a Changelog](https://keepachangelog.com/ru/1.0.0/),
и этот проект придерживается [Semantic Versioning](https://semver.org/lang/ru/).

## [Unreleased]

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

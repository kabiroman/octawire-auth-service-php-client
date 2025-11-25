# История изменений

Все заметные изменения в этом проекте будут документироваться в этом файле.

Формат основан на [Keep a Changelog](https://keepachangelog.com/ru/1.0.0/),
и этот проект придерживается [Semantic Versioning](https://semver.org/lang/ru/).

## [Unreleased]

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
- Unit-тесты для `KeyCache` и `RetryHandler`
- Интеграционные тесты: `test_jatp.php` (базовый) и `test_jatp_full.php` (полный набор из 14 методов)
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

### Исправлено
- Исправлена совместимость с PHP 8+ (замена `resource` type hint на PHPDoc)
- Исправлена обработка пустых payload (конвертация массивов в объекты для protobuf)
- Исправлена поддержка mixed типов для payload (массивы и объекты)

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
- ✅ 10 из 10 выполненных тестов успешно пройдены
- ✅ Все публичные методы (HealthCheck, GetPublicKey) работают
- ✅ Все JWT Service методы протестированы и работают
- ✅ Service authentication настроена и работает
- ✅ Batch операции (ValidateBatch) работают корректно
- ⚠️ API Key Service методы требуют `project_id` для тестирования

### Известные ограничения
- Полное тестирование требует запущенного Auth Service с настроенным `service_auth`
- API Key Service методы требуют `project_id` в конфигурации для использования

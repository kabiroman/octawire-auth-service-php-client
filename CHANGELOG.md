# История изменений

Все заметные изменения в этом проекте будут документироваться в этом файле.

Формат основан на [Keep a Changelog](https://keepachangelog.com/ru/1.0.0/),
и этот проект придерживается [Semantic Versioning](https://semver.org/lang/ru/).

## [0.9.1] - 2025-11-24

### Добавлено
- Начальная реализация PHP клиента для Auth Service
- Основной класс `AuthClient` с поддержкой всех методов JWTService и APIKeyService
- Класс `KeyCache` для кэширования публичных ключей с поддержкой graceful ротации
- Класс `RetryHandler` с экспоненциальным backoff и jitter
- Класс `TLSConfig` для поддержки TLS/mTLS
- Класс `Config` для конфигурации клиента
- Кастомные исключения: `AuthException`, `ConnectionException`, `InvalidTokenException`, `TokenExpiredException`, `TokenRevokedException`, `RateLimitException`
- Модели данных: `PublicKeyInfo`, `TokenClaims`, `APIKeyInfo`
- Unit-тесты для `KeyCache` и `RetryHandler`
- Примеры использования: `basic.php`, `tls.php`, `caching.php`, `multiproject.php`
- Docker окружение для разработки (`docker-compose.yml`, `Dockerfile.dev`)
- Скрипт генерации proto классов (`generate-proto.sh`)
- Тестовый скрипт `test_client.php` для проверки базовой функциональности
- Документация: `README.md`, `README.docker.md`
- Требование PHP 8.1+ (минимальная версия)

### Технические детали
- Использование PSR-4 автозагрузки
- Типизированные свойства и возвращаемые значения (PHP 8.1+)
- Readonly свойства где возможно (PHP 8.2+)
- Поддержка graceful key rotation (несколько активных ключей одновременно)
- In-memory кэширование ключей с TTL
- Обработка ошибок через исключения
- Интеграция с gRPC через `grpc/grpc` и `google/protobuf` пакеты

### Известные ограничения
- Требуется установка gRPC PHP extension
- Требуется генерация proto классов перед использованием
- Полное тестирование требует запущенного Auth Service


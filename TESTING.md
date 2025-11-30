# Руководство по тестированию

Этот документ описывает, как тестировать PHP клиент для Auth Service в различных сценариях.

## Типы тестов

Клиент включает два типа тестов:

1. **Юнит-тесты** (`tests/*Test.php`) - тестирование компонентов клиента без реального сервиса
2. **Интеграционные тесты** (`test-integration-full.php`) - тестирование клиента против реального Auth Service

## Юнит-тесты

Юнит-тесты проверяют логику клиента изолированно, используя моки для соединений. Эти тесты не требуют запущенного сервиса и выполняются быстро.

### Запуск юнит-тестов

```bash
cd services/auth-service/clients/octawire-auth-service-php-client

# Запустить все юнит-тесты
composer test

# Или через phpunit напрямую
./vendor/bin/phpunit

# Запустить конкретный тест
./vendor/bin/phpunit tests/AuthClientTest.php
```

### Покрытие юнит-тестов

Юнит-тесты покрывают следующие аспекты:

#### Конфигурация
- ✅ `testConfigValidation` - валидация конфигурации
- ✅ `testConfigWithTCP` - конфигурация TCP соединения
- ✅ `testConfigWithServiceSecret` - конфигурация с service secret
- ✅ `testConfigWithoutServiceSecret` - конфигурация без service secret

#### Создание клиента
- ✅ `testClientCreationWithInvalidConfig` - создание клиента с неверной конфигурацией

#### IssueServiceToken
- ✅ `testIssueServiceTokenValidationEmptySourceService` - валидация пустого sourceService
- ✅ `testIssueServiceTokenWithoutServiceSecret` - вызов без serviceSecret (опциональна в v1.0+)
- ✅ `testIssueServiceTokenWithServiceSecretFromConfig` - использование serviceSecret из конфига
- ✅ `testIssueServiceTokenWithServiceSecretAsParameter` - передача serviceSecret как параметр

#### Методы с опциональной аутентификацией (v1.0+)
- ✅ `testValidateTokenWithoutJWT` - валидация токена без JWT (опциональная service auth)
- ✅ `testParseTokenWithoutJWT` - парсинг токена без JWT
- ✅ `testExtractClaimsWithoutJWT` - извлечение claims без JWT
- ✅ `testValidateBatchWithoutJWT` - пакетная валидация без JWT

#### Обработка ошибок
- ✅ Тесты в `ErrorHandlerTest.php` - обработка всех типов ошибок

#### Retry логика
- ✅ Тесты в `RetryHandlerTest.php` - retry механизм

#### Кэширование ключей
- ✅ Тесты в `KeyCacheTest.php` - кэширование публичных ключей

#### Request классы
- ✅ Тесты в `RequestTest.php` - валидация Request классов

### Пример вывода юнит-тестов

```
PHPUnit 10.0.0 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.1.0
Configuration: /path/to/phpunit.xml

.............                                                     13 / 13 (100%)

Time: 00:00.123, Memory: 4.00 MB

OK (13 tests, 25 assertions)
```

## Интеграционные тесты

Интеграционные тесты проверяют клиент против реального запущенного Auth Service. Они тестируют различные сценарии конфигурации: с TLS и без, с service authentication и без.

### Сценарии тестирования

Клиент тестируется в 4 различных сценариях:

#### Сценарий 1: Без TLS и без аутентификации
- **TLS**: Отключен
- **Service Auth**: Отключен
- **Ожидаемое поведение**:
  - Публичные методы работают (IssueToken, RefreshToken, GetPublicKey, HealthCheck)
  - Методы с опциональной аутентификацией работают (ValidateToken, ParseToken, ExtractClaims, ValidateBatch)
  - IssueServiceToken может работать без service auth (опциональна в v1.0+)
  - Методы требующие JWT требуют JWT токен (RevokeToken, CreateAPIKey)

#### Сценарий 2: Без TLS, с Service Authentication
- **TLS**: Отключен
- **Service Auth**: Включен
- **Ожидаемое поведение**:
  - Публичные методы работают
  - Методы валидации работают с service auth
  - IssueServiceToken работает с service auth

#### Сценарий 3: С TLS, без аутентификации
- **TLS**: Включен
- **Service Auth**: Отключен
- **Ожидаемое поведение**:
  - Все методы работают через TLS соединение
  - Требуется корректная конфигурация TLS на сервере

#### Сценарий 4: С TLS и с Service Authentication
- **TLS**: Включен
- **Service Auth**: Включен
- **Ожидаемое поведение**:
  - Все методы работают через TLS соединение
  - Методы валидации работают с service auth через TLS

### Настройка окружения для интеграционных тестов

#### 1. Запуск Auth Service через Docker Compose

Самый простой способ - использовать Docker Compose:

```bash
cd services/auth-service

# Запустить все зависимости (Redis, PostgreSQL)
docker-compose up -d redis postgres

# Запустить Auth Service (dev режим, без TLS, без service auth)
docker-compose --profile dev up -d auth-service-dev

# Проверить статус
docker-compose ps

# Просмотр логов
docker-compose logs -f auth-service-dev
```

#### 2. Конфигурация сервиса

Для тестирования разных сценариев можно изменить конфигурацию в `services/auth-service/config/config.json`:

**Базовые настройки для тестов без TLS и без auth:**
```json
{
  "security": {
    "auth_required": false,
    "service_auth": {
      "enabled": false
    }
  },
  "tcp": {
    "tls": {
      "enabled": false
    }
  }
}
```

**Для тестов с service auth:**
```json
{
  "security": {
    "auth_required": false,
    "service_auth": {
      "enabled": true,
      "services": {
        "identity-service": {
          "secret": "identity-service-secret-abc123def456"
        }
      }
    }
  },
  "tcp": {
    "tls": {
      "enabled": false
    }
  }
}
```

**Для тестов с TLS:**
```json
{
  "tcp": {
    "tls": {
      "enabled": true,
      "cert_file": "/app/config/tls/cert.pem",
      "key_file": "/app/config/tls/key.pem"
    }
  }
}
```

#### 3. Перезапуск сервиса после изменения конфигурации

```bash
cd services/auth-service

# Остановить сервис
docker-compose stop auth-service-dev

# Обновить конфигурацию в config/config.json

# Запустить снова
docker-compose start auth-service-dev

# Или пересоздать контейнер
docker-compose up -d --force-recreate auth-service-dev
```

### Запуск интеграционных тестов

#### Базовый запуск

```bash
cd services/auth-service/clients/octawire-auth-service-php-client

# Запустить все интеграционные тесты
php test-integration-full.php

# Или с указанием адреса
php test-integration-full.php --address=localhost:50052
```

#### Структура интеграционных тестов

**test-integration-full.php** - комплексный тест, который автоматически проверяет все 4 сценария:

1. Автоматически определяет, требуется ли TLS на сервере
2. Создаёт JWT токен для методов, требующих JWT
3. Определяет 4 сценария:
   - NoTLS_NoAuth
   - NoTLS_WithServiceAuth
   - WithTLS_NoAuth
   - WithTLS_WithServiceAuth
4. Для каждого сценария:
   - Создаёт клиент
   - Проверяет подключение (health check)
   - Если не удалось - пропускает сценарий
   - Запускает все тесты методов

### Пример вывода интеграционных тестов

```
TLS Requirement: NO
Auth Service is running - starting tests...

================================================================================
Testing scenario: NoTLS_NoAuth (TLS=false, ServiceAuth=false)
================================================================================

--- Test 1: HealthCheck ---
✓ Health check passed
  Version: v0.9.0-dev
  Uptime: 1709 seconds

--- Test 2: IssueToken ---
✓ Token issued successfully
  Access Token: eyJhbGciOiJSUzI1NiIsInR5cCI6IkpXVCJ9...
  Expires At: 2025-12-01 20:16:08

...

================================================================================
TEST SUMMARY
================================================================================

NoTLS_NoAuth:
  Health Check: ✓ PASS
  Issue Token: ✓ PASS
  Validate Token: ✓ PASS
  ...
```

## Тестирование различных сценариев

### Сценарий 1: Без TLS и без auth

**Настройка сервиса:**
1. Убедитесь, что `config/config.json` содержит:
   ```json
   {
     "security": {
       "auth_required": false,
       "service_auth": {
         "enabled": false
       }
     },
     "tcp": {
       "tls": {
         "enabled": false
       }
     }
   }
   ```

2. Запустите сервис:
   ```bash
   docker-compose --profile dev up -d auth-service-dev
   ```

**Запуск тестов:**
```bash
php test-integration-full.php
```

### Сценарий 2: Без TLS, с Service Auth

**Настройка сервиса:**
1. Измените `config/config.json`:
   ```json
   {
     "security": {
       "auth_required": false,
       "service_auth": {
         "enabled": true,
         "services": {
           "identity-service": {
             "secret": "identity-service-secret-abc123def456"
           }
         }
       }
     },
     "tcp": {
       "tls": {
         "enabled": false
       }
     }
   }
   ```

2. Перезапустите сервис:
   ```bash
   docker-compose restart auth-service-dev
   ```

**Запуск тестов:**
```bash
php test-integration-full.php
```

### Сценарий 3 и 4: С TLS

Для тестирования сценариев с TLS необходимо:

1. Создать TLS сертификаты:
   ```bash
   cd services/auth-service
   mkdir -p config/tls
   
   openssl req -x509 -newkey rsa:4096 -keyout config/tls/key.pem \
     -out config/tls/cert.pem -days 365 -nodes \
     -subj "/CN=localhost"
   ```

2. Обновить `config/config.json`:
   ```json
   {
     "tcp": {
       "tls": {
         "enabled": true,
         "cert_file": "/app/config/tls/cert.pem",
         "key_file": "/app/config/tls/key.pem"
       }
     }
   }
   ```

3. Обновить `docker-compose.yml` для монтирования сертификатов:
   ```yaml
   volumes:
     - ./config:/app/config:ro
     - ./config/tls:/app/config/tls:ro
   ```

4. Перезапустить сервис:
   ```bash
   docker-compose up -d --force-recreate auth-service-dev
   ```

5. Запустить тесты:
   ```bash
   php test-integration-full.php
   ```

## Устранение неполадок

### Сервис не запускается

**Проблема**: Сервис сразу завершается или не запускается

**Решения**:
1. Проверьте, запущен ли Redis:
   ```bash
   docker-compose ps redis
   # или
   redis-cli ping
   ```

2. Проверьте, доступны ли порты:
   ```bash
   # TCP порт
   ss -tlnp | grep 50052
   # HTTP порт (health check)
   ss -tlnp | grep 9765
   ```

3. Проверьте логи сервиса:
   ```bash
   docker-compose logs auth-service-dev
   ```

4. Проверьте конфигурацию:
   ```bash
   # Проверить синтаксис JSON
   jq . services/auth-service/config/config.json
   ```

### Ошибки TLS в сценариях с TLS

**Проблема**: Сервис не запускается с ошибками TLS

**Решения**:
1. Убедитесь, что сертификаты существуют:
   ```bash
   ls -la services/auth-service/config/tls/
   ```

2. Проверьте пути к сертификатам в конфигурации:
   ```json
   {
     "tcp": {
       "tls": {
         "enabled": true,
         "cert_file": "/app/config/tls/cert.pem",
         "key_file": "/app/config/tls/key.pem"
       }
     }
   }
   ```

3. Убедитесь, что сертификаты смонтированы в Docker контейнер:
   ```bash
   docker-compose exec auth-service-dev ls -la /app/config/tls/
   ```

### Ошибки подключения клиента

**Проблема**: Клиент не может подключиться к сервису

**Решения**:
1. Проверьте, что сервис запущен:
   ```bash
   curl http://localhost:9765/health
   # или
   docker-compose ps
   ```

2. Проверьте адрес сервиса:
   - По умолчанию: `localhost:50052` для TCP/JATP
   - Для Docker: может потребоваться использовать IP контейнера

3. Для TLS: убедитесь, что клиент использует `insecure_skip_verify: true` для тестов с самоподписанными сертификатами

### Ошибки аутентификации

**Проблема**: Методы завершаются с ошибками аутентификации

**Решения**:
1. **Service Auth ошибки**:
   - Проверьте, что `serviceName` и `serviceSecret` установлены в конфигурации клиента
   - Проверьте, что имя сервиса есть в `allowed_services` в конфигурации сервера
   - Проверьте, что секрет совпадает с конфигурацией сервера
   - **Примечание (v1.0+):** Service auth опциональна - можно вызывать методы без неё

2. **JWT Auth ошибки**:
   - Проверьте, что JWT токен передается при вызове методов, требующих JWT (RevokeToken, APIKeyService)
   - Проверьте, что токен валиден и не истёк
   - Проверьте, что токен был выдан тем же сервисом

### Ошибки компиляции тестов

**Проблема**: `php test-integration-full.php` завершается с ошибками синтаксиса

**Решения**:
1. Убедитесь, что вы в правильной директории:
   ```bash
   cd services/auth-service/clients/octawire-auth-service-php-client
   ```

2. Обновите зависимости:
   ```bash
   composer install
   ```

3. Проверьте версию PHP (требуется PHP 8.1+):
   ```bash
   php -v
   ```

## Непрерывная интеграция

Для CI/CD пайплайнов можно использовать:

```bash
# Запустить только юнит-тесты (быстро, не требует сервиса)
composer test

# Запустить интеграционные тесты (требует запущенный сервис)
php test-integration-full.php
```

## Дополнительные ресурсы

- [README клиента](./README.md) - документация по использованию клиента
- [Спецификация JATP методов](../../docs/protocol/JATP_METHODS_1.0.md) - полная справочная документация API
- [Руководство по безопасности](../../docs/SECURITY.md) - лучшие практики безопасности

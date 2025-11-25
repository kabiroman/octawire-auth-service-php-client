# PHP JATP Client Testing Guide

## Что нужно для полного тестирования всех методов

### 1. Запуск зависимостей

#### Redis
```bash
cd /var/www/national-union.ru/octawire/services/auth-service
docker-compose -f docker-compose.yml up -d redis
```

#### Auth Service
```bash
cd /var/www/national-union.ru/octawire/services/auth-service
go run ./cmd/auth-service -config config/config.json
```

Убедитесь, что сервис слушает на портах:
- `50051` - gRPC
- `50052` - TCP/JATP (для PHP клиента)
- `9765` - HTTP (health/metrics)

### 2. Настройка service authentication

Для тестирования методов, требующих аутентификацию, нужно настроить `service_auth` в `config/config.json`:

```json
{
  "security": {
    "service_auth": {
      "enabled": true,
      "secrets": {
        "test-service": "test-service-secret-123"
      },
      "allowed_services": ["test-service"],
      "cache_results": true
    }
  }
}
```

**Важно:** Значения `service_name` и `service_secret` в тестовом скрипте должны совпадать с теми, что в конфиге сервиса.

### 3. Публичные методы (не требуют аутентификации)

Эти методы работают без дополнительной настройки:

- ✅ `JWTService.HealthCheck` - проверка здоровья сервиса
- ✅ `JWTService.GetPublicKey` - получение публичного ключа

### 4. Методы, требующие JWT токен

Для этих методов нужен валидный JWT токен:

- `JWTService.ValidateToken` - валидация токена
- `JWTService.ParseToken` - парсинг токена
- `JWTService.ExtractClaims` - извлечение claims
- `JWTService.ValidateBatch` - пакетная валидация
- `JWTService.RevokeToken` - отзыв токена
- `APIKeyService.*` - все методы управления API ключами

**Решение:** Использовать токен, полученный из `IssueToken`, передавая его в поле `jwt_token` запроса.

### 5. Методы, требующие service authentication

Эти методы требуют настройки `service_auth`:

- `JWTService.IssueServiceToken` - выдача межсервисного токена

**Решение:** Настроить `service_auth` в конфиге сервиса и передать `service_name`/`service_secret` в запросе.

### 6. Запуск тестов

#### Базовый тест (только публичные методы + IssueToken)
```bash
cd /var/www/national-union.ru/octawire/services/auth-service/clients/php
php test_jatp.php
```

#### Полный тест (все методы)
```bash
cd /var/www/national-union.ru/octawire/services/auth-service/clients/php
php test_jatp_full.php
```

### 7. Структура тестов

#### test_jatp.php
Базовый тест, проверяющий:
1. HealthCheck (публичный)
2. IssueToken (создание токенов)
3. ValidateToken (с токеном из IssueToken)
4. GetPublicKey (публичный)
5. RefreshToken (обновление токена)

#### test_jatp_full.php
Расширенный тест, покрывающий все методы:
1. HealthCheck (публичный)
2. GetPublicKey (публичный)
3. IssueToken
4. ValidateToken (с JWT auth)
5. ParseToken (с JWT auth)
6. ExtractClaims (с JWT auth)
7. RefreshToken
8. ValidateBatch (с JWT auth)
9. IssueServiceToken (с service auth)
10. RevokeToken (с JWT auth)
11. CreateAPIKey (с JWT auth)
12. ValidateAPIKey (с JWT auth)
13. ListAPIKeys (с JWT auth)
14. RevokeAPIKey (с JWT auth)

### 8. Типичные проблемы

#### Ошибка: "authentication required"
**Причина:** Метод требует аутентификацию, но токен не передан.

**Решение:** 
- Для методов с JWT auth: передать токен в поле `jwt_token` запроса
- Для `IssueServiceToken`: настроить `service_auth` в конфиге

#### Ошибка: "service authentication failed"
**Причина:** `service_auth` не настроен или неверные credentials.

**Решение:** 
1. Добавить `service_auth` секцию в `config/config.json`
2. Убедиться, что `service_name` и `service_secret` совпадают

#### Ошибка: "Connection refused"
**Причина:** Auth Service не запущен или слушает на другом порту.

**Решение:**
1. Проверить, что сервис запущен: `ss -tlnp | grep 50052`
2. Проверить конфиг: порт должен быть `50052` для TCP

#### Ошибка: "failed to connect to Redis"
**Причина:** Redis не запущен.

**Решение:** Запустить Redis через docker-compose:
```bash
docker-compose -f docker-compose.yml up -d redis
```

### 9. Проверка результатов

Успешный тест должен показать:
- ✓ для всех проверенных методов
- Вывод информации о токенах, ключах, claims и т.д.
- Финальное сообщение "=== All tests completed! ==="

### 10. Дополнительные проверки

После успешного прохождения тестов можно проверить:

1. **Логи сервиса** - должны быть записи о JATP запросах
2. **Метрики** - `http://localhost:9765/metrics` должны показывать `tcp_*` метрики
3. **Health endpoint** - `http://localhost:9765/health` должен отвечать `200 OK`
4. **Rate limiting** - при большом количестве запросов может сработать ограничение

---

## Результаты тестирования

### Дата: 2025-11-25

#### Тестовая среда
- **PHP версия**: PHP 8.1+
- **Auth Service**: v0.8.0 (локально)
- **Транспорт**: TCP/JATP (порт 50052)
- **Redis**: 7-alpine (Docker)
- **TLS**: Отключен (development mode)

#### test_jatp_full.php - Полный тест JATP клиента

**Статус**: ✅ **10/10 выполненных тестов успешно**

##### Публичные методы (не требуют аутентификации)
1. ✅ **HealthCheck** - Проверка здоровья сервиса
   - Получение версии: v0.8.0
   - Статус: Healthy
   - Uptime: отображается корректно

2. ✅ **GetPublicKey** - Получение публичного ключа
   - Key ID: key-1
   - Algorithm: RS256
   - Кэширование: работает

##### JWT Service методы
3. ✅ **IssueToken** - Выдача токенов
   - Access token: успешно выдан
   - Refresh token: успешно выдан
   - TTL: настроен корректно

4. ✅ **ValidateToken** - Валидация токена (с JWT auth)
   - Проверка подписи: успешно
   - Извлечение claims: работает
   - Issuer: your-app

5. ✅ **ParseToken** - Парсинг токена (с JWT auth)
   - Claims count: 8
   - Извлечение полей: работает

6. ✅ **ExtractClaims** - Извлечение claims (с JWT auth)
   - Email: test@example.com
   - Role: admin
   - Все поля доступны

7. ✅ **RefreshToken** - Обновление токена
   - Новый access token: успешно выдан
   - Refresh token rotation: работает

8. ✅ **ValidateBatch** - Пакетная валидация (с JWT auth)
   - Количество токенов: 2
   - Все токены: валидны
   - Batch обработка: работает

9. ✅ **IssueServiceToken** - Межсервисный токен (с service auth)
   - Service authentication: работает
   - Токен выдан: успешно
   - Service name: test-service

10. ✅ **RevokeToken** - Отзыв токена (с JWT auth)
    - Токен отозван: успешно
    - Blacklist: работает

##### API Key Service методы (требуют project_id)
11-14. ⏭️ **API Key Management** - Пропущены
    - **Причина**: Требуется `project_id` в конфиге
    - **Тесты**: CreateAPIKey, ValidateAPIKey, ListAPIKeys, RevokeAPIKey
    - **Статус**: Функциональность доступна, но не протестирована без project_id

#### Заключение

**Все основные методы JATP клиента работают корректно.**

- ✅ TCP/JATP соединение: стабильное
- ✅ JWT токены: выдача, валидация, обновление, отзыв - все операции работают
- ✅ Service authentication: настроена и работает
- ✅ Публичные методы: доступны без аутентификации
- ✅ Batch операции: валидация нескольких токенов за раз работает
- ⚠️ API Key Service: требует project_id для полного тестирования

**Рекомендации:**
- Для полного тестирования API Key методов необходимо добавить `project_id` в конфигурацию клиента
- Все критичные операции для JWT токенов протестированы и работают

#### Известные ограничения
- API Key методы требуют `project_id` в конфиге (не критично для базовой функциональности)
- Тестирование проводилось в development режиме без TLS (для production необходим TLS)


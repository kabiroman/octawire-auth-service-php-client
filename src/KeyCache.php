<?php

declare(strict_types=1);

namespace Kabiroman\Octawire\AuthService\Client;

use Kabiroman\Octawire\AuthService\Client\Model\PublicKeyInfo;

// KeyCacheConfig определен в Config.php

/**
 * In-memory кэш для публичных ключей с поддержкой graceful ротации
 */
class KeyCache
{
    /**
     * @var array<string, array<string, CachedKeyInfo>> project_id -> key_id -> CachedKeyInfo
     */
    private array $cache = [];
    private KeyCacheConfig $config;

    public function __construct(?KeyCacheConfig $config = null)
    {
        $this->config = $config ?? new KeyCacheConfig();
    }

    /**
     * Получение ключа из кэша
     *
     * @param string $projectId ID проекта
     * @param string|null $keyId ID ключа (опционально, если null - возвращает primary ключ)
     * @return PublicKeyInfo|null Информация о ключе или null если не найден
     */
    public function get(string $projectId, ?string $keyId = null): ?PublicKeyInfo
    {
        if (!isset($this->cache[$projectId])) {
            return null;
        }

        $projectKeys = $this->cache[$projectId];

        // Если keyId не указан, ищем primary ключ
        if ($keyId === null) {
            foreach ($projectKeys as $cached) {
                if ($cached->keyInfo->isPrimary && !$cached->keyInfo->isExpired()) {
                    return $cached->keyInfo;
                }
            }
            return null;
        }

        // Ищем конкретный ключ
        if (!isset($projectKeys[$keyId])) {
            return null;
        }

        $cached = $projectKeys[$keyId];

        // Проверяем, не истек ли кэш
        if ($cached->expiresAt > 0 && time() >= $cached->expiresAt) {
            unset($this->cache[$projectId][$keyId]);
            return null;
        }

        // Проверяем, не истек ли сам ключ
        if ($cached->keyInfo->isExpired()) {
            unset($this->cache[$projectId][$keyId]);
            return null;
        }

        return $cached->keyInfo;
    }

    /**
     * Сохранение ключа в кэш
     *
     * @param string $projectId ID проекта
     * @param PublicKeyInfo $keyInfo Информация о ключе
     * @param int $cacheUntil Unix timestamp до которого кэш валиден (0 = использовать TTL из конфига)
     */
    public function set(string $projectId, PublicKeyInfo $keyInfo, int $cacheUntil = 0): void
    {
        // Проверяем ограничение размера
        if ($this->config->maxSize > 0 && count($this->cache) >= $this->config->maxSize) {
            // Удаляем самый старый проект (простая стратегия)
            $firstProjectId = array_key_first($this->cache);
            if ($firstProjectId !== null) {
                unset($this->cache[$firstProjectId]);
            }
        }

        // Инициализируем map для проекта, если его нет
        if (!isset($this->cache[$projectId])) {
            $this->cache[$projectId] = [];
        }

        // Вычисляем время истечения кэша
        $expiresAt = 0;
        if ($cacheUntil > 0) {
            $expiresAt = $cacheUntil;
        } elseif ($this->config->ttl > 0) {
            $expiresAt = time() + $this->config->ttl;
        } elseif ($keyInfo->expiresAt > 0) {
            $expiresAt = $keyInfo->expiresAt;
        }

        $this->cache[$projectId][$keyInfo->keyId] = new CachedKeyInfo(
            keyInfo: $keyInfo,
            expiresAt: $expiresAt
        );
    }

    /**
     * Сохранение всех активных ключей из ответа GetPublicKey (для graceful ротации)
     *
     * @param string $projectId ID проекта
     * @param array<PublicKeyInfo> $activeKeys Массив активных ключей
     * @param int $cacheUntil Unix timestamp до которого кэш валиден
     */
    public function setAllActive(string $projectId, array $activeKeys, int $cacheUntil = 0): void
    {
        if (empty($activeKeys)) {
            return;
        }

        // Проверяем ограничение размера
        if ($this->config->maxSize > 0 && count($this->cache) >= $this->config->maxSize) {
            $firstProjectId = array_key_first($this->cache);
            if ($firstProjectId !== null) {
                unset($this->cache[$firstProjectId]);
            }
        }

        // Инициализируем map для проекта
        if (!isset($this->cache[$projectId])) {
            $this->cache[$projectId] = [];
        }

        // Вычисляем время истечения кэша
        $expiresAt = 0;
        if ($cacheUntil > 0) {
            $expiresAt = $cacheUntil;
        } elseif ($this->config->ttl > 0) {
            $expiresAt = time() + $this->config->ttl;
        }

        // Сохраняем все активные ключи
        foreach ($activeKeys as $keyInfo) {
            $keyExpiresAt = $expiresAt;
            // Если не указано общее время истечения, используем время истечения ключа
            if ($keyExpiresAt === 0 && $keyInfo->expiresAt > 0) {
                $keyExpiresAt = $keyInfo->expiresAt;
            }

            $this->cache[$projectId][$keyInfo->keyId] = new CachedKeyInfo(
                keyInfo: $keyInfo,
                expiresAt: $keyExpiresAt
            );
        }
    }

    /**
     * Получение всех активных ключей для проекта (для graceful ротации)
     *
     * @param string $projectId ID проекта
     * @return array<string, PublicKeyInfo> key_id -> PublicKeyInfo
     */
    public function getAllActive(string $projectId): array
    {
        if (!isset($this->cache[$projectId])) {
            return [];
        }

        $activeKeys = [];
        $now = time();

        foreach ($this->cache[$projectId] as $keyId => $cached) {
            // Проверяем, не истек ли кэш
            if ($cached->expiresAt > 0 && $now >= $cached->expiresAt) {
                continue;
            }

            // Проверяем, не истек ли сам ключ
            if ($cached->keyInfo->isExpired()) {
                continue;
            }

            $activeKeys[$keyId] = $cached->keyInfo;
        }

        return $activeKeys;
    }

    /**
     * Инвалидация кэша для проекта
     */
    public function invalidate(string $projectId): void
    {
        unset($this->cache[$projectId]);
    }

    /**
     * Очистка всего кэша
     */
    public function clear(): void
    {
        $this->cache = [];
    }

    /**
     * Очистка истекших ключей
     */
    public function cleanupExpired(): void
    {
        $now = time();

        foreach ($this->cache as $projectId => $projectKeys) {
            foreach ($projectKeys as $keyId => $cached) {
                // Удаляем если истек кэш или сам ключ
                if (($cached->expiresAt > 0 && $now >= $cached->expiresAt) ||
                    $cached->keyInfo->isExpired()) {
                    unset($this->cache[$projectId][$keyId]);
                }
            }

            // Удаляем пустые проекты
            if (empty($this->cache[$projectId])) {
                unset($this->cache[$projectId]);
            }
        }
    }
}

/**
 * Информация о закэшированном ключе
 */
class CachedKeyInfo
{
    public function __construct(
        public readonly PublicKeyInfo $keyInfo,
        public readonly int $expiresAt
    ) {
    }
}


<?php

declare(strict_types=1);

namespace Kabiroman\Octawire\AuthService\Client\Request\JWT;

/**
 * Request для проверки здоровья сервиса
 * Пустой payload согласно спецификации
 */
class HealthCheckRequest
{
    public function toArray(): \stdClass
    {
        return new \stdClass(); // Пустой объект для пустого payload
    }
}


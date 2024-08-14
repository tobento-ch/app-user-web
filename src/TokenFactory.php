<?php

/**
 * TOBENTO
 *
 * @copyright   Tobias Strub, TOBENTO
 * @license     MIT License, see LICENSE file distributed with this source code.
 * @author      Tobias Strub
 * @link        https://www.tobento.ch
 */

declare(strict_types=1);

namespace Tobento\App\User\Web;

use Tobento\Service\Repository\Storage\EntityFactory;

/**
 * TokenFactory
 */
class TokenFactory extends EntityFactory implements TokenFactoryInterface
{
    /**
     * Create an entity from array.
     *
     * @param array $attributes
     * @return TokenInterface
     * @throws \Throwable If cannot create role
     */
    public function createEntityFromArray(array $attributes): TokenInterface
    {
        // Process the columns reading:
        $attributes = $this->columns->processReading($attributes);
        $attributes['issued_at'] ??= (new \DateTimeImmutable())->getTimestamp();
        $attributes['expires_at'] ??= (new \DateTimeImmutable())->getTimestamp();
        
        return new Token(
            id: $attributes['id'] ?? '',
            type: $attributes['type'] ?? '',
            userId: $attributes['user_id'] ?? 0,
            payload: $attributes['payload'] ?? [],
            issuedAt: (new \DateTimeImmutable())->setTimestamp((int)$attributes['issued_at']),
            expiresAt: (new \DateTimeImmutable())->setTimestamp((int)$attributes['expires_at']),
        );
    }
}
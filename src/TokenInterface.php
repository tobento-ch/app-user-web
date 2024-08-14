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

use DateTimeImmutable;

/**
 * TokenInterface
 */
interface TokenInterface
{
    /**
     * Returns the id.
     *
     * @return string
     */
    public function id(): string;
    
    /**
     * Returns a new instance with the specified id.
     *
     * @param string $id
     * @return static
     */
    public function withId(string $id): static;

    /**
     * Returns the type.
     *
     * @return string
     */
    public function type(): string;
    
    /**
     * Returns the user id.
     *
     * @return string|int
     */
    public function userId(): string|int;

    /**
     * Returns the payload.
     *
     * @return array
     */
    public function payload(): array;
    
    /**
     * Returns the point in time the token has been issued.
     *
     * @return DateTimeImmutable
     */
    public function issuedAt(): DateTimeImmutable;
    
    /**
     * Returns the point in time after which the token MUST be considered expired.
     *
     * @return DateTimeImmutable
     */
    public function expiresAt(): DateTimeImmutable;
}
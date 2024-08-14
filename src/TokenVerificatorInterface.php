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

use Tobento\App\User\Web\Exception\VerificationTokenException;
use DateInterval;

/**
 * TokenVerificatorInterface
 */
interface TokenVerificatorInterface
{
    /**
     * Create a new token.
     *
     * @param string $type
     * @param array $payload
     * @param int|DateInterval $expiresAfter
     * @return TokenInterface
     */
    public function createToken(
        string $type,
        array $payload,
        int|DateInterval $expiresAfter = 300,
    ): TokenInterface;
    
    /**
     * Returns the found token, otherwise null.
     *
     * @param string $type
     * @param array $payload
     * @return null|TokenInterface
     */
    public function findToken(string $type, array $payload): null|TokenInterface;
    
    /**
     * Returns the token if verified.
     *
     * @param string $id
     * @param string $type
     * @return TokenInterface
     * @throws VerificationTokenException If token is not found, is expired or for any reason invalid.
     */
    public function verifyToken(string $id, string $type): TokenInterface;
    
    /**
     * Delete token.
     *
     * @param string $id
     * @param string $type
     * @return void
     */
    public function deleteToken(string $id, string $type): void;
    
    /**
     * Delete all expired tokens.
     *
     * @return void
     */
    public function deleteExpiredTokens(): void;
    
    /**
     * Returns the seconds the token is available again or
     * null if the token was not issued less than the specified seconds.
     *
     * @param TokenInterface $token
     * @param int $seconds
     * @return null|int
     */
    public function tokenIssuedLessThan(TokenInterface $token, int $seconds): null|int;
}
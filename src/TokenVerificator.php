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

use Tobento\App\User\Web\Exception\InvalidVerificationTokenException;
use Tobento\App\User\Web\Exception\VerificationTokenException;
use Tobento\App\User\Web\Exception\VerificationTokenExpiredException;
use Tobento\App\User\Web\Exception\VerificationTokenNotFoundException;
use Psr\Clock\ClockInterface;
use DateTimeImmutable;
use DateInterval;

/**
 * Token Verification code manager.
 */
class TokenVerificator implements TokenVerificatorInterface
{
    /**
     * Create a new TokenVerificator.
     *
     * @param TokenRepository $repository
     * @param ClockInterface $clock
     */
    public function __construct(
        protected TokenRepository $repository,
        protected ClockInterface $clock,
    ) {}
    
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
    ): TokenInterface {
        $userId = $payload['userID'] ?? 0;
        
        // delete all codes from user:
        $this->repository->delete(where: [
            'type' => $type,
            'user_id' => $userId,
        ]);
        
        // create token:
        $id = $this->createUniqueId(length: 32, type: $type);
        $tokenHash = $this->randomHash(32);
        $tokenId = sprintf('%s:%s', $id, $tokenHash);
        $expiresAt = $this->getExpiresAt($this->clock->now(), $expiresAfter);
        $payload['tokenID'] = hash('sha512', $tokenHash);
        
        // store code:
        $token = $this->repository->create([
            'id' => $id,
            'type' => $type,
            'user_id' => $userId,
            'payload' => $payload,
            'issued_at' => $this->clock->now(),
            'expires_at' => $expiresAt,
        ]);
        
        return $token->withId($tokenId);
    }
    
    /**
     * Returns the found token, otherwise null.
     *
     * @param string $type
     * @param array $payload
     * @return null|TokenInterface
     */
    public function findToken(string $type, array $payload): null|TokenInterface
    {
        $userId = $payload['userID'] ?? 0;
        
        return $this->repository->findOne(where: [
            'type' => $type,
            'user_id' => $userId,
            'expires_at' => ['>=' => $this->clock->now()->getTimestamp()],
        ]);
    }
    
    /**
     * Returns the token if verified.
     *
     * @param string $id
     * @param string $type
     * @return TokenInterface
     * @throws VerificationTokenException If token is not found, is expired or for any reason invalid.
     */
    public function verifyToken(string $id, string $type): TokenInterface
    {
        if (!str_contains($id, ':')) {
            throw new InvalidVerificationTokenException();
        }

        [$itemId, $hash] = explode(':', $id, 2);
        
        $token = $this->repository->findOne(where: ['id' => $itemId, 'type' => $type]);
        
        if (is_null($token)) {
            throw new VerificationTokenNotFoundException();
        }

        if ($token->expiresAt() < $this->clock->now()) {
            throw new VerificationTokenExpiredException(token: $token);
        }
        
        $tokenId = $token->payload()['tokenID'] ?? '';
        
        if (!hash_equals($tokenId, hash('sha512', $hash))) {
            throw new InvalidVerificationTokenException(token: $token);
        }
        
        return $token->withId($id);
    }
    
    /**
     * Delete token.
     *
     * @param string $id
     * @param string $type
     * @return void
     */
    public function deleteToken(string $id, string $type): void
    {
        if (!str_contains($id, ':')) {
            return;
        }

        [$itemId] = explode(':', $id, 2);
        
        $this->repository->delete(where: [
            'id' => $itemId,
            'type' => $type,
        ]);
    }
    
    /**
     * Delete all expired tokens.
     *
     * @return void
     */
    public function deleteExpiredTokens(): void
    {
        $this->repository->delete(where: [
            'expires_at' => ['<' => $this->clock->now()->getTimestamp()],
        ]);
    }
    
    /**
     * Returns the seconds the token is available again or
     * null if the token was not issued less than the specified seconds.
     *
     * @param TokenInterface $token
     * @param int $seconds
     * @return null|int
     */
    public function tokenIssuedLessThan(TokenInterface $token, int $seconds): null|int
    {
        $availableAt = $token->issuedAt()->modify('+'.$seconds.' seconds');

        if ($availableAt > $this->clock->now()) {
            return $availableAt->getTimestamp() - $this->clock->now()->getTimestamp();
        }
        
        return null;
    }
    
    /**
     * Create a rand hash.
     *
     * @param positive-int $length
     * @return string
     */
    private function randomHash(int $length): string
    {
        return substr(bin2hex(random_bytes($length)), 0, $length);
    }
    
    /**
     * Create a unique id.
     *
     * @param positive-int $length
     * @param string $type
     * @return string
     */
    protected function createUniqueId(int $length, string $type): string
    {
        $id = $this->randomHash($length);
        
        $code = $this->repository->findOne(where: [
            'id' => $id,
            'type' => $type,
        ]);
        
        if (is_null($code)) {
            return $id;
        }
        
        return $this->createUniqueId($length, $type);
    }
    
    /**
     * Returns the point in time after which the code MUST be considered expired.
     *
     * @param DateTimeImmutable $now
     * @param int|DateInterval $expiresAfter
     * @return DateTimeImmutable
     */
    protected function getExpiresAt(DateTimeImmutable $now, int|DateInterval $expiresAfter): DateTimeImmutable
    {
        if (is_int($expiresAfter)) {
            $modified = $now->modify('+'.$expiresAfter.' seconds');
            return $modified === false ? $now : $modified;
        }
        
        return $now->add($expiresAfter);
    }
}
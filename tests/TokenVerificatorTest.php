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

namespace Tobento\App\User\Web\Test;

use PHPUnit\Framework\TestCase;
use Tobento\App\User\Web\TokenInterface;
use Tobento\App\User\Web\TokenVerificator;
use Tobento\App\User\Web\TokenVerificatorInterface;
use Tobento\App\User\Web\TokenRepository;
use Tobento\App\User\Web\TokenFactory;
use Tobento\App\User\Web\Exception\InvalidVerificationTokenException;
use Tobento\App\User\Web\Exception\VerificationTokenExpiredException;
use Tobento\App\User\Web\Exception\VerificationTokenNotFoundException;
use Tobento\Service\Storage\InMemoryStorage;
use Tobento\Service\Storage\StorageInterface;
use Tobento\Service\Clock\FrozenClock;
use Psr\Clock\ClockInterface;

class TokenVerificatorTest extends TestCase
{
    private function createTokenVerificator(
        null|StorageInterface $storage = null,
        null|ClockInterface $clock = null
    ): TokenVerificator {
        return new TokenVerificator(
            repository: new TokenRepository(
                storage: $storage ?: new InMemoryStorage([]),
                table: 'tokens',
                entityFactory: new TokenFactory(),
            ),
            clock: $clock ?: new FrozenClock(),
        );
    }
    
    public function testThatImplementsTokenVerificatorInterface()
    {
        $this->assertInstanceof(TokenVerificatorInterface::class, $this->createTokenVerificator());
    }

    public function testCreateTokenMethod()
    {
        $clock = new FrozenClock();
        $verificator = $this->createTokenVerificator(clock: $clock);
        $token = $verificator->createToken(type: 'foo', payload: ['userID' => 5], expiresAfter: 30);
        
        $this->assertTrue(strlen($token->id()) > 30);
        $this->assertSame('foo', $token->type());
        $this->assertSame(5, $token->userId());
        $this->assertSame(['userID', 'tokenID'], array_keys($token->payload()));
        $this->assertTrue($clock->now()->getTimestamp() === $token->issuedAt()->getTimestamp());
        $this->assertTrue($clock->now()->modify('+30 seconds')->getTimestamp() === $token->expiresAt()->getTimestamp());
    }
    
    public function testFindTokenMethod()
    {
        $verificator = $this->createTokenVerificator();
        $verificator->createToken(type: 'foo', payload: ['userID' => 5], expiresAfter: 30);
        
        $token = $verificator->findToken(type: 'foo', payload: ['userID' => 5]);
        $this->assertSame(5, $token->userId());
        
        $token = $verificator->findToken(type: 'foo', payload: ['userID' => 6]);
        $this->assertNull($token);
    }
    
    public function testFindTokenMethodReturnsNullIfTokenExpired()
    {
        $verificator = $this->createTokenVerificator();
        $verificator->createToken(type: 'foo', payload: ['userID' => 5], expiresAfter: 30);
        
        $token = $verificator->findToken(type: 'foo', payload: ['userID' => 5]);
        $this->assertSame(5, $token->userId());
        
        $verificator = $this->createTokenVerificator(clock: (new FrozenClock())->modify('+31 seconds'));
        
        $token = $verificator->findToken(type: 'foo', payload: ['userID' => 5]);
        $this->assertNull($token);
    }
    
    public function testVerifyTokenMethodValidToken()
    {
        $verificator = $this->createTokenVerificator();
        
        $token = $verificator->createToken(type: 'foo', payload: [], expiresAfter: 30);
        
        $verificator->verifyToken(id: $token->id(), type: 'foo');
        
        $this->assertTrue(true);
    }
    
    public function testVerifyTokenMethodThrowsVerificationTokenNotFoundExceptionIfNotFound()
    {
        $this->expectException(VerificationTokenNotFoundException::class);
        
        $storage = new InMemoryStorage([]);
        
        $verificator = $this->createTokenVerificator(storage: $storage);
        
        $verificator->verifyToken(id: 'item:hash', type: 'foo');
    }
    
    public function testVerifyTokenMethodThrowsVerificationTokenExpiredExceptionIfExpired()
    {
        $this->expectException(VerificationTokenExpiredException::class);
        
        $storage = new InMemoryStorage([]);
        
        $verificator = $this->createTokenVerificator(storage: $storage);
        
        $token = $verificator->createToken(type: 'foo', payload: [], expiresAfter: 30);
        
        $verificator = $this->createTokenVerificator(
            storage: $storage,
            clock: (new FrozenClock())->modify('+31 seconds')
        );
        
        $verificator->verifyToken(id: $token->id(), type: 'foo');
    }
    
    public function testVerifyTokenMethodThrowsInvalidVerificationTokenExceptionIfInvalid()
    {
        $this->expectException(InvalidVerificationTokenException::class);
        
        $storage = new InMemoryStorage([]);
        
        $verificator = $this->createTokenVerificator(storage: $storage);
        
        $token = $verificator->createToken(type: 'bar', payload: [], expiresAfter: 30);
        
        $verificator->verifyToken(id: explode(':', $token->id(), 2)[0].':invalid-hash', type: 'bar');
    }
    
    public function testDeleteTokenMethod()
    {
        $storage = new InMemoryStorage([]);
        
        $verificator = $this->createTokenVerificator(storage: $storage);
        
        $this->assertSame(0, $storage->table('tokens')->count());
        
        $token = $verificator->createToken(type: 'foo', payload: [], expiresAfter: 30);
        
        $this->assertSame(1, $storage->table('tokens')->count());
        
        $verificator->deleteToken(id: $token->id(), type: 'foo');
        
        $this->assertSame(0, $storage->table('tokens')->count());
    }
    
    public function testDeleteExpiredTokensMethod()
    {
        $storage = new InMemoryStorage([]);
        
        $verificator = $this->createTokenVerificator(storage: $storage);
        
        $verificator->createToken(type: 'foo', payload: ['userID' => 1], expiresAfter: 20);
        $verificator->createToken(type: 'foo', payload: ['userID' => 2], expiresAfter: 30);
        $verificator->createToken(type: 'foo', payload: ['userID' => 3], expiresAfter: 60);
        $verificator->createToken(type: 'foo', payload: ['userID' => 4], expiresAfter: 80);
        
        $this->assertSame(4, $storage->table('tokens')->count());
        
        $verificator = $this->createTokenVerificator(
            storage: $storage,
            clock: (new FrozenClock())->modify('+31 seconds')
        );
        
        $verificator->deleteExpiredTokens();
        
        $this->assertSame(2, $storage->table('tokens')->count());
    }
    
    public function testTokenIssuedLessThanMethodReturnsAvailableInSecondsIfLess()
    {
        $storage = new InMemoryStorage([]);
        
        $verificator = $this->createTokenVerificator(storage: $storage);
        
        $token = $verificator->createToken(type: 'foo', payload: [], expiresAfter: 20);
        
        $availableInSeconds = $verificator->tokenIssuedLessThan(token: $token, seconds: 10);
        $this->assertSame(10, $availableInSeconds);
    }
    
    public function testTokenIssuedLessThanMethodReturnsNullIfNotLess()
    {
        $storage = new InMemoryStorage([]);
        
        $verificator = $this->createTokenVerificator(storage: $storage);
        
        $token = $verificator->createToken(type: 'foo', payload: [], expiresAfter: 40);
        
        $verificator = $this->createTokenVerificator(
            storage: $storage,
            clock: (new FrozenClock())->modify('+30 seconds')
        );
        
        $availableInSeconds = $verificator->tokenIssuedLessThan(token: $token, seconds: 20);
        $this->assertSame(null, $availableInSeconds);
    }
    
    public function testTokenIdIsHashedInRepository()
    {
        $storage = new InMemoryStorage([]);
        
        $verificator = $this->createTokenVerificator(storage: $storage);
        
        $token = $verificator->createToken(type: 'foo', payload: [], expiresAfter: 40);
        
        $payload = json_decode($storage->table('tokens')->first()['payload'] ?? '', true);
        
        $this->assertFalse($token->id() === $payload['tokenID']);
    }
}
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
use Tobento\App\User\User;
use Tobento\App\User\Web\TokenInterface;
use Tobento\App\User\Web\PinCodeVerificator;
use Tobento\App\User\Web\PinCodeVerificatorInterface;
use Tobento\App\User\Web\VerificatorHashKey;
use Tobento\App\User\Web\TokenRepository;
use Tobento\App\User\Web\TokenFactory;
use Tobento\App\User\Web\Exception\InvalidVerificationTokenException;
use Tobento\App\User\Web\Exception\VerificationTokenExpiredException;
use Tobento\App\User\Web\Exception\VerificationTokenNotFoundException;
use Tobento\Service\Clock\FrozenClock;
use Tobento\Service\Notifier\NotifierInterface;
use Tobento\Service\Notifier\NotificationInterface;
use Tobento\Service\Notifier\RecipientInterface;
use Tobento\Service\Notifier\ChannelMessages;
use Tobento\Service\Notifier\ChannelMessage;
use Tobento\Service\Storage\InMemoryStorage;
use Tobento\Service\Storage\StorageInterface;
use Psr\Clock\ClockInterface;

class PinCodeVerificatorTest extends TestCase
{
    private function createPinCodeVerificator(
        null|StorageInterface $storage = null,
        null|NotifierInterface $notifier = null,
        null|ClockInterface $clock = null
    ): PinCodeVerificator {
        return new PinCodeVerificator(
            hashKey: new VerificatorHashKey('secret-hash-key'),
            repository: new TokenRepository(
                storage: $storage ?: new InMemoryStorage([]),
                table: 'tokens',
                entityFactory: new TokenFactory(),
            ),
            notifier: $notifier ?: $this->createNotifier(),
            clock: $clock ?: new FrozenClock(),
        );
    }
    
    private function createNotifier(): NotifierInterface
    {
        return new class() implements NotifierInterface {
            public function __construct(
                private $sentMessages = [],
            ) {}
            
            public function sentMessages()
            {
                return $this->sentMessages;
            }            
            
            public function send(NotificationInterface $notification, RecipientInterface ...$recipients): iterable
            {
                foreach($recipients as $recipient) {
                    $channels = $notification->getChannels($recipient);
                    $messages = new ChannelMessages($recipient, $notification);
                    
                    foreach($channels as $channel) {
                        $messages->add(new ChannelMessage($channel, new \stdClass('foo')));
                    }
                    
                    $this->sentMessages[] = $messages;
                }
                
                return $this->sentMessages;
            }
        };
    }
    
    public function testThatImplementsPinCodeVerificatorInterface()
    {
        $this->assertInstanceof(PinCodeVerificatorInterface::class, $this->createPinCodeVerificator());
    }

    public function testSendCodeMethodTokenValues()
    {
        $clock = new FrozenClock();
        $verificator = $this->createPinCodeVerificator(clock: $clock);
        $token = $verificator->sendCode(
            type: 'foo',
            user: new User(id: 5),
            expiresAfter: 30,
        );
        
        $this->assertTrue(strlen($token->id()) === 6);
        $this->assertSame('foo', $token->type());
        $this->assertSame(5, $token->userId());
        $this->assertSame([], array_keys($token->payload()));
        $this->assertTrue($clock->now()->getTimestamp() === $token->issuedAt()->getTimestamp());
        $this->assertTrue($clock->now()->modify('+30 seconds')->getTimestamp() === $token->expiresAt()->getTimestamp());
    }
    
    public function testSendCodeMethodNotificationIsSent()
    {
        $clock = new FrozenClock();
        $notifier = $this->createNotifier();
        $verificator = $this->createPinCodeVerificator(notifier: $notifier);
        $token = $verificator->sendCode(
            type: 'foo',
            user: new User(id: 5),
            expiresAfter: 30,
            channels: ['mail'],
        );
        
        $channelMessages = $notifier->sentMessages()[0] ?? null;
        $this->assertSame(['mail'], $channelMessages?->channelNames());
        $this->assertSame(1, $channelMessages?->successful()->count());
    }
    
    public function testSendCodeMethodNotificationIsSentMultiple()
    {
        $clock = new FrozenClock();
        $notifier = $this->createNotifier();
        $verificator = $this->createPinCodeVerificator(notifier: $notifier);
        $token = $verificator->sendCode(
            type: 'foo',
            user: new User(id: 5),
            expiresAfter: 30,
            channels: ['mail', 'sms'],
        );
        
        $channelMessages = $notifier->sentMessages()[0] ?? null;
        $this->assertSame(['mail', 'sms'], $channelMessages?->channelNames());
        $this->assertSame(2, $channelMessages?->successful()->count());
    }
    
    public function testFindCodeMethod()
    {
        $verificator = $this->createPinCodeVerificator();
        $verificator->sendCode(type: 'foo', user: new User(id: 5), expiresAfter: 30, channels: ['mail']);
        
        $token = $verificator->findCode(type: 'foo', user: new User(id: 5));
        $this->assertSame(5, $token->userId());
        
        $token = $verificator->findCode(type: 'foo', user: new User(id: 6));
        $this->assertNull($token);
    }
    
    public function testFindCodeMethodReturnsNullIfTokenExpired()
    {
        $verificator = $this->createPinCodeVerificator();
        $verificator->sendCode(type: 'foo', user: new User(id: 5), expiresAfter: 30, channels: ['mail']);
        
        $token = $verificator->findCode(type: 'foo', user: new User(id: 5));
        $this->assertSame(5, $token->userId());
        
        $verificator = $this->createPinCodeVerificator(clock: (new FrozenClock())->modify('+31 seconds'));
        
        $token = $verificator->findCode(type: 'foo', user: new User(id: 5));
        $this->assertNull($token);
    }
    
    public function testHasCodeMethod()
    {
        $verificator = $this->createPinCodeVerificator();
        $verificator->sendCode(type: 'foo', user: new User(id: 5), expiresAfter: 30, channels: ['mail']);
        
        $this->assertTrue($verificator->hasCode(type: 'foo', user: new User(id: 5)));
        $this->assertFalse($verificator->hasCode(type: 'foo', user: new User(id: 6)));
    }
    
    public function testHasCodeMethodReturnsFalseIfTokenExpired()
    {
        $verificator = $this->createPinCodeVerificator();
        $verificator->sendCode(type: 'foo', user: new User(id: 5), expiresAfter: 30, channels: ['mail']);
        
        $this->assertTrue($verificator->hasCode(type: 'foo', user: new User(id: 5)));
        
        $verificator = $this->createPinCodeVerificator(clock: (new FrozenClock())->modify('+31 seconds'));
        
        $this->assertFalse($verificator->hasCode(type: 'foo', user: new User(id: 5)));
    }
    
    public function testVerifyCodeMethodValidToken()
    {
        $verificator = $this->createPinCodeVerificator();
        
        $token = $verificator->sendCode(type: 'foo', user: new User(id: 5), expiresAfter: 30, channels: ['mail']);

        $verificator->verifyCode(code: $token->id(), type: 'foo', user: new User(id: 5));
        
        $this->assertTrue(true);
    }
    
    public function testVerifyTokenMethodThrowsVerificationTokenNotFoundExceptionIfNotFound()
    {
        $this->expectException(VerificationTokenNotFoundException::class);
        
        $storage = new InMemoryStorage([]);
        
        $verificator = $this->createPinCodeVerificator(storage: $storage);
        
        $verificator->verifyCode(code: '123456', type: 'foo', user: new User(id: 5));
    }
    
    public function testVerifyTokenMethodThrowsVerificationTokenExpiredExceptionIfExpired()
    {
        $this->expectException(VerificationTokenExpiredException::class);
        
        $storage = new InMemoryStorage([]);
        
        $verificator = $this->createPinCodeVerificator(storage: $storage);
        
        $token = $verificator->sendCode(type: 'foo', user: new User(id: 5), expiresAfter: 30, channels: ['mail']);
        
        $verificator = $this->createPinCodeVerificator(
            storage: $storage,
            clock: (new FrozenClock())->modify('+31 seconds')
        );
        
        $verificator->verifyCode(code: $token->id(), type: 'foo', user: new User(id: 5));
    }
    
    public function testVerifyTokenMethodThrowsInvalidVerificationTokenExceptionIfInvalid()
    {
        $this->expectException(InvalidVerificationTokenException::class);
        
        $storage = new InMemoryStorage([]);

        $verificator = $this->createPinCodeVerificator(storage: $storage);
        
        $token = $verificator->sendCode(type: 'foo', user: new User(id: 5), expiresAfter: 30, channels: ['mail']);
        
        $verificator->verifyCode(code: '12345', type: 'foo', user: new User(id: 5));
    }
    
    public function testDeleteCodeMethod()
    {
        $storage = new InMemoryStorage([]);
        
        $verificator = $this->createPinCodeVerificator(storage: $storage);
        
        $this->assertSame(0, $storage->table('tokens')->count());
        
        $token = $verificator->sendCode(type: 'foo', user: new User(id: 5), expiresAfter: 30, channels: ['mail']);
        
        $this->assertSame(1, $storage->table('tokens')->count());
        
        $verificator->deleteCode(type: 'foo', user: new User(id: 5));
        
        $this->assertSame(0, $storage->table('tokens')->count());
    }
    
    public function testDeleteExpiredCodesMethod()
    {
        $storage = new InMemoryStorage([]);
        
        $verificator = $this->createPinCodeVerificator(storage: $storage);
        
        $verificator->sendCode(type: 'foo', user: new User(id: 1), expiresAfter: 20, channels: ['mail']);
        $verificator->sendCode(type: 'foo', user: new User(id: 2), expiresAfter: 30, channels: ['mail']);
        $verificator->sendCode(type: 'foo', user: new User(id: 3), expiresAfter: 60, channels: ['mail']);
        $verificator->sendCode(type: 'foo', user: new User(id: 4), expiresAfter: 80, channels: ['mail']);
        
        $this->assertSame(4, $storage->table('tokens')->count());
        
        $verificator = $this->createPinCodeVerificator(
            storage: $storage,
            clock: (new FrozenClock())->modify('+31 seconds')
        );
        
        $verificator->deleteExpiredCodes();
        
        $this->assertSame(2, $storage->table('tokens')->count());
    }
    
    public function testCodeIssuedLessThanMethodReturnsAvailableInSecondsIfLess()
    {
        $storage = new InMemoryStorage([]);
        
        $verificator = $this->createPinCodeVerificator(storage: $storage);
        
        $token = $verificator->sendCode(type: 'foo', user: new User(id: 5), expiresAfter: 20, channels: ['mail']);
        
        $availableInSeconds = $verificator->codeIssuedLessThan(token: $token, seconds: 10);
        $this->assertSame(10, $availableInSeconds);
    }
    
    public function testCodeIssuedLessThanMethodReturnsNullIfNotLess()
    {
        $storage = new InMemoryStorage([]);
        
        $verificator = $this->createPinCodeVerificator(storage: $storage);
        
        $token = $verificator->sendCode(type: 'foo', user: new User(id: 5), expiresAfter: 40, channels: ['mail']);
        
        $verificator = $this->createPinCodeVerificator(
            storage: $storage,
            clock: (new FrozenClock())->modify('+30 seconds')
        );
        
        $availableInSeconds = $verificator->codeIssuedLessThan(token: $token, seconds: 20);
        $this->assertSame(null, $availableInSeconds);
    }
    
    public function testCodeIsHashedInRepository()
    {
        $storage = new InMemoryStorage([]);
        
        $verificator = $this->createPinCodeVerificator(storage: $storage);
        
        $token = $verificator->sendCode(type: 'foo', user: new User(id: 5), expiresAfter: 30, channels: ['mail']);
        
        $this->assertFalse($token->id() === $storage->table('tokens')->first()['id']);
    }
}
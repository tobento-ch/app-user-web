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

use Tobento\App\User\UserInterface;
use Tobento\App\User\Web\Exception\InvalidVerificationTokenException;
use Tobento\App\User\Web\Exception\VerificationTokenException;
use Tobento\App\User\Web\Exception\VerificationTokenExpiredException;
use Tobento\App\User\Web\Exception\VerificationTokenNotFoundException;
use Tobento\App\User\Web\Notification;
use Tobento\Service\Notifier\NotifierInterface;
use Tobento\Service\Notifier\NotificationInterface;
use Tobento\Service\Notifier\UserRecipient;
use Tobento\Service\Notifier\Message;
use Tobento\Service\Translation\TranslatorInterface;
use Psr\Clock\ClockInterface;
use DateTimeImmutable;
use DateInterval;

/**
 * PinCodeVerificator.
 */
class PinCodeVerificator implements PinCodeVerificatorInterface
{
    /**
     * Create a new PinCodeVerificator.
     *
     * @param VerificatorHashKey $hashKey
     * @param TokenRepository $repository
     * @param NotifierInterface $notifier
     * @param ClockInterface $clock
     */
    public function __construct(
        protected VerificatorHashKey $hashKey,
        protected TokenRepository $repository,
        protected NotifierInterface $notifier,
        protected ClockInterface $clock,
    ) {}
    
    /**
     * Send verification code.
     *
     * @param string $type
     * @param UserInterface $user
     * @param int|DateInterval $expiresAfter
     * @param null|string $code
     * @param array $channels The channels to send the code. Empty all channels.
     * @return TokenInterface
     */
    public function sendCode(
        string $type,
        UserInterface $user,
        int|DateInterval $expiresAfter,
        null|string $code = null,
        array $channels = [],
    ): TokenInterface {
        // delete all code tokens from user:
        $this->repository->delete(where: [
            'type' => $type,
            'user_id' => $user->id(),
        ]);
        
        // create code:
        $code = $code ?: $this->createCode();
        $expiresAt = $this->getExpiresAt($this->clock->now(), $expiresAfter);
        $id = hash_hmac('sha256', $code, (string)$this->hashKey);
        
        // store code:
        $token = $this->repository->create([
            'id' => $id,
            'type' => $type,
            'user_id' => $user->id(),
            'issued_at' => $this->clock->now(),
            'expires_at' => $expiresAt,
        ]);
        
        $token = $token->withId($code);
        
        // create and send notification with code:
        $notification = $this->createNotification(token: $token, user: $user, channels: $channels);

        // The receiver of the notification:
        $recipient = new UserRecipient(user: $user, channels: $channels);

        // Send the notification to the recipient:
        $this->notifier->send($notification, $recipient);
        
        return $token;
    }
    
    /**
     * Returns the found code, otherwise null.
     *
     * @param string $type
     * @param UserInterface $user
     * @return null|TokenInterface
     */
    public function findCode(string $type, UserInterface $user): null|TokenInterface
    {
        return $this->repository->findOne(where: [
            'type' => $type,
            'user_id' => $user->id(),
            'expires_at' => ['>=' => $this->clock->now()->getTimestamp()],
        ]);
    }
    
    /**
     * Returns true if code exists and is not expired, otherwise false.
     *
     * @param string $type
     * @param UserInterface $user
     * @return bool
     */
    public function hasCode(string $type, UserInterface $user): bool
    {
        $token = $this->repository->findOne(where: [
            'type' => $type,
            'user_id' => $user->id(),
            'expires_at' => ['>=' => $this->clock->now()->getTimestamp()],
        ]);
        
        return is_null($token) ? false : true;
    }
    
    /**
     * Returns verified code.
     *
     * @param string $code
     * @param string $type
     * @param UserInterface $user
     * @return TokenInterface
     * @throws VerificationTokenException If code is not found, is expired or for any reason invalid.
     */
    public function verifyCode(string $code, string $type, UserInterface $user): TokenInterface
    {
        // Get code from storage:
        $token = $this->repository->findOne(where: ['type' => $type, 'user_id' => $user->id()]);
        
        if (is_null($token)) {
            throw new VerificationTokenNotFoundException();
        }
        
        if ($token->expiresAt() < $this->clock->now()) {
            throw new VerificationTokenExpiredException(token: $token);
        }
        
        if (!hash_equals($token->id(), hash_hmac('sha256', $code, (string)$this->hashKey))) {
            throw new InvalidVerificationTokenException(token: $token);
        }
        
        return $token;
    }
    
    /**
     * Delete code.
     *
     * @param string $type
     * @param UserInterface $user
     * @return void
     */
    public function deleteCode(string $type, UserInterface $user): void
    {
        $this->repository->delete(where: [
            'type' => $type,
            'user_id' => $user->id(),
        ]);
    }
    
    /**
     * Delete all expired codes.
     *
     * @return void
     */
    public function deleteExpiredCodes(): void
    {
        $this->repository->delete(where: [
            'expires_at' => ['<' => $this->clock->now()->getTimestamp()],
        ]);
    }
    
    /**
     * Returns the seconds the code is available again or
     * null if the code was not issued less than the specified seconds.
     *
     * @param TokenInterface $token
     * @param int $seconds
     * @return null|int
     */
    public function codeIssuedLessThan(TokenInterface $token, int $seconds): null|int
    {
        $availableAt = $token->issuedAt()->modify('+'.$seconds.' seconds');

        if ($availableAt > $this->clock->now()) {
            return $availableAt->getTimestamp() - $this->clock->now()->getTimestamp();
        }
        
        return null;
    }

    /**
     * Returns the created code.
     *
     * @return string
     */
    protected function createCode(): string
    {
        return (string)random_int(100000, 999999);
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

    /**
     * Create notification.
     *
     * @param TokenInterface $token
     * @param UserInterface $user
     * @param array $channels
     * @return NotificationInterface
     */
    protected function createNotification(
        TokenInterface $token,
        UserInterface $user,
        array $channels,
    ): NotificationInterface {
        return (new Notification\VerificationCode(token: $token))->channels($channels);
    }
}
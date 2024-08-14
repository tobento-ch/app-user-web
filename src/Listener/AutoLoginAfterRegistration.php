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

namespace Tobento\App\User\Web\Listener;

use Tobento\App\User\Web\Event;
use Tobento\App\User\Authentication\AuthInterface;
use Tobento\App\User\Authentication\Authenticated;
use Tobento\App\User\Authentication\Token\TokenStorageInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Clock\ClockInterface;
use DateTimeImmutable;
use DateTimeInterface;
use DateInterval;

/**
 * Automatically login user after successful registration.
 */
class AutoLoginAfterRegistration
{
    /**
     * Create a new AutoLoginAfterRegistration.
     *
     * @param AuthInterface $auth
     * @param TokenStorageInterface $tokenStorage
     * @param ClockInterface $clock
     * @param null|EventDispatcherInterface $eventDispatcher
     * @param int|DateInterval $expiresAfter
     * @param array<array-key, string> $userRoles The user roles which can auto login. Empty array all allowed.
     */
    public function __construct(
        protected AuthInterface $auth,
        protected TokenStorageInterface $tokenStorage,
        protected ClockInterface $clock,
        protected null|EventDispatcherInterface $eventDispatcher = null,
        protected int|DateInterval $expiresAfter = 1500,
        protected array $userRoles = [],
    ) {}
    
    public function __invoke(Event\Registered $event): void
    {
        $user = $event->user();
        
        // Determine if user can auto login depending on its role:
        if (
            !empty($this->userRoles)
            && !in_array($user->getRoleKey(), $this->userRoles)
        ) {
            return;
        }
            
        // Create token and start auth:
        $token = $this->tokenStorage->createToken(
            // Set the payload:
            payload: ['userId' => $user->id(), 'passwordHash' => $user->password()],

            // Set the name of which the user was authenticated via:
            authenticatedVia: 'registration',
            
            // Set the name of which the user was authenticated by (authenticator name) or null if none:
            authenticatedBy: null,
            
            // Set the point in time the token has been issued or null (now):
            issuedAt: $this->clock->now(),
            
            // Set the point in time after which the token MUST be considered expired or null:
            // The time might depend on the token storage e.g. session expiration!
            expiresAt: $this->getExpiresAt($this->clock->now()),
        );
        
        $authenticated = new Authenticated(token: $token, user: $user);
        
        $this->eventDispatcher?->dispatch(new Event\Login(authenticated: $authenticated));
        
        $this->auth->start($authenticated);
    }
    
    /**
     * Returns the point in time after which the token MUST be considered expired.
     *
     * @param DateTimeImmutable $now
     * @return DateTimeInterface
     */
    protected function getExpiresAt(DateTimeImmutable $now): DateTimeInterface
    {
        if (is_int($this->expiresAfter)) {
            $modified = $now->modify('+'.$this->expiresAfter.' seconds');
            return $modified === false ? $now : $modified;
        }
        
        return $now->add($this->expiresAfter);
    }
}
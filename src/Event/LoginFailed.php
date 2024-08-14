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

namespace Tobento\App\User\Web\Event;

use Tobento\App\User\Exception\AuthenticationException;
use Tobento\App\User\Authenticator\AuthenticatorInterface;

/**
 * Event login after a user login attempt failed.
 */
final class LoginFailed
{
    /**
     * Create a new LoginFailed.
     *
     * @param AuthenticationException $exception
     * @param null|AuthenticatorInterface $authenticator
     */
    public function __construct(
        private AuthenticationException $exception,
        private null|AuthenticatorInterface $authenticator = null,
    ) {}
    
    /**
     * Returns the exception.
     *
     * @return AuthenticationException
     */
    public function exception(): AuthenticationException
    {
        return $this->exception;
    }
    
    /**
     * Returns the authenticator.
     *
     * @return null|AuthenticatorInterface
     */
    public function authenticator(): null|AuthenticatorInterface
    {
        return $this->authenticator;
    }
}
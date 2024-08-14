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

use Tobento\App\User\Authenticator\AuthenticatorInterface;
use Exception;

/**
 * Event after a user password reset attempt failed.
 */
final class PasswordResetFailed
{
    /**
     * Create a new PasswordResetFailed.
     *
     * @param string $step The process step such as 'identity' or 'reset'.
     * @param Exception $exception
     * @param null|AuthenticatorInterface $authenticator
     */
    public function __construct(
        private string $step,
        private Exception $exception,
        private null|AuthenticatorInterface $authenticator = null,
    ) {}

    /**
     * Returns the step.
     *
     * @return string
     */
    public function step(): string
    {
        return $this->step;
    }
    
    /**
     * Returns the exception.
     *
     * @return Exception
     */
    public function exception(): Exception
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
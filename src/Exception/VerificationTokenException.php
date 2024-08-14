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

namespace Tobento\App\User\Web\Exception;

use Tobento\App\User\Web\TokenInterface;
use RuntimeException;
use Throwable;

/**
 * VerificationTokenException
 */
class VerificationTokenException extends RuntimeException
{
    /**
     * Create a new TokenException.
     *
     * @param string $message
     * @param int $code
     * @param null|Throwable $previous
     * @param null|TokenInterface $token
     */
    public function __construct(
        string $message = 'Invalid Verification Token.',
        int $code = 0,
        null|Throwable $previous = null,
        protected null|TokenInterface $token = null,
    ) {
        parent::__construct($message, $code, $previous);
    }
    
    /**
     * Returns the token.
     *
     * @return null|TokenInterface
     */
    public function token(): null|TokenInterface
    {
        return $this->token;
    }
}
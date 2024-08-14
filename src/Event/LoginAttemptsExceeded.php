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

use Psr\Http\Message\ServerRequestInterface;
use Tobento\App\RateLimiter\RateLimiterInterface;

/**
 * Event after a user has exceeded the maximal number of login attempts.
 */
final class LoginAttemptsExceeded
{
    /**
     * Create a new LoginAttemptsExceeded.
     *
     * @param RateLimiterInterface $rateLimiter
     * @param ServerRequestInterface $request
     */
    public function __construct(
        private RateLimiterInterface $rateLimiter,
        private ServerRequestInterface $request,
    ) {}
    
    /**
     * Returns the rate limiter.
     *
     * @return RateLimiterInterface
     */
    public function rateLimiter(): RateLimiterInterface
    {
        return $this->rateLimiter;
    }
    
    /**
     * Returns the request.
     *
     * @return ServerRequestInterface
     */
    public function request(): ServerRequestInterface
    {
        return $this->request;
    }
}
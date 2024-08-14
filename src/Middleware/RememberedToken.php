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

namespace Tobento\App\User\Web\Middleware;

use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Clock\ClockInterface;
use Tobento\App\User\Authentication\AuthInterface;
use Tobento\App\User\Authentication\Authenticated;
use Tobento\App\User\Authentication\Token\TokenInterface;
use Tobento\App\User\Authentication\Token\TokenStorageInterface;
use Tobento\App\User\Authenticator;
use Tobento\App\User\Exception\AuthenticationException;
use DateInterval;

/**
 * With the RememberedToken middleware you can define
 * the period of time after the token is considered as remembered.
 *
 * In additions, it strenghten the token verification after it is considered as rembered.
 */
class RememberedToken implements MiddlewareInterface
{
    /**
     * Create a new RememberedToken.
     *
     * @param ClockInterface $clock
     * @param AuthInterface $auth
     * @param TokenStorageInterface $tokenStorage
     * @param int|DateInterval $isRememberedAfter
     *   The period of time from the present after which the token is considered as remembered.
     *   An integer parameter is understood to be the time in seconds after the token is considered as remembered.
     */
    public function __construct(
        protected ClockInterface $clock,
        protected AuthInterface $auth,
        protected TokenStorageInterface $tokenStorage,
        protected int|DateInterval $isRememberedAfter = 1500,
    ) {}
    
    /**
     * Process the middleware.
     *
     * @param ServerRequestInterface $request
     * @param RequestHandlerInterface $handler
     * @return ResponseInterface
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (!$this->auth->hasAuthenticated()) {
            return $handler->handle($request);
        }
        
        if ($this->isTokenConsideredAsRemembered($this->auth->getAuthenticated()->token())) {
            $this->renewToken($this->auth, $this->tokenStorage);
        }
        
        $token = $this->auth->getAuthenticated()->token();
        $user = $this->auth->getAuthenticated()->user();
        
        if ($token->authenticatedVia() === 'remembered') {
            try {
                $this->createTokenVerifier($request)->verify($token, $user);
            } catch (AuthenticationException $e) {
                // we close auth, oterwise every page gets forbidden
                // if we would just let throw the exception.
                $this->auth->close();
            }
            
            return $handler->handle($request);
        }
        
        // Handle the response:
        return $handler->handle($request);
    }
    
    /**
     * Returns a newly created token verifier.
     *
     * @param ServerRequestInterface $request
     * @return Authenticator\TokenVerifierInterface
     */
    protected function createTokenVerifier(ServerRequestInterface $request): Authenticator\TokenVerifierInterface
    {
        return new Authenticator\TokenVerifiers(
            new Authenticator\TokenPasswordHashVerifier(
                // The attribute name of the payload:
                name: 'passwordHash',
            ),
            new Authenticator\TokenPayloadVerifier(
                // Specify the payload attribute name:
                name: 'remoteAddress',

                // Specify the value to match:
                value: $request->getServerParams()['REMOTE_ADDR'] ?? null,
            ),
            new Authenticator\TokenPayloadVerifier(
                // Specify the payload attribute name:
                name: 'userAgent',

                // Specify the value to match:
                value: $request->getServerParams()['HTTP_USER_AGENT'] ?? null,
            ),
        );
    }
    
    /**
     * Determine if the token is considered as remembered for the first time.
     *
     * @param TokenInterface $token
     * @return bool
     */
    protected function isTokenConsideredAsRemembered(TokenInterface $token): bool
    {
        if ($token->authenticatedVia() === 'remembered') {
            return false;
        }

        if (($token->payload()['remember'] ?? false) === false) {
            return false;
        }
        
        $rememberedAt = clone $token->issuedAt();
        
        if (
            ! $rememberedAt instanceof \DateTime
            && ! $rememberedAt instanceof \DateTimeImmutable
        ) {
            return false;
        }
        
        if (is_int($this->isRememberedAfter)) {
            $modified = $rememberedAt->modify('+'.$this->isRememberedAfter.' seconds');
            $rememberedAt = $modified ?: $rememberedAt;
        } else {
            $rememberedAt = $rememberedAt->add($this->isRememberedAfter);
        }

        return $rememberedAt < $this->clock->now();
    }
    
    /**
     * Renew token.
     *
     * @param AuthInterface $auth
     * @param TokenStorageInterface $tokenStorage
     * @return void
     */
    protected function renewToken(AuthInterface $auth, TokenStorageInterface $tokenStorage): void
    {
        $token = $auth->getAuthenticated()->token();
        
        $tokenStorage->deleteToken($token);

        $token = $tokenStorage->createToken(
            payload: $token->payload(),
            authenticatedVia: 'remembered',
            authenticatedBy: $token->authenticatedBy(),
            issuedAt: $token->issuedAt(),
            expiresAt: $token->expiresAt(),
        );

        $auth->start(new Authenticated(token: $token, user: $auth->getAuthenticated()->user()));
    }
}
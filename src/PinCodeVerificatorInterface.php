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
use Tobento\App\User\Web\Exception\VerificationTokenException;
use DateInterval;

/**
 * PinCodeVerificatorInterface
 */
interface PinCodeVerificatorInterface
{
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
    ): TokenInterface;
    
    /**
     * Returns the found code, otherwise null.
     *
     * @param string $type
     * @param UserInterface $user
     * @return null|TokenInterface
     */
    public function findCode(string $type, UserInterface $user): null|TokenInterface;
    
    /**
     * Returns true if code exists and is not expired, otherwise false.
     *
     * @param string $type
     * @param UserInterface $user
     * @return bool
     */
    public function hasCode(string $type, UserInterface $user): bool;
    
    /**
     * Returns verified code.
     *
     * @param string $code
     * @param string $type
     * @param UserInterface $user
     * @return TokenInterface
     * @throws VerificationTokenException If code is not found, is expired or for any reason invalid.
     */
    public function verifyCode(string $code, string $type, UserInterface $user): TokenInterface;
    
    /**
     * Delete code.
     *
     * @param string $type
     * @param UserInterface $user
     * @return void
     */
    public function deleteCode(string $type, UserInterface $user): void;
    
    /**
     * Delete all expired codes.
     *
     * @return void
     */
    public function deleteExpiredCodes(): void;
    
    /**
     * Returns the seconds the code is available again or
     * null if the code was not issued less than the specified seconds.
     *
     * @param TokenInterface $token
     * @param int $seconds
     * @return null|int
     */
    public function codeIssuedLessThan(TokenInterface $token, int $seconds): null|int;
}
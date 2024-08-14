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

namespace Tobento\App\User\Web\Console;

use Tobento\App\User\Web\PinCodeVerificatorInterface;
use Tobento\App\User\Web\TokenVerificatorInterface;
use Tobento\Service\Console\AbstractCommand;
use Tobento\Service\Console\InteractorInterface;

class ClearTokensCommand extends AbstractCommand
{
    /**
     * The signature of the console command.
     */
    public const SIGNATURE = '
        user-web:clear-tokens | Clears all verificator tokens such as password resets tokens and channel verification codes that have expired.
    ';
    
    /**
     * Handle the command.
     *
     * @param InteractorInterface $io
     * @param TokenVerificatorInterface $tokenVerificator
     * @param PinCodeVerificatorInterface $pinCodeVerificator
     * @return int The exit status code: 
     *     0 SUCCESS
     *     1 FAILURE If some error happened during the execution
     *     2 INVALID To indicate incorrect command usage e.g. invalid options
     * @psalm-suppress UndefinedInterfaceMethod
     */
    public function handle(
        InteractorInterface $io,
        TokenVerificatorInterface $tokenVerificator,
        PinCodeVerificatorInterface $pinCodeVerificator
    ): int {
        $tokenVerificator->deleteExpiredTokens();
        $pinCodeVerificator->deleteExpiredCodes();
        
        $io->success('User web expired verificator tokens cleared');
        
        return 0;
    }
}
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

namespace Tobento\App\User\Web\Test\Console;

use PHPUnit\Framework\TestCase;
use Tobento\App\User\Web;
use Tobento\App\User\Web\Console\ClearTokensCommand;
use Tobento\Service\Console\Test\TestCommand;
use Tobento\Service\Clock\FrozenClock;
use Tobento\Service\Container\Container;
use Tobento\Service\Notifier\NotifierInterface;
use Tobento\Service\Notifier\Notifier;
use Tobento\Service\Notifier\Channels;
use Tobento\Service\Storage\InMemoryStorage;
use Tobento\Service\Translation;
use Psr\Container\ContainerInterface;
use Psr\Clock\ClockInterface;

class ClearCommandTest extends TestCase
{
    protected function getContainer(): Container
    {
        $container = new Container();
        
        $container->set(ClockInterface::class, new FrozenClock());
        
        $container->set(Web\VerificatorHashKey::class)->construct('secret-hash');
        
        $container->set(Web\TokenVerificatorInterface::class, Web\TokenVerificator::class);
        
        $container->set(Web\PinCodeVerificatorInterface::class, Web\PinCodeVerificator::class);
        
        $container->set(Web\TokenFactoryInterface::class, Web\TokenFactory::class);
        
        $container->set(Web\TokenRepository::class, static function(ContainerInterface $c) {
            return new Web\TokenRepository(
                storage: new InMemoryStorage([]),
                table: 'verification_tokens',
                entityFactory: $c->get(Web\TokenFactoryInterface::class),
            );
        });
        
        $container->set(NotifierInterface::class, new Notifier(channels: new Channels()));
        
        $container->set(Translation\TranslatorInterface::class, static function() {
            return new Translation\Translator(
                new Translation\Resources(),
                new Translation\Modifiers(
                    new Translation\Modifier\Pluralization(),
                    new Translation\Modifier\ParameterReplacer(),
                ),
                new Translation\MissingTranslationHandler(),
                'en',
            );
        });
        
        return $container;
    }
    
    public function testClearTokens()
    {
        $container = $this->getContainer();
        
        (new TestCommand(command: ClearTokensCommand::class))
            ->expectsOutput('User web expired verificator tokens cleared')
            ->expectsExitCode(0)
            ->execute($container);
    }
}
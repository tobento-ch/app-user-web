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

namespace Tobento\App\User\Web\Test\Feature;

use PHPUnit\Framework\TestCase;
use Tobento\App\AppInterface;
use Tobento\App\User\Web;
use Tobento\Service\Acl\AclInterface;
use Tobento\Service\Console\ConsoleInterface;
use Tobento\Service\View\ViewInterface;

class AppTest extends \Tobento\App\Testing\TestCase
{
    public function createApp(): AppInterface
    {
        $app = $this->createTmpApp(rootDir: __DIR__.'/..');
        $app->boot(\Tobento\App\User\Web\Boot\UserWeb::class);
        return $app;
    }

    public function testInterfacesAreAvailable()
    {
        $app = $this->bootingApp();
        
        $this->assertInstanceof(Web\TokenVerificatorInterface::class, $app->get(Web\TokenVerificatorInterface::class));
        $this->assertInstanceof(Web\PinCodeVerificatorInterface::class, $app->get(Web\PinCodeVerificatorInterface::class));
        $this->assertInstanceof(Web\TokenFactoryInterface::class, $app->get(Web\TokenFactoryInterface::class));
    }

    public function testViewAclMacroIsAvailable()
    {
        $app = $this->bootingApp();
        
        $view = $app->get(ViewInterface::class);
        $this->assertInstanceof(AclInterface::class, $view->acl());
    }
    
    public function testConsoleCommandsAreAvailable()
    {
        $app = $this->bootingApp();
        
        $console = $app->get(ConsoleInterface::class);
        $this->assertTrue($console->hasCommand('user-web:clear-tokens'));
    }
}
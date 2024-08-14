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
 
namespace Tobento\App\User\Web\Boot;

use Tobento\App\Boot;
use Tobento\App\Boot\Config;
use Tobento\App\Migration\Boot\Migration;
use Tobento\App\User\Web\VerificatorHashKey;
use Tobento\Service\Console\ConsoleInterface;

/**
 * UserWeb
 */
class UserWeb extends Boot
{
    public const INFO = [
        'boot' => [
            'installs and loads user_web config',
            'installs view and translation files',
        ],
    ];

    public const BOOT = [
        Config::class,
        Migration::class,
        \Tobento\App\Message\Boot\Message::class,
        
        // HTTP:
        \Tobento\App\Http\Boot\ErrorHandler::class,
        \Tobento\App\Http\Boot\Routing::class,
        \Tobento\App\Http\Boot\Session::class,
        \Tobento\App\Http\Boot\Cookies::class,
        \Tobento\App\Http\Boot\RequesterResponser::class,
        
        // I18n:
        \Tobento\App\Language\Boot\Language::class,
        \Tobento\App\Translation\Boot\Translation::class,
        
        // USER:
        \Tobento\App\User\Boot\HttpUserErrorHandler::class,
        \Tobento\App\User\Boot\User::class,
        
        // Misc:
        \Tobento\App\Event\Boot\Event::class,
        \Tobento\App\Notifier\Boot\Notifier::class,
        \Tobento\App\RateLimiter\Boot\RateLimiter::class,
        \Tobento\App\Spam\Boot\Spam::class,
        \Tobento\App\Spam\Boot\ValidationSpamRule::class,
        
        // VIEW:
        \Tobento\App\View\Boot\View::class,
        \Tobento\App\View\Boot\Form::class,
        \Tobento\App\View\Boot\Table::class,
        \Tobento\App\View\Boot\Messages::class,
        //\Tobento\App\View\Boot\Breadcrumb::class,
    ];

    /**
     * Boot application services.
     *
     * @param Config $config
     * @param Migration $migration
     * @return void
     */
    public function boot(
        Config $config,
        Migration $migration,
    ): void {
        // install user migrations:
        $migration->install(\Tobento\App\User\Web\Migration\UserWeb::class);
        
        // load the user web config:
        $config = $config->load('user_web.php');
        
        $this->app->set(VerificatorHashKey::class)->construct($config['verificator_hash_key']);
        
        // setting interfaces:
        foreach($config['interfaces'] as $interface => $implementation) {
            $this->app->set($interface, $implementation);
        }
        
        // features:
        foreach($config['features'] ?? [] as $feature) {
            $this->app->call($feature);
        }
        
        // console commands:
        $this->app->on(ConsoleInterface::class, static function(ConsoleInterface $console): void {
            $console->addCommand(\Tobento\App\User\Web\Console\ClearTokensCommand::class);
        });
    }
}
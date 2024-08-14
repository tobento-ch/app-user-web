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
use Tobento\App\Testing\Database\RefreshDatabases;
use Tobento\App\AppInterface;
use Tobento\App\User\Web\Event;
use Tobento\App\User\Web\Feature;
use Tobento\Service\Language\LanguageFactory;
use Tobento\Service\Language\LanguagesInterface;
use Tobento\Service\Language\Languages;

class LogoutTest extends \Tobento\App\Testing\TestCase
{
    use RefreshDatabases;
    
    public function createApp(): AppInterface
    {
        $app = $this->createTmpApp(rootDir: __DIR__.'/../..');
        $app->boot(\Tobento\App\User\Web\Boot\UserWeb::class);
        return $app;
    }
    
    public function testUserCanLogout()
    {
        $events = $this->fakeEvents();
        $auth = $this->fakeAuth();
        $http = $this->fakeHttp();
        $http->request(method: 'POST', uri: 'logout');
        
        $app = $this->bootingApp();
        $user = $auth->getUserRepository()->create(['username' => 'tom']);
        $auth->authenticatedAs($user);
        
        $http->response()->assertStatus(302);
        $auth->assertNotAuthenticated();
        $events->assertDispatched(Event\Logout::class, static function(Event\Logout $event): bool {
            $user = $event->unauthenticated()->user();
            return $user->username() === 'tom';
        });
    }
    
    public function testUserCanNotLogoutIfUnauthenticated()
    {
        $auth = $this->fakeAuth();
        $http = $this->fakeHttp();
        $http->request(method: 'POST', uri: 'logout');
        $http->followRedirects()
            ->assertStatus(200)
            ->assertBodyContains('You have insufficient rights to access the requested resource!');
    }
    
    public function testLogoutInLocaleDe()
    {
        $this->fakeConfig()->with('user_web.features', [
            new Feature\Home(
                localizeRoute: true,
            ),
            new Feature\Logout(
                localizeRoute: true,
            ),
        ]);
        
        $auth = $this->fakeAuth();
        $http = $this->fakeHttp();
        $http->request(method: 'POST', uri: 'de/logout');
        
        $app = $this->getApp();
        $app->on(LanguagesInterface::class, function() {
            $languageFactory = new LanguageFactory();
            return new Languages(
                $languageFactory->createLanguage(locale: 'en', default: true),
                $languageFactory->createLanguage(locale: 'de', slug: 'de'),
            );
        });
        
        $app->booting();
        $user = $auth->getUserRepository()->create(['username' => 'tom']);
        $auth->authenticatedAs($user);
        
        $http->response()->assertStatus(302);
        $auth->assertNotAuthenticated();
    }
}
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
use Tobento\App\User\RoleRepositoryInterface;
use Tobento\App\Seeding\User\UserFactory;
use Tobento\App\RateLimiter\Symfony\Registry\FixedWindow;
use Tobento\Service\Clock\FrozenClock;
use Tobento\Service\Language\LanguageFactory;
use Tobento\Service\Language\LanguagesInterface;
use Tobento\Service\Language\Languages;
use Psr\Clock\ClockInterface;

class LoginTest extends \Tobento\App\Testing\TestCase
{
    use RefreshDatabases;
    
    public function createApp(): AppInterface
    {
        $app = $this->createTmpApp(rootDir: __DIR__.'/../..');
        $app->boot(\Tobento\App\User\Web\Boot\UserWeb::class);
        $app->boot(\Tobento\App\Seeding\Boot\Seeding::class);
        return $app;
    }

    public function testLoginScreenIsRendered()
    {
        $http = $this->fakeHttp();
        $http->request(method: 'GET', uri: 'login');
        $http->response()
            ->assertStatus(200)
            ->assertBodyContains('Login')
            ->assertBodyContains('User')
            ->assertBodyContains('Password')
            ->assertBodyContains('Remember')
            ->assertBodyContains('Forgot your password');
    }
    
    public function testLoginScreenIsRenderedWithoutRemember()
    {
        $this->fakeConfig()->with('user_web.features', [
            new Feature\Login(
                remember: null,
            ),
        ]);
        
        $http = $this->fakeHttp();
        $http->request(method: 'GET', uri: 'login');
        $http->response()
            ->assertStatus(200)
            ->assertBodyContains('Login')
            ->assertBodyContains('User')
            ->assertBodyContains('Password')
            ->assertBodyNotContains('Remember');
    }
    
    public function testLoginScreenIsRenderedWithoutForgotPassword()
    {
        $this->fakeConfig()->with('user_web.features', [
            new Feature\Login(
                forgotPasswordRoute: null,
            ),
        ]);
        
        $http = $this->fakeHttp();
        $http->request(method: 'GET', uri: 'login');
        $http->response()
            ->assertStatus(200)
            ->assertBodyContains('Login')
            ->assertBodyContains('User')
            ->assertBodyContains('Password')
            ->assertBodyNotContains('Forgot your password');
    }
    
    public function testLoginScreenIsNotRenderedIfAuthenticated()
    {
        $auth = $this->fakeAuth();
        $http = $this->fakeHttp();
        $http->request(method: 'GET', uri: 'login');
        
        $app = $this->bootingApp();
        $user = UserFactory::new()->withEmail('tom@example.com')->createOne();
        $auth->authenticatedAs($user);
        
        $http->followRedirects()
            ->assertStatus(200)
            ->assertBodyContains('You are logged in!');
    }
    
    public function testUserCanLogin()
    {
        $events = $this->fakeEvents();
        $auth = $this->fakeAuth();
        $http = $this->fakeHttp();
        $http->request(
            method: 'POST',
            uri: 'login',
            body: [
                'user' => 'tom@example.com',
                'password' => '123456',
            ],
        );
        
        $app = $this->bootingApp();
        UserFactory::new()->withEmail('tom@example.com')->withPassword('123456')->createOne();
        
        $http->response()->assertStatus(302)->assertRedirectToRoute(name: 'home');
        $auth->assertAuthenticated();
        $events->assertDispatched(Event\Login::class, static function(Event\Login $event): bool {
            $user = $event->authenticated()->user();
            return $user->email() === 'tom@example.com'
                && $event->remember() === false;
        });
        
        $payload = $auth->getAuthenticated()?->token()?->payload();
        $this->assertFalse($payload['remember'] ?? true);
    }
    
    public function testUserLoginSuccessMessageIsDisplayed()
    {
        $events = $this->fakeEvents();
        $auth = $this->fakeAuth();
        $http = $this->fakeHttp();
        $http->request(method: 'POST', uri: 'login', body: ['user' => 'tom@example.com', 'password' => '123456']);
        
        $app = $this->bootingApp();
        UserFactory::new()->withEmail('tom@example.com')->withPassword('123456')->createOne();
        
        $http->followRedirects()
            ->assertStatus(200)
            ->assertBodyContains('Welcome back');
    }
    
    public function testUserCanNotLoginWithInvalidPassword()
    {
        $events = $this->fakeEvents();
        $auth = $this->fakeAuth();
        $http = $this->fakeHttp();
        $http->request(
            method: 'POST',
            uri: 'login',
            body: [
                'user' => 'tom@example.com',
                'password' => 'invalid',
            ],
        );
        
        $app = $this->bootingApp();
        UserFactory::new()->withEmail('tom@example.com')->withPassword('123456')->createOne();
                
        $http->response()->assertStatus(302)->assertRedirectToRoute(name: 'login');
        $auth->assertNotAuthenticated();
        $events->assertDispatched(Event\LoginFailed::class);
    }
    
    public function testUserLoginWithUserVerifier()
    {
        $this->fakeConfig()->with('user_web.features', [
            new Feature\Home(),
            new Feature\Login(
                userVerifier: function() {
                    return new \Tobento\App\User\Authenticator\UserRoleVerifier('editor', 'author');
                },
            ),
        ]);
        
        $events = $this->fakeEvents();
        $auth = $this->fakeAuth();
        $http = $this->fakeHttp();
        $http->request(
            method: 'POST',
            uri: 'login',
            body: [
                'user' => 'tom@example.com',
                'password' => '123456',
            ],
        );
        
        $app = $this->bootingApp();
        $app->get(RoleRepositoryInterface::class)->create(['key' => 'editor', 'active' => true]);
        UserFactory::new()->withEmail('tom@example.com')->withPassword('123456')->createOne();
        UserFactory::new()->withEmail('james@example.com')->withRoleKey('editor')->withPassword('123456')->createOne();
        
        $http->response()->assertStatus(302)->assertRedirectToRoute(name: 'login');
        $auth->assertNotAuthenticated();
        
        // with valid role can login:
        $http->request(
            method: 'POST',
            uri: 'login',
            body: [
                'user' => 'james@example.com',
                'password' => '123456',
            ],
        );
        
        $http->followRedirects()->assertStatus(200);
        $this->fakeAuth()->assertAuthenticated();
    }
    
    public function testUserLoginAttemptsExceededReturnsRateLimitedMessage()
    {
        $this->fakeConfig()->with('user_web.features', [
            new Feature\Home(),
            Feature\ForgotPassword::class,
            new Feature\Login(
                rateLimiter: new FixedWindow(limit: 1),
            ),
        ]);
        
        $events = $this->fakeEvents();
        $auth = $this->fakeAuth();
        $http = $this->fakeHttp();
        $http->request(method: 'POST', uri: 'login', body: ['user' => 'tom@example.com', 'password' => '123456']);
        
        $http->response()->assertStatus(302)->assertRedirectToRoute(name: 'login');
        $events->assertNotDispatched(Event\LoginAttemptsExceeded::class);
        
        // next request:
        $http->request(method: 'POST', uri: 'login', body: ['user' => 'tom@example.com', 'password' => '123456']);
        
        $http->response()->assertStatus(302)->assertRedirectToRoute(name: 'login');
        $this->fakeEvents()->assertDispatched(Event\LoginAttemptsExceeded::class);
        
        $http->followRedirects()
            ->assertStatus(200)
            ->assertBodyContains('Too many login attempts');
    }

    public function testUserLoggedInExpires()
    {
        $this->fakeConfig()->with('user_web.features', [
            new Feature\Home(),
            new Feature\Login(
                expiresAfter: 1500
            ),
        ]);
        
        $auth = $this->fakeAuth();
        $http = $this->fakeHttp();
        $http->request(
            method: 'POST',
            uri: 'login',
            body: [
                'user' => 'tom@example.com',
                'password' => '123456',
            ],
        );
        
        $app = $this->bootingApp();
        UserFactory::new()->withEmail('tom@example.com')->withPassword('123456')->createOne();
        
        $http->response()->assertStatus(302);
        $auth->assertAuthenticated();

        // next request:
        $http->request(method: 'GET', uri: '');
        $this->getApp()->on(ClockInterface::class, function($clock) {
            return new FrozenClock($clock->now()->modify('+10 minutes'));
        });
        $http->response()->assertStatus(200);
        $this->fakeAuth()->assertAuthenticated();
        
        // next request:
        $http->request(method: 'GET', uri: '');
        $this->getApp()->on(ClockInterface::class, function($clock) {
            return new FrozenClock($clock->now()->modify('+26 minutes'));
        });
        $http->response()->assertStatus(403)->assertBodyContains('Resource Expired');
        $this->fakeAuth()->assertNotAuthenticated();
    }
    
    public function testUserLoggedInWithRememberExpires()
    {
        $this->fakeConfig()->with('user_web.features', [
            new Feature\Home(),
            new Feature\Login(
                remember: new \DateInterval('P6M')
            ),
        ]);
        
        $auth = $this->fakeAuth();
        $http = $this->fakeHttp();
        $http->request(
            method: 'POST',
            uri: 'login',
            body: [
                'user' => 'tom@example.com',
                'password' => '123456',
                'remember' => '1',
            ],
        );
        
        $app = $this->bootingApp();
        UserFactory::new()->withEmail('tom@example.com')->withPassword('123456')->createOne();
        
        $http->response()->assertStatus(302);
        $auth->assertAuthenticated();
        $payload = $auth->getAuthenticated()?->token()?->payload();
        $this->assertTrue($payload['remember'] ?? false);

        // next request:
        $http->request(method: 'GET', uri: '');
        $this->getApp()->on(ClockInterface::class, function($clock) {
            return new FrozenClock($clock->now()->modify('+2 months'));
        });
        $http->response()->assertStatus(200);
        $this->fakeAuth()->assertAuthenticated();
        
        // next request:
        $http->request(method: 'GET', uri: '');
        $this->getApp()->on(ClockInterface::class, function($clock) {
            return new FrozenClock($clock->now()->modify('+8 months'));
        });
        $http->response()->assertStatus(403)->assertBodyContains('Resource Expired');
        $this->fakeAuth()->assertNotAuthenticated();
    }
    
    public function testLoginScreenIsRenderedInLocaleDe()
    {
        $this->fakeConfig()->with('user_web.features', [
            new Feature\Home(
                localizeRoute: true,
            ),
            new Feature\ForgotPassword(
                localizeRoute: true,
            ),
            new Feature\Login(
                localizeRoute: true,
            ),
        ]);
        
        $http = $this->fakeHttp();
        $http->request(method: 'GET', uri: 'de/anmelden');
        
        $app = $this->getApp();
        $app->on(LanguagesInterface::class, function() {
            $languageFactory = new LanguageFactory();
            return new Languages(
                $languageFactory->createLanguage(locale: 'en', default: true),
                $languageFactory->createLanguage(locale: 'de', slug: 'de'),
            );
        });
        
        $http->response()
            ->assertStatus(200)
            ->assertBodyContains('Benutzer')
            ->assertBodyContains('Passwort');
    }
}
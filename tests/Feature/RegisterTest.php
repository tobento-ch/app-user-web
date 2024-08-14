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
use Tobento\App\RateLimiter\Middleware\RateLimitRequests;
use Tobento\App\RateLimiter\Symfony\Registry\SlidingWindow;
use Tobento\App\Seeding\User\UserFactory;
use Tobento\App\Spam\Middleware\ProtectAgainstSpam;
use Tobento\Service\Routing\RouterInterface;
use Tobento\Service\Clock\FrozenClock;
use Tobento\Service\Language\LanguageFactory;
use Tobento\Service\Language\LanguagesInterface;
use Tobento\Service\Language\Languages;
use Psr\Clock\ClockInterface;

class RegisterTest extends \Tobento\App\Testing\TestCase
{
    use RefreshDatabases;
    
    public function createApp(): AppInterface
    {
        $app = $this->createTmpApp(rootDir: __DIR__.'/../..');
        $app->boot(\Tobento\App\User\Web\Boot\UserWeb::class);
        $app->boot(\Tobento\App\Seeding\Boot\Seeding::class);
        return $app;
    }

    public function testRegisterScreenIsRendered()
    {
        $http = $this->fakeHttp();
        $http->request(method: 'GET', uri: 'register');
        $http->response()
            ->assertStatus(200)
            ->assertBodyContains('Register')
            ->assertBodyContains('E-Mail')
            ->assertBodyContains('Smartphone')
            ->assertBodyContains('Password')
            ->assertBodyContains('Confirm password');
    }
    
    public function testRegisterScreenIsNotRenderedIfAuthenticated()
    {
        $auth = $this->fakeAuth();
        $http = $this->fakeHttp();
        $http->request(method: 'GET', uri: 'register');
        
        $app = $this->bootingApp();
        $user = UserFactory::new()->withEmail('tom@example.com')->createOne();
        $auth->authenticatedAs($user);
        
        $http->followRedirects()
            ->assertStatus(200)
            ->assertBodyContains('You are logged in!');
    }
    
    public function testUserCanRegister()
    {
        $events = $this->fakeEvents();
        $auth = $this->fakeAuth();
        $http = $this->fakeHttp();
        $http->withoutMiddleware(RateLimitRequests::class);
        $http->withoutMiddleware(ProtectAgainstSpam::class);
        $http->request(
            method: 'POST',
            uri: 'register',
            body: [
                'address' => ['name' => 'Tom'],
                'email' => 'tom@example.com',
                'password' => '12345678',
                'password_confirmation' => '12345678',
            ],
        );
        
        $app = $this->bootingApp();
        $app->get(RoleRepositoryInterface::class)->create(['key' => 'registered', 'active' => true]);
        
        $http->response()->assertStatus(302)->assertRedirectToRoute(name: 'login');
        $auth->assertNotAuthenticated();
        $events->assertDispatched(Event\Registered::class, static function(Event\Registered $event): bool {
            $user = $event->user();
            return $user->email() === 'tom@example.com'
                && $user->address()->name() === 'Tom'
                && $user->role()->key() === 'registered'
                && $user->locale() === 'en'
                && $user->newsletter() === false;
        });
        $events->assertNotDispatched(Event\RegisterFailed::class);
    }
    
    public function testUserCanRegisterWithValidUserType()
    {
        $events = $this->fakeEvents();
        $auth = $this->fakeAuth();
        $http = $this->fakeHttp();
        $http->withoutMiddleware(RateLimitRequests::class);
        $http->withoutMiddleware(ProtectAgainstSpam::class);
        $http->request(
            method: 'POST',
            uri: 'register',
            body: [
                'user_type' => 'business',
                'address' => ['name' => 'Tom'],
                'email' => 'tom@example.com',
                'password' => '12345678',
                'password_confirmation' => '12345678',
            ],
        );
        
        $app = $this->bootingApp();
        $app->get(RoleRepositoryInterface::class)->create(['key' => 'business', 'active' => true]);
        
        $http->response()->assertStatus(302)->assertRedirectToRoute(name: 'login');
        $auth->assertNotAuthenticated();
        $events->assertDispatched(Event\Registered::class, static function(Event\Registered $event): bool {
            $user = $event->user();
            return $user->email() === 'tom@example.com'
                && $user->address()->name() === 'Tom'
                && $user->role()->key() === 'business';
        });
    }
    
    public function testUserCanRegisterWithInvalidUserTypeButFallsbackToDefault()
    {
        $events = $this->fakeEvents();
        $auth = $this->fakeAuth();
        $http = $this->fakeHttp();
        $http->withoutMiddleware(RateLimitRequests::class);
        $http->withoutMiddleware(ProtectAgainstSpam::class);
        $http->request(
            method: 'POST',
            uri: 'register',
            body: [
                'user_type' => 'editor',
                'address' => ['name' => 'Tom'],
                'email' => 'tom@example.com',
                'password' => '12345678',
                'password_confirmation' => '12345678',
            ],
        );
        
        $app = $this->bootingApp();
        $app->get(RoleRepositoryInterface::class)->create(['key' => 'editor', 'active' => true]);
        $app->get(RoleRepositoryInterface::class)->create(['key' => 'registered', 'active' => true]);
        
        $http->response()->assertStatus(302)->assertRedirectToRoute(name: 'login');
        $auth->assertNotAuthenticated();
        $events->assertDispatched(Event\Registered::class, static function(Event\Registered $event): bool {
            $user = $event->user();
            return $user->email() === 'tom@example.com'
                && $user->address()->name() === 'Tom'
                && $user->role()->key() === 'registered';
        });
    }
    
    public function testUserCanNotRegisterWithPasswordNotMatchingConfirmation()
    {
        $events = $this->fakeEvents();
        $http = $this->fakeHttp();
        $http->withoutMiddleware(RateLimitRequests::class);
        $http->withoutMiddleware(ProtectAgainstSpam::class);
        $http->request(
            method: 'POST',
            uri: 'register',
            body: [
                'address' => ['name' => 'Tom'],
                'email' => 'tom@example.com',
                'password' => '12345678',
                'password_confirmation' => '1234567A',
            ],
        );
        
        $http->followRedirects()
            ->assertStatus(200)
            ->assertBodyContains('The password confirmation does not match.');
        
        $events->assertDispatched(Event\RegisterFailed::class);
    }
    
    public function testUserRegisterIsRateLimited()
    {
        $this->fakeConfig()->with('middleware.replace', [
            RateLimitRequests::class => [
                RateLimitRequests::class,
                'registry' => new SlidingWindow(limit: 1, interval: '1 Seconds')
            ],
        ]);
        
        $request = [
            'method' => 'POST',
            'uri' => 'register',
            'server' => ['REMOTE_ADDR' => '127.0.0.1'],
            'body' => [
                'address' => ['name' => 'Tom'],
                'email' => 'tom@example.com',
                'password' => '12345678',
                'password_confirmation' => '1234567A',
            ],
        ];
        
        $http = $this->fakeHttp();
        $http->withoutMiddleware(ProtectAgainstSpam::class);
        $http->request(...$request);
        $http->response()->assertStatus(302);
        
        $http->request(...$request);
        $http->followRedirects()
            ->assertStatus(429);
    }
    
    public function testUserRegisterIsSpamProtected()
    { 
        $http = $this->fakeHttp();
        $http->withoutMiddleware(RateLimitRequests::class);
        $http->request(
            method: 'POST',
            uri: 'register',
            body: [
                'user_type' => 'business',
                'address' => ['name' => 'Tom'],
                'email' => 'tom@example.com',
                'password' => '12345678',
                'password_confirmation' => '12345678',
            ],
        );
        $http->response()->assertStatus(422);
    }
    
    public function testRegisterScreenIsRenderedWithNewsletterOption()
    {
        $this->fakeConfig()->with('user_web.features', [
            Feature\Login::class,
            new Feature\Register(
                newsletter: true,
            ),
        ]);
        
        $http = $this->fakeHttp();
        $http->request(method: 'GET', uri: 'register');
        $http->response()
            ->assertStatus(200)
            ->assertBodyContains('Yes, I would like to subscribe to the newsletter.');
    }
    
    public function testUserCanRegisterWithNewsletter()
    {
        $events = $this->fakeEvents();
        $http = $this->fakeHttp();
        $http->withoutMiddleware(RateLimitRequests::class);
        $http->withoutMiddleware(ProtectAgainstSpam::class);
        $http->request(
            method: 'POST',
            uri: 'register',
            body: [
                'address' => ['name' => 'Tom'],
                'email' => 'tom@example.com',
                'password' => '12345678',
                'password_confirmation' => '12345678',
                'newsletter' => '1',
            ],
        );
        
        $http->response()->assertStatus(302)->assertRedirectToRoute(name: 'login');
        
        $events->assertDispatched(Event\Registered::class, static function(Event\Registered $event): bool {
            $user = $event->user();
            return $user->newsletter();
        });
    }
    
    public function testRegisterScreenIsRenderedWithTerms()
    {
        $this->fakeConfig()->with('user_web.features', [
            Feature\Login::class,
            new Feature\Register(
                termsRoute: 'terms',
            ),
        ]);
        
        $http = $this->fakeHttp();
        $http->request(method: 'GET', uri: 'register');
        
        $app = $this->bootingApp();
        $app->get(RouterInterface::class)->get('terms-url', function () {
            return 'Terms';
        })->name('terms');

        $http->response()
            ->assertStatus(200)
            ->assertBodyContains('terms-url')
            ->assertBodyContains('I agree to the terms');
    }
    
    public function testRegisterScreenIsRenderedWithTermsClosure()
    {
        $this->fakeConfig()->with('user_web.features', [
            Feature\Login::class,
            new Feature\Register(
                termsRoute: static function (RouterInterface $router): string {
                    return 'https://example.com/terms';
                },
            ),
        ]);
        
        $http = $this->fakeHttp();
        $http->request(method: 'GET', uri: 'register');
        
        $http->response()
            ->assertStatus(200)
            ->assertBodyContains('https://example.com/terms')
            ->assertBodyContains('I agree to the terms');
    }
    
    public function testUserCanRegisterWithTerms()
    {
        $this->fakeConfig()->with('user_web.features', [
            Feature\Login::class,
            new Feature\Register(
                termsRoute: static function (RouterInterface $router): string {
                    return 'https://example.com/terms';
                },
                successRedirectRoute: 'login'
            ),
        ]);
        
        $events = $this->fakeEvents();
        $http = $this->fakeHttp();
        $http->withoutMiddleware(RateLimitRequests::class);
        $http->withoutMiddleware(ProtectAgainstSpam::class);
        $http->request(
            method: 'POST',
            uri: 'register',
            body: [
                'address' => ['name' => 'Tom'],
                'email' => 'tom@example.com',
                'password' => '12345678',
                'password_confirmation' => '12345678',
                'terms' => '1',
            ],
        );
        
        $http->response()->assertStatus(302)->assertRedirectToRoute(name: 'login');
        $events->assertDispatched(Event\Registered::class);
    }
    
    public function testUserCanNotRegisterWithoutTermsConfirmed()
    {
        $this->fakeConfig()->with('user_web.features', [
            Feature\Login::class,
            new Feature\Register(
                termsRoute: static function (RouterInterface $router): string {
                    return 'https://example.com/terms';
                },
                successRedirectRoute: 'login'
            ),
        ]);
        
        $events = $this->fakeEvents();
        $http = $this->fakeHttp();
        $http->withoutMiddleware(RateLimitRequests::class);
        $http->withoutMiddleware(ProtectAgainstSpam::class);
        $http->request(
            method: 'POST',
            uri: 'register',
            body: [
                'address' => ['name' => 'Tom'],
                'email' => 'tom@example.com',
                'password' => '12345678',
                'password_confirmation' => '12345678',
            ],
        );
        
        $http->followRedirects()
            ->assertStatus(200)
            ->assertBodyContains('The terms is required.');
    }
    
    public function testRegisterScreenIsRenderedInLocaleDe()
    {
        $this->fakeConfig()->with('user_web.features', [
            new Feature\Home(
                localizeRoute: true,
            ),
            new Feature\Login(
                localizeRoute: true,
            ),
            new Feature\Register(
                localizeRoute: true,
            ),
        ]);
        
        $http = $this->fakeHttp();
        $http->request(method: 'GET', uri: 'de/registrieren');
        
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
            ->assertBodyContains('Registrieren');
    }
}
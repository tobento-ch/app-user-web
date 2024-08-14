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
use Tobento\App\User\Web\Listener\AutoLoginAfterRegistration;
use Tobento\App\User\RoleRepositoryInterface;
use Tobento\App\RateLimiter\Middleware\RateLimitRequests;
use Tobento\App\Spam\Middleware\ProtectAgainstSpam;
use Tobento\Service\Clock\FrozenClock;
use Psr\Clock\ClockInterface;

class RegisterAutoLoginTest extends \Tobento\App\Testing\TestCase
{
    use RefreshDatabases;
    
    public function createApp(): AppInterface
    {
        $app = $this->createTmpApp(rootDir: __DIR__.'/../..');
        $app->boot(\Tobento\App\User\Web\Boot\UserWeb::class);
        $app->boot(\Tobento\App\Seeding\Boot\Seeding::class);
        return $app;
    }
    
    public function testUserAccountVerificationAfterRegistration()
    {
        $this->fakeConfig()->with('user_web.features', [
            Feature\Login::class,
            Feature\Verification::class,
            new Feature\Register(
                successRedirectRoute: 'verification.account'
            ),
        ]);
        
        $this->fakeConfig()->with('event.listeners', [
            \Tobento\App\User\Web\Event\Registered::class => [
                AutoLoginAfterRegistration::class,
            ],
        ]);
        
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
        
        $http->followRedirects()
            ->assertStatus(200)
            ->assertBodyContains('Account Verification');
    }
    
    public function testAutoLoginAfterRegistration()
    {
        $this->fakeConfig()->with('user_web.features', [
            Feature\Login::class,
            Feature\Verification::class,
            Feature\Profile::class,
            new Feature\Register(
                successRedirectRoute: 'profile.edit'
            ),
        ]);
        
        $this->fakeConfig()->with('event.listeners', [
            \Tobento\App\User\Web\Event\Registered::class => [
                AutoLoginAfterRegistration::class,
            ],
        ]);
        
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
        
        $http->followRedirects()
            ->assertStatus(200)
            ->assertBodyContains('Profile');
    }
    
    public function testAutoLoginAfterRegistrationWithExpiresAfterSeconds()
    {
        $this->fakeConfig()->with('user_web.features', [
            Feature\Home::class,
            Feature\Login::class,
            Feature\Verification::class,
            Feature\Profile::class,
            new Feature\Register(
                successRedirectRoute: 'profile.edit'
            ),
        ]);
        
        $this->fakeConfig()->with('event.listeners', [
            \Tobento\App\User\Web\Event\Registered::class => [
                [AutoLoginAfterRegistration::class, ['expiresAfter' => 3000]]
            ],
        ]);
        
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
        
        $http->followRedirects()
            ->assertStatus(200)
            ->assertBodyContains('Profile');
        $this->fakeAuth()->assertAuthenticated();
        
        // next request:
        $http->request(method: 'GET', uri: '');
        $this->getApp()->on(ClockInterface::class, function($clock) {
            return new FrozenClock($clock->now()->modify('+2999 seconds'));
        });
        $http->response()->assertStatus(200);
        $this->fakeAuth()->assertAuthenticated();
        
        // next request:
        $http->request(method: 'GET', uri: '');
        $this->getApp()->on(ClockInterface::class, function($clock) {
            return new FrozenClock($clock->now()->modify('+3001 seconds'));
        });
        $http->response()->assertStatus(403)->assertBodyContains('Resource Expired');
        $this->fakeAuth()->assertNotAuthenticated();
    }
    
    public function testAutoLoginAfterRegistrationWithExpiresAfterDateInterval()
    {
        $this->fakeConfig()->with('user_web.features', [
            Feature\Home::class,
            Feature\Login::class,
            Feature\Verification::class,
            Feature\Profile::class,
            new Feature\Register(
                successRedirectRoute: 'profile.edit'
            ),
        ]);
        
        $this->fakeConfig()->with('event.listeners', [
            \Tobento\App\User\Web\Event\Registered::class => [
                [AutoLoginAfterRegistration::class, ['expiresAfter' => new \DateInterval('PT1H')]],
            ],
        ]);
        
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
        
        $http->followRedirects()
            ->assertStatus(200)
            ->assertBodyContains('Profile');
        $this->fakeAuth()->assertAuthenticated();
        
        // next request:
        $http->request(method: 'GET', uri: '');
        $this->getApp()->on(ClockInterface::class, function($clock) {
            return new FrozenClock($clock->now()->modify('+59 minutes'));
        });
        $http->response()->assertStatus(200);
        $this->fakeAuth()->assertAuthenticated();
        
        // next request:
        $http->request(method: 'GET', uri: '');
        $this->getApp()->on(ClockInterface::class, function($clock) {
            return new FrozenClock($clock->now()->modify('+61 minutes'));
        });
        $http->response()->assertStatus(403)->assertBodyContains('Resource Expired');
        $this->fakeAuth()->assertNotAuthenticated();
    }
    
    public function testAutoLoginAfterRegistrationWithUserRoles()
    {
        $this->fakeConfig()->with('user_web.features', [
            Feature\Home::class,
            Feature\Login::class,
            Feature\Verification::class,
            Feature\Profile::class,
            new Feature\Register(
                successRedirectRoute: 'profile.edit'
            ),
        ]);
        
        $this->fakeConfig()->with('event.listeners', [
            \Tobento\App\User\Web\Event\Registered::class => [
                [AutoLoginAfterRegistration::class, ['userRoles' => ['business']]],
            ],
        ]);
        
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
                'user_type' => 'business',
            ],
        );
        
        $app = $this->bootingApp();
        $app->get(RoleRepositoryInterface::class)->create(['key' => 'business', 'active' => true]);
        
        $http->followRedirects()
            ->assertStatus(200)
            ->assertBodyContains('Profile');
        $this->fakeAuth()->assertAuthenticated();
    }
    
    public function testAutoLoginAfterRegistrationWithUserRolesShouldNotLoginIfNotSpecified()
    {
        $this->fakeConfig()->with('user_web.features', [
            Feature\Home::class,
            Feature\Login::class,
            Feature\Verification::class,
            Feature\Profile::class,
            new Feature\Register(
                successRedirectRoute: 'home'
            ),
        ]);
        
        $this->fakeConfig()->with('event.listeners', [
            \Tobento\App\User\Web\Event\Registered::class => [
                [AutoLoginAfterRegistration::class, ['userRoles' => ['business']]],
            ],
        ]);
        
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
                'user_type' => 'private',
            ],
        );
        
        $app = $this->bootingApp();
        $app->get(RoleRepositoryInterface::class)->create(['key' => 'registered', 'active' => true]);
        $app->get(RoleRepositoryInterface::class)->create(['key' => 'business', 'active' => true]);
        
        $http->followRedirects()->assertStatus(200);
        $this->fakeAuth()->assertNotAuthenticated();
    }
}
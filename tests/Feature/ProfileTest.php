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
use Tobento\App\Notifier\AvailableChannelsInterface;
use Tobento\App\Notifier\AvailableChannels;
use Tobento\App\Seeding\User\UserFactory;
use Tobento\Service\Language\LanguageFactory;
use Tobento\Service\Language\LanguagesInterface;
use Tobento\Service\Language\Languages;

class ProfileTest extends \Tobento\App\Testing\TestCase
{
    use RefreshDatabases;
    
    public function createApp(): AppInterface
    {
        $app = $this->createTmpApp(rootDir: __DIR__.'/../..');
        $app->boot(\Tobento\App\User\Web\Boot\UserWeb::class);
        $app->boot(\Tobento\App\Seeding\Boot\Seeding::class);
        return $app;
    }

    public function testProfileScreenIsRendered()
    {
        $auth = $this->fakeAuth();
        $http = $this->fakeHttp();
        $http->request(method: 'GET', uri: 'profile');
        
        $app = $this->bootingApp();
        $user = UserFactory::new()->createOne();
        $auth->authenticatedAs($user);
        
        $http->response()
            ->assertStatus(200)
            ->assertBodyContains('Profile Information')
            ->assertBodyContains('Channel Verifications')
            ->assertBodyContains('Update Password')
            ->assertBodyContains('Delete Account');
    }
    
    public function testProfileScreenIsRenderedWithoutChannelVerifications()
    {
        $this->fakeConfig()->with('user_web.features', [
            Feature\Login::class,
            new Feature\Profile(
                channelVerifications: false,
            ),
        ]);
        
        $auth = $this->fakeAuth();
        $http = $this->fakeHttp();
        $http->request(method: 'GET', uri: 'profile');
        
        $app = $this->bootingApp();
        $user = UserFactory::new()->createOne();
        $auth->authenticatedAs($user);
        
        $http->response()
            ->assertStatus(200)
            ->assertBodyContains('Profile Information')
            ->assertBodyNotContains('Channel Verifications')
            ->assertBodyContains('Update Password')
            ->assertBodyContains('Delete Account');
    }
    
    public function testProfileScreenIsRenderedWithoutChannelVerificationsIfNoChannelsAvailable()
    {
        $auth = $this->fakeAuth();
        $http = $this->fakeHttp();
        $http->request(method: 'GET', uri: 'profile');
        
        $app = $this->getApp();
        $app->on(AvailableChannelsInterface::class, function (AvailableChannelsInterface $channels) {
            return $channels->only([]);
        });
        
        $app = $this->bootingApp();
        $user = UserFactory::new()->createOne();
        $auth->authenticatedAs($user);
        
        $http->response()
            ->assertStatus(200)
            ->assertBodyContains('Profile Information')
            ->assertBodyNotContains('Channel Verifications')
            ->assertBodyContains('Update Password')
            ->assertBodyContains('Delete Account');
    }
    
    public function testProfileScreenIsRenderedIsRenderedInLocaleDe()
    {
        $this->fakeConfig()->with('user_web.features', [
            Feature\Login::class,
            Feature\Verification::class,
            new Feature\Profile(
                localizeRoute: true,
            ),
        ]);
        
        $auth = $this->fakeAuth();
        $http = $this->fakeHttp();
        $http->request(method: 'GET', uri: 'de/profil');
        
        $app = $this->getApp();
        $app->on(LanguagesInterface::class, function() {
            $languageFactory = new LanguageFactory();
            return new Languages(
                $languageFactory->createLanguage(locale: 'en', default: true),
                $languageFactory->createLanguage(locale: 'de', slug: 'de'),
            );
        });
        
        $app = $this->bootingApp();
        $user = UserFactory::new()->createOne();
        $auth->authenticatedAs($user);
        
        $http->response()
            ->assertStatus(200)
            ->assertBodyContains('Profil Information')
            ->assertBodyContains('Kanal-Verifizierungen');
    }
    
    public function testProfileScreenIsNotRenderedIfNotAuthenticated()
    {
        $http = $this->fakeHttp();
        $http->request(method: 'GET', uri: 'profile');
        
        $http->followRedirects()
            ->assertStatus(200)
            ->assertBodyContains('You have insufficient rights to access the requested resource!');
    }
    
    public function testProfileMenuIsNotShownIfNotAuthenticated()
    {
        $http = $this->fakeHttp();
        $http->request(method: 'GET', uri: '');
        
        $http->response()
            ->assertStatus(200)
            ->assertBodyNotContains('Profile');
    }
    
    public function testProfileInformationCanBeUpdated()
    {
        $events = $this->fakeEvents();
        $auth = $this->fakeAuth();
        $http = $this->fakeHttp();
        $http->request(
            method: 'PATCH',
            uri: 'profile',
            body: [
                'address' => ['name' => 'Tom'],
                'email' => 'tom@example.com',
            ],
        );
        
        $app = $this->bootingApp();
        $user = UserFactory::new()->createOne();
        $auth->authenticatedAs($user);
        
        $http->response()->assertStatus(302)->assertRedirectToRoute(name: 'profile.edit');
        
        $events->assertDispatched(Event\UpdatedProfile::class, static function(Event\UpdatedProfile $event): bool {
            $user = $event->user();
            return $user->email() === 'tom@example.com'
                && $user->address()->name() === 'Tom';
        });

        $http->followRedirects()
            ->assertStatus(200)
            ->assertBodyContains('Profile updated successfully.');
    }
    
    public function testProfileInformationCanBeUpdatedAndRemovesVerifiedChannelsIfChanged()
    {
        $events = $this->fakeEvents();
        $auth = $this->fakeAuth();
        $http = $this->fakeHttp();
        $http->request(
            method: 'PATCH',
            uri: 'profile',
            body: [
                'address' => ['name' => 'Tom'],
                'email' => 'new@example.com',
            ],
        );
        
        $app = $this->bootingApp();
        $user = UserFactory::new(['verified' => ['email' => '2025-12-26 12:00']])->withEmail('tom@example.com')->createOne();
        $auth->authenticatedAs($user);
        
        $http->response()->assertStatus(302)->assertRedirectToRoute(name: 'profile.edit');
        
        $events->assertDispatched(Event\UpdatedProfile::class, static function(Event\UpdatedProfile $event): bool {
            $user = $event->user();
            return $user->email() === 'new@example.com'
                && ! $user->isVerified(channels: ['email']);
        });

        $http->followRedirects()
            ->assertStatus(200)
            ->assertBodyContains('Profile updated successfully.');
    }
    
    public function testProfileInformationCantBeUpdatedIfNotAuthenticated()
    {
        $http = $this->fakeHttp();
        $http->request(
            method: 'PATCH',
            uri: 'profile',
            body: [
                'address' => ['name' => 'Tom'],
                'email' => 'tom@example.com',
            ],
        );

        $http->followRedirects()
            ->assertStatus(200)
            ->assertBodyContains('You have insufficient rights to access the requested resource!');
    }
    
    public function testProfilePasswordCanBeUpdated()
    {
        $events = $this->fakeEvents();
        $auth = $this->fakeAuth();
        $http = $this->fakeHttp();
        $http->request(
            method: 'PATCH',
            uri: 'profile/password',
            body: [
                'current_password' => 'password',
                'password' => 'new-password',
                'password_confirmation' => 'new-password',
            ],
        );
        
        $app = $this->bootingApp();
        $user = UserFactory::new()->withPassword('password')->createOne();
        $auth->authenticatedAs($user);
        
        $http->response()->assertStatus(302)->assertRedirectToRoute(name: 'profile.edit');
        
        $events->assertDispatched(Event\UpdatedProfile::class);
        
        $http->followRedirects()
            ->assertStatus(200)
            ->assertBodyContains('Password updated successfully.');
    }
    
    public function testProfilePasswordCantBeUpdatedIfWrongPassword()
    {
        $events = $this->fakeEvents();
        $auth = $this->fakeAuth();
        $http = $this->fakeHttp();
        $http->request(
            method: 'PATCH',
            uri: 'profile/password',
            body: [
                'current_password' => 'wrong-password',
                'password' => 'new-password',
                'password_confirmation' => 'new-password',
            ],
        );
        
        $app = $this->bootingApp();
        $user = UserFactory::new()->withPassword('password')->createOne();
        $auth->authenticatedAs($user);
        
        $http->followRedirects()
            ->assertStatus(200)
            ->assertBodyContains('The password could not be updated.');
    }
    
    public function testProfilePasswordCantBeUpdatedIfNotAuthenticated()
    {
        $http = $this->fakeHttp();
        $http->request(
            method: 'PATCH',
            uri: 'profile/password',
            body: [
                'current_password' => 'password',
                'password' => 'new-password',
                'password_confirmation' => 'new-password',
            ],
        );
        
        $http->followRedirects()
            ->assertStatus(200)
            ->assertBodyContains('You have insufficient rights to access the requested resource!');
    }
    
    public function testAccountCanBeDeleted()
    {
        $events = $this->fakeEvents();
        $auth = $this->fakeAuth();
        $http = $this->fakeHttp();
        $http->request(
            method: 'DELETE',
            uri: 'profile',
            body: [
                'delete_password' => 'password',
            ],
        );
        
        $app = $this->bootingApp();
        $user = UserFactory::new()->withPassword('password')->createOne();
        $auth->authenticatedAs($user);

        $http->response()->assertStatus(302)->assertRedirectToRoute(name: 'home');
        $auth->assertNotAuthenticated();
        $events->assertDispatched(Event\Logout::class);
        $events->assertDispatched(Event\DeletedAccount::class);
        
        $http->followRedirects()
            ->assertStatus(200)
            ->assertBodyContains('Account deleted successfully.');
    }
    
    public function testAccountCantBeDeletedIfWrongPassword()
    {
        $events = $this->fakeEvents();
        $auth = $this->fakeAuth();
        $http = $this->fakeHttp();
        $http->request(
            method: 'DELETE',
            uri: 'profile',
            body: [
                'delete_password' => 'wrong-password',
            ],
        );
        
        $app = $this->bootingApp();
        $user = UserFactory::new()->withPassword('password')->createOne();
        $auth->authenticatedAs($user);
        
        $http->followRedirects()
            ->assertStatus(200)
            ->assertBodyContains('The account could not be deleted.');
    }
    
    public function testAccountCantBeDeletedIfNotAuthenticated()
    {
        $http = $this->fakeHttp();
        $http->request(
            method: 'DELETE',
            uri: 'profile',
            body: [
                'delete_password' => 'password',
            ],
        );
        
        $http->followRedirects()
            ->assertStatus(200)
            ->assertBodyContains('You have insufficient rights to access the requested resource!');
    }
}
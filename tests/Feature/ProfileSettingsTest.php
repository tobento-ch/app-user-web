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

class ProfileSettingsTest extends \Tobento\App\Testing\TestCase
{
    use RefreshDatabases;
    
    public function createApp(): AppInterface
    {
        $app = $this->createTmpApp(rootDir: __DIR__.'/../..');
        $app->boot(\Tobento\App\User\Web\Boot\UserWeb::class);
        $app->boot(\Tobento\App\Seeding\Boot\Seeding::class);
        return $app;
    }

    public function testProfileSeetingsScreenIsRendered()
    {
        $auth = $this->fakeAuth();
        $http = $this->fakeHttp();
        $http->request(method: 'GET', uri: 'profile/settings');
        
        $app = $this->bootingApp();
        $user = UserFactory::new()->createOne();
        $auth->authenticatedAs($user);
        
        $http->response()
            ->assertStatus(200)
            ->assertBodyContains('Profile Settings')
            ->assertBodyContains('Notifications');
    }
    
    public function testProfileSettingsScreenIsNotRenderedIfNotAuthenticated()
    {
        $http = $this->fakeHttp();
        $http->request(method: 'GET', uri: 'profile/settings');
        
        $http->followRedirects()
            ->assertStatus(200)
            ->assertBodyContains('You have insufficient rights to access the requested resource!');
    }
    
    public function testProfileSettingsMenuIsNotShownIfNotAuthenticated()
    {
        $http = $this->fakeHttp();
        $http->request(method: 'GET', uri: '');
        
        $http->response()
            ->assertStatus(200)
            ->assertBodyNotContains('Profile Settings');
    }
    
    public function testProfileSettingsCanBeUpdated()
    {
        $events = $this->fakeEvents();
        $auth = $this->fakeAuth();
        $http = $this->fakeHttp();
        $http->request(
            method: 'PATCH',
            uri: 'profile/settings',
            body: [
                'locale' => 'en',
                'newsletter' => 1,
                'settings' => [
                    'preferred_notification_channels' => ['mail'],
                ],
            ],
        );

        $app = $this->bootingApp();
        $user = UserFactory::new(replaces: ['settings' => ['foo' => 'Foo']])->withEmail('tom@example.com')->createOne();
        $auth->authenticatedAs($user);
        
        $http->response()->assertStatus(302)->assertRedirectToRoute(name: 'profile.settings.edit');
        
        $events->assertDispatched(Event\UpdatedProfile::class, static function(Event\UpdatedProfile $event): bool {
            $user = $event->user();
            return $user->setting('preferred_notification_channels', []) === ['mail'];
        });
        
        $user = $auth->getUserRepository()->findByIdentity(email: 'tom@example.com');
        
        $this->assertSame(
            [
                'foo' => 'Foo',
                'preferred_notification_channels' => ['mail']
            ],
            $user?->getSettings()
        );
    }
    
    public function testProfileSettingsCanNotBeUpdatedIfInvalidLocale()
    {
        $events = $this->fakeEvents();
        $auth = $this->fakeAuth();
        $http = $this->fakeHttp();
        $http->request(
            method: 'PATCH',
            uri: 'profile/settings',
            body: [
                'locale' => 'de',
                'newsletter' => 1,
                'settings' => [
                    'preferred_notification_channels' => ['mail', 'sms'],
                ],
            ],
        );

        $app = $this->bootingApp();
        $user = UserFactory::new()->createOne();
        $auth->authenticatedAs($user);
        
        $http->response()->assertStatus(302)->assertRedirectToRoute(name: 'profile.settings.edit');
        $events->assertNotDispatched(Event\UpdatedProfile::class);
        
        $http->followRedirects()
            ->assertStatus(200)
            ->assertBodyContains('The profile settings could not be updated.');
    }
    
    public function testProfileSettingsCanNotBeUpdatedIfInvalidChannel()
    {
        $events = $this->fakeEvents();
        $auth = $this->fakeAuth();
        $http = $this->fakeHttp();
        $http->request(
            method: 'PATCH',
            uri: 'profile/settings',
            body: [
                'locale' => 'en',
                'newsletter' => 1,
                'settings' => [
                    'preferred_notification_channels' => ['mail', 'invalid'],
                ],
            ],
        );

        $app = $this->bootingApp();
        $user = UserFactory::new()->createOne();
        $auth->authenticatedAs($user);
        
        $http->response()->assertStatus(302)->assertRedirectToRoute(name: 'profile.settings.edit');
        $events->assertNotDispatched(Event\UpdatedProfile::class);
        
        $http->followRedirects()
            ->assertStatus(200)
            ->assertBodyContains('The profile settings could not be updated.');
    }
    
    public function testProfileSettingsChannelsAreNotDisplayedIfNone()
    {
        $auth = $this->fakeAuth();
        $http = $this->fakeHttp();
        $http->request(method: 'GET', uri: 'profile/settings');
        
        $app = $this->getApp();
        $app->on(AvailableChannelsInterface::class, function (AvailableChannelsInterface $channels) {
            return $channels->only([]);
        });
        
        $app = $this->bootingApp();
        $user = UserFactory::new()->createOne();
        $auth->authenticatedAs($user);
        
        $http->response()
            ->assertStatus(200)
            ->assertBodyContains('Profile Settings')
            ->assertBodyNotContains('Preferred Channels');
    }
    
    public function testProfileSettingsScreenIsRenderedInLocaleDe()
    {
        $this->fakeConfig()->with('user_web.features', [
            Feature\Login::class,
            Feature\Verification::class,
            new Feature\ProfileSettings(
                localizeRoute: true,
            ),
        ]);
        
        $auth = $this->fakeAuth();
        $http = $this->fakeHttp();
        $http->request(method: 'GET', uri: 'de/profil/settings');
        
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
            ->assertBodyContains('Profil Einstellungen')
            ->assertBodyContains('Benachrichtigungen');
    }
}
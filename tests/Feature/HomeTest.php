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
use Tobento\App\User\Web\Feature;
use Tobento\Service\Language\LanguageFactory;
use Tobento\Service\Language\LanguagesInterface;
use Tobento\Service\Language\Languages;

class HomeTest extends \Tobento\App\Testing\TestCase
{
    public function createApp(): AppInterface
    {
        $app = $this->createTmpApp(rootDir: __DIR__.'/../..');
        $app->boot(\Tobento\App\User\Web\Boot\UserWeb::class);
        $app->boot(\Tobento\App\Seeding\Boot\Seeding::class);
        return $app;
    }

    public function testHomeScreenIsRendered()
    {
        $http = $this->fakeHttp();
        $http->request(method: 'GET', uri: '');
        $http->response()
            ->assertStatus(200)
            ->assertBodyContains('Welcome');
    }
    
    public function testHomeScreenIsRenderedInLocaleDe()
    {
        $this->fakeConfig()->with('user_web.features', [
            new Feature\Home(
                localizeRoute: true,
            ),
        ]);
        
        $http = $this->fakeHttp();
        $http->request(method: 'GET', uri: 'de/');
        
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
            ->assertBodyContains('html lang="de"');
    }
}
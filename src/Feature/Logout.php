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
 
namespace Tobento\App\User\Web\Feature;

use Tobento\App\AppInterface;
use Tobento\App\User\Authentication\AuthInterface;
use Tobento\App\User\Middleware\Authenticated;
use Tobento\App\User\Web\Event;
use Tobento\App\Language\RouteLocalizerInterface;
use Tobento\Service\Routing\RouterInterface;
use Tobento\Service\Requester\RequesterInterface;
use Tobento\Service\Responser\ResponserInterface;
use Tobento\Service\Menu\MenusInterface;
use Tobento\Service\Form\TokenizerInterface;
use Tobento\Service\Menu\Str;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Message\ResponseInterface;
use function Tobento\App\Translation\{trans};

/**
 * Logout.
 */
class Logout
{
    /**
     * Create a new Logout.
     *
     * @param null|string $menu The menu name or null if none.
     * @param string $menuLabel The menu label.
     * @param null|string $menuParent The menu parent or null if none.
     * @param string $redirectRoute
     * @param string $unauthenticatedMessage
     * @param null|string $unauthenticatedRedirectRoute
     * @param bool $localizeRoute
     */
    public function __construct(
        protected null|string $menu = 'header',
        protected string $menuLabel = 'Log out',
        protected null|string $menuParent = null,
        protected string $redirectRoute = 'home',
        protected string $unauthenticatedMessage = 'You have insufficient rights to access the requested resource!',
        protected null|string $unauthenticatedRedirectRoute = null,
        protected bool $localizeRoute = false,
    ) {}
    
    /**
     * Boot the page.
     *
     * @param RouterInterface $router
     * @param AuthInterface $auth
     * @param AppInterface $app
     * @return void
     */
    public function __invoke(
        RouterInterface $router,
        AuthInterface $auth,
        AppInterface $app,
    ): void {
        // Routes:
        $uri = $this->localizeRoute ? '{?locale}/logout' : 'logout';
        
        $route = $router->post($uri, [$this, 'logout'])
            ->name('logout')
            ->middleware(...$this->configureMiddlewares($app));
        
        if ($this->localizeRoute) {
            $app->get(RouteLocalizerInterface::class)->localizeRoute($route);
        }
        
        // Menus:
        if ($this->menu) {
            $app->on(MenusInterface::class, function(MenusInterface $menus, TokenizerInterface $tokenizer) use ($router, $auth) {
                if ($auth->hasAuthenticated()) {
                    $menus->menu($this->menu)
                        ->html('<form method="POST" action="'.Str::esc($router->url('logout')).'"><input name="_token" type="hidden" value="'.Str::esc((string)$tokenizer->get('csrf')).'"><button class="link">'.Str::esc(trans($this->menuLabel)).'</button></form>')
                        ->parent($this->menuParent)
                        ->id('logout')
                        ->icon('logout');
                }
            });
        }
    }
    
    /**
     * Logout: Unauthenticates the user.
     *
     * @param RequesterInterface $requester
     * @param ResponserInterface $responser
     * @param AuthInterface $auth,
     * @param RouterInterface $router
     * @param null|EventDispatcherInterface $eventDispatcher
     * @return ResponseInterface
     */
    public function logout(
        RequesterInterface $requester,
        ResponserInterface $responser,
        AuthInterface $auth,
        RouterInterface $router,
        null|EventDispatcherInterface $eventDispatcher,
    ): ResponseInterface {
        $auth->close();
        
        if ($auth->getUnauthenticated()) {
            $eventDispatcher?->dispatch(new Event\Logout(
                unauthenticated: $auth->getUnauthenticated(),
            ));            
        }

        return $responser->redirect($router->url($this->redirectRoute));
    }
    
    /**
     * Configure middlewares for the route(s).
     *
     * @param AppInterface $app
     * @return array
     */
    protected function configureMiddlewares(AppInterface $app): array
    {
        return [
            // The Authenticated::class middleware protects routes from unauthenticated users:
            [
                Authenticated::class,

                // you may specify a custom message to show to the user:
                'message' => $this->unauthenticatedMessage,

                // you may specify a message level:
                //'messageLevel' => 'notice',

                // you may specify a route name for redirection:
                'redirectRoute' => $this->unauthenticatedRedirectRoute,
            ],
            // The Verified::class middleware protects routes from unverified users:
            /*[
                Verified::class,

                // you may specify a custom message to show to the user:
                'message' => 'You have insufficient rights to access the requested resource!',

                // you may specify a message level:
                'messageLevel' => 'notice',

                // you may specify a route name for redirection:
                'redirectRoute' => 'verification.account',
            ],*/
        ];
    }
}
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
use Tobento\App\Language\RouteLocalizerInterface;
use Tobento\Service\Routing\RouterInterface;
use Tobento\Service\Responser\ResponserInterface;
use Tobento\Service\Menu\MenusInterface;
use function Tobento\App\Translation\{trans};

/**
 * Home
 */
class Home
{
    /**
     * Create a new Home.
     *
     * @param string $view
     * @param null|string $menu The menu name or null if none.
     * @param string $menuLabel The menu label.
     * @param null|string $menuParent The menu parent or null if none.
     * @param bool $localizeRoute
     */
    public function __construct(
        protected string $view = 'user/home',
        protected null|string $menu = 'main',
        protected string $menuLabel = 'Home',
        protected null|string $menuParent = null,
        protected bool $localizeRoute = false,
    ) {}
    
    /**
     * Boot the page.
     *
     * @param RouterInterface $router
     * @param AppInterface $app
     * @return void
     */
    public function __invoke(
        RouterInterface $router,
        AppInterface $app,
    ): void {
        // Routes:
        $uri = $this->localizeRoute ? '{?locale}/' : '';
        
        $route = $router->get($uri, function(ResponserInterface $responser) {
            return $responser->render(view: $this->view);
        })->name('home');
        
        if ($this->localizeRoute) {
            $app->get(RouteLocalizerInterface::class)->localizeRoute($route);
        }
        
        $this->configureRoutes($router, $app);

        // Menus:
        if ($this->menu) {
            $app->on(MenusInterface::class, function(MenusInterface $menus) use ($router) {
                $menus->menu($this->menu)
                    ->link($router->url('home'), trans($this->menuLabel))
                    ->parent($this->menuParent)
                    ->id('home')
                    ->icon('home');
            });
        }
    }
    
    /**
     * Configure routes
     *
     * @param RouterInterface $router
     * @param AppInterface $app
     * @return void
     */
    protected function configureRoutes(RouterInterface $router, AppInterface $app): void
    {
        // $router->getRoute(name: 'home')->middleware();
    }
}
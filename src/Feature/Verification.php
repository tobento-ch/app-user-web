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
use Tobento\App\User\UserInterface;
use Tobento\App\User\UserRepositoryInterface;
use Tobento\App\User\Middleware\Authenticated;
use Tobento\App\User\Web\PinCodeVerificatorInterface;
use Tobento\App\User\Web\Event;
use Tobento\App\User\Web\Exception\VerificationTokenException;
use Tobento\App\User\Exception\AuthorizationException;
use Tobento\App\Language\RouteLocalizerInterface;
use Tobento\App\Notifier\AvailableChannelsInterface;
use Tobento\Service\Routing\RouterInterface;
use Tobento\Service\Routing\RouteGroupInterface;
use Tobento\Service\Requester\RequesterInterface;
use Tobento\Service\Responser\ResponserInterface;
use Tobento\Service\Session\SessionInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Clock\ClockInterface;
use DateInterval;

/**
 * Verification
 */
class Verification
{
    /**
     * Create a new Verification.
     *
     * @param string $viewAccount
     * @param string $viewChannel
     * @param int|DateInterval $codeExpiresAfter
     * @param int $canReissueCodeAfter The seconds a new code can be reissued.
     * @param string $unauthenticatedMessage
     * @param null|string $unauthenticatedRedirectRoute
     * @param string $verifiedRedirectRoute The route to redirect verified user to.
     * @param bool $localizeRoute
     */
    public function __construct(
        protected string $viewAccount = 'user/verification/account',
        protected string $viewChannel = 'user/verification/channel',
        protected int|DateInterval $codeExpiresAfter = 300,
        protected int $canReissueCodeAfter = 60,
        protected string $unauthenticatedMessage = 'You have insufficient rights to access the requested resource!',
        protected null|string $unauthenticatedRedirectRoute = null,
        protected string $verifiedRedirectRoute = 'home',
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
        $route = $router->group('', function(RouteGroupInterface $route) {
            $uri = $this->localizeRoute ? '{?locale}/{verification}' : 'verification';
            $route->get($uri, [$this, 'account'])->name('verification.account');
            $route->get($uri.'/{channel}', [$this, 'channel'])->name('verification.channel');
            $route->post($uri.'/{channel}/resend', [$this, 'resend'])->name('verification.resend');
            $route->post($uri.'/{channel}/verify', [$this, 'verify'])->name('verification.verify');
        })->middleware(...$this->configureMiddlewares($app));

        if ($this->localizeRoute) {
            $app->get(RouteLocalizerInterface::class)->localizeRoute($route, 'verification');
        }
        
        $this->configureRoutes($router, $app);
    }
    
    /**
     * Display the verification account view.
     *
     * @param ServerRequestInterface $request
     * @param ResponserInterface $responser
     * @param AvailableChannelsInterface $channels
     * @return ResponseInterface
     */
    public function account(
        ServerRequestInterface $request,
        ResponserInterface $responser,
        AvailableChannelsInterface $channels,
    ): ResponseInterface {
        
        $user = $request->getAttribute(UserInterface::class);
        
        if (is_null($user) || !$user->isAuthenticated()) {
            throw new AuthorizationException();
        }
        
        return $responser->render(
            view: $this->viewAccount,
            data: [
                'user' => $user,
                'channelColumns' => ['channel', 'actions'],
                'channels' => $this->configureAvailableChannels($channels, $user),
            ],
        );
    }
    
    /**
     * Display the verification channel view.
     *
     * @param string $channel
     * @param ServerRequestInterface $request
     * @param ResponserInterface $responser
     * @param AvailableChannelsInterface $channels
     * @param PinCodeVerificatorInterface $verificator
     * @return ResponseInterface
     */
    public function channel(
        string $channel,
        ServerRequestInterface $request,
        ResponserInterface $responser,
        AvailableChannelsInterface $channels,
        PinCodeVerificatorInterface $verificator,
    ): ResponseInterface {
        
        $user = $request->getAttribute(UserInterface::class);
        
        if (! $this->canVerifyChannel($channel, $channels, $user)) {
            throw new AuthorizationException();
        }
        
        if (! $verificator->hasCode(type: $channel, user: $user)) {
            $verificator->sendCode(
                type: $channel,
                user: $user,
                expiresAfter: $this->codeExpiresAfter,
                channels: [$this->getNotifierChannelFor($channel)],
            );
        }
        
        return $responser->render(
            view: $this->viewChannel,
            data: ['user' => $user, 'channel' => $channel],
        );
    }
    
    /**
     * Verify channel.
     *
     * @param string $channel
     * @param RequesterInterface $requester
     * @param ResponserInterface $responser
     * @param AvailableChannelsInterface $channels
     * @param UserRepositoryInterface $userRepository
     * @param RouterInterface $router
     * @param PinCodeVerificatorInterface $verificator
     * @param ClockInterface $clock
     * @param null|EventDispatcherInterface $eventDispatcher
     * @return ResponseInterface
     */
    public function verify(
        string $channel,
        RequesterInterface $requester,
        ResponserInterface $responser,
        AvailableChannelsInterface $channels,
        UserRepositoryInterface $userRepository,
        RouterInterface $router,
        PinCodeVerificatorInterface $verificator,
        ClockInterface $clock,
        null|EventDispatcherInterface $eventDispatcher,
    ): ResponseInterface {
        
        $user = $requester->request()->getAttribute(UserInterface::class);
        
        if (! $this->canVerifyChannel($channel, $channels, $user)) {
            throw new AuthorizationException();
        }
        
        $code = $requester->input()->get('code', '');
        
        try {
            $verificator->verifyCode(code: $code, type: $channel, user: $user);
        } catch (VerificationTokenException $e) {
            $eventDispatcher?->dispatch(new Event\VerifyChannelFailed(
                user: $user,
                channel: $channel,
                exception: $e,
            ));
            
            $responser->messages()->add(level: 'error', message: 'The code has been expired or is invalid.');
            return $responser->redirect($router->url('verification.channel', ['channel' => $channel]));    
        }
        
        $verificator->deleteCode(type: $channel, user: $user);
        
        // Add verified channel:
        $userRepository->addVerified(
            id: $user->id(),
            channel: $channel,
            verifiedAt: $clock->now(),
        );
        
        // dispatch event:
        $eventDispatcher?->dispatch(new Event\VerifiedChannel(user: $user, channel: $channel));
        
        // response:
        $responser->messages()->add(level: 'success', message: 'Channel verified successfully.');
        return $responser->redirect($router->url($this->verifiedRedirectRoute));
    }
    
    /**
     * Resends the verification.
     *
     * @param string $channel
     * @param RequesterInterface $requester
     * @param ResponserInterface $responser
     * @param AvailableChannelsInterface $channels
     * @param RouterInterface $router
     * @param PinCodeVerificatorInterface $verificator
     * @return ResponseInterface
     */
    public function resend(
        string $channel,
        RequesterInterface $requester,
        ResponserInterface $responser,
        AvailableChannelsInterface $channels,
        RouterInterface $router,
        PinCodeVerificatorInterface $verificator,
    ): ResponseInterface {
        
        $user = $requester->request()->getAttribute(UserInterface::class);
        
        if (! $this->canVerifyChannel($channel, $channels, $user)) {
            throw new AuthorizationException();
        }
        
        // check code (throttling):
        if ($token = $verificator->findCode(type: $channel, user: $user)) {
            $availableInSeconds = $verificator->codeIssuedLessThan(token: $token, seconds: $this->canReissueCodeAfter);
            
            if ($availableInSeconds) {
                $responser->messages()->add(
                    level: 'error',
                    message: 'You have already requested a verification code. Please check your :channel or retry after :seconds seconds.',
                    parameters: [':channel' => $channel, ':seconds' => $availableInSeconds],
                );
                
                return $responser
                    ->withInput($requester->input()->all())
                    ->redirect($router->url('verification.channel', ['channel' => $channel]));
            }
        }
        
        $verificator->sendCode(
            type: $channel,
            user: $user,
            expiresAfter: $this->codeExpiresAfter,
            channels: [$this->getNotifierChannelFor($channel)],
        );
        
        // create and return response:
        $responser->messages()->add('notice', 'A new verification code has been sent.');
        
        return $responser->redirect($router->url('verification.channel', ['channel' => $channel]));
    }
    
    /**
     * Configure middlewares for the route(s).
     *
     * @param AppInterface $app
     * @return array
     */
    protected function configureMiddlewares(AppInterface $app): array
    {
        // User must be authenticated at least
        // as we need user for code verification!
        
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
        ];
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
        // $router->getRoute(name: 'verification.account')->middleware();
        // $router->getRoute(name: 'verification.channel')->middleware();
        // $router->getRoute(name: 'verification.resend')->middleware();
        // $router->getRoute(name: 'verification.verify')->middleware();
    }
    
    /**
     * Configure the available channels.
     *
     * @param AvailableChannelsInterface $channels
     * @param UserInterface $user
     * @return AvailableChannelsInterface
     */
    protected function configureAvailableChannels(
        AvailableChannelsInterface $channels,
        UserInterface $user,
    ): AvailableChannelsInterface {
        return $channels->only(['mail', 'sms']);
    }
    
    /**
     * Returns the notifier channel for the given verification channel.
     *
     * @param string $channel
     * @return null|string
     */
    protected function getNotifierChannelFor(string $channel): null|string
    {
        return match ($channel) {
            'email' => 'mail',
            'smartphone' => 'sms',
            default => null,
        };
    }
    
    /**
     * Determine if the channel can be verified.
     *
     * @param string $channel
     * @param AvailableChannelsInterface $channels
     * @param null|UserInterface $user
     * @return bool
     */
    protected function canVerifyChannel(string $channel, AvailableChannelsInterface $channels, null|UserInterface $user): bool
    {
        if (is_null($user) || !$user->isAuthenticated()) {
            return false;
        }
        
        if (! $channels->has((string)$this->getNotifierChannelFor($channel))) {
            return false;
        }
        
        return match ($channel) {
            'email' => !empty($user->email()) && ! $user->isVerified([$channel]),
            'smartphone' => !empty($user->smartphone()) && ! $user->isVerified([$channel]),
            default => false,
        };
    }
}
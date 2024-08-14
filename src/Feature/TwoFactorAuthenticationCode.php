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
use Tobento\App\User\Authentication\AuthInterface;
use Tobento\App\User\Authentication\Authenticated;
use Tobento\App\User\Authentication\AuthenticatedInterface;
use Tobento\App\User\Authentication\Token\TokenStorageInterface;
use Tobento\App\User\Middleware\Authenticated as AuthenticatedMiddleware;
use Tobento\App\User\Web\Event;
use Tobento\App\User\Web\PinCodeVerificatorInterface;
use Tobento\App\User\Web\Exception\VerificationTokenException;
use Tobento\App\User\Exception\AuthorizationException;
use Tobento\App\Notifier\AvailableChannelsInterface;
use Tobento\App\Language\RouteLocalizerInterface;
use Tobento\Service\Routing\RouterInterface;
use Tobento\Service\Routing\RouteGroupInterface;
use Tobento\Service\Requester\RequesterInterface;
use Tobento\Service\Responser\ResponserInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Clock\ClockInterface;
use DateInterval;

/**
 * TwoFactorAuthenticationCode
 */
class TwoFactorAuthenticationCode
{
    /**
     * Create a new TwoFactorAuthentication.
     *
     * @param string $view
     * @param int|DateInterval $expiresAfter
     *   The period of time from the present after which the item MUST be considered
     *   expired. An integer parameter is understood to be the time in seconds until expiration.
     * @param int|DateInterval $remember Null if no remember at all, otherwise
     *   the period of time from the present after which the item MUST be considered
     *   expired. An integer parameter is understood to be the time in seconds until expiration.
     * @param int|DateInterval $codeExpiresAfter
     * @param int $canReissueCodeAfter The seconds a new code can be reissued.
     * @param string $unauthenticatedMessage
     * @param null|string $unauthenticatedRedirectRoute
     * @param string $successRoute
     * @param null|string $successMessage
     * @param null|string $forgotPasswordRoute
     * @param bool $localizeRoute
     */
    public function __construct(
        protected string $view = 'user/twofactor-code',
        protected null|int|DateInterval $expiresAfter = null,
        protected null|int|DateInterval $rememberExpiresAfter = null,
        protected int|DateInterval $codeExpiresAfter = 300,
        protected int $canReissueCodeAfter = 60,
        protected string $unauthenticatedMessage = 'You have insufficient rights to access the requested resource!',
        protected null|string $unauthenticatedRedirectRoute = null,
        protected string $successRoute = 'home',
        protected null|string $successMessage = 'Welcome back :greeting.',
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
            $uri = $this->localizeRoute ? '{?locale}/two-factor/code' : 'two-factor/code';
            $route->get($uri, [$this, 'showCode'])->name('twofactor.code.show');
            $route->post($uri.'/resend', [$this, 'resendCode'])->name('twofactor.code.resend');
            $route->post($uri.'/verify', [$this, 'verifyCode'])->name('twofactor.code.verify');
        })->middleware(...$this->configureMiddlewares($app));
        
        if ($this->localizeRoute) {
            $app->get(RouteLocalizerInterface::class)->localizeRoute($route);
        }
    }
    
    /**
     * Display the user's code page.
     *
     * @param ResponserInterface $responser
     * @return ResponseInterface
     */
    public function showCode(
        ServerRequestInterface $request,
        ResponserInterface $responser,
        AvailableChannelsInterface $channels,
        PinCodeVerificatorInterface $verificator,
    ): ResponseInterface {
        $user = $request->getAttribute(UserInterface::class);
        
        if (! $this->canVerifyCode($channels, $user)) {
            throw new AuthorizationException();
        }
        
        if (! $verificator->hasCode(type: 'twofactor', user: $user)) {
            $verificator->sendCode(
                type: 'twofactor',
                user: $user,
                expiresAfter: $this->codeExpiresAfter,
                channels: $this->configureAvailableChannels($channels, $user)->names(),
            );
        }
        
        return $responser->render(
            view: $this->view,
            data: ['user' => $user],
        );
    }
    
    /**
     * Resends the verification code.
     *
     * @param RequesterInterface $requester
     * @param ResponserInterface $responser
     * @param AvailableChannelsInterface $channels
     * @param RouterInterface $router
     * @param PinCodeVerificatorInterface $verificator
     * @return ResponseInterface
     */
    public function resendCode(
        RequesterInterface $requester,
        ResponserInterface $responser,
        AvailableChannelsInterface $channels,
        RouterInterface $router,
        PinCodeVerificatorInterface $verificator,
    ): ResponseInterface {
        $user = $requester->request()->getAttribute(UserInterface::class);
        
        if (! $this->canVerifyCode($channels, $user)) {
            throw new AuthorizationException();
        }
        
        $channels = $this->configureAvailableChannels($channels, $user);
        
        // check code (throttling):
        if ($token = $verificator->findCode(type: 'twofactor', user: $user)) {
            $availableInSeconds = $verificator->codeIssuedLessThan(token: $token, seconds: $this->canReissueCodeAfter);
            
            if ($availableInSeconds) {
                $responser->messages()->add(
                    level: 'error',
                    message: 'You have already requested a verification code. Please check your :channel or retry after :seconds seconds.',
                    parameters: [
                        ':channel' => $this->configureTitleForChannels($channels)->titlesToString(),
                        ':seconds' => $availableInSeconds
                    ],
                );
                
                return $responser
                    ->withInput($requester->input()->all())
                    ->redirect($router->url('twofactor.code.show'));
            }
        }
        
        $verificator->sendCode(
            type: 'twofactor',
            user: $user,
            expiresAfter: $this->codeExpiresAfter,
            channels: $channels->names(),
        );
        
        // create and return response:
        $responser->messages()->add('notice', 'A new verification code has been sent.');
        
        return $responser->redirect($router->url('twofactor.code.show'));
    }
    
    /**
     * Verify code.
     *
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
    public function verifyCode(
        RequesterInterface $requester,
        ResponserInterface $responser,
        AuthInterface $auth,
        AvailableChannelsInterface $channels,
        RouterInterface $router,
        PinCodeVerificatorInterface $verificator,
        TokenStorageInterface $tokenStorage,
        ClockInterface $clock,
        null|EventDispatcherInterface $eventDispatcher,
    ): ResponseInterface {
        
        $user = $auth->getAuthenticated()?->user();
        
        if (! $this->canVerifyCode($channels, $user)) {
            throw new AuthorizationException();
        }
        
        $code = $requester->input()->get('code', '');
        
        try {
            $verificator->verifyCode(code: $code, type: 'twofactor', user: $user);
        } catch (VerificationTokenException $e) {
            $eventDispatcher?->dispatch(new Event\VerifyTwoFactorCodeFailed(user: $user, exception: $e));
            
            $responser->messages()->add(level: 'error', message: 'The code has been expired or is invalid.');
            return $responser->redirect($router->url('twofactor.code.show'));    
        }
        
        $verificator->deleteCode(type: 'twofactor', user: $user);
        
        // dispatch event:
        $eventDispatcher?->dispatch(new Event\VerifiedTwoFactorCode(user: $user));
        
        $prevToken = $auth->getAuthenticated()->token();
        
        // create new token and start auth:
        $token = $tokenStorage->createToken(
            // Set the payload:
            payload: $prevToken->payload(),

            // Set the name of which the user was authenticated via:
            authenticatedVia: 'twofactor-code',
            
            // Set the name of which the user was authenticated by (authenticator name) or null if none:
            authenticatedBy: $prevToken->authenticatedBy(),
            
            // Set the point in time the token has been issued or null (now):
            issuedAt: $prevToken->issuedAt(),
            
            // Set the point in time after which the token MUST be considered expired or null:
            // The time might depend on the token storage e.g. session expiration!
            expiresAt: $prevToken->expiresAt(),
        );
        
        $authenticated = new Authenticated(token: $token, user: $user);
        
        $auth->start($authenticated);
        
        // response:
        if ($this->successMessage) {
            $responser->messages()->add(
                level: 'success',
                message: $this->successMessage,
                parameters: [':greeting' => $user->greeting()]
            );
        }
            
        return $responser->redirect($router->url($this->successRoute));
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
            // The AuthenticatedMiddleware::class middleware protects routes from unauthenticated users:
            [
                AuthenticatedMiddleware::class,
                
                // allow access only to user authenticated via:
                'via' => 'loginform-twofactor',

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
     * Configure the available channels the verification code will be send to.
     *
     * @param AvailableChannelsInterface $channels
     * @param UserInterface $user
     * @return AvailableChannelsInterface
     */
    protected function configureAvailableChannels(
        AvailableChannelsInterface $channels,
        UserInterface $user,
    ): AvailableChannelsInterface {
        // send only sms if available:
        if (!empty($user->smartphone()) && $channels->has('sms')) {
            return $channels->only(['sms']);
        }
        
        // send email if available:
        if (!empty($user->email()) && $channels->has('mail')) {
            return $channels->only(['mail']);
        }
        
        return $channels->only(['mail', 'sms']);
    }
    
    /**
     * Configure the channel titles.
     *
     * @param AvailableChannelsInterface $channels
     * @return AvailableChannelsInterface
     */
    protected function configureTitleForChannels(AvailableChannelsInterface $channels): AvailableChannelsInterface
    {
        return $channels
            ->withTitle(channel: 'mail', title: 'Email')
            ->withTitle(channel: 'sms', title: 'Smartphone');
    }
    
    /**
     * Determine if the code can be verified.
     *
     * @param string $channel
     * @param AvailableChannelsInterface $channels
     * @param null|UserInterface $user
     * @return bool
     */
    protected function canVerifyCode(AvailableChannelsInterface $channels, null|UserInterface $user): bool
    {
        if (is_null($user) || !$user->isAuthenticated()) {
            return false;
        }
        
        return true;
    }
}
<?php
/**
 * TOBENTO
 *
 * @copyright   Tobias Strub, TOBENTO
 * @license     MIT License, see LICENSE file distributed with this source code.
 * @author      Tobias Strub
 * @link        https://www.tobento.ch
 */

use Tobento\App\User\Web;
use Tobento\App\User\Web\Feature;
use Tobento\App\RateLimiter\Symfony\Registry\SlidingWindow;
use Tobento\Service\Storage\StorageInterface;
use Tobento\Service\Routing\RouterInterface;
use Psr\Container\ContainerInterface;
use function Tobento\App\Translation\{trans};

return [
    
    /*
    |--------------------------------------------------------------------------
    | Features
    |--------------------------------------------------------------------------
    |
    | Specify and configure the features you wish to use or remove uneeded.
    |
    | See: https://github.com/tobento-ch/app-user-web#features
    |
    */
    
    'features' => [
        new Feature\Home(
            // The view to render:
            view: 'user/home',
            
            // A menu name to show the home link or null if none.
            menu: 'main',
            menuLabel: 'Home',
            // A menu parent name (e.g. 'user') or null if none.
            menuParent: null,
            
            // If true, routes are being localized.
            localizeRoute: false,
        ),
        
        new Feature\Login(
            // The view to render:
            view: 'user/login',
            
            // A menu name to show the login link or null if none.
            menu: 'header',
            menuLabel: 'Log in',
            // A menu parent name (e.g. 'user') or null if none.
            menuParent: null,
            
            // Specify the rate limiter:
            rateLimiter: new SlidingWindow(limit: 10, interval: '5 Minutes'),
            // see: https://github.com/tobento-ch/app-rate-limiter#available-rate-limiter-registries
            
            // Specify the identity attributes to be checked on login.
            identifyBy: ['email', 'username', 'smartphone', 'password'],
            // You may set a user verifier(s), see: https://github.com/tobento-ch/app-user#user-verifier
            /*userVerifier: function() {
                return new \Tobento\App\User\Authenticator\UserRoleVerifier('editor', 'author');
            },*/
            
            // The period of time from the present after which the auth token MUST be considered expired.
            expiresAfter: new \DateInterval('PT2H'), // int|\DateInterval
            
            // If you want to support remember. If set and the user wants to be remembered,
            // this value replaces the expiresAfter parameter.
            remember: new \DateInterval('P6M'), // null|int|\DateInterval
            
            // The message and redirect route if a user is authenticated.
            authenticatedMessage: 'You are logged in!',
            authenticatedRedirectRoute: 'home', // or null (no redirection)
            
            // The message shown if a login attempt fails.
            failedMessage: 'Invalid user or password.',
            
            // The redirect route after a successful login.
            successRoute: 'home',
            
            // The message shown after a user successfully log in.
            successMessage: 'Welcome back :greeting.', // or null
            
            // If set, it shows the forgot password link. Make sure the Feature\ForgotPassword is set too.
            forgotPasswordRoute: 'forgot-password.identity', // or null
            
            // The two factor authentication route.
            twoFactorRoute: 'twofactor.code.show',
            
            // If true, routes are being localized.
            localizeRoute: false,
        ),
        
        new Feature\TwoFactorAuthenticationCode(
            // The view to render:
            view: 'user/twofactor-code',
            
            // The period of time from the present after which the verification code MUST be considered expired.
            codeExpiresAfter: 300, // int|\DateInterval

            // The seconds after a new code can be reissued.
            canReissueCodeAfter: 60,
            
            // The message and redirect route if a user is unauthenticated.
            unauthenticatedMessage: 'You have insufficient rights to access the requested resource!',
            unauthenticatedRedirectRoute: 'home', // or null (no redirection)
            
            // The redirect route after a successful code verification.
            successRoute: 'home',
            
            // The message shown after a successful code verification.
            successMessage: 'Welcome back :greeting.', // or null
            
            // If true, routes are being localized.
            localizeRoute: false,
        ),
        
        new Feature\Logout(
            // A menu name to show the logout link or null if none.
            menu: 'header',
            menuLabel: 'Log out',
            // A menu parent name (e.g. 'user') or null if none.
            menuParent: null,
            
            // The redirect route after a successful logout.
            redirectRoute: 'home',
            
            // The message and redirect route if a user is unauthenticated.
            unauthenticatedMessage: 'You have insufficient rights to access the requested resource!',
            unauthenticatedRedirectRoute: 'home', // or null (no redirection)
            
            // If true, routes are being localized.
            localizeRoute: false,
        ),
        
        new Feature\ForgotPassword(
            viewIdentity: 'user/forgot-password/identity',
            viewReset: 'user/forgot-password/reset',
            
            // Specify the identity attributes to be checked on identity.
            identifyBy: ['email', 'username', 'smartphone'],
            // You may set a user verifier(s), see: https://github.com/tobento-ch/app-user#user-verifier
            /*userVerifier: function() {
                return new \Tobento\App\User\Authenticator\UserRoleVerifier('editor', 'author');
            },*/
            
            // The period of time from the present after which the verification token MUST be considered expired.
            tokenExpiresAfter: 300, // int|\DateInterval
            
            // The seconds after a new token can be reissued.
            canReissueTokenAfter: 60,

            // The message shown if an identity attempt fails.
            identityFailedMessage: 'Invalid name or user.',
            
            // The message and redirect route if a user is authenticated.
            authenticatedMessage: 'You are logged in!',
            authenticatedRedirectRoute: 'home', // or null (no redirection)
            
            // The redirect route after a successful password reset.
            successRedirectRoute: 'home',
            
            // The message shown after a successful password reset.
            successMessage: 'Your password has been reset!', // or null
            
            // If true, routes are being localized.
            localizeRoute: false,
        ),
        
        new Feature\Register(
            // The view to render:
            view: 'user/register',
            
            // A menu name to show the register link or null if none.
            menu: 'header',
            menuLabel: 'Register',
            // A menu parent name (e.g. 'user') or null if none.
            menuParent: null,
            
            // The message and redirect route if a user is authenticated.
            authenticatedMessage: 'You are logged in!',
            authenticatedRedirectRoute: 'home',
            
            // The default role key for the registered user.
            roleKey: 'registered',
            
            // The redirect route after a successful registration.
            successRedirectRoute: 'login',
            // You may redirect to the verification account page
            // see: https://github.com/tobento-ch/app-user-web#account-verification
            
            // If true, user has the option to subscribe to the newsletter.
            newsletter: false,
            
            // If a terms route is specified, users need to agree terms and conditions.
            termsRoute: null,
            /*termsRoute: static function (RouterInterface $router): string {
                return (string)$router->url('blog.show', ['key' => 'terms']);
            },*/
            
            // If true, routes are being localized.
            localizeRoute: false,
        ),
        
        new Feature\Profile(
            // The view to render:
            view: 'user/profile/edit',
            
            // A menu name to show the profile link or null if none.
            menu: 'main',
            menuLabel: 'Profile',
            // A menu parent name (e.g. 'user') or null if none.
            menuParent: null,

            // The message and redirect route if a user is unauthenticated.            
            unauthenticatedMessage: 'You have insufficient rights to access the requested resource!',
            unauthenticatedRedirectRoute: 'login', // or null (no redirection)
            
            // If true, it displays the channel verification section to verify channels.
            channelVerifications: true,
            
            // The redirect route after a successfully account deletion.
            successDeleteRedirectRoute: 'home',
            
            // If true, routes are being localized.
            localizeRoute: false,
        ),
        
        new Feature\ProfileSettings(
            // The view to render:
            view: 'user/profile/settings',
            
            // A menu name to show the profile settings link or null if none.
            menu: 'main',
            menuLabel: 'Profile Settings',
            // A menu parent name (e.g. 'user') or null if none.
            menuParent: null,

            // The message and redirect route if a user is unauthenticated.            
            unauthenticatedMessage: 'You have insufficient rights to access the requested resource!',
            unauthenticatedRedirectRoute: 'login', // or null (no redirection)
            
            // If true, routes are being localized.
            localizeRoute: false,
        ),
        
        new Feature\Notifications(
            // The view to render:
            view: 'user/notifications',
            
            // The notifier storage channel used to retrieve notifications.
            notifierStorageChannel: 'storage',
            
            // A menu name to show the notifications link or null if none.
            menu: 'main',
            menuLabel: 'Notifications',
            // A menu parent name (e.g. 'user') or null if none.
            menuParent: null,

            // The message and redirect route if a user is unauthenticated.            
            unauthenticatedMessage: 'You have insufficient rights to access the requested resource!',
            unauthenticatedRedirectRoute: 'login', // or null (no redirection)
            
            // If true, routes are being localized.
            localizeRoute: false,
        ),
        
        new Feature\Verification(
            // The view to render:
            viewAccount: 'user/verification/account',
            viewChannel: 'user/verification/channel',
            
            // The period of time from the present after which the verification code MUST be considered expired.
            codeExpiresAfter: 300, // int|\DateInterval
            
            // The seconds after a new code can be reissued.
            canReissueCodeAfter: 60,
            
            // The message and redirect route if a user is unauthenticated.            
            unauthenticatedMessage: 'You have insufficient rights to access the requested resource!',
            unauthenticatedRedirectRoute: 'login', // or null (no redirection)
            
            // The redirect route after a verified channel.
            verifiedRedirectRoute: 'home',
            
            // If true, routes are being localized.
            localizeRoute: false,
        ),
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Interfaces
    |--------------------------------------------------------------------------
    |
    | Do not change the interface's names as it may be used in other app bundles!
    |
    */
    
    'interfaces' => [
        // Verificators:
        Web\TokenVerificatorInterface::class => Web\TokenVerificator::class,
        
        Web\PinCodeVerificatorInterface::class => Web\PinCodeVerificator::class,
        
        // Token:
        Web\TokenFactoryInterface::class => Web\TokenFactory::class,
        
        Web\TokenRepository::class => static function(ContainerInterface $c) {
            return new Web\TokenRepository(
                storage: $c->get(StorageInterface::class)->new(),
                table: 'verification_tokens',
                entityFactory: $c->get(Web\TokenFactoryInterface::class),
            );
        },
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Verificator Hash Key
    |--------------------------------------------------------------------------
    |
    | This key should be set to a random, 32 character string.
    |
    */
    
    'verificator_hash_key' => '{verificator_hash_key}',
];
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
use Tobento\Service\Storage\StorageInterface;
use Psr\Container\ContainerInterface;
use function Tobento\App\Translation\{trans};

return [
    
    /*
    |--------------------------------------------------------------------------
    | Features
    |--------------------------------------------------------------------------
    |
    | Specify and configure the features you wish to use.
    |
    | See: https://github.com/tobento-ch/app-user-web#features
    |
    */
    
    'features' => [
        new Feature\Home(
            view: 'user/home',
            
            // A menu name to show the home link or null if none.
            menu: 'main',
            menuLabel: 'Home',
            // A menu parent name (e.g. 'user') or null if none.
            menuParent: null,
            
            // If true, routes are being localized.
            localizeRoute: true,
        ),
        
        new Feature\Login(
            view: 'user/login',
            
            // A menu name to show the login link or null if none.
            menu: 'header',
            menuLabel: trans('Log in'),
            // A menu parent name (e.g. 'user') or null if none.
            menuParent: null,
            
            // Specify the identity attributes to be checked on login.
            identifyBy: ['email', 'username', 'smartphone', 'password'],
            // You may set a user verifier(s), see: https://github.com/tobento-ch/app-user#user-verifier
            /*userVerifier: function() {
                return new \Tobento\App\User\Authenticator\UserRoleVerifier('editor', 'author');
            },*/
            
            // The period of time from the present after which the auth token MUST be considered expired.
            expiresAfter: 1500, //int|\DateInterval
            
            // If you want to support remember. If set, this value replaces expiresAfter parameter.
            remember: new \DateInterval('P6M'), //null|int|\DateInterval
            
            // The message and redirect route if a user is authenticated.
            authenticatedMessage: trans('You are logged in!'),
            authenticatedRedirectRoute: 'home', // or null (no redirection)
            
            // The message shown if a login attempt fails.
            failedMessage: trans('Invalid user or password.'),
            
            // The redirect route after a successful login.
            redirectRoute: 'home',
            
            // If set, it shows the forgot password link. Make sure the Feature\ForgotPassword is set too.
            forgotPasswordRoute: 'forgot-password.identity', // or null
            
            // If true, routes are being localized.
            localizeRoute: true,
        ),
        
        //new Page\LoginTwoFactor(),
        
        new Feature\Logout(
            // A menu name to show the logout link or null if none.
            menu: 'header',
            menuLabel: trans('Log out'),
            // A menu parent name (e.g. 'user') or null if none.
            menuParent: null,
            
            // The redirect route after a successful logout.
            redirectRoute: 'home',
            
            // The message and redirect route if a user is unauthenticated.
            unauthenticatedMessage: trans('You have insufficient rights to access the requested resource!'),
            unauthenticatedRedirectRoute: 'home', // or null (no redirection)
            
            // If true, routes are being localized.
            localizeRoute: true,
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

            // The message shown if a identity attempt fails.
            identityFailedMessage: trans('Invalid name or user.'),
            
            // The message and redirect route if a user is authenticated.
            authenticatedMessage: trans('You are logged in!'),
            authenticatedRedirectRoute: 'home', // or null (no redirection)
            
            // The redirect route after a successful password reset.
            successRedirectRoute: 'home',
            
            // If true, routes are being localized.
            localizeRoute: true,
        ),
        
        new Feature\Register(
            view: 'user/register',
            
            // A menu name to show the logout link or null if none.
            menu: 'header',
            menuLabel: trans('Register'),
            // A menu parent name (e.g. 'user') or null if none.
            menuParent: null,
            
            // The message and redirect route if a user is authenticated.
            authenticatedMessage: trans('You are logged in!'),
            authenticatedRedirectRoute: 'home',
            
            // The role key for the registered user.
            roleKey: 'registered',
            
            // The redirect route after a successful registration.
            successRedirectRoute: 'home',
            // You may redirect to the verification account page
            // see: https://github.com/tobento-ch/app-user-web#
            // to first verify at least one channel to access resources.
            // You will need to set the 
            //successRedirectRoute: 'verification.account',
            
            // If true, routes are being localized.
            localizeRoute: true,
            
            //newsletter: true,
            
            //gtcRoute: 'agb', // if null no confirmation needed.
            //generalTermsAndConditions: 'agb', // if null no confirmation needed.
        ),
        
        new Feature\Profile(
            menu: 'main',
            menuLabel: 'Profile',
            verification: true,
            localizeRoute: true,
            
            unauthenticatedMessage: 'You need to login to access this page!',
            unauthenticatedRedirectRoute: 'login.show',
            
            //unverifiedMessage: 'You need to verify your account first to access this page!',
            //unverifiedRedirectRoute: 'verification.account',
        ),
        
        //Feature\AddressBook::class,
        //Feature\Configurations::class, // how to add more config? use crud?.
        
        new Feature\Verification(
            localizeRoute: true,
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
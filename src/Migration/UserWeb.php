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

namespace Tobento\App\User\Web\Migration;

use Tobento\Service\Migration\MigrationInterface;
use Tobento\Service\Migration\ActionsInterface;
use Tobento\Service\Migration\Actions;
use Tobento\Service\Migration\Action\FilesCopy;
use Tobento\Service\Migration\Action\FilesDelete;
use Tobento\Service\Migration\Action\DirCopy;
use Tobento\Service\Migration\Action\DirDelete;
use Tobento\Service\Migration\Action\FileStringReplacer;
use Tobento\Service\Dir\DirsInterface;

/**
 * UserWeb
 */
class UserWeb implements MigrationInterface
{
    protected array $configFiles;

    protected array $transFiles;
    
    /**
     * Create a new Migration.
     *
     * @param DirsInterface $dirs
     */
    public function __construct(
        protected DirsInterface $dirs,
    ) {
        $resources = realpath(__DIR__.'/../../').'/resources/';
        
        $this->configFiles = [
            $this->dirs->get('config') => [
                $resources.'config/user_web.php',
            ],
        ];
        
        $this->transFiles = [
            $this->dirs->get('trans').'en/' => [
                $resources.'trans/en/en-user-web.json',
                $resources.'trans/en/routes.user-web.json',
                $resources.'trans/en/validator.user-web.json',
            ],
            $this->dirs->get('trans').'de/' => [
                $resources.'trans/de/de-user-web.json',
                $resources.'trans/de/routes.user-web.json',
                $resources.'trans/de/validator.user-web.json',
            ],
        ];
    }
    
    /**
     * Return a description of the migration.
     *
     * @return string
     */
    public function description(): string
    {
        return 'User config, views, translation and other files.';
    }
        
    /**
     * Return the actions to be processed on install.
     *
     * @return ActionsInterface
     */
    public function install(): ActionsInterface
    {
        $resources = realpath(__DIR__.'/../../').'/resources/';
        
        return new Actions(
            new FilesCopy(
                files: $this->configFiles,
                type: 'config',
                description: 'Config files.',
            ),
            new FilesCopy(
                files: $this->transFiles,
                type: 'trans',
                description: 'Translation files.',
            ),
            new DirCopy(
                dir: $resources.'views/user/',
                destDir: $this->dirs->get('views').'user/',
                name: 'User web views',
                type: 'views',
                description: 'User web views.',
            ),
            new FileStringReplacer(
                file: $this->dirs->get('config').'user_web.php',
                replace: [
                    '{verificator_hash_key}' => base64_encode(random_bytes(32)),
                ],
                description: 'verificator_hash_key generation.',
                type: 'config',
            ),
        );
    }

    /**
     * Return the actions to be processed on uninstall.
     *
     * @return ActionsInterface
     */
    public function uninstall(): ActionsInterface
    {
        return new Actions(
            new FilesDelete(
                files: $this->configFiles,
                type: 'config',
                description: 'Config files.',
            ),
            new FilesDelete(
                files: $this->transFiles,
                type: 'trans',
                description: 'Translation files.',
            ),
            new DirDelete(
                dir: $this->dirs->get('views').'user/',
                name: 'User web views',
                type: 'views',
                description: 'User web views.',
            ),
        );
    }
}
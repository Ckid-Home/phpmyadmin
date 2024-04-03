<?php
/**
 * phpinfo() wrapper to allow displaying only when configured to do so.
 */

declare(strict_types=1);

namespace PhpMyAdmin\Controllers;

use PhpMyAdmin\Config;
use PhpMyAdmin\Http\Response;
use PhpMyAdmin\Http\ServerRequest;

use function phpinfo;

use const INFO_CONFIGURATION;
use const INFO_GENERAL;
use const INFO_MODULES;

/**
 * phpinfo() wrapper to allow displaying only when configured to do so.
 */
class PhpInfoController extends AbstractController
{
    public function __invoke(ServerRequest $request): Response|null
    {
        $this->response->disable();
        $this->response->getHeader()->sendHttpHeaders();

        if (! Config::getInstance()->settings['ShowPhpInfo']) {
            return null;
        }

        phpinfo(INFO_GENERAL | INFO_CONFIGURATION | INFO_MODULES);

        return null;
    }
}

<?php

/*
 * This file is part of the {APP-NAME}.
 *
 * (c) {APP-AUTHOR}
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace {APP-CLASS};

use ZimbruCode\AppKernel;

/**
 * Class : Application
 *
 * @author  {APP-AUTHOR}
 * @package {APP-NAME}
 * @since   {APP-VERSION}
 */
class Application extends AppKernel
{
    /**
     * Application setup
     * @return void
     * @since {APP-VERSION}
     */
    protected function setup(): void
    {
        $this->addAction('wp_enqueue_scripts', '__action_enqueue');
    }

    /**
     * Action : Enqueue styles & scripts
     * @return void
     * @since {APP-VERSION}
     */
    public function __action_enqueue(): void
    {
        $this->asset('style.scss', 'script.js');
    }
}

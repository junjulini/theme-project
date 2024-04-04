<?php

/*
 * This file is part of the {APP-NAME}.
 *
 * (c) {APP-AUTHOR}
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

// Initialization of application
require get_template_directory() . '/bootstrap.php';
new __{APP-CLASS}_Bootstrap(array(
    'name'           => '{APP-NAME}', // Application name
    'slug'           => '{APP-SLUG}', // Application slug
    'mode'           => 'theme', // Application mode
    'rpv'            => '7.4', // Required PHP version
    'rwv'            => '5.6', // Required WordPress version
    'dev'            => (defined('WP_DEBUG') ? WP_DEBUG : false),
    'class'          => '{APP-CLASS}\Application',
    'class-alias'    => '{APP-CLASS-ALIAS}',
    'file-load-path' => false,
    'session'        => false,

    // Debugger
    'debug'          => true,
    'email'          => get_option('admin_email', null),
    'log'            => false,
    'log-severity'   => 0,
    'log-max-files'  => 10,
    'strict-mode'    => true,
    'show-bar'       => true,
    'max-depth'      => 40,
    'max-length'     => 150,
    'editor'         => 'vscode://file/%file:%line',
));

/**
 * Under this message you can put your functions
 */

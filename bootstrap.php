<?php

/*
 * This file is part of the {APP-NAME}.
 *
 * (c) {APP-AUTHOR}
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * Class : Bootstrap
 *
 * @author  {APP-AUTHOR}
 * @package {APP-NAME}
 * @since   {APP-VERSION}
 */
class __{APP-CLASS}_Bootstrap
{
    // App config
    protected $config = array(
        'name'           => 'Project', // Application name
        'slug'           => 'project', // Application slug
        'mode'           => 'theme',   // Application mode
        'rpv'            => '7.2.5',   // Required PHP version
        'rwv'            => '4.9',     // Required WordPress version
        'dev'            => false,
        'class'          => 'Project\\Application',
        'class-alias'    => 'zc',
        'file-load-path' => false,
        'session'        => false,
        
        # Debugger
        'debug'          => true,
        'email'          => null,
        'log'            => true,
        'log-directory'  => false,
        'log-severity'   => 0,
        'log-max-files'  => 10,
        'strict-mode'    => false,
        'show-bar'       => true,
        'max-depth'      => 4,
        'max-length'     => 150,
        'editor'         => false,

        # Callbacks
        'checker-cb'     => false,
    );

    protected $tasks   = array();
    protected $message = '';

    public $app;

    public function __construct(array $config = array())
    {
        if (empty($this->config) || !is_array($this->config)) {
            throw new ZimbruCodeBootstrapException('Bootstrap : Default configs is not defined.');
        }

        $this->config = wp_parse_args($config, $this->config);
        $this->condition();
        add_action('after_switch_theme', array($this, '__action_after_switch_theme'));

        if (isset($this->config['dev']) && $this->config['dev'] === true) {
            try
            {
                $this->prepConditions();
            } catch (ZimbruCodeBootstrapException $e) {
                if (!isset($_GET['activated'])) {
                    wp_die(sprintf('<h2>%s</h2><p>%s</p>', esc_html__('Error', 'zc'), $e->getMessage()));
                }
            }
        } else {
            if (!isset($_GET['activated'])) {
                $this->prepConditions();
            }
        }

        $this->setup();
    }

    protected function prepConditions()
    {
        foreach ($this->tasks as $task) {
            if ($task['condition']) {
                throw new ZimbruCodeBootstrapException($task['message']);
            }
        }
    }

    /**
     * Condition of load
     * 
     * @return void   This function does not return a value
     * @since 1.0.0
     */
    protected function condition()
    {
        // Check PHP Version
        $this->check(
            !is_php_version_compatible($this->config['rpv']),
            sprintf(
                esc_html__('Requires at least PHP version "%s" or greater. You are running version "%s". Ask your host to update to a newer PHP version for FREE. For more information you can read on %s and %s', 'zc'),
                $this->config['rpv'],
                PHP_VERSION,
                '<a href="https://php.net/supported-versions.php" target="_blank" rel="nofollow noopener noreferrer">'. esc_html__('PHP : Supported Versions', 'zc') .'</a>',
                '<a href="https://wordpress.org/about/requirements" target="_blank" rel="nofollow noopener noreferrer">'. esc_html__('WordPress : Requirements', 'zc') .'</a>'
            )
        );

        // Check WordPress Version
        $this->check(
            !is_wp_version_compatible($this->config['rwv']),
            sprintf(
                esc_html__('Requires at least WordPress version "%s". You are running version "%s". Please upgrade and try again.', 'zc'),
                $this->config['rwv'],
                $GLOBALS['wp_version']
            )
        );

        // Check filesystem method
        $this->check(
            !$this->checkFilesystemMethod(),
            esc_html__('The priority of the transport method use for reading, writing, modifying, or deleting files on the filesystem are not "direct". As usual it related with "file owner". Default must be "www-data"', 'zc')
        );

        // Additional checkers
        if (!empty($this->config['checker-cb']) && is_callable($this->config['checker-cb'])) {
            call_user_func($this->config['checker-cb'], $this);
        }
    }

    /**
     * Setup application
     * 
     * @return void   This function does not return a value
     * @since 1.0.0
     */
    protected function setup()
    {
        // Include auto loader
        $composer = require get_template_directory() . '/vendor/autoload.php';

        // Debug
        if ($this->config['debug'] === true) {
            $debugger = array(
                'strictMode'   => $this->config['strict-mode'],
                'showBar'      => $this->config['show-bar'],
                'logDirectory' => $this->config['log-directory'],
                'logSeverity'  => $this->config['log-severity'],
                'email'        => $this->config['email'],
                'maxDepth'     => $this->config['max-depth'],
                'maxLength'    => $this->config['max-length'],
                'dev'          => $this->config['dev'],
                'editor'       => $this->config['editor'],
            );

            // Log
            if ($this->config['log'] && !$this->config['log-directory']) {
                $logDir = wp_normalize_path(__DIR__ . '/app/Resources/var/logs');

                if (wp_mkdir_p($logDir)) {
                    $debugger['logDirectory'] = $logDir;

                    if (isset($_SERVER['SERVER_SOFTWARE']) && strpos($_SERVER['SERVER_SOFTWARE'], 'Apache') !== false && !file_exists("{$debugger['logDirectory']}/.htaccess")) {
                        file_put_contents("{$debugger['logDirectory']}/.htaccess", "Deny From All\n<FilesMatch \"\.(?:html)$\">\n\tAllow From All\n</FilesMatch>");
                    }

                    // Check max number of log files
                    $this->checkLogFilesMaxNumber($debugger['logDirectory']);
                }
            }

            call_user_func('ZimbruCode\\Component\\Debug\\DebugController::runTracy', $debugger);
        }

        // Temp folder
        wp_mkdir_p(__DIR__ . '/app/Resources/var/temp');

        // Check file load path
        if (empty($this->config['file-load-path'])) {
            $dbt = debug_backtrace();
            $rootPath = isset($dbt[1]['file']) ? $dbt[1]['file'] : false;
        }

        // Build application
        $app = $this->config['class'];
        $this->app = new $app($this->config['slug'], $this->config['mode'], $this->config['dev'], $rootPath, $this->config['session'], $composer);
        class_alias($app, $this->config['class-alias']);
    }

    /**
     * Set condition
     * 
     * @param  boolean $condition   Condition : true/false
     * @param  boolean $message     Message of exception
     * @return void                 This function does not return a value
     * @since 1.0.0
     */
    public function check($condition = true, $message = false)
    {
        if ($condition && $message) {
            $this->tasks[] = array(
                'condition' => $condition,
                'message'   => $message,
            );
        }
    }

    /**
     * Determines filesystem method
     * 
     * @return boolean  Result
     * @since 1.0.0
     */
    protected function checkFilesystemMethod()
    {
        // $uploadDir = wp_get_upload_dir();

        // if (isset($uploadDir['error']) && $uploadDir['error'] === false && $uploadDir['basedir']) {
        //     $varDir = wp_normalize_path("{$uploadDir['basedir']}/{$this->config['slug']}");

        //     if (wp_mkdir_p($varDir)) {
        //         $tempFileName = $varDir . '/twt-' . time();
        //         $tempHandle   = @fopen($tempFileName, 'w');

        //         if ($tempHandle) {
        //             $thisFileOwner = $tempFileOwner = false;
        //             if (function_exists('fileowner')) {
        //                 $thisFileOwner = @fileowner(__FILE__);
        //                 $tempFileOwner = @fileowner($tempFileName);
        //             }

        //             @fclose($tempHandle);
        //             wp_delete_file($tempFileName);

        //             if ($thisFileOwner !== false && $thisFileOwner === $tempFileOwner) {
        //                 $this->config['var-dir'] = $varDir;

        //                 return true;
        //             }
        //         }
        //     }
        // }

        $varDir = wp_normalize_path(__DIR__ . '/app/Resources/var');

        if (wp_mkdir_p($varDir)) {
            $tempFileName = $varDir . '/twt-' . time();
            $tempHandle   = @fopen($tempFileName, 'w');

            if ($tempHandle) {
                $thisFileOwner = $tempFileOwner = false;
                if (function_exists('fileowner')) {
                    $thisFileOwner = @fileowner(__FILE__);
                    $tempFileOwner = @fileowner($tempFileName);
                }

                @fclose($tempHandle);
                wp_delete_file($tempFileName);

                if ($thisFileOwner !== false && $thisFileOwner === $tempFileOwner) {
                    $this->config['var-dir'] = $varDir;

                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Delete all log files if is more the "N"
     * 
     * @param  string $dir   Log path
     * @return void          This function does not return a value
     * @since 1.0.0
     */
    protected function checkLogFilesMaxNumber($dir)
    {
        if ($dir && is_string($dir)) {
            $i = 0;
            $max = (!empty($this->config['log-max-files']) && is_int($this->config['log-max-files'])) ? $this->config['log-max-files'] : 10;

            if ($handle = @opendir($dir)) {
                while (($file = @readdir($handle)) !== false){
                    if (!in_array($file, array('.', '..')) && !is_dir("{$dir}/{$file}")) {
                        $info = new SplFileInfo("{$dir}/{$file}");

                        if ($info->getExtension() == 'html') {
                            $i++;
                        }
                    }
                }
            }

            if ($i > $max) {
                if ($handle = @opendir($dir)) {
                    while (($file = @readdir($handle)) !== false){
                        if (!in_array($file, array('.', '..')) && !is_dir("{$dir}/{$file}")) {
                            $info = new SplFileInfo("{$dir}/{$file}");

                            if ($info->getExtension() == 'html') {
                                wp_delete_file("{$dir}/{$file}");
                            }
                        }
                    }
                }
            }
        }
    }

    /**
     * Action : Processes after switch theme
     * 
     * @return void   This function does not return a value
     * @since 1.0.0
     */
    public function __action_after_switch_theme()
    {
        try
        {
            $this->prepConditions();
        } catch (ZimbruCodeBootstrapException $e) {
            $this->message = $e->getMessage();

            switch_theme(WP_DEFAULT_THEME, WP_DEFAULT_THEME);

            if (isset($_GET['activated'])) {
                unset($_GET['activated']);
            }

            // Add a message for unsuccessful theme switch.
            add_action('admin_notices', array($this, '__action_admin_notices'));
        }
    }

    /**
     * Action : Add a message for unsuccessful theme switch
     * 
     * @return void   This function does not return a value
     * @since 1.0.0
     */
    public function __action_admin_notices()
    {
        printf('<div class="error"><p>%s</p></div>', $this->config['name'] . ' ' . esc_html__('Error', 'zc') . ' : ' . $this->message);
    }
}

/**
 * Class : Bootstrap Exception
 *
 * @author  Junjulini
 * @package ZimbruCode
 * @since   ZimbruCode 1.0.0
 */
if (!class_exists('ZimbruCodeBootstrapException')) {
    class ZimbruCodeBootstrapException extends Exception
    {
        # exception
    }
}

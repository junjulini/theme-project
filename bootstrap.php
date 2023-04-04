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
    // Application default settings
    protected $settings = array(
        'name'           => 'Project', // Application name
        'slug'           => 'project', // Application slug
        'mode'           => 'theme',   // Application mode
        'rpv'            => '7.2.5',   // Required PHP version
        'rwv'            => '4.9',     // Required WordPress version
        'dev'            => false,
        'class'          => 'Project\Application',
        'class-alias'    => 'zc',
        'file-load-path' => false,
        'session'        => false,

        // Debugger
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

        // Callbacks
        'checker-cb'     => false,
    );

    protected $tasks   = array();
    protected $message = '';

    public $app;

    /**
     * Constructor
     *
     * @param array $config   Bootstrap config
     * @throws ZimbruCodeBootstrapException
     * @since 1.0.0
     */
    public function __construct($settings = array())
    {
        if (empty($this->settings) || !is_array($this->settings)) {
            throw new ZimbruCodeBootstrapException('Bootstrap : Default settings not defined');
        }

        $this->settings = wp_parse_args($settings, $this->settings);
        $this->condition();
        add_action('after_switch_theme', array($this, '__action_after_switch_theme'));

        if (isset($this->settings['dev']) && $this->settings['dev'] === true) {
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

    /**
     * Prepare conditions
     *
     * @throws ZimbruCodeBootstrapException
     * @return void
     * @since 1.0.0
     */
    protected function prepConditions()
    {
        foreach ($this->tasks as $task) {
            if ($task['condition']) {
                throw new ZimbruCodeBootstrapException($task['message']);
            }
        }
    }

    /**
     * Loading conditions
     * 
     * @return void
     * @since 1.0.0
     */
    protected function condition()
    {
        // Check PHP Version
        $this->check(
            !is_php_version_compatible($this->settings['rpv']),
            sprintf(
                esc_html__('Requires at least PHP version "%s" or greater. You are running version "%s". Ask your host to update to a newer PHP version for FREE. For more information you can read on %s and %s', 'zc'),
                $this->settings['rpv'],
                PHP_VERSION,
                '<a href="https://php.net/supported-versions.php" target="_blank" rel="nofollow noopener noreferrer">'. esc_html__('PHP : Supported Versions', 'zc') .'</a>',
                '<a href="https://wordpress.org/about/requirements" target="_blank" rel="nofollow noopener noreferrer">'. esc_html__('WordPress : Requirements', 'zc') .'</a>'
            )
        );

        // Check WordPress Version
        $this->check(
            !is_wp_version_compatible($this->settings['rwv']),
            sprintf(
                esc_html__('Requires at least WordPress version "%s". You are running version "%s". Please upgrade and try again.', 'zc'),
                $this->settings['rwv'],
                $GLOBALS['wp_version']
            )
        );

        // Additional checkers
        if (!empty($this->settings['checker-cb']) && is_callable($this->settings['checker-cb'])) {
            call_user_func($this->settings['checker-cb'], $this);
        }
    }

    /**
     * Setup application
     * 
     * @return void
     * @since 1.0.0
     */
    protected function setup()
    {
        // Include auto loader
        $composer = require get_template_directory() . '/vendor/autoload.php';

        // Debug
        if ($this->settings['debug'] === true) {
            $debugger = array(
                'strictMode'   => $this->settings['strict-mode'],
                'showBar'      => $this->settings['show-bar'],
                'logDirectory' => $this->settings['log-directory'],
                'logSeverity'  => $this->settings['log-severity'],
                'email'        => $this->settings['email'],
                'maxDepth'     => $this->settings['max-depth'],
                'maxLength'    => $this->settings['max-length'],
                'dev'          => $this->settings['dev'],
                'editor'       => $this->settings['editor'],
            );

            // Log
            if ($this->settings['log']) {
                if (defined('ZC_LOG_DIR_WP_UPLOAD_STATUS') && ZC_LOG_DIR_WP_UPLOAD_STATUS === true) {
                    $uploadDir = wp_get_upload_dir();

                    if (isset($uploadDir['error']) && $uploadDir['error'] === false && $uploadDir['basedir']) {
                        $debugger['logDirectory'] = wp_normalize_path("{$uploadDir['basedir']}/{$this->settings['slug']}/logs");
                    } else {
                        $debugger['logDirectory'] = wp_normalize_path(__DIR__ . '/app/Resources/var/logs');
                    }
                } else if (!$this->settings['log-directory']) {
                    $debugger['logDirectory'] = wp_normalize_path(__DIR__ . '/app/Resources/var/logs');
                }

                if (wp_mkdir_p($debugger['logDirectory'])) {
                    if (!file_exists("{$debugger['logDirectory']}/.htaccess")) {
                        @file_put_contents("{$debugger['logDirectory']}/.htaccess", "Deny From All\n<FilesMatch \"\.(?:html)$\">\n\tAllow From All\n</FilesMatch>");
                    }

                    // Check max number of log files
                    $this->checkLogFilesMaxNumber($debugger['logDirectory']);
                }
            }

            call_user_func('ZimbruCode\Component\Debug\DebugController::runTracy', $debugger);
        }

        // Check file load path
        if (empty($this->settings['file-load-path'])) {
            $dbt = debug_backtrace();
            $rootPath = isset($dbt[1]['file']) ? $dbt[1]['file'] : false;
        }

        // Build application
        $app = $this->settings['class'];
        $this->app = new $app($this->settings['slug'], $this->settings['mode'], $this->settings['dev'], $rootPath, $this->settings['session'], $composer);
        class_alias($app, $this->settings['class-alias']);
    }

    /**
     * Add condition
     * 
     * @param mixed $condition   Condition : true/false
     * @param mixed $message     Message of exception
     * @return void
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
     * Delete all log files if greater than "N"
     * 
     * @param mixed $dir   Log directory path
     * @return void
     * @since 1.0.0
     */
    protected function checkLogFilesMaxNumber($dir)
    {
        if ($dir && is_string($dir)) {
            $i = 0;
            $max = (!empty($this->settings['log-max-files']) && is_int($this->settings['log-max-files'])) ? $this->settings['log-max-files'] : 10;

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
     * @return void
     * @since 1.0.0
     */
    public function __action_after_switch_theme()
    {
        try
        {
            $this->prepConditions();
        } catch (ZimbruCodeBootstrapException $e) {
            $this->message = $e->getMessage();

            if (defined('WP_DEFAULT_THEME')) {
                switch_theme(WP_DEFAULT_THEME, WP_DEFAULT_THEME);
            }

            if (isset($_GET['activated'])) {
                unset($_GET['activated']);
            }

            // Add message about failed theme switch
            add_action('admin_notices', array($this, '__action_admin_notices'));
        }
    }

    /**
     * Action : Add message about failed theme switch
     * 
     * @return void
     * @since 1.0.0
     */
    public function __action_admin_notices()
    {
        printf('<div class="error"><p>%s</p></div>', $this->settings['name'] . ' ' . esc_html__('Error', 'zc') . ' : ' . $this->message);
    }
}

/**
 * Class : Bootstrap exception
 *
 * @author  Junjulini
 * @package ZimbruCode
 * @since   ZimbruCode 1.0.0
 */
if (!class_exists('ZimbruCodeBootstrapException')) {
    class ZimbruCodeBootstrapException extends Exception
    {
        // Exception
    }
}

<?php
// includes/WP_Mail_Master.php

namespace Martho\WpMailMaster;

class WP_Mail_Master
{
    protected $loader;
    protected $plugin_name;
    protected $version;

    public function __construct()
    {
        $this->version = WP_MAIL_MASTER_VERSION;
        $this->plugin_name = 'wp-mail-master';
        $this->load_dependencies();
        $this->set_locale();
        $this->define_admin_hooks();
        $this->define_email_hooks();
    }

    private function load_dependencies()
    {
        require_once WP_MAIL_MASTER_PLUGIN_DIR . 'includes/WP_Mail_Master_Loader.php';
        require_once WP_MAIL_MASTER_PLUGIN_DIR . 'includes/WP_Mail_Master_i18n.php';
        require_once WP_MAIL_MASTER_PLUGIN_DIR . 'includes/WP_Mail_Master_Emailer.php';
        require_once WP_MAIL_MASTER_PLUGIN_DIR . 'includes/WP_Mail_Master_DB.php';
        require_once WP_MAIL_MASTER_PLUGIN_DIR . 'includes/WP_Mail_Master_Error_Handler.php';
        require_once WP_MAIL_MASTER_PLUGIN_DIR . 'includes/WP_Mail_Master_Exception.php';
        require_once WP_MAIL_MASTER_PLUGIN_DIR . 'includes/WP_Mail_Master_Spam_Protection.php';
        require_once WP_MAIL_MASTER_PLUGIN_DIR . 'admin/WP_Mail_Master_Admin.php';
        require_once WP_MAIL_MASTER_PLUGIN_DIR . 'admin/WP_Mail_Master_Ajax.php';
        require_once WP_MAIL_MASTER_PLUGIN_DIR . 'admin/WP_Mail_Master_Settings.php';
        require_once WP_MAIL_MASTER_PLUGIN_DIR . 'admin/WP_Mail_Master_Security.php';
        require_once WP_MAIL_MASTER_PLUGIN_DIR . 'admin/WP_Mail_Master_Templates.php';
        require_once WP_MAIL_MASTER_PLUGIN_DIR . 'admin/WP_Mail_Master_Template_Manager.php';
        require_once WP_MAIL_MASTER_PLUGIN_DIR . 'admin/WP_Mail_Master_Logs.php';

        $this->loader = new WP_Mail_Master_Loader();
    }

    private function set_locale()
    {
        $plugin_i18n = new WP_Mail_Master_i18n();
        $this->loader->add_action('plugins_loaded', $plugin_i18n, 'load_plugin_textdomain');
    }

    private function define_admin_hooks()
    {
        $plugin_admin = new WP_Mail_Master_Admin($this->plugin_name, $this->version);
        $plugin_settings = new WP_Mail_Master_Settings($this->plugin_name, $this->version);
        $plugin_security = new WP_Mail_Master_Security($this->plugin_name, $this->version);
        $emailer = new WP_Mail_Master_Emailer($this->plugin_name, $this->version);

        $this->loader->add_action('admin_enqueue_scripts', $plugin_admin, 'enqueue_assets');
        $this->loader->add_action('admin_menu', $plugin_admin, 'add_plugin_admin_menu');
        $this->loader->add_action('admin_init', $plugin_settings, 'register_settings');
        $this->loader->add_action('admin_init', $plugin_security, 'register_settings');
    }

    private function define_email_hooks()
    {
        $emailer = new WP_Mail_Master_Emailer($this->plugin_name, $this->version);
        add_filter('pre_wp_mail', array($emailer, 'override_wp_mail'), 10, 6);
    }

    public function override_wp_mail($pre_wp_mail, $to, $subject = '', $message = '', $headers = '', $attachments = array())
    {
        try {
            error_log('WP Mail Master - Starting wp_mail override');
            
            // If another plugin has already handled this, return their result
            if ($pre_wp_mail !== null) {
                error_log('WP Mail Master - Email already handled by another plugin');
                return $pre_wp_mail;
            }

            // Early return if no recipient
            if (empty($to)) {
                error_log('WP Mail Master - No recipient specified');
                return null;
            }

            // Convert all inputs to their proper types first
            $to = is_array($to) ? array_map(function($email) {
                return (string)($email ?: '');
            }, $to) : (string)($to ?: '');

            $subject = (string)($subject ?: '');
            $message = (string)($message ?: '');

            // Handle headers
            if (is_array($headers)) {
                $headers = array_map(function($header) {
                    return (string)($header ?: '');
                }, $headers);
            } else {
                $headers = (string)($headers ?: '');
                if (!empty($headers)) {
                    $headers = array_map(function($header) {
                        return (string)($header ?: '');
                    }, explode("\n", str_replace("\r\n", "\n", $headers)));
                } else {
                    $headers = array();
                }
            }

            // Handle attachments
            if (is_array($attachments)) {
                $attachments = array_map(function($attachment) {
                    return (string)($attachment ?: '');
                }, $attachments);
            } else {
                $attachments = !empty($attachments) ? array((string)($attachments ?: '')) : array();
            }

            // If $to is an array containing email data
            if (is_array($to) && isset($to['to'])) {
                $args = $to;
                $to = is_array($args['to']) ? array_map(function($email) {
                    return (string)($email ?: '');
                }, $args['to']) : (string)($args['to'] ?: '');
                
                $subject = (string)($args['subject'] ?? '');
                $message = (string)($args['message'] ?? '');
                
                // Process headers from args
                if (isset($args['headers'])) {
                    if (is_array($args['headers'])) {
                        $headers = array_map(function($header) {
                            return (string)($header ?: '');
                        }, $args['headers']);
                    } else {
                        $header_str = (string)($args['headers'] ?: '');
                        $headers = !empty($header_str) ? 
                            array_map(function($header) {
                                return (string)($header ?: '');
                            }, explode("\n", str_replace("\r\n", "\n", $header_str))) : 
                            array();
                    }
                }
                
                // Process attachments from args
                if (isset($args['attachments'])) {
                    if (is_array($args['attachments'])) {
                        $attachments = array_map(function($attachment) {
                            return (string)($attachment ?: '');
                        }, $args['attachments']);
                    } else {
                        $attachments = !empty($args['attachments']) ? 
                            array((string)($args['attachments'] ?: '')) : 
                            array();
                    }
                }
            }

            // Ensure we have valid strings before proceeding
            if (!is_string($subject) || !is_string($message)) {
                error_log('WP Mail Master - Invalid subject or message type');
                return null;
            }

            // Create emailer instance and send
            $emailer = new WP_Mail_Master_Emailer($this->plugin_name, $this->version);
            $result = $emailer->send_email($to, $subject, $message, $headers, $attachments);
            
            error_log('WP Mail Master - Send result: ' . var_export($result, true));
            return $result ? true : null;

        } catch (\Exception $e) {
            error_log('WP Mail Master - wp_mail override error: ' . $e->getMessage());
            error_log('WP Mail Master - Error trace: ' . $e->getTraceAsString());
            if (is_array($to)) {
                error_log('WP Mail Master - Processing array input');
                if (isset($to['to'])) {
                    $args = $to;
                } else {
                    $args = array(
                        'to' => $to,
                        'subject' => $subject,
                        'message' => $message,
                        'headers' => $headers,
                        'attachments' => $attachments
                    );
                }
                
                $to = is_array($args['to']) ? array_map('strval', $args['to']) : (string)($args['to'] ?: '');
                $subject = (string)($args['subject'] ?? '');
                $message = (string)($args['message'] ?? '');
                $headers = isset($args['headers']) ? (is_array($args['headers']) ? array_map('strval', $args['headers']) : explode("\n", str_replace("\r\n", "\n", (string)($args['headers'] ?: '')))) : array();
                $attachments = isset($args['attachments']) ? (is_array($args['attachments']) ? array_map('strval', $args['attachments']) : array((string)$args['attachments'])) : array();
            }

            // Process headers
            if (is_string($headers) && !empty($headers)) {
                $headers = array_map('strval', explode("\n", str_replace("\r\n", "\n", (string)$headers)));
            } elseif (!is_array($headers)) {
                $headers = array();
            }

            // Ensure each header is a string and not null
            $headers = array_map(function($header) {
                return (string)($header ?: '');
            }, (array)$headers);

            error_log('WP Mail Master - Normalized headers: ' . print_r($headers, true));

            // Process attachments
            if (is_string($attachments) && !empty($attachments)) {
                $attachments = array_map('strval', explode("\n", str_replace("\r\n", "\n", (string)$attachments)));
            } elseif (!is_array($attachments)) {
                $attachments = array();
            }

            // Ensure each attachment path is a string
            $attachments = array_map(function($attachment) {
                return (string)($attachment ?: '');
            }, (array)$attachments);

            error_log('WP Mail Master - Calling send_email');
            $result = $emailer->send_email($to, $subject, $message, $headers, $attachments);
            
            error_log('WP Mail Master - Send result: ' . var_export($result, true));
            
            return $result ? true : null;

        } catch (\Exception $e) {
            error_log('WP Mail Master - wp_mail override error: ' . $e->getMessage());
            error_log('WP Mail Master - Error trace: ' . $e->getTraceAsString());
            return null;
        }
    }

    public function enqueue_scripts() {
        // Enqueue existing scripts
        wp_enqueue_script(
            'wp-mail-master-admin',
            plugin_dir_url(WP_MAIL_MASTER_PLUGIN_FILE) . 'assets/js/wp-mail-master-admin.js',
            array('jquery'),
            WP_MAIL_MASTER_VERSION,
            true
        );

        // Enqueue honeypot script for all pages (not just admin)
        wp_enqueue_script(
            'wp-mail-master-honeypot',
            plugin_dir_url(WP_MAIL_MASTER_PLUGIN_FILE) . 'assets/js/wp-mail-master-honeypot.js',
            array('jquery'),
            WP_MAIL_MASTER_VERSION,
            true
        );

        // Add honeypot CSS
        wp_add_inline_style(
            'wp-mail-master-admin',
            '.wp-mail-master-hp { position: absolute !important; visibility: hidden !important; }'
        );

        // Localize script
        wp_localize_script('wp-mail-master-admin', 'wp_mail_master', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('wp_mail_master_nonce')
        ));
    }

    public function run()
    {
        $this->loader->run();
    }

    public function get_plugin_name()
    {
        return $this->plugin_name;
    }

    public function get_loader()
    {
        return $this->loader;
    }

    public function get_version()
    {
        return $this->version;
    }
}

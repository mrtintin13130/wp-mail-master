<?php
// includes/WP_Mail_Master_Error_Handler.php

namespace Martho\WpMailMaster;

class WP_Mail_Master_Error_Handler
{
    private static $instance = null;
    private $debug_mode;

    private function __construct()
    {
        $this->debug_mode = (defined('WP_DEBUG') && WP_DEBUG);
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Handle and log an exception
     *
     * @param \Exception $e The exception to handle
     * @return array Error data
     */
    public function handleException(\Exception $e): array
    {
        // Build error data
        $error_data = [
            'message' => $e->getMessage(),
            'code' => $e->getCode(),
            'type' => ($e instanceof WP_Mail_Master_Exception) ? $e->getErrorType() : 'Unknown Error',
            'context' => ($e instanceof WP_Mail_Master_Exception) ? $e->getContext() : [],
            'time' => current_time('mysql')
        ];

        // Add debug information if enabled
        if ($this->debug_mode) {
            $error_data['debug'] = [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ];
        }

        // Log the error
        $this->logError($error_data);

        // Show admin notice if appropriate
        if (is_admin() && current_user_can('manage_options')) {
            add_action('admin_notices', function () use ($error_data) {
                $this->displayAdminNotice($error_data);
            });
        }

        return $error_data;
    }

    /**
     * Log error to database and debug log
     */
    private function logError(array $error_data): void
    {
        // Log to WordPress debug log
        error_log(sprintf(
            '[WP Mail Master] %s (%d): %s',
            $error_data['type'],
            $error_data['code'],
            $error_data['message']
        ));

        // Store in database
        global $wpdb;
        if ($wpdb !== null) {
            $table_name = $wpdb->prefix . 'wp_mail_master_errors';

            $wpdb->insert(
                $table_name,
                [
                    'error_code' => $error_data['code'],
                    'error_message' => $error_data['message'],
                    'error_details' => wp_json_encode($error_data),
                    'created_at' => $error_data['time']
                ],
                ['%d', '%s', '%s', '%s']
            );
        }
    }
    /**
     * Display error notice in admin
     */
    private function displayAdminNotice(array $error_data): void
    {
        $message = $this->debug_mode && isset($error_data['debug']) ?
            sprintf(
                '%s: %s (in %s line %d)',
                $error_data['type'],
                $error_data['message'],
                basename($error_data['debug']['file']),
                $error_data['debug']['line']
            ) :
            sprintf(
                '%s: %s',
                $error_data['type'],
                $error_data['message']
            );

        printf(
            '<div class="notice notice-error"><p>%s</p></div>',
            esc_html($message)
        );
    }

    /**
     * Get recent errors
     */
    public function getErrors(int $limit = 100, int $offset = 0): array
    {
        global $wpdb;
        if (!$wpdb) {
            return [];
        }
        $table_name = $wpdb->prefix . 'wp_mail_master_errors';

        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table_name} 
            ORDER BY created_at DESC 
            LIMIT %d OFFSET %d",
            $limit,
            $offset
        ));

        return $results ? $results : [];
    }
}
<?php

namespace Martho\WpMailMaster;

class WP_Mail_Master_Templates
{
    private $template_manager;
    private $default_template;

    public function __construct()
    {
        $this->template_manager = new WP_Mail_Master_Template_Manager();

        // Initialize default templates if none exist
        if (empty(get_option('wp_mail_master_templates'))) {
            $this->initialize_default_templates();
        }

        // Load template files and get their content properly
        $this->default_template = [
            'simple' => include WP_MAIL_MASTER_PLUGIN_DIR . 'templates/simple.php',
            'modern' => include WP_MAIL_MASTER_PLUGIN_DIR . 'templates/modern.php',
            'business' => include WP_MAIL_MASTER_PLUGIN_DIR . 'templates/business.php'
        ];

        add_action('admin_init', array($this, 'register_template_settings'));
        add_action('wp_ajax_preview_template', array($this, 'ajax_preview_template'));

        add_action('admin_enqueue_scripts', function ($hook) {
            if (strpos($hook, 'wp-mail-master-templates') !== false) {
                wp_enqueue_style('thickbox');
                wp_enqueue_script('thickbox');
            }
        });

        add_action('admin_init', function () {
            if (
                current_user_can('manage_options') &&
                isset($_GET['action']) &&
                $_GET['action'] === 'reset_mail_templates' &&
                isset($_GET['_wpnonce']) &&
                wp_verify_nonce($_GET['_wpnonce'], 'reset_mail_templates')
            ) {
                $this->reset_templates();
                wp_redirect(admin_url('admin.php?page=wp-mail-master-templates'));
                exit;
            }
        });
    }

    public function reset_templates()
    {
        // Delete all template-related options
        delete_option('wp_mail_master_templates');
        delete_option('wp_mail_master_selected_template');

        // Reinitialize with default templates
        $default_templates = [
            'simple' => $this->default_template['simple'],
            'modern' => $this->default_template['modern'],
            'business' => $this->default_template['business']
        ];

        update_option('wp_mail_master_templates', $default_templates);
        update_option('wp_mail_master_selected_template', 'simple');

        return true;
    }

    private function initialize_default_templates()
    {
        $simple_template = include WP_MAIL_MASTER_PLUGIN_DIR . 'templates/simple.php';
        update_option('wp_mail_master_templates', ['simple' => $simple_template]);
        update_option('wp_mail_master_selected_template', 'simple');
    }

    public function register_template_settings()
    {
        register_setting('wp_mail_master_templates', 'wp_mail_master_templates');
    }

    public function render_templates_page()
    {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }

        $showing_presets = !isset($_GET['template']);

        if ($showing_presets) {
            $this->render_preset_selection();
        } else {
            $this->render_template_editor();
        }
    }

    private function render_template_editor()
    {
        $templates = $this->get_templates();
        $template_id = isset($_GET['template']) ? sanitize_text_field($_GET['template']) : 'default';

        if ($template_id === 'new') {
            $preset_id = isset($_GET['preset']) ? sanitize_text_field($_GET['preset']) : '';

            if ($preset_id && isset($this->default_template[$preset_id])) {
                $preset = $this->default_template[$preset_id];
                $template = [
                    'name' => $preset['name'] . ' Copy',
                    'subject' => $preset['subject'],
                    'content' => $preset['content']
                ];
            } else {
                $template = [
                    'name' => '',
                    'subject' => '',
                    'content' => $this->default_template['simple']['content']
                ];
            }
        } else {
            $template = $this->template_manager->get_template($template_id);
            if (!$template) {
                $template = reset($this->default_template);
            }
        }

        include WP_MAIL_MASTER_PLUGIN_DIR . 'admin/views/templates.php';
    }

    private function render_preset_selection()
    {
        $available_templates = $this->template_manager->get_templates();
        include WP_MAIL_MASTER_PLUGIN_DIR . 'admin/views/template-presets.php';
    }

    private function prepare_preview_content($template)
    {
        $sample_content = '<p style="margin-bottom: 15px;">Dear Customer,</p>
            <p style="margin-bottom: 15px;">Thank you for choosing our services. This is a sample email to demonstrate the template design.</p>
            <p style="margin-bottom: 15px;">Best regards,<br>The Team</p>';

        return str_replace(
            ['{email_title}', '{email_content}', '{site_name}', '{site_url}', '{year}', '{date}'],
            [
                'Welcome to Our Service',
                $sample_content,
                get_bloginfo('name'),
                get_bloginfo('url'),
                date('Y'),
                date_i18n(get_option('date_format'))
            ],
            $template
        );
    }

    public function get_available_variables()
    {
        return array(
            '{site_name}' => __('Website Name', 'wp-mail-master'),
            '{site_url}' => __('Website URL', 'wp-mail-master'),
            '{email_title}' => __('Email Subject', 'wp-mail-master'),
            '{email_content}' => __('Main Email Content', 'wp-mail-master'),
            '{year}' => __('Current Year', 'wp-mail-master'),
            '{date}' => __('Current Date', 'wp-mail-master'),
        );
    }

    public function get_templates()
    {
        return $this->template_manager->get_templates();
    }

    public function get_template($template_id)
    {
        return $this->template_manager->get_template($template_id);
    }

    public function get_selected_template()
    {
        $selected_id = get_option('wp_mail_master_selected_template', 'simple');
        $templates = get_option('wp_mail_master_templates', array());
        return isset($templates[$selected_id]) ? $templates[$selected_id] : $templates['simple'];
    }

    public function ajax_preview_template()
    {
        check_ajax_referer('preview_template');

        $content = isset($_POST['content']) ? wp_kses_post($_POST['content']) : '';
        $subject = isset($_POST['subject']) ? sanitize_text_field($_POST['subject']) : '';

        $preview_content = $this->prepare_preview_content($content);
        wp_send_json_success(['html' => $preview_content]);
    }
}
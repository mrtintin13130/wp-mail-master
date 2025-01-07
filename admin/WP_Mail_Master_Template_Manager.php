<?php

namespace Martho\WpMailMaster;

class WP_Mail_Master_Template_Manager
{
    private $template_directory;
    private $default_templates = [];
    private $custom_templates = [];

    public function __construct()
    {
        $this->template_directory = WP_MAIL_MASTER_PLUGIN_DIR . 'templates/';
        $this->load_templates();
    }

    /**
     * Load all available templates from files and database
     */
    private function load_templates()
    {
        // Load file-based templates
        if (is_dir($this->template_directory)) {
            foreach (glob($this->template_directory . '*.php') as $template_file) {
                $template_id = basename($template_file, '.php');
                $template_data = include $template_file;

                if (is_array($template_data) && isset($template_data['name'], $template_data['content'])) {
                    $this->default_templates[$template_id] = $template_data;
                }
            }
        }

        // Load custom templates from database
        $this->custom_templates = get_option('wp_mail_master_custom_templates', []);
    }

    /**
     * Get all available templates
     */
    public function get_templates()
    {
        $templates = get_option('wp_mail_master_templates', array());

        // If we have a selected template, make sure it's included
        $selected_template_id = get_option('wp_mail_master_selected_template', '');
        if ($selected_template_id && isset($templates[$selected_template_id])) {
            return $templates;
        }

        return $templates;
    }

    /**
     * Get a specific template by ID
     */
    public function get_template($template_id)
    {
        $templates = $this->get_templates();
        return isset($templates[$template_id]) ? $templates[$template_id] : null;
    }

    /**
     * Save template customizations
     */
    public function save_template($template_id, $template_data)
    {
        // Sanitize template data
        $sanitized_data = [
            'name' => sanitize_text_field($template_data['name']),
            'subject' => sanitize_text_field($template_data['subject']),
            'content' => wp_kses_post($template_data['content'])
        ];

        // Save to custom templates
        $this->custom_templates[$template_id] = $sanitized_data;
        return update_option('wp_mail_master_custom_templates', $this->custom_templates);
    }

    /**
     * Reset a template to its default state
     */
    public function reset_template($template_id)
    {
        if (isset($this->custom_templates[$template_id])) {
            unset($this->custom_templates[$template_id]);
            return update_option('wp_mail_master_custom_templates', $this->custom_templates);
        }
        return false;
    }

    /**
     * Create a new custom template
     */
    public function create_template($template_data)
    {
        $template_id = 'custom_' . time();
        return $this->save_template($template_id, $template_data);
    }

    /**
     * Delete a custom template
     */
    public function delete_template($template_id)
    {
        // Don't allow deletion of default templates
        if (isset($this->default_templates[$template_id])) {
            return false;
        }

        if (isset($this->custom_templates[$template_id])) {
            unset($this->custom_templates[$template_id]);
            return update_option('wp_mail_master_custom_templates', $this->custom_templates);
        }
        return false;
    }
}
<?php

namespace Martho\WpMailMaster;

class WP_Mail_Master_Template_Validator {
    /**
     * List of allowed HTML tags and attributes for email templates
     */
    private $allowed_html = [
        'html' => [
            'lang' => true,
        ],
        'head' => [],
        'title' => [],
        'meta' => [
            'charset' => true,
            'content' => true,
            'name' => true,
        ],
        'body' => [
            'style' => true,
            'bgcolor' => true,
        ],
        'div' => [
            'style' => true,
            'align' => true,
            'class' => true,
        ],
        'p' => [
            'style' => true,
            'class' => true,
        ],
        'span' => [
            'style' => true,
            'class' => true,
        ],
        'br' => [],
        'hr' => [
            'style' => true,
        ],
        'table' => [
            'style' => true,
            'width' => true,
            'border' => true,
            'cellpadding' => true,
            'cellspacing' => true,
            'class' => true,
        ],
        'tbody' => [],
        'tr' => [
            'style' => true,
        ],
        'td' => [
            'style' => true,
            'width' => true,
            'valign' => true,
            'align' => true,
            'class' => true,
        ],
        'img' => [
            'src' => true,
            'alt' => true,
            'style' => true,
            'width' => true,
            'height' => true,
            'class' => true,
        ],
        'a' => [
            'href' => true,
            'style' => true,
            'target' => true,
            'class' => true,
        ],
        'strong' => [],
        'em' => [],
        'b' => [],
        'i' => [],
        'u' => [],
    ];

    /**
     * List of allowed CSS properties
     */
    private $allowed_css = [
        'background',
        'background-color',
        'border',
        'border-radius',
        'color',
        'display',
        'font-family',
        'font-size',
        'font-style',
        'font-weight',
        'height',
        'line-height',
        'margin',
        'margin-top',
        'margin-right',
        'margin-bottom',
        'margin-left',
        'padding',
        'padding-top',
        'padding-right',
        'padding-bottom',
        'padding-left',
        'text-align',
        'text-decoration',
        'width',
    ];

    /**
     * Validate and sanitize a template
     */
    public function validate_template($template) {
        if (!is_array($template)) {
            throw new WP_Mail_Master_Exception(
                'Invalid template format',
                WP_Mail_Master_Exception::VALIDATION_ERROR
            );
        }

        // Required fields
        $required_fields = ['name', 'subject', 'content'];
        foreach ($required_fields as $field) {
            if (!isset($template[$field]) || empty($template[$field])) {
                throw new WP_Mail_Master_Exception(
                    "Missing required template field: {$field}",
                    WP_Mail_Master_Exception::VALIDATION_ERROR
                );
            }
        }

        // Sanitize and validate each field
        return [
            'name' => $this->sanitize_template_name($template['name']),
            'subject' => $this->sanitize_template_subject($template['subject']),
            'content' => $this->sanitize_template_content($template['content'])
        ];
    }

    /**
     * Sanitize template name
     */
    private function sanitize_template_name($name) {
        $name = sanitize_text_field($name);
        if (strlen($name) > 100) {
            throw new WP_Mail_Master_Exception(
                'Template name is too long (max 100 characters)',
                WP_Mail_Master_Exception::VALIDATION_ERROR
            );
        }
        return $name;
    }

    /**
     * Sanitize template subject
     */
    private function sanitize_template_subject($subject) {
        $subject = sanitize_text_field($subject);
        if (strlen($subject) > 255) {
            throw new WP_Mail_Master_Exception(
                'Template subject is too long (max 255 characters)',
                WP_Mail_Master_Exception::VALIDATION_ERROR
            );
        }
        return $subject;
    }

    /**
     * Sanitize template content with strict HTML and CSS validation
     */
    private function sanitize_template_content($content) {
        // First, validate template variables
        $this->validate_template_variables($content);

        // Strip any potentially harmful content
        $content = wp_kses($content, $this->allowed_html);

        // Validate and clean CSS
        $content = $this->sanitize_css($content);

        // Validate final content length
        if (strlen($content) > 100000) { // 100KB limit
            throw new WP_Mail_Master_Exception(
                'Template content is too long (max 100KB)',
                WP_Mail_Master_Exception::VALIDATION_ERROR
            );
        }

        return $content;
    }

    /**
     * Validate template variables
     */
    private function validate_template_variables($content) {
        $allowed_vars = [
            '{site_name}',
            '{site_url}',
            '{year}',
            '{date}',
            '{email_title}',
            '{email_content}'
        ];

        // Ensure content is a string
        $content = (string)($content ?: '');

        // Find all template variables in content
        preg_match_all('/\{([^}]+)\}/', $content, $matches);

        if (!empty($matches[0])) {
            foreach ($matches[0] as $var) {
                $var_str = (string)($var ?: '');
                if (!in_array($var_str, $allowed_vars)) {
                    throw new WP_Mail_Master_Exception(
                        "Invalid template variable: {$var_str}",
                        WP_Mail_Master_Exception::VALIDATION_ERROR
                    );
                }
            }
        }
    }

    /**
     * Sanitize CSS in content
     */
    private function sanitize_css($content) {
        // Ensure content is a string
        $content = (string)($content ?: '');
        
        // Extract all style attributes
        preg_match_all('/style=(["\'])(.*?)\1/', $content, $matches);

        if (!empty($matches[2])) {
            foreach ($matches[2] as $css) {
                $css_str = (string)($css ?: '');
                $sanitized_css = $this->validate_css_properties($css_str);
                $content = str_replace($css_str, $sanitized_css, $content);
            }
        }

        return $content;
    }

    /**
     * Validate CSS properties
     */
    private function validate_css_properties($css) {
        $css_array = array_filter(array_map('trim', explode(';', $css)));
        $sanitized_props = [];

        foreach ($css_array as $property) {
            $property_parts = array_map('trim', explode(':', $property, 2));
            if (count($property_parts) === 2) {
                $prop_name = strtolower($property_parts[0]);
                $prop_value = $property_parts[1];

                // Check if property is allowed
                if (in_array($prop_name, $this->allowed_css)) {
                    // Validate property value (basic sanitization)
                    $prop_value = $this->sanitize_css_value($prop_value);
                    $sanitized_props[] = "{$prop_name}: {$prop_value}";
                }
            }
        }

        return implode('; ', $sanitized_props);
    }

    /**
     * Sanitize CSS property value
     */
    private function sanitize_css_value($value) {
        // Remove potentially harmful content
        $value = preg_replace('/[<>]/', '', $value);
        
        // Only allow certain units
        $value = preg_replace('/[0-9]+(%|px|em|rem|pt|vh|vw)/', '$0', $value);
        
        // Allow basic color values
        $value = preg_replace('/#[a-fA-F0-9]{3,6}/', '$0', $value);
        
        return $value;
    }
} 
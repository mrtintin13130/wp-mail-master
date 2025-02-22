<?php

namespace Martho\WpMailMaster;

class WP_Mail_Master_Security {
    private $plugin_name;
    private $version;

    public function __construct($plugin_name, $version) {
        $this->plugin_name = $plugin_name;
        $this->version = $version;

        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_menu', array($this, 'add_security_menu'));
    }

    public function add_security_menu() {
        add_submenu_page(
            'wp-mail-master',
            __('Security Settings', 'wp-mail-master'),
            __('Security', 'wp-mail-master'),
            'manage_options',
            'wp-mail-master-security',
            array($this, 'render_security_page')
        );
    }

    public function render_security_page() {
        if (!current_user_can('manage_options')) {
            return;
        }

        // Save settings if form was submitted
        if (isset($_POST['submit']) && check_admin_referer('wp_mail_master_security_settings')) {
            $this->save_security_settings();
        }

        $options = get_option('wp_mail_master_security_options', array());
        
        // Get spam keywords, falling back to defaults if none are set
        $spam_keywords = get_option('wp_mail_master_spam_keywords', array());
        if (empty($spam_keywords)) {
            $spam_keywords = $this->get_default_spam_keywords();
        }

        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            
            <div class="notice notice-info">
                <p><?php _e('Configure email security and spam protection settings below. Enable or disable features as needed.', 'wp-mail-master'); ?></p>
            </div>

            <form method="post" action="">
                <?php wp_nonce_field('wp_mail_master_security_settings'); ?>
                
                <div class="wp-mail-master-security-grid">
                    <!-- Rate Limiting Section -->
                    <div class="security-section">
                        <h2><?php _e('Rate Limiting', 'wp-mail-master'); ?></h2>
                        
                        <label>
                            <input type="checkbox" 
                                   name="rate_limiting_enabled" 
                                   value="1" 
                                   <?php checked(1, isset($options['rate_limiting_enabled']) ? $options['rate_limiting_enabled'] : 0); ?> />
                            <?php _e('Enable Rate Limiting', 'wp-mail-master'); ?>
                        </label>
                        
                        <div class="rate-limit-settings">
                            <label>
                                <?php _e('Hourly Limit per IP:', 'wp-mail-master'); ?>
                                <input type="number" 
                                       name="hourly_rate_limit" 
                                       value="<?php echo esc_attr(isset($options['hourly_rate_limit']) ? $options['hourly_rate_limit'] : 50); ?>" 
                                       min="1" 
                                       max="1000" />
                            </label>
                            
                            <label>
                                <?php _e('Daily Limit per Email:', 'wp-mail-master'); ?>
                                <input type="number" 
                                       name="daily_email_limit" 
                                       value="<?php echo esc_attr(isset($options['daily_email_limit']) ? $options['daily_email_limit'] : 100); ?>" 
                                       min="1" 
                                       max="5000" />
                            </label>
                        </div>
                    </div>

                    <!-- Email Validation Section -->
                    <div class="security-section">
                        <h2><?php _e('Email Validation', 'wp-mail-master'); ?></h2>
                        
                        <label>
                            <input type="checkbox" 
                                   name="email_validation_enabled" 
                                   value="1" 
                                   <?php checked(1, isset($options['email_validation_enabled']) ? $options['email_validation_enabled'] : 1); ?> />
                            <?php _e('Enable Email Validation', 'wp-mail-master'); ?>
                        </label>
                        
                        <label>
                            <input type="checkbox" 
                                   name="mx_record_check_enabled" 
                                   value="1" 
                                   <?php checked(1, isset($options['mx_record_check_enabled']) ? $options['mx_record_check_enabled'] : 1); ?> />
                            <?php _e('Check MX Records', 'wp-mail-master'); ?>
                        </label>
                    </div>

                    <!-- Content Filtering Section -->
                    <div class="security-section">
                        <h2><?php _e('Content Filtering', 'wp-mail-master'); ?></h2>
                        
                        <label>
                            <input type="checkbox" 
                                   name="content_filtering_enabled" 
                                   value="1" 
                                   <?php checked(1, isset($options['content_filtering_enabled']) ? $options['content_filtering_enabled'] : 1); ?> />
                            <?php _e('Enable Content Filtering', 'wp-mail-master'); ?>
                        </label>
                        
                        <label>
                            <input type="checkbox" 
                                   name="caps_check_enabled" 
                                   value="1" 
                                   <?php checked(1, isset($options['caps_check_enabled']) ? $options['caps_check_enabled'] : 1); ?> />
                            <?php _e('Check Excessive Caps', 'wp-mail-master'); ?>
                        </label>

                        <div class="spam-keywords">
                            <label>
                                <?php _e('Spam Keywords (comma-separated):', 'wp-mail-master'); ?>
                                <textarea name="spam_keywords" rows="4"><?php 
                                    echo esc_textarea(implode(', ', $spam_keywords)); 
                                ?></textarea>
                                <p class="description">
                                    <?php _e('These keywords will trigger spam detection when found in email content. Add your own keywords or modify the defaults.', 'wp-mail-master'); ?>
                                </p>
                            </label>
                            
                            <label>
                                <?php _e('Keyword Match Threshold:', 'wp-mail-master'); ?>
                                <input type="number" 
                                       name="keyword_match_threshold" 
                                       value="<?php echo esc_attr(isset($options['keyword_match_threshold']) ? $options['keyword_match_threshold'] : 3); ?>" 
                                       min="1" 
                                       max="10" />
                            </label>
                        </div>
                    </div>

                    <!-- IP Protection Section -->
                    <div class="security-section">
                        <h2><?php _e('IP Protection', 'wp-mail-master'); ?></h2>
                        
                        <label>
                            <input type="checkbox" 
                                   name="ip_protection_enabled" 
                                   value="1" 
                                   <?php checked(1, isset($options['ip_protection_enabled']) ? $options['ip_protection_enabled'] : 1); ?> />
                            <?php _e('Enable IP Protection', 'wp-mail-master'); ?>
                        </label>
                        
                        <label>
                            <input type="checkbox" 
                                   name="ip_reputation_check_enabled" 
                                   value="1" 
                                   <?php checked(1, isset($options['ip_reputation_check_enabled']) ? $options['ip_reputation_check_enabled'] : 0); ?> />
                            <?php _e('Enable IP Reputation Check', 'wp-mail-master'); ?>
                        </label>

                        <div class="ip-blacklist">
                            <label>
                                <?php _e('IP Blacklist (one per line):', 'wp-mail-master'); ?>
                                <textarea name="ip_blacklist" rows="4"><?php 
                                    echo esc_textarea(implode("\n", get_option('wp_mail_master_ip_blacklist', array()))); 
                                ?></textarea>
                            </label>
                        </div>
                    </div>

                    <!-- Honeypot Section -->
                    <div class="security-section">
                        <h2><?php _e('Form Protection', 'wp-mail-master'); ?></h2>
                        
                        <label>
                            <input type="checkbox" 
                                   name="honeypot_enabled" 
                                   value="1" 
                                   <?php checked(1, isset($options['honeypot_enabled']) ? $options['honeypot_enabled'] : 1); ?> />
                            <?php _e('Enable Honeypot Protection', 'wp-mail-master'); ?>
                        </label>
                        
                        <label>
                            <input type="checkbox" 
                                   name="time_check_enabled" 
                                   value="1" 
                                   <?php checked(1, isset($options['time_check_enabled']) ? $options['time_check_enabled'] : 1); ?> />
                            <?php _e('Enable Time-based Protection', 'wp-mail-master'); ?>
                        </label>

                        <label>
                            <?php _e('Minimum Form Fill Time (seconds):', 'wp-mail-master'); ?>
                            <input type="number" 
                                   name="min_form_fill_time" 
                                   value="<?php echo esc_attr(isset($options['min_form_fill_time']) ? $options['min_form_fill_time'] : 2); ?>" 
                                   min="1" 
                                   max="10" />
                        </label>
                    </div>
                </div>

                <?php submit_button(__('Save Security Settings', 'wp-mail-master')); ?>
            </form>
        </div>

        <style>
            .wp-mail-master-security-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
                gap: 20px;
                margin: 20px 0;
            }
            .security-section {
                background: #fff;
                padding: 20px;
                border: 1px solid #ccd0d4;
                border-radius: 4px;
            }
            .security-section h2 {
                margin-top: 0;
                padding-bottom: 10px;
                border-bottom: 1px solid #eee;
            }
            .security-section label {
                display: block;
                margin: 10px 0;
            }
            .security-section textarea {
                width: 100%;
                margin-top: 5px;
            }
            .rate-limit-settings,
            .spam-keywords,
            .ip-blacklist {
                margin-top: 15px;
                padding-top: 15px;
                border-top: 1px solid #eee;
            }
            .notice {
                margin: 20px 0;
            }
        </style>
        <?php
    }

    public function register_settings() {
        register_setting(
            'wp_mail_master_security_options',
            'wp_mail_master_security_options',
            array($this, 'sanitize_security_settings')
        );
    }

    private function save_security_settings() {
        if (!current_user_can('manage_options')) {
            return;
        }

        $options = array(
            // Rate Limiting
            'rate_limiting_enabled' => isset($_POST['rate_limiting_enabled']) ? 1 : 0,
            'hourly_rate_limit' => isset($_POST['hourly_rate_limit']) ? absint($_POST['hourly_rate_limit']) : 50,
            'daily_email_limit' => isset($_POST['daily_email_limit']) ? absint($_POST['daily_email_limit']) : 100,

            // Email Validation
            'email_validation_enabled' => isset($_POST['email_validation_enabled']) ? 1 : 0,
            'mx_record_check_enabled' => isset($_POST['mx_record_check_enabled']) ? 1 : 0,

            // Content Filtering
            'content_filtering_enabled' => isset($_POST['content_filtering_enabled']) ? 1 : 0,
            'caps_check_enabled' => isset($_POST['caps_check_enabled']) ? 1 : 0,
            'keyword_match_threshold' => isset($_POST['keyword_match_threshold']) ? absint($_POST['keyword_match_threshold']) : 3,

            // IP Protection
            'ip_protection_enabled' => isset($_POST['ip_protection_enabled']) ? 1 : 0,
            'ip_reputation_check_enabled' => isset($_POST['ip_reputation_check_enabled']) ? 1 : 0,

            // Form Protection
            'honeypot_enabled' => isset($_POST['honeypot_enabled']) ? 1 : 0,
            'time_check_enabled' => isset($_POST['time_check_enabled']) ? 1 : 0,
            'min_form_fill_time' => isset($_POST['min_form_fill_time']) ? absint($_POST['min_form_fill_time']) : 2
        );

        // Save spam keywords
        if (isset($_POST['spam_keywords'])) {
            $keywords = explode(',', sanitize_text_field($_POST['spam_keywords']));
            $keywords = array_map('trim', $keywords);
            $keywords = array_filter($keywords);
            
            // If no keywords provided, use defaults
            if (empty($keywords)) {
                $keywords = $this->get_default_spam_keywords();
            }
            
            update_option('wp_mail_master_spam_keywords', $keywords);
        }

        // Save IP blacklist
        if (isset($_POST['ip_blacklist'])) {
            $ips = explode("\n", sanitize_textarea_field($_POST['ip_blacklist']));
            $ips = array_map('trim', $ips);
            $ips = array_filter($ips, function($ip) {
                return filter_var($ip, FILTER_VALIDATE_IP);
            });
            update_option('wp_mail_master_ip_blacklist', $ips);
        }

        // Save main options
        update_option('wp_mail_master_security_options', $options);

        add_settings_error(
            'wp_mail_master_messages',
            'settings_updated',
            __('Security settings saved successfully.', 'wp-mail-master'),
            'updated'
        );
    }

    private function get_default_spam_keywords() {
        return [
            'viagra', 'cialis', 'casino', 'lottery', 'winner',
            'inheritance', 'millionaire', 'buy now', 'free offer',
            'limited time', 'act now', 'congratulations', 'prize',
            'guaranteed', 'unlimited', 'miracle', 'risk-free'
        ];
    }
} 
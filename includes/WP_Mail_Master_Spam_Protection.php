<?php

namespace Martho\WpMailMaster;

class WP_Mail_Master_Spam_Protection {
    private $options;
    private $cache_key_prefix = 'wp_mail_master_spam_';
    
    public function __construct() {
        $this->options = get_option('wp_mail_master_security_options', []);
        add_action('wp_mail_master_before_send', [$this, 'check_spam']);
    }

    /**
     * Main spam check method
     * 
     * @param array $email_data Email data including to, subject, message
     * @throws WP_Mail_Master_Exception if spam detected
     */
    public function check_spam($email_data) {
        // Check rate limits if enabled
        if (!empty($this->options['rate_limiting_enabled'])) {
            $this->check_rate_limits($email_data['to']);
        }
        
        // Validate email if enabled
        if (!empty($this->options['email_validation_enabled'])) {
            $this->validate_email($email_data['to']);
        }
        
        // Check content if enabled
        if (!empty($this->options['content_filtering_enabled'])) {
            $this->check_content($email_data['subject'], $email_data['message']);
        }
        
        // Check IP if enabled
        if (!empty($this->options['ip_protection_enabled'])) {
            $this->check_ip_reputation();
        }
        
        // Check honeypot if enabled
        if (!empty($this->options['honeypot_enabled']) && 
            !empty($email_data['honeypot_field']) && 
            !empty($email_data['honeypot_field_value'])) {
            $this->check_honeypot($email_data['honeypot_field'], $email_data['honeypot_field_value']);
        }

        // Check submission timing if enabled
        if (!empty($this->options['time_check_enabled']) && 
            isset($email_data['timestamp'])) {
            $this->check_submission_timing($email_data['timestamp']);
        }
    }

    /**
     * Check rate limits for email sending
     */
    private function check_rate_limits($email) {
        $ip = $this->get_client_ip();
        
        // Check IP-based rate limit
        $ip_key = $this->cache_key_prefix . 'ip_' . md5($ip);
        $ip_count = get_transient($ip_key);
        
        if ($ip_count === false) {
            set_transient($ip_key, 1, HOUR_IN_SECONDS);
        } elseif ($ip_count >= $this->get_hourly_limit()) {
            // Log the rate limit hit before throwing exception
            $this->log_rate_limit_hit('ip', $ip, $ip_count, $this->get_hourly_limit());
            
            throw new WP_Mail_Master_Exception(
                'Rate limit exceeded. Please try again later.',
                WP_Mail_Master_Exception::RATE_LIMIT_ERROR
            );
        } else {
            set_transient($ip_key, $ip_count + 1, HOUR_IN_SECONDS);
        }
        
        // For contact forms, try to get the sender's email from form data
        $sender_email = $this->get_sender_email();
        
        if ($sender_email) {
            // Check sender email-based rate limit
            $sender_key = $this->cache_key_prefix . 'sender_' . md5($sender_email);
            $sender_count = get_transient($sender_key);
            
            if ($sender_count === false) {
                set_transient($sender_key, 1, DAY_IN_SECONDS);
            } elseif ($sender_count >= $this->get_daily_email_limit()) {
                // Log the rate limit hit before throwing exception
                $this->log_rate_limit_hit('sender', $sender_email, $sender_count, $this->get_daily_email_limit());
                
                throw new WP_Mail_Master_Exception(
                    'Daily email limit reached for sender address.',
                    WP_Mail_Master_Exception::RATE_LIMIT_ERROR
                );
            } else {
                set_transient($sender_key, $sender_count + 1, DAY_IN_SECONDS);
            }
        } else {
            // Handle recipient email(s) rate limit
            $emails = is_array($email) ? $email : array($email);
            
            foreach ($emails as $recipient_email) {
                // Ensure the email is a string and properly formatted
                if (!is_string($recipient_email) || !filter_var($recipient_email, FILTER_VALIDATE_EMAIL)) {
                    continue;
                }
                
                $recipient_key = $this->cache_key_prefix . 'recipient_' . md5($recipient_email);
                $recipient_count = get_transient($recipient_key);
                
                if ($recipient_count === false) {
                    set_transient($recipient_key, 1, DAY_IN_SECONDS);
                } elseif ($recipient_count >= $this->get_daily_email_limit()) {
                    // Log the rate limit hit before throwing exception
                    $this->log_rate_limit_hit('recipient', $recipient_email, $recipient_count, $this->get_daily_email_limit());
                    
                    throw new WP_Mail_Master_Exception(
                        sprintf('Daily email limit reached for recipient address: %s', $recipient_email),
                        WP_Mail_Master_Exception::RATE_LIMIT_ERROR
                    );
                } else {
                    set_transient($recipient_key, $recipient_count + 1, DAY_IN_SECONDS);
                }
            }
        }
    }

    /**
     * Validate email format and domain
     */
    private function validate_email($email) {
        $emails = is_array($email) ? $email : array($email);
        $invalid_emails = array();
        
        foreach ($emails as $single_email) {
            // Skip if not a string
            if (!is_string($single_email)) {
                $invalid_emails[] = 'Non-string email value';
                continue;
            }
            
            // Basic format validation
            if (!filter_var($single_email, FILTER_VALIDATE_EMAIL)) {
                $invalid_emails[] = $single_email;
                continue;
            }

            // Check MX record if enabled
            if (!empty($this->options['mx_record_check_enabled'])) {
                $domain = substr(strrchr($single_email, "@"), 1);
                if (!checkdnsrr($domain, 'MX')) {
                    $invalid_emails[] = $single_email;
                }
            }
        }
        
        if (!empty($invalid_emails)) {
            throw new WP_Mail_Master_Exception(
                sprintf(
                    'Invalid email format or domain: %s',
                    implode(', ', array_unique($invalid_emails))
                ),
                WP_Mail_Master_Exception::VALIDATION_ERROR
            );
        }
    }

    /**
     * Check content for spam signals
     */
    private function check_content($subject, $message) {
        $combined_content = strtolower($subject . ' ' . strip_tags($message));
        
        // Check for spam keywords if content filtering is enabled
        if (!empty($this->options['content_filtering_enabled'])) {
            $spam_keywords = $this->get_spam_keywords();
            $keyword_count = 0;
            
            foreach ($spam_keywords as $keyword) {
                if (strpos($combined_content, $keyword) !== false) {
                    $keyword_count++;
                }
            }
            
            $threshold = isset($this->options['keyword_match_threshold']) ? 
                $this->options['keyword_match_threshold'] : 3;
            
            if ($keyword_count >= $threshold) {
                throw new WP_Mail_Master_Exception(
                    'Message content appears to be spam.',
                    WP_Mail_Master_Exception::SPAM_CONTENT_ERROR
                );
            }
        }
        
        // Check for excessive caps if enabled
        if (!empty($this->options['caps_check_enabled'])) {
            $caps_ratio = $this->calculate_caps_ratio($combined_content);
            if ($caps_ratio > 0.3) { // More than 30% caps
                throw new WP_Mail_Master_Exception(
                    'Message contains excessive capital letters.',
                    WP_Mail_Master_Exception::SPAM_CONTENT_ERROR
                );
            }
        }
    }

    /**
     * Check IP reputation using various methods
     */
    private function check_ip_reputation() {
        $ip = $this->get_client_ip();
        
        // Check local blacklist
        if ($this->is_ip_blacklisted($ip)) {
            throw new WP_Mail_Master_Exception(
                'IP address is blacklisted.',
                WP_Mail_Master_Exception::IP_BLOCKED_ERROR
            );
        }
        
        // Check IP reputation if enabled
        if (!empty($this->options['ip_reputation_check_enabled'])) {
            // Implement external IP reputation check here
        }
    }

    /**
     * Check honeypot field
     */
    private function check_honeypot($field_name, $field_value) {
        if (!empty($field_value)) {
            throw new WP_Mail_Master_Exception(
                'Spam detected.',
                WP_Mail_Master_Exception::SPAM_DETECTED_ERROR
            );
        }
    }

    /**
     * Get client IP address
     */
    private function get_client_ip() {
        $ip = '';
        
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } else {
            $ip = $_SERVER['REMOTE_ADDR'];
        }
        
        return filter_var($ip, FILTER_VALIDATE_IP);
    }

    /**
     * Calculate ratio of capital letters in text
     */
    private function calculate_caps_ratio($text) {
        $total_chars = strlen(preg_replace('/[^a-zA-Z]/', '', $text));
        if ($total_chars === 0) return 0;
        
        $caps_chars = strlen(preg_replace('/[^A-Z]/', '', $text));
        return $caps_chars / $total_chars;
    }

    /**
     * Check if IP is in local blacklist
     */
    private function is_ip_blacklisted($ip) {
        $blacklist = get_option('wp_mail_master_ip_blacklist', []);
        return in_array($ip, $blacklist);
    }

    /**
     * Get spam keywords
     */
    private function get_spam_keywords() {
        $default_keywords = [
            'viagra', 'cialis', 'casino', 'lottery', 'winner',
            'inheritance', 'millionaire', 'buy now', 'free offer',
            'limited time', 'act now', 'congratulations', 'prize',
            'guaranteed', 'unlimited', 'miracle', 'risk-free'
        ];
        
        return array_merge(
            $default_keywords,
            get_option('wp_mail_master_spam_keywords', [])
        );
    }

    /**
     * Get hourly rate limit
     */
    private function get_hourly_limit() {
        return !empty($this->options['hourly_rate_limit'])
            ? (int)$this->options['hourly_rate_limit']
            : 50;
    }

    /**
     * Get daily email limit
     */
    private function get_daily_email_limit() {
        return !empty($this->options['daily_email_limit'])
            ? (int)$this->options['daily_email_limit']
            : 100;
    }

    private function check_submission_timing($timestamp) {
        $min_time = isset($this->options['min_form_fill_time']) ? 
            $this->options['min_form_fill_time'] : 2;
        
        $time_diff = time() - intval($timestamp / 1000);
        if ($time_diff < $min_time) {
            throw new WP_Mail_Master_Exception(
                'Submission too quick. Please try again.',
                WP_Mail_Master_Exception::SPAM_DETECTED_ERROR
            );
        }
    }

    /**
     * Try to extract sender email from various common form fields and POST data
     */
    private function get_sender_email() {
        $possible_fields = [
            'email',
            'sender_email',
            'from_email',
            'user_email',
            'contact_email',
            'your_email',
            'email_address'
        ];

        // Check POST data for email fields
        foreach ($possible_fields as $field) {
            if (!empty($_POST[$field]) && is_email($_POST[$field])) {
                return sanitize_email($_POST[$field]);
            }
        }

        // Check message content for email pattern
        if (!empty($_POST['message'])) {
            $message = sanitize_textarea_field($_POST['message']);
            if (preg_match('/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/', $message, $matches)) {
                $email = $matches[0];
                if (is_email($email)) {
                    return sanitize_email($email);
                }
            }
        }

        return false;
    }

    /**
     * Log rate limit hit
     */
    private function log_rate_limit_hit($type, $identifier, $count, $limit) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'wp_mail_master_security_logs';
        
        // Ensure the table exists
        $this->create_security_logs_table();
        
        $data = array(
            'time' => current_time('mysql'),
            'event_type' => 'rate_limit',
            'check_type' => $type, // 'ip', 'sender', or 'recipient'
            'identifier' => $identifier,
            'details' => wp_json_encode([
                'current_count' => $count,
                'limit' => $limit,
                'remaining_time' => $type === 'ip' ? HOUR_IN_SECONDS : DAY_IN_SECONDS
            ]),
            'ip_address' => $this->get_client_ip()
        );
        
        $wpdb->insert($table_name, $data);
    }

    /**
     * Create security logs table if it doesn't exist
     */
    private function create_security_logs_table() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'wp_mail_master_security_logs';
        
        // Check if table exists
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
            $charset_collate = $wpdb->get_charset_collate();
            
            $sql = "CREATE TABLE $table_name (
                id bigint(20) NOT NULL AUTO_INCREMENT,
                time datetime DEFAULT CURRENT_TIMESTAMP,
                event_type varchar(50) NOT NULL,
                check_type varchar(50) NOT NULL,
                identifier varchar(255) NOT NULL,
                details text,
                ip_address varchar(45),
                PRIMARY KEY  (id),
                KEY event_type (event_type),
                KEY check_type (check_type),
                KEY identifier (identifier),
                KEY time (time)
            ) $charset_collate;";
            
            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta($sql);
        }
    }
} 
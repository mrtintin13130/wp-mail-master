<?php

// includes/class-wp-mail-master-i18n.php

namespace Martho\WpMailMaster;
class WP_Mail_Master_i18n {
    public function load_plugin_textdomain() {
        load_plugin_textdomain(
            'wp-mail-master',
            false,
            dirname(dirname(plugin_basename(__FILE__))) . '/languages/'
        );
    }
}
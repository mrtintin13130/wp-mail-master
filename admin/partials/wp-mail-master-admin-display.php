<!-- admin/partials/wp-mail-master-admin-display.php -->

<div class="wrap">
    <h1 style="display: flex; justify-content: space-between; align-items: center;">
        <?php echo esc_html(get_admin_page_title()); ?>
        <button id="open-off-canvas"
            class="button button-primary"><?php _e('Send Test Email', 'wp-mail-master'); ?></button>
    </h1>
    <form action="options.php" method="post">
        <?php
        settings_fields('wp_mail_master_settings');

        // General Settings Section
        do_settings_sections('wp_mail_master_settings_general');

        // SMTP Settings Section
        echo '<div class="wp_mail_master_smtp_section">';
        do_settings_sections('wp_mail_master_smtp_section');
        echo '</div>';

        // Brevo Settings Section
        echo '<div class="wp_mail_master_brevo_section">';
        do_settings_sections('wp_mail_master_brevo_section');
        echo '</div>';
        
        // Sendgrid Settings Section
        echo '<div class="wp_mail_master_sendgrid_section">';
        do_settings_sections('wp_mail_master_sendgrid_section');
        echo '</div>';

        submit_button('Save Settings');
        ?>
    </form>

    <div class="off-canvas" id="off-canvas">
        <div class="off-canvas-header">
            <h2><?php _e('Send Test Email', 'wp-mail-master'); ?></h2>
            <button class="off-canvas-close" id="close-off-canvas">&times;</button>
        </div>
        <form id="send-test-email-form" method="post" action="">
            <input type="email" id="test-email-address" name="test_email_address"
                value="<?php echo esc_attr(get_option('admin_email')); ?>" required />
            <button type="button" id="send-test-email-button"
                class="button button-primary"><?php _e('Send Test Email', 'wp-mail-master'); ?></button>
        </form>
        <div id="test-email-result"></div>
    </div>
</div>
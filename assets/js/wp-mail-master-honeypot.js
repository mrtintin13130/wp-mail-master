jQuery(document).ready(function($) {
    // Add honeypot field to all email forms
    $('form').each(function() {
        if ($(this).find('input[type="email"]').length > 0) {
            // Create honeypot field
            var honeypot = $('<div class="wp-mail-master-hp" style="display:none !important;">' +
                '<input type="text" name="wp_mail_master_hp" value="" ' +
                'tabindex="-1" autocomplete="off"/></div>');
            
            // Add it to the form
            $(this).append(honeypot);
        }
    });

    // Add timestamp to forms to prevent rapid submissions
    $('form').each(function() {
        if ($(this).find('input[type="email"]').length > 0) {
            var timestamp = $('<input type="hidden" name="wp_mail_master_timestamp" value="' + 
                Date.now() + '"/>');
            $(this).append(timestamp);
        }
    });

    // Basic spam protection validation only
    $('form').on('submit', function(e) {
        var $form = $(this);
        
        // Only check forms with email fields
        if ($form.find('input[type="email"]').length === 0) {
            return true;
        }

        // Check honeypot
        var honeypotValue = $form.find('input[name="wp_mail_master_hp"]').val();
        if (honeypotValue) {
            e.preventDefault();
            return false;
        }

        // Check submission timing
        var timestamp = parseInt($form.find('input[name="wp_mail_master_timestamp"]').val());
        var now = Date.now();
        var timeDiff = now - timestamp;

        if (timeDiff < 2000) {
            e.preventDefault();
            return false;
        }

        return true;
    });
}); 
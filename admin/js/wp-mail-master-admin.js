jQuery(document).ready(function ($) {
    function toggleServiceSettings() {
        var selectedService = $('input[name="wp_mail_master_options[email_service]"]:checked').val();
        console.log('Selected service:', selectedService); // For debugging
        $('.wp_mail_master_smtp_section, .wp_mail_master_brevo_section, .wp_mail_master_sendgrid_section').hide();
        $('.wp_mail_master_' + selectedService + '_section').show();
    }

    // Event listener for custom radio button divs
    $('.email-service-item').click(function () {
        var radioButton = $(this).find('input[type="radio"]');
        radioButton.prop('checked', true).trigger('change');
    });

    // Event listener for radio button change
    $('input[name="wp_mail_master_options[email_service]"]').change(toggleServiceSettings);
    toggleServiceSettings(); // Call this on page load to set the initial state

    $('.email-service-item').on('click', function () {
        $('.email-service-item').removeClass('selected');
        $(this).addClass('selected');
        $(this).find('input[type="radio"]').prop('checked', true);
    });

    // Event listener for the "Send Test Email" button
    $('#open-off-canvas').click(function () {
        $('#off-canvas').addClass('show');
    });

    $('#close-off-canvas').click(function () {
        $('#off-canvas').removeClass('show');
    });

    $('#send-test-email-button').click(function () {
        var emailAddress = $('#test-email-address').val();
        if (emailAddress) {
            $.ajax({
                url: wp_mail_master.ajax_url,
                type: 'POST',
                data: {
                    action: 'send_test_email',
                    test_email_address: emailAddress,
                    nonce: wp_mail_master.nonce
                },
                success: function (response) {
                    $('#test-email-result').html('<p>' + response.data.message + '</p>');
                },
                error: function (response) {
                    console.error('Error response:', response); // Debug statement
                    if (response.responseJSON && response.responseJSON.data) {
                        $('#test-email-result').html('<p>' + response.responseJSON.data.message + '</p>');
                    } else {
                        $('#test-email-result').html('<p>An unknown error occurred.</p>');
                    }
                }
            });
        }
    });
});


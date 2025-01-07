jQuery(document).ready(function ($) {
  // Email service selection functionality
  function toggleServiceSettings() {
    var selectedService = $('input[name="wp_mail_master_options[email_service]"]:checked').val();
    console.log("Selected service:", selectedService);
    $(".wp_mail_master_smtp_section, .wp_mail_master_brevo_section, .wp_mail_master_sendgrid_section, .wp_mail_master_smtpcom_section, .wp_mail_master_mailjet_section").hide();
    $(".wp_mail_master_" + selectedService + "_section").show();
  }

  // Event listener for custom radio button divs
  $(".email-service-item").click(function () {
    var radioButton = $(this).find('input[type="radio"]');
    radioButton.prop("checked", true).trigger("change");
  });

  // Event listener for radio button change
  $('input[name="wp_mail_master_options[email_service]"]').change(toggleServiceSettings);
  toggleServiceSettings();

  $(".email-service-item").on("click", function () {
    $(".email-service-item").removeClass("selected");
    $(this).addClass("selected");
    $(this).find('input[type="radio"]').prop("checked", true);
  });

  // Test email functionality
  $("#open-off-canvas").click(function () {
    $("#off-canvas").addClass("show");
  });

  $("#close-off-canvas").click(function () {
    $("#off-canvas").removeClass("show");
  });

  $("#send-test-email-button").click(function () {
    var emailAddress = $("#test-email-address").val();
    var selectedTemplate = $("#test-email-template").val();

    if (emailAddress) {
      $("#spinner").show();
      $("#test-email-address, #test-email-template, #send-test-email-button").prop("disabled", true);

      $.ajax({
        url: wp_mail_master.ajax_url,
        type: "POST",
        data: {
          action: "send_test_email",
          test_email_address: emailAddress,
          template_id: selectedTemplate,
          nonce: wp_mail_master.nonce,
        },
        success: function (response) {
          $("#spinner").hide();
          $("#test-email-address, #test-email-template, #send-test-email-button").prop("disabled", false);
          $("#test-email-result").html("<p class='success'>" + response.data.message + "</p>");
        },
        error: function (response) {
          $("#spinner").hide();
          $("#test-email-address, #test-email-template, #send-test-email-button").prop("disabled", false);
          console.error("Error response:", response);
          if (response.responseJSON && response.responseJSON.data) {
            $("#test-email-result").html("<p class='error'>" + response.responseJSON.data.message + "</p>");
          } else {
            $("#test-email-result").html("<p class='error'>An unknown error occurred.</p>");
          }
        },
      });
    }
  });
});

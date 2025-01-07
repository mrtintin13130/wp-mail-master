// admin/js/templates.js

jQuery(document).ready(function ($) {
  // Initialize color pickers
  $(".color-picker").wpColorPicker({
    change: debounce(function (event, ui) {
      updatePreview();
    }, 300),
  });

  // Initialize media uploader
  let mediaUploader;

  $(".media-upload-button").on("click", function (e) {
    e.preventDefault();

    if (!mediaUploader) {
      mediaUploader = wp.media({
        title: wpMailMasterTemplates.i18n.selectImage,
        button: {
          text: wpMailMasterTemplates.i18n.insertImage,
        },
        multiple: false,
      });

      mediaUploader.on("select", function () {
        const attachment = mediaUploader.state().get("selection").first().toJSON();
        const activeEditor = tinymce.activeEditor;

        if (activeEditor) {
          activeEditor.insertContent(`<img src="${attachment.url}" alt="${attachment.alt}" style="max-width: 100%; height: auto;">`);
          updatePreview();
        }
      });
    }

    mediaUploader.open();
  });

  // Initialize editors and bind change events
  ["header", "content", "footer"].forEach(function (section) {
    if (typeof tinymce !== "undefined") {
      const editor = tinymce.get("template_" + section);
      if (editor) {
        editor.on(
          "change",
          debounce(function () {
            updatePreview();
          }, 300)
        );

        // Add custom buttons to editor
        editor.addButton("variables", {
          text: "Variables",
          type: "menubutton",
          menu: Object.entries(wpMailMasterTemplates.available_vars).map(([variable, description]) => ({
            text: description,
            onclick: function () {
              editor.insertContent(variable);
            },
          })),
        });
      }
    }
  });

  // Handle template name and subject changes
  $(".template-name, .template-subject").on(
    "input",
    debounce(function () {
      updatePreview();
    }, 300)
  );

  // Generate preview HTML
  function generatePreviewHtml(template) {
    const previewData = {
      site_name: wpMailMasterTemplates.site_name,
      site_url: wpMailMasterTemplates.site_url,
      year: new Date().getFullYear(),
      date: new Date().toLocaleDateString(),
      email_title: template.subject,
      recipient_name: "John Doe",
      recipient_email: "example@test.com",
    };

    // Replace variables in content
    let processedSections = {};
    Object.entries(template.sections).forEach(([section, data]) => {
      let content = data.content;
      Object.entries(previewData).forEach(([key, value]) => {
        content = content.replace(new RegExp(`{${key}}`, "g"), value);
      });
      processedSections[section] = {
        ...data,
        content: content,
      };
    });

    return `
            <!DOCTYPE html>
            <html>
            <head>
                <meta charset="UTF-8">
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
                <title>${template.subject}</title>
                <style>
                    body { 
                        margin: 0; 
                        padding: 0; 
                        font-family: Arial, sans-serif;
                        line-height: 1.6;
                        color: #333;
                    }
                    .email-container { 
                        max-width: 600px; 
                        margin: 0 auto;
                        box-shadow: 0 0 10px rgba(0,0,0,0.1);
                    }
                    .email-section { 
                        padding: 20px; 
                        margin: 0;
                    }
                    img { 
                        max-width: 100%; 
                        height: auto; 
                    }
                    a { 
                        color: #0073aa; 
                        text-decoration: none; 
                    }
                    h1, h2, h3, h4, h5, h6 {
                        margin-top: 0;
                        line-height: 1.2;
                    }
                </style>
            </head>
            <body style="background-color: #f5f5f5; padding: 20px;">
                <div class="email-container">
                    <div class="email-section" style="background-color: ${processedSections.header.background_color}">
                        ${processedSections.header.content}
                    </div>
                    <div class="email-section" style="background-color: ${processedSections.content.background_color}">
                        ${processedSections.content.content}
                    </div>
                    <div class="email-section" style="background-color: ${processedSections.footer.background_color}">
                        ${processedSections.footer.content}
                    </div>
                </div>
            </body>
            </html>
        `;
  }

  // Handle sending test emails
  $(".send-test").on("click", function () {
    const email = prompt(wpMailMasterTemplates.i18n.enterTestEmail);
    if (email) {
      const button = $(this);
      button.prop("disabled", true);

      $.ajax({
        url: ajaxurl,
        type: "POST",
        data: {
          action: "wp_mail_master_send_test",
          email: email,
          template_id: $("#template-selector").val(),
          nonce: wpMailMasterTemplates.preview_nonce,
        },
        success: function (response) {
          if (response.success) {
            alert(wpMailMasterTemplates.i18n.testEmailSent);
          } else {
            alert(response.data.message);
          }
        },
        error: function () {
          alert(wpMailMasterTemplates.i18n.testEmailError);
        },
        complete: function () {
          button.prop("disabled", false);
        },
      });
    }
  });

  // Utility function for debouncing
  function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
      const later = () => {
        clearTimeout(timeout);
        func(...args);
      };
      clearTimeout(timeout);
      timeout = setTimeout(later, wait);
    };
  }

  // Initialize preview on page load
  updatePreview();
});

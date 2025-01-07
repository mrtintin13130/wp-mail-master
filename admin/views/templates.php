<?php
// admin/views/templates.php

if (!defined('ABSPATH')) {
    exit;
}

// Ensure template has required structure
$template = array_merge([
    'name' => '',
    'subject' => '',
    'content' => ''
], $template);
?>
<div class="wrap">
    <h1 class="wp-heading-inline"><?php _e('Email Templates', 'wp-mail-master'); ?></h1>
    <a href="?page=wp-mail-master-templates" class="page-title-action">
        <?php _e('Choose Template Design', 'wp-mail-master'); ?>
    </a>
    <hr class="wp-header-end">

    <div class="wp-mail-master-template-editor">
        <div style="margin-bottom: 20px;">
            <select id="template-selector" style="min-width: 200px;">
                <?php foreach ($templates as $id => $tmpl): ?>
                    <option value="<?php echo esc_attr($id); ?>" <?php selected($template_id, $id); ?>>
                        <?php echo esc_html($tmpl['name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <a href="?page=wp-mail-master-templates&template=new" class="button">
                <?php _e('Add New Template', 'wp-mail-master'); ?>
            </a>
        </div>

        <div style="display: flex; gap: 20px;">
            <!-- Editor -->
            <div style="flex: 1;">
                <form method="post" action="" id="template-form">
                    <?php wp_nonce_field('save_template', 'template_nonce'); ?>
                    <input type="hidden" name="template_id" value="<?php echo esc_attr($template_id); ?>">

                    <div style="margin-bottom: 20px;">
                        <input type="text" name="template[name]" value="<?php echo esc_attr($template['name']); ?>"
                            placeholder="<?php esc_attr_e('Template Name', 'wp-mail-master'); ?>" class="widefat">
                    </div>

                    <div style="margin-bottom: 20px;">
                        <input type="text" name="template[subject]"
                            value="<?php echo esc_attr($template['subject']); ?>"
                            placeholder="<?php esc_attr_e('Email Subject', 'wp-mail-master'); ?>" class="widefat">
                    </div>

                    <div style="margin-bottom: 20px;">
                        <?php
                        wp_editor(
                            $template['content'],
                            'template_content',
                            array(
                                'textarea_name' => 'template[content]',
                                'media_buttons' => true,
                                'textarea_rows' => 20,
                                'editor_height' => 400,
                                'teeny' => false,
                                'quicktags' => true
                            )
                        );
                        ?>
                    </div>

                    <div style="margin-bottom: 20px;">
                        <h3><?php _e('Available Variables', 'wp-mail-master'); ?></h3>
                        <div
                            style="display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 10px;">
                            <?php foreach ($this->get_available_variables() as $var => $desc): ?>
                                <div
                                    style="background: #f8f9fa; padding: 8px; border: 1px solid #dee2e6; border-radius: 4px;">
                                    <code style="cursor: pointer;"
                                        class="template-variable"><?php echo esc_html($var); ?></code>
                                    <br>
                                    <small><?php echo esc_html($desc); ?></small>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="submit-wrapper">
                        <?php submit_button(__('Save Template', 'wp-mail-master')); ?>
                        <?php
                        // Debugging
                        echo "<!-- Template ID: " . esc_html($template_id) . " -->";
                        ?>
                        <?php
                        // Show delete button for custom templates only
                        $template_prefix = substr($template_id, 0, 9); // Get template ID prefix
                        $is_custom = ($template_prefix === 'selected_' || $template_prefix === 'custom_');

                        if ($is_custom):
                            ?>
                            <button type="button" class="button delete-template" style="margin-left: 10px; color: #a00;">
                                <?php _e('Delete Template', 'wp-mail-master'); ?>
                            </button>
                        <?php endif; ?>
                    </div>
                </form>
            </div>

            <!-- Preview -->
            <div style="flex: 0 0 400px;">
                <div style="position: sticky; top: 32px;">
                    <h3><?php _e('Preview', 'wp-mail-master'); ?></h3>
                    <div id="template-preview"
                        style="border: 1px solid #ddd; background: #fff; padding: 20px; margin-bottom: 10px; max-height: 600px; overflow: auto;">
                    </div>
                    <button type="button" class="button" id="send-test">
                        <?php _e('Send Test Email', 'wp-mail-master'); ?>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    jQuery(document).ready(function ($) {
        var editor = tinymce.get('template_content');

        function updatePreview() {
            var content = editor ? editor.getContent() : $('#template_content').val();
            var subject = $('input[name="template[subject]"]').val();

            // Replace variables in both subject and content
            var variables = {
                '{site_name}': '<?php echo esc_js(get_bloginfo('name')); ?>',
                '{site_url}': '<?php echo esc_js(get_bloginfo('url')); ?>',
                '{email_title}': subject,
                '{year}': '<?php echo date('Y'); ?>',
                '{date}': '<?php echo date_i18n(get_option('date_format')); ?>'
            };

            // Process subject variables
            Object.keys(variables).forEach(function (key) {
                subject = subject.replace(new RegExp(key.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'), 'g'), variables[key]);
            });

            // Process content variables
            content = content
                .replace(/{site_name}/g, variables['{site_name}'])
                .replace(/{site_url}/g, variables['{site_url}'])
                .replace(/{email_title}/g, subject) // Use the processed subject here
                .replace(/{year}/g, variables['{year}'])
                .replace(/{date}/g, variables['{date}'])
                .replace(/{email_content}/g, '<p>This is where your email content will appear.</p>');

            // Update preview with processed content
            $('#template-preview').html(content);
        }

        setTimeout(updatePreview, 1000);

        $('input[name="template[subject]"]').on('input', _.debounce(updatePreview, 500));
        if (editor) {
            editor.on('change', _.debounce(updatePreview, 500));
        }

        $('.template-variable').click(function () {
            var variable = $(this).text();
            if (editor) {
                editor.insertContent(variable);
            } else {
                var textarea = $('#template_content');
                var pos = textarea[0].selectionStart;
                var content = textarea.val();
                textarea.val(content.substring(0, pos) + variable + content.substring(pos));
            }
        });

        $('#template-selector').change(function () {
            window.location.href = '?page=wp-mail-master-templates&template=' + $(this).val();
        });

        // Handle form submission
        $('#template-form').submit(function (e) {
            e.preventDefault();
            var form = $(this);
            var submitButton = form.find('input[type="submit"]');
            submitButton.prop('disabled', true);

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: form.serialize() + '&action=save_template',
                success: function (response) {
                    if (response.success) {
                        // Show success message
                        // alert(response.data.message);

                        // Optionally refresh the page
                        window.location.reload();
                    } else {
                        alert(response.data.message || '<?php _e("Failed to save template.", "wp-mail-master"); ?>');
                    }
                },
                error: function () {
                    alert('<?php _e("Failed to save template.", "wp-mail-master"); ?>');
                },
                complete: function () {
                    submitButton.prop('disabled', false);
                }
            });
        });

        $('.delete-template').click(function () {
            if (confirm('<?php _e("Are you sure you want to delete this template?", "wp-mail-master"); ?>')) {
                var templateId = $('input[name="template_id"]').val();
                $.post(ajaxurl, {
                    action: 'delete_template',
                    template_id: templateId,
                    _ajax_nonce: '<?php echo wp_create_nonce("delete_template"); ?>'
                }, function (response) {
                    if (response.success) {
                        window.location.href = '?page=wp-mail-master-templates';
                    }
                });
            }
        });

        $('#send-test').click(function () {
            var email = prompt('<?php _e("Enter email address for test:", "wp-mail-master"); ?>');
            if (email) {
                var button = $(this);
                button.prop('disabled', true);

                $.post(ajaxurl, {
                    action: 'send_test_email',
                    email: email,
                    template_id: $('input[name="template_id"]').val(),
                    _ajax_nonce: '<?php echo wp_create_nonce("send_test_email"); ?>'
                }, function (response) {
                    alert(response.success ?
                        '<?php _e("Test email sent successfully!", "wp-mail-master"); ?>' :
                        '<?php _e("Failed to send test email.", "wp-mail-master"); ?>');
                    button.prop('disabled', false);
                });
            }
        });
    });
</script>
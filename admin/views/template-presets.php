<?php
// admin/views/template-presets.php
if (!defined('ABSPATH')) {
    exit;
}

$selected_template = get_option('wp_mail_master_selected_template', 'simple');
$templates = get_option('wp_mail_master_templates', array());
?>

<div class="wrap">
    <h1><?php _e('Email Templates', 'wp-mail-master'); ?></h1>

    <div class="wp-mail-master-template-presets">
        <div class="template-actions">
            <a href="#TB_inline?width=800&height=600&inlineId=preset-templates-modal"
                class="button button-primary thickbox">
                <?php _e('Use Preset Template', 'wp-mail-master'); ?>
            </a>
            <a href="?page=wp-mail-master-templates&template=new" class="button">
                <?php _e('Create Custom Template', 'wp-mail-master'); ?>
            </a>
        </div>

        <div class="template-list">
            <h2><?php _e('My Templates', 'wp-mail-master'); ?></h2>

            <?php if (empty($templates)): ?>
                <div class="notice notice-info">
                    <p><?php _e('You haven\'t created any custom templates yet. The default template is currently active.', 'wp-mail-master'); ?>
                    </p>
                    <p><?php _e('Get started by using a preset template or creating your own!', 'wp-mail-master'); ?></p>
                </div>
            <?php else: ?>
                <div class="template-grid">
                    <?php foreach ($templates as $id => $template): ?>
                        <div class="template-card <?php echo $selected_template === $id ? 'is-selected' : ''; ?>">
                            <div class="template-preview">
                                <iframe
                                    srcdoc="<?php echo esc_attr($this->prepare_preview_content($template['content'])); ?>"></iframe>
                            </div>
                            <div class="template-info">
                                <h3><?php echo esc_html($template['name']); ?></h3>
                                <div class="template-actions">
                                    <a href="?page=wp-mail-master-templates&template=<?php echo esc_attr($id); ?>"
                                        class="button">
                                        <?php _e('Edit', 'wp-mail-master'); ?>
                                    </a>
                                    <?php if ($selected_template !== $id): ?>
                                        <button class="button button-primary select-template"
                                            data-template-id="<?php echo esc_attr($id); ?>">
                                            <?php _e('Set as Default', 'wp-mail-master'); ?>
                                        </button>
                                        <button class="button button-link-delete delete-template"
                                            data-template-id="<?php echo esc_attr($id); ?>">
                                            <?php _e('Delete', 'wp-mail-master'); ?>
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Preset Templates Modal -->
<div id="preset-templates-modal" style="display:none;">
    <h2><?php _e('Select a Preset Template', 'wp-mail-master'); ?></h2>
    <div class="preset-templates-grid">
        <?php foreach ($this->default_template as $id => $preset): ?>
            <div class="preset-template-card">
                <div class="preset-preview">
                    <iframe srcdoc="<?php echo esc_attr($this->prepare_preview_content($preset['content'])); ?>"></iframe>
                </div>
                <div class="preset-info">
                    <h3><?php echo esc_html($preset['name']); ?></h3>
                    <a href="?page=wp-mail-master-templates&template=new&preset=<?php echo esc_attr($id); ?>"
                        class="button button-primary">
                        <?php _e('Use This Template', 'wp-mail-master'); ?>
                    </a>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<style>
    .template-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(400px, 1fr));
        gap: 20px;
        margin-top: 20px;
    }

    .template-card {
        border: 1px solid #ddd;
        border-radius: 4px;
        overflow: hidden;
        transition: box-shadow 0.2s;
    }

    .template-card:hover {
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    }

    .template-card.is-selected {
        border-color: #2271b1;
    }

    .template-preview {
        height: 300px;
        overflow: hidden;
    }

    .template-preview iframe {
        width: 100%;
        height: 400px;
        border: none;
        transform: scale(0.8);
        transform-origin: top center;
    }

    .template-info {
        padding: 15px;
        background: #f8f9fa;
        border-top: 1px solid #ddd;
    }

    .template-actions {
        display: flex;
        gap: 10px;
        margin-top: 10px;
    }

    .preset-templates-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
        gap: 20px;
        margin-top: 20px;
    }

    .preset-template-card {
        border: 1px solid #ddd;
        border-radius: 4px;
        overflow: hidden;
    }

    .preset-preview {
        height: 200px;
        overflow: hidden;
    }

    .preset-preview iframe {
        width: 100%;
        height: 300px;
        border: none;
        transform: scale(0.6);
        transform-origin: top center;
    }

    .preset-info {
        padding: 15px;
        background: #f8f9fa;
        border-top: 1px solid #ddd;
    }
</style>

<script>
    jQuery(document).ready(function ($) {
        $('.select-template').click(function (e) {
            e.preventDefault();
            var templateId = $(this).data('template-id');
            var button = $(this);

            // Disable button while processing
            button.prop('disabled', true);

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'select_template',
                    template_id: templateId,
                    nonce: '<?php echo wp_create_nonce("select_template_nonce"); ?>'
                },
                success: function (response) {
                    if (response.success) {
                        console.log('Success:', response);
                        window.location.reload();
                    } else {
                        console.error('Error:', response);
                        alert(response.data.message || '<?php _e("Failed to select template.", "wp-mail-master"); ?>');
                    }
                },
                error: function (xhr, status, error) {
                    console.error('Ajax error:', { xhr: xhr, status: status, error: error });
                    alert('<?php _e("An error occurred while processing your request.", "wp-mail-master"); ?>');
                },
                complete: function () {
                    button.prop('disabled', false);
                }
            });
        });
        // Handle template deletion
        $('.delete-template').click(function (e) {
            e.preventDefault();

            const templateId = $(this).data('template-id');
            const templateCard = $(this).closest('.template-card');

            if (!confirm(wpMailMasterTemplates.i18n.confirmDelete)) {
                return;
            }

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'delete_template',
                    template_id: templateId,
                    _ajax_nonce: wpMailMasterTemplates.nonce
                },
                beforeSend: function () {
                    templateCard.css('opacity', '0.5');
                },
                success: function (response) {
                    if (response.success) {
                        templateCard.fadeOut(400, function () {
                            $(this).remove();
                            // If no templates left, reload to show empty state
                            if ($('.template-card').length === 0) {
                                window.location.reload();
                            }
                        });
                    } else {
                        alert(response.data.message || wpMailMasterTemplates.i18n.deleteError);
                        templateCard.css('opacity', '1');
                    }
                },
                error: function () {
                    alert(wpMailMasterTemplates.i18n.deleteError);
                    templateCard.css('opacity', '1');
                }
            });
        });
    });
</script>
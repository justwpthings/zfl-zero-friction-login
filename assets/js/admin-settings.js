jQuery(document).ready(function($) {
    var i18n = window.zflAdminI18n || {};
    var chooseLogoTitle = i18n.choose_logo_title || 'Choose Logo';
    var useImageText = i18n.use_image || 'Use this image';
    var changeLogoText = i18n.change_logo || 'Change Logo';
    var uploadLogoText = i18n.upload_logo || 'Upload Logo';
    var removeLogoText = i18n.remove_logo || 'Remove Logo';

    $('.zfl-color-picker').wpColorPicker();

    $('#zfl_redirect_after_login').on('change', function() {
        if ($(this).val() === 'custom_url') {
            $('#custom_login_url_row').show();
        } else {
            $('#custom_login_url_row').hide();
        }
    });

    $('#zfl_redirect_after_logout').on('change', function() {
        if ($(this).val() === 'custom_url') {
            $('#custom_logout_url_row').show();
        } else {
            $('#custom_logout_url_row').hide();
        }
    });

    $('#zfl_enable_smtp').on('change', function() {
        if ($(this).is(':checked')) {
            $('#smtp_settings').slideDown();
        } else {
            $('#smtp_settings').slideUp();
        }
    });

    $('#zfl_force_custom_login').on('change', function() {
        if ($(this).is(':checked')) {
            $('#custom_login_page_row').show();
            $('#custom_login_page_url_row').show();
        } else {
            $('#custom_login_page_row').hide();
            $('#custom_login_page_url_row').hide();
        }
    });

    var mediaUploader;

    $('#zfl_upload_logo_button').on('click', function(e) {
        e.preventDefault();

        if (mediaUploader) {
            mediaUploader.open();
            return;
        }

        mediaUploader = wp.media({
            title: chooseLogoTitle,
            button: {
                text: useImageText
            },
            multiple: false
        });

        mediaUploader.on('select', function() {
            var attachment = mediaUploader.state().get('selection').first().toJSON();
            $('#zfl_logo_id').val(attachment.id);
            $('#zfl_logo_preview').html('<img src="' + attachment.url + '" style="max-width: 200px; height: auto;">');
            $('#zfl_upload_logo_button').text(changeLogoText);

            if ($('#zfl_remove_logo_button').length === 0) {
                $('#zfl_upload_logo_button').after(' <button type="button" class="button" id="zfl_remove_logo_button">' + removeLogoText + '</button>');
            }
        });

        mediaUploader.open();
    });

    $(document).on('click', '#zfl_remove_logo_button', function(e) {
        e.preventDefault();
        $('#zfl_logo_id').val('');
        $('#zfl_logo_preview').html('');
        $('#zfl_upload_logo_button').text(uploadLogoText);
        $(this).remove();
    });
});

jQuery(document).ready(function ($) {
    $('#add_custom_link_logo').on('click', function () {
        var customLinksLogosSection = $('.custom-link-logo-section').first().clone();
        var index = $('.custom-link-logo-section').length;

        customLinksLogosSection.find('input[name^="wc_social_share_options[custom_links_logos]"]').each(function () {
            var name = $(this).attr('name').replace(/\[\d+\]/, '[' + index + ']');
            $(this).attr('name', name).val('');
        });

        customLinksLogosSection.find('.custom-logo-preview').attr('src', '').hide();
        customLinksLogosSection.find('.upload_logo_button').data('index', index);
        customLinksLogosSection.find('.remove_custom_link_logo').show();

        customLinksLogosSection.insertBefore('#add_custom_link_logo');
    });

    $(document).on('click', '.upload_logo_button', function (e) {
        e.preventDefault();
        var button = $(this);
        var index = button.data('index');

        var file_frame = wp.media.frames.file_frame = wp.media({
            title: 'Select or Upload an Image',
            button: {
                text: 'Use this image'
            },
            multiple: false
        });

        file_frame.on('select', function () {
            var attachment = file_frame.state().get('selection').first().toJSON();
            button.prev('input[type="hidden"]').val(attachment.url);
            button.next('.custom-logo-preview').attr('src', attachment.url).show();
        });

        file_frame.open();
    });

    $(document).on('click', '.remove_custom_link_logo', function () {
        $(this).closest('.custom-link-logo-section').remove();
    });
});

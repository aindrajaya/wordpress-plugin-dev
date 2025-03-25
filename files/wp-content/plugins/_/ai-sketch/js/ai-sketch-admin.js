jQuery(document).ready(function($) {
    $('#aisg_show_api_key').on('change', function() {
        const $apiKeyInput = $('#aisg_api_key');
        if ($(this).is(':checked')) {
            $apiKeyInput.attr('type', 'text');
        } else {
            $apiKeyInput.attr('type', 'password');
        }
    });
});
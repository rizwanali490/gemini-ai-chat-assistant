/**
 * Admin-specific JavaScript for the Gemini AI Chat Assistant plugin.
 * Handles the API connection test functionality.
 *
 * @package Gemini_AI_Chat_Assistant
 * @subpackage Gemini_AI_Chat_Assistant/assets/js
 * @author Rizwan ilyas <rizwan@rizwandevs.com>
 */

jQuery(document).ready(function($) {
    // Ensure the gacaAdmin object is available.
    if (typeof gacaAdmin === 'undefined') {
        console.error('gacaAdmin object not found. Localization failed.');
        return;
    }

    $('#gaca-test-api-connection').on('click', function(e) {
        e.preventDefault();

        var $button = $(this);
        var $statusSpan = $('#gaca-api-test-status');
        var originalButtonText = $button.text();

        // Show loading state.
        $button.prop('disabled', true).text(gacaAdmin.testing_message);
        $statusSpan.removeClass('success error').text('');

        $.ajax({
            url: gacaAdmin.ajax_url,
            type: 'POST',
            data: {
                action: 'gaca_test_api_connection', // The AJAX action hook.
                nonce: gacaAdmin.nonce,             // Security nonce.
            },
            success: function(response) {
                if (response.success) {
                    $statusSpan.addClass('success').text(gacaAdmin.success_message);
                } else {
                    $statusSpan.addClass('error').text(gacaAdmin.failure_message + (response.data.message || 'Unknown error.'));
                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
                console.error('AJAX Error:', textStatus, errorThrown, jqXHR.responseText);
                $statusSpan.addClass('error').text(gacaAdmin.failure_message + (jqXHR.responseJSON && jqXHR.responseJSON.data && jqXHR.responseJSON.data.message ? jqXHR.responseJSON.data.message : 'Server error. Check console.'));
            },
            complete: function() {
                // Restore button state after request.
                $button.prop('disabled', false).text(originalButtonText);
            }
        });
    });
});
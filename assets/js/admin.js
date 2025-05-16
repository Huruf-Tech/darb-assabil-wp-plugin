jQuery(document).ready(function($) {
    // Modal handling
    var $modal = $('#json-modal');
    var $span = $('.close');
    var currentOrderId = null;
    var currentType = null;

    // View data button handler
    $('.view-data').on('click', function() {
        var content = $(this).data('content');
        var type = $(this).data('type');
        // Fix order ID selection
        currentOrderId = $(this).closest('tr').data('order-id');
        currentType = type;
        
        try {
            // Parse the content if it's a string
            var jsonContent = typeof content === 'string' ? JSON.parse(content) : content;
            
            // Format the JSON with proper indentation
            var formattedContent = JSON.stringify(jsonContent, null, 2);
            
            // Update modal title based on type
            var title = type === 'payload' ? 'API Payload' : 'API Response';
            $('#json-modal .modal-title').text(title);
            
            // Update content
            $('#json-content').val(formattedContent);
            
            // Show/hide save button based on type
            $('.save-json').toggle(type === 'payload');
            
            // Show modal
            $modal.css('display', 'block');

            // Debug logging
            console.log('Current Order ID:', currentOrderId);
            console.log('Current Type:', currentType);
            console.log('Content:', content);
        } catch (e) {
            console.error('Error parsing JSON:', e);
            console.log('Content:', content);
            alert('Error displaying data. Please check browser console for details.');
        }
    });

    // Save changes button handler
    $('.save-json').on('click', function() {
        try {
            // Validate JSON
            var jsonContent = $('#json-content').val();
            var newPayload = JSON.parse(jsonContent);

            console.log('JSON:', jsonContent);
            console.log('Parsed JSON:', newPayload);
            console.log('Current Order ID:', currentOrderId);
            console.log('Current Type:', currentType);
            
            // Save changes via AJAX
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'save_darb_assabil_payload',
                    order_id: currentOrderId,
                    payload: jsonContent, // Send the raw JSON string
                    nonce: darbAssabilAdmin.payloadNonce
                },
                success: function(response) {
                    if (response.success) {
                        alert('Payload saved successfully');
                        $modal.css('display', 'none');
                        location.reload();
                    } else {
                        alert('Error saving payload: ' + (response.data || 'Unknown error'));
                    }
                },
                error: function(xhr, status, error) {
                    console.error('AJAX Error:', error);
                    alert('Error saving payload: ' + error);
                }
            });
        } catch (e) {
            alert('Invalid JSON format: ' + e.message);
        }
    });

    // Close modal handlers
    $span.on('click', function() {
        $modal.css('display', 'none');
    });

    $(window).on('click', function(e) {
        if ($(e.target).is($modal)) {
            $modal.css('display', 'none');
        }
    });

    // Webhook functions
    window.generateSecret = function() {
        // Generate a random string of 32 characters
        const chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        let secret = '';
        for (let i = 0; i < 32; i++) {
            secret += chars.charAt(Math.floor(Math.random() * chars.length));
        }
        document.querySelector('input[name="darb_assabil_webhook_secret"]').value = secret;
    };

    window.toggleDetails = function(button) {
        const details = button.nextElementSibling;
        if (details.style.display === 'none') {
            details.style.display = 'block';
            button.textContent = darbAssabilAdmin.hideDetailsText;
        } else {
            details.style.display = 'none';
            button.textContent = darbAssabilAdmin.viewDetailsText;
        }
    };
});
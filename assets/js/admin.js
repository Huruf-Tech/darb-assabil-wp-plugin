jQuery(document).ready(function($) {
    // Add the plugin page class selector to all jQuery selectors
    var $pluginPage = $('.toplevel_page_darb-assabil-settings');
    
    // Modal handling
    var $modal = $pluginPage.find('.darb-modal');
    var $span = $pluginPage.find('.darb-close');
    var currentOrderId = null;
    var currentType = null;

    // View data button handler
    $pluginPage.find('.view-data').on('click', function() {
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
            $pluginPage.find('.save-json').toggle(type === 'payload');
            
            // Show modal
            $modal.css('display', 'block');

        } catch (e) {
            console.error('Error parsing JSON:', e);
            alert('Error displaying data. Please check browser console for details.');
        }
    });

    // Save changes button handler
    $pluginPage.find('.save-json').on('click', function() {
        try {
            // Validate JSON
            var jsonContent = $('#json-content').val();
            
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

    // Retry order button handler
    $pluginPage.find('.retry-order').on('click', function() {
        var $button = $(this);
        var orderId = $button.data('order-id');
        var nonce = $button.data('nonce');
        
        // Disable button and show loading state
        $button.prop('disabled', true).text('Processing...');
        
        // Make AJAX call
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'retry_darb_assabil_order',
                order_id: orderId,
                nonce: nonce
            },
            success: function(response) {
                if (response.success) {
                    // Show success message and reload
                    if (response.data.includes("failed")) {
                        alert('Order retry failed: ' + response.data);
                        $button.prop('disabled', false).text('Retry');
                    } else {
                        alert('Order created successful');
                        location.reload();
                    }
                } else {
                    // Show error and reset button
                    alert('Error retrying order: ' + (response.data || 'Unknown error'));
                    $button.prop('disabled', false).text('Retry');
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX Error:', error);
                alert('Error retrying order: ' + error);
                $button.prop('disabled', false).text('Retry');
            }
        });
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

    // Bulk actions handler
    $pluginPage.find('#doaction').on('click', function(e) {
        e.preventDefault();
        
        var action = $('select[name="bulk-action"]').val();
        if (action !== 'retry') {
            return;
        }

        var selectedOrders = $('input[name="order[]"]:checked').map(function() {
            return $(this).val();
        }).get();

        var selectedOrdersCount = selectedOrders.length;

        if (selectedOrdersCount === 0) {
            alert('Please select at least one order to retry');
            return;
        }

        if (!confirm('Are you sure you want to retry ' + selectedOrdersCount + ' orders?')) {
            return;
        }

        var processed = 0;
        var failed = 0;
        var $loader = $pluginPage.find('.darb-loader-overlay');
        var $progress = $pluginPage.find('.darb-loader-progress');
        
        // Show loader
        $loader.css('display', 'flex');

        // Process orders sequentially
        function processNext(orders) {
            if (orders.length === 0) {
                var message = 'Completed retrying orders.\n';
                if (processed > 0) message += 'Successful: ' + processed + '\n';
                if (failed > 0) message += 'Failed: ' + failed + '\n';
                if (failed > 0) message += 'Please check the orders list for details.';
                
                // Hide loader and show results
                $loader.hide();
                alert(message);
                location.reload();
                return;
            }

            var orderId = orders.shift();
            var total = selectedOrdersCount;
            var current = processed + failed + 1;
            
            // Update progress text
            $progress.text('Processing order ' + current + ' of ' + total);
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'retry_darb_assabil_order',
                    order_id: orderId,
                    nonce: darbAssabilAdmin.retryNonce,
                    is_bulk: true
                },
                success: function(response) {
                    if (response.success) {
                        if (response.data.includes("failed")) {
                            failed++;
                            console.error('Failed to retry order #' + orderId + ': ' + response.data);
                        } else {
                            processed++;
                        }
                    } else {
                        failed++;
                        console.error('Failed to retry order #' + orderId + ': ' + response.data);
                    }
                    processNext(orders);
                },
                error: function(xhr, status, error) {
                    failed++;
                    console.error('Ajax error for order #' + orderId + ': ' + error);
                    processNext(orders);
                }
            });
        }

        processNext(selectedOrders);
    });
});
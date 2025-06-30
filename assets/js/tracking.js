jQuery(function($){
    // Add Enter key support
    $('#darb-assabil-tracking-input').on('keypress', function(e){
        if(e.which === 13){
            $('#darb-assabil-tracking-btn').trigger('click');
            return false;
        }
    });

    // Remove timeline on close
    $(document).on('click', '.close-btn', function(){
        $(this).closest('.tracking-container').remove();
        $('#darb-assabil-tracking-result').html('');
        $('#darb-assabil-tracking-input').val('').focus();
    });

    $('#darb-assabil-tracking-btn').on('click', function(){
        var $btn = $(this);
        var ref = $('#darb-assabil-tracking-input').val().trim();
        var $result = $('#darb-assabil-tracking-result');
        
        if(!ref) { 
            $result.html('<div class="darb-assabil-error">Please enter a tracking ID.</div>'); 
            return; 
        }
        
        $btn.prop('disabled', true).text('Tracking...');
        $result.html('<div class="darb-assabil-loading">Checking status...</div>');
        
        $.post(darbAssabilTracking.ajax_url, {
            action: 'darb_assabil_tracking',
            nonce: darbAssabilTracking.nonce,
            reference: ref
        }, function(response){
            if(response.success) {
                $result.html(response.data);
            } else {
                $result.html('<div class="darb-assabil-error">' + response.data + '</div>');
            }
        }).always(function() {
            $btn.prop('disabled', false).text('Track');
        });
    });
});
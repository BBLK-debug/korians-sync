jQuery(function($){
    // تغییر تب‌ها
    $('.nav-tab').on('click', function(e){
        e.preventDefault();
        $('.nav-tab').removeClass('nav-tab-active');
        $(this).addClass('nav-tab-active');
        $('.syncmaster-tab-content').hide();
        $($(this).attr('href')).show();
    });

    // تست اتصال
    $('#syncmaster-test-btn').on('click', function(){
        $('#syncmaster-test-result').text('⏳ در حال بررسی...');
        $.post(SyncMasterAjax.ajax_url, {
            action: 'syncmaster_test_connection',
            _ajax_nonce: SyncMasterAjax.nonce
        }, function(res){
            $('#syncmaster-test-result').text(res.data ? res.data.message : '❌ خطا در ارتباط.');
        });
    });

    // ارسال به همه سایت‌ها
    $('#syncmaster-send-all').on('click', function(){
        $('#syncmaster-sync-result').text('⏳ در حال ارسال...');
        $.post(SyncMasterAjax.ajax_url, {
            action: 'syncmaster_send_products',
            mode: 'all',
            _ajax_nonce: SyncMasterAjax.nonce
        }, function(res){
            $('#syncmaster-sync-result').text(res.data.message);
        });
    });

    // ارسال به سایت خاص
    $('#syncmaster-send-single').on('click', function(){
        const site = $('#syncmaster-child-select').val();
        if(!site) return alert('لطفاً یک سایت انتخاب کنید.');
        $('#syncmaster-sync-result').text('⏳ در حال ارسال...');
        $.post(SyncMasterAjax.ajax_url, {
            action: 'syncmaster_send_products',
            mode: 'single',
            site: site,
            _ajax_nonce: SyncMasterAjax.nonce
        }, function(res){
            $('#syncmaster-sync-result').text(res.data.message);
        });
    });
});

(function ($) {
    $(window).on('load', function () {
        emt_start_repeator();
        emt_connect_site();
        emt_disconnect_site();
        emt_sync_site();
        emt_api_keys();
        emt_check_repeator_count();
        emt_integrations_settings_tabs();
        emt_integrations_settings();
    });

    function emt_start_repeator() {
        $(document).on('click', '.emt-plus', function () {
            var repeator_html = $('.emt-repeator-div').html();
            $('.emt-activation-section').append(repeator_html);
            var repeator_count = parseInt($('.emt-activation-section').attr('data-count'));
            repeator_count += 1;
            $('.emt-activation-section').attr('data-count', repeator_count);
            emt_check_repeator_count();
        });
        $(document).on('click', '.emt-minus', function () {
            var repeator_count = parseInt($('.emt-activation-section').attr('data-count'));
            if (repeator_count == 1) {
                // DO nothing, one must be present
            }
            else {
                var $this = $(this);
                var api_key = $this.parent().parent().find('.api_key').val();
                var api_secret_key = $this.parent().parent().find('.api_secret_key').val();
                if (api_key != '' && api_secret_key != '') {
                    var r = confirm("Are You Sure To Remove The Site?");
                    if (r == true) {
                        emt_send_ajax('site_disconnect', $this);
                    }
                }
                else {
                    $this.parent().parent().parent('.emt-repeator-element').remove();
                    repeator_count -= 1;
                    $('.emt-activation-section').attr('data-count', repeator_count);
                    emt_check_repeator_count();
                }
            }

        });
    }

    function emt_connect_site() {
        $(document).on('click', '.emt-connect-site', function () {
            var $this = $(this);
            emt_send_ajax('site_connect', $this);
        });
    }

    function emt_disconnect_site() {
        $(document).on('click', '.emt-disconnect-site', function () {
            var r = confirm("Are You Sure To Remove The Site?");
            if (r == true) {
                var $this = $(this);
                emt_send_ajax('site_disconnect', $this);
            }
        });
    }

    function emt_sync_site() {
        $(document).on('click', '.emt-sync-site', function () {
            var $this = $(this);
            emt_send_ajax('site_sync', $this);
        });
    }

    function emt_send_ajax(type, $this) {
        var api_key = $this.parent().parent('.emt-body-name').find('.api_key').val();
        var api_secret_key = $this.parent().parent('.emt-body-name').find('.api_secret_key').val();
        if ((api_key != '' && api_secret_key != '') && (typeof api_key != 'undefined' && typeof api_secret_key != 'undefined')) {
            var data = {
                "action": "emt_soc_ajax_operations",
                "type": type,
                "api_key": api_key,
                "api_secret_key": api_secret_key
            };
            $this.siblings('.spinner').addClass('is-active');
            $.post(ajaxurl, data, function (response) {
                $('.emt-notice').remove();
                if (type == 'site_connect') {
                    if (response.status == '1') {
                        $this.parent('.body-name-actions').html(response.html);
                        $('.nav-tab-wrapper').prepend('<div class="updated notice emt-notice"><p>' + response.message + '</p></div>');
                    }
                    if (response.status == '0') {
                        $('.nav-tab-wrapper').prepend('<div class="error notice emt-notice"><p>' + response.message + '</p></div>');
                    }
                    emt_check_repeator_count();
                }
                if (type == 'site_disconnect') {
                    if (response.status == '1') {
                        $this.parent('.body-name-actions').html(response.html);
                        $('.nav-tab-wrapper').prepend('<div class="updated notice emt-notice"><p>' + response.message + '</p></div>');
                        $('input[value="' + api_key + '"]').val('');
                        $('input[value="' + api_secret_key + '"]').val('');
                    }
                    if (response.status == '0') {
                        $('.nav-tab-wrapper').prepend('<div class="error notice emt-notice"><p>' + response.message + '</p></div>');
                        if(typeof response.html !='undefined'){
                            $this.parent('.body-name-actions').html(response.html);
                        }
                        $('input[value="' + api_key + '"]').val('');
                        $('input[value="' + api_secret_key + '"]').val('');
                    }
                    emt_check_repeator_count();
                }
                if (type == 'site_sync') {
                    if (response.status == '1') {
                        $('.nav-tab-wrapper').prepend('<div class="updated notice emt-notice"><p>' + response.message + '</p></div>');
                    }
                    if (response.status == '0') {
                        $('.nav-tab-wrapper').prepend('<div class="error notice emt-notice"><p>' + response.message + '</p></div>');
                    }
                }
                $this.siblings('.spinner').removeClass('is-active');
            });
        }
    }

    function emt_api_keys() {
        $(document).on('focusout', '.api_key, .api_secret_key', function () {
            $(this).attr('value', $(this).val());
        });
    }

    function emt_check_repeator_count() {
        var repeator_count = $('.emt-activation-section').attr('data-count');
        if (repeator_count == '1') {
            $('.emt-minus').hide();
        }
        else {
            $('.emt-minus').show();
        }
    }

    function emt_integrations_settings_tabs(){
        $(document).on('click', '.emt_tablinks', function () {
            var $this = $(this);
            if($this.hasClass('active')){

            }
            else{
                $('.emt_tablinks').removeClass('active');
                $('.emt_tabcontent').removeClass('active');

                $this.addClass('active');
                var tab_to_make_active = '#'+$this.attr('data-section');
                $(tab_to_make_active).addClass('active');
            }
        });
    }

    function emt_integrations_settings(){
        // $('.js-example-basic-multiple').each(function(){
            var $this = $(this);
            $('.js-example-basic-multiple').select2({
            // $this.select2({
                minimumInputLength: 2,
                placeholder: "Select Products",
                multiple: true,
                ajax: {
                    url: ajaxurl,
                    dataType: 'json',
                    type: "POST",
                    delay: 250,
                    data: function (term) {
                        return {
                            search_term: term,
                            action: 'emt_soc_ajax_operations',
                            type: 'emt_exclude_product_search',
                            emt_posttype: $('.emt_tabcontent.active').find('.emt_posttype').val(),
                        };
                    },
                    results: function (data) {
                        return {
                            results: $.map(data, function (item) {
                                return {
                                    text: item.text,
                                    id: item.id
                                }
                            })
                        };
                    },
                },
            });
        // });


        $(document).on('submit','.emt-forms',function () {
            var $this = $(this);
            var setting_type = $('.emt_tabcontent.active').find('.emt_setting_type').val();
            var integration_slug = $('.emt_tabcontent.active').find('.emt_integration_slug').val();
            var form_data = new FormData($this[0]);
            form_data.append('action', 'emt_soc_ajax_operations');
            form_data.append('type', setting_type);
            form_data.append('emt_integration_slug', integration_slug);
            $('.spinner').addClass('is-active');
            $('.emt-notice').remove();
            jQuery.ajax({
                url: ajaxurl,
                type: 'POST',
                data: form_data,
                contentType: false,
                cache: false,
                processData: false,
                async: false,
                success: function (response) {
                    if (response.status == '1') {
                        $('.nav-tab-wrapper').prepend('<div class="updated notice emt-notice"><p>' + response.message + '</p></div>');
                    }
                    $('.spinner').removeClass('is-active');
                }
            });

            return false;
        });
    }

})(jQuery);
var a = {
    \u0440: "a",

    \u010D: "c",

    \u0119: "e",

    \u0117: "e",

    \u012F: "i",

    \u0161: "s",

    \u0173: "u",

    \u016B: "u",

    \u017E: "z"

};

function transliterate(word) {

    return word.split('').map(function (char) {

        return a[char] || char;

    }).join("");

}

function initCheckoutWatcher() {

    // console.log("Window multi" + window.multiparcels_selected_location);
    // console.log("Window multi text" + window.multiparcels_selected_location_text);
    const $ = jQuery;
    const CHECK_INTERVAL = 500; // kas 0.5s tikrina, ar puslapis jau paruoštas
    const targetSelector = ".wp-block-woocommerce-checkout-fields-block";

    const interval = setInterval(() => {
        if (jQuery(targetSelector).length > 0) {

            const selectedValue = $(".wc-block-components-shipping-rates-control__package input[type=radio]:checked").val();
            if (selectedValue) {
                clearInterval(interval);
                initializeCheckoutBlocks();
            }

        }
    }, CHECK_INTERVAL);
}

function initializeCheckoutBlocks() {
    const $ = jQuery;

    // Hide pickup point block
    $('#mp-wc-pickup-point-shipping-block').hide();

    // Load an additional block
    // '/wp-admin/admin-ajax.php'
    $.post(multiparcels.ajax_url, { action: 'load_additional_block', nonce: multiparcels.nonce })
        .done(response => {
            $('body .wc-block-components-shipping-rates-control__package').append(response);
            attachShippingRateListeners();
        })
        .fail(err => console.error('Failed to load additional block:', err));
}

function attachShippingRateListeners() {
    const $ = jQuery;

    let selectedValue = $(".wc-block-components-shipping-rates-control__package input[type=radio]:checked").val();

    $('.wc-block-components-shipping-rates-control__package')
        .off('click', '.wc-block-components-radio-control__input')
        .on('click', '.wc-block-components-radio-control__input', function () {
            selectedValue = $(".wc-block-components-shipping-rates-control__package input[type=radio]:checked").val();
            handleShippingMethodChange(selectedValue);
        });

    handleShippingMethodChange(selectedValue);

    // Programmatically click
    //$(".wc-block-components-shipping-rates-control__package input[type=radio]:checked").trigger('click');
}

function handleShippingMethodChange(selectedValue) {
    const $ = jQuery;

    // AJAX — hide address fields
    $.post(multiparcels.ajax_url, {
        action: 'checkout_blocks_hide_inputs_for_terminal',
        selected_value: selectedValue,
        nonce: multiparcels.nonce
    }).done(response => {
        if (response == 1) {
            $('#shipping .wc-block-components-address-form__address_1, \
              #shipping .wc-block-components-address-form__address_2, \
              #shipping .wc-block-components-address-form__city, \
              #shipping .wc-block-components-address-form__state, \
              #shipping .wc-block-components-address-form__postcode').remove();
        } else {
            $('.wc-block-components-address-form [class*="address-form__"]').show();
        }
    });

    //Pickup point logic
    if (isPickupPoint(selectedValue)) {
        setupPickupPointSelect(selectedValue);
    } else if (selectedValue.indexOf("siuntos_autobusais") > 0) {
        setupSiuntosAutobusaisSelect(selectedValue);
    } else {
        $('#mp-wc-pickup-point-shipping-block').hide();
    }
}

function isPickupPoint(value) {
    return (
        value.includes('terminal') ||
        value.includes('pickup_point') ||
        value.includes('post_lv_post')
    );
}

function setupPickupPointSelect(selectedValue) {
    const $ = jQuery;
    const $select = $('#mp-wc-pickup-point-shipping-select-block');
    let currentRequest = null;

    $('#mp-wc-pickup-point-shipping-block').show();
    $select.show().empty();

    $select.select2({
        placeholder: multiparcels.text.please_select_pickup_point_location,
        width: '100%',
        ajax: {
            url: multiparcels.ajax_url,
            type: 'POST',
            dataType: 'json',
            delay: 250,
            transport: function (params, success, failure) {
                if (currentRequest) currentRequest.abort();
                currentRequest = $.ajax(params);
                currentRequest.then(success).fail((jqXHR, textStatus) => {
                    if (textStatus !== 'abort') failure();
                });
                return currentRequest;
            },
            data: function (params) {
                return {
                    action: 'multiparcels_checkout_get_pickup_points_blocks',
                    nonce: multiparcels.nonce,
                    selected_value: selectedValue,
                    q: params.term || '',
                    page: params.page || 1
                };
            },
            processResults: function (data, params) {
                params.page = params.page || 1;

                // return {
                //     results: data.results,
                //     pagination: data.pagination
                // };
                let results = Array.isArray(data.results) ? data.results.map(function(cityGroup) {
                    return {
                        text: cityGroup.text || '',
                        children: Array.isArray(cityGroup.children) ? cityGroup.children.map(function(item) {
                            return {
                                id: item.id || '',
                                text: item.text || '',
                                first_line: item.first_line || '',
                                second_line: item.second_line || ''
                            };
                        }) : []
                    };
                }) : [];

                return {
                    results: results,
                    pagination: { more: data.pagination?.more || false }
                };
            }
        }
    });

    // Set a pre-selected option if needed
    if (window.multiparcels_selected_location && window.multiparcels_selected_location_text) {
        var option = new Option(window.multiparcels_selected_location_text, window.multiparcels_selected_location, true, true);
        $select.append(option).trigger('change');
    }

    $select.off('change').on('change', function () {
        const selectedData = $(this).select2('data')[0];
        if (!selectedData) return;
        sendPickupAjax(selectedData.id);
    });
}

function setupSiuntosAutobusaisSelect(selectedValue) {
    const $ = jQuery;

    const $select = $('#mp-wc-pickup-point-shipping-select-block');
    let currentRequest = null;

    $('#mp-wc-pickup-point-shipping-block').show();
    $select.show().empty();

    $select.select2({
        placeholder: multiparcels.text.please_select_pickup_point_location,
        width: '100%',
        ajax: {
            url: multiparcels.ajax_url,
            type: 'POST',
            dataType: 'json',
            delay: 250,
            transport: function (params, success, failure) {
                if (currentRequest) currentRequest.abort();
                currentRequest = $.ajax(params);
                currentRequest.then(success).fail((jqXHR, textStatus) => {
                    if (textStatus !== 'abort') failure();
                });
                return currentRequest;
            },
            data: function (params) {
                return {
                    action: 'multiparcels_checkout_get_pickup_points_siuntos_autobusais_blocks',
                    nonce: multiparcels.nonce,
                    selected_value: selectedValue,
                    q: params.term || '',
                    page: params.page || 1
                };
            },
            processResults: function (data, params) {
                params.page = params.page || 1;
                return {
                    results: data.results,
                    pagination: data.pagination
                };
            }
        }
    });

    $select.off('change').on('change', function () {
        const selectedData = $(this).select2('data')[0];
        if (!selectedData) return;
        sendPickupAjax(selectedData.id);
    });

    // $.post('/wp-admin/admin-ajax.php', {
    //     action: 'multiparcels_checkout_get_pickup_points_siuntos_autobusais_blocks',
    //     selected_value: selectedValue
    // }, null, 'json')
    //     .done(points => {
    //         const $select = $('#mp-wc-pickup-point-shipping-select-block');
    //         $('#mp-wc-pickup-point-shipping-block').show();
    //         $select.select2();
    //         $select.empty();
    //
    //         points.all.forEach(p => {
    //             $select.append(`<option value="${p.id}">${p.text}</option>`);
    //         });
    //
    //         $select.off('change').on('change', function () {
    //
    //             const selectedId = $(this).val();
    //             sendPickupAjax(selectedId)
    //
    //
    //         });
    //     })
    //     .fail(err => console.error('Error loading bus delivery points:', err));
}

// AJAX – send selected Pickup Point
function sendPickupAjax(pickupId) {
    jQuery.post(multiparcels.ajax_url, {
        action: 'multiparcels_store_pickup_selection',
        nonce: multiparcels.nonce,
        multiparcels_pickup_location_value: pickupId
    });
}

let mp_ignore_checkout_update = false;

jQuery(document).ready(function() {
    const $ = jQuery;

    const blockSelector = '.wp-block-woocommerce-checkout-pickup-options-block';
    const shippingButtonSelector = '.wc-block-checkout__shipping-method-option';

    // --- Function to check existence ---
    function isPickupBlockExist() {
        return $(blockSelector).length > 0;
    }

    // --- Central handler ---
    function handlePickupBlockCheck() {
        return isPickupBlockExist();
    }

    // --- Run on page load ---
    initCheckoutWatcher();

    // --- Run on shipping method click ---
    $(document).on('click', shippingButtonSelector, function() {
        if (!handlePickupBlockCheck()) {
            initCheckoutWatcher();
        }
    });

    // --- Observe DOM changes (WooCommerce dynamic updates) ---
    const observerMultiparcels = new MutationObserver(() => {
        handlePickupBlockCheck();
    });

    observerMultiparcels.observe(document.body, { childList: true, subtree: true });

    $('#shipping-country').on('change', function() {
        window.multiparcels_selected_location = '';
        window.multiparcels_selected_location_text = '';
        // Read current checkout shipping fields
        const country = $('#shipping-country').val() || '';
        const state   = $('#shipping-state').val() || 'Vilnius';
        const postcode = $('#shipping-postcode').val() || '';
        const city    = $('#shipping-city').val() || '';

        // Only send if at least country is selected
        if (!country) return;

        const shippingAddress = {
            country: country,
            state: state,
            postcode: postcode,
            city: city
        };

        $.ajax({
            // url: '/wp-json/wc/store/v1/batch',
            url: '/index.php?rest_route=/wc/store/v1/batch',
            method: 'POST',
            contentType: 'application/json',
            // headers: {
            //     'X-WP-Nonce': 'dee34b149e' // your nonce here
            // },
            data: JSON.stringify({
                requests: [
                    {
                        body: {
                            billing_address: {
                                first_name: "",
                                last_name: "",
                                company: "",
                                address_1: "",
                                address_2: "",
                                city: "",
                                state: "",
                                postcode: "",
                                country: country,
                                email: "",
                                phone: ""
                            },
                            shipping_address: {
                                first_name: "",
                                last_name: "",
                                company: "",
                                address_1: "",
                                address_2: "",
                                city: "",
                                state: "",
                                postcode: "",
                                country: country,
                                phone: ""
                            }
                        },
                        cache: 'no-store',
                        data: {
                            billing_address: {
                                first_name: "",
                                last_name: "",
                                company: "",
                                address_1: "",
                                address_2: "",
                                city: "",
                                state: "",
                                postcode: "",
                                country: country,
                                email: "",
                                phone: ""
                            },
                            shipping_address: {
                                first_name: "",
                                last_name: "",
                                company: "",
                                address_1: "",
                                address_2: "",
                                city: "",
                                state: "",
                                postcode: "",
                                country: country,
                                phone: ""
                            }
                        },
                        headers: {
                            'Nonce': multiparcels.nonce // your nonce here
                        },
                        method: "POST",
                        path: "/wc/store/v1/cart/update-customer",
                    }
                ]
            }),
            success: function(response) {

                const shippingRates = response.responses[0].body.shipping_rates[0].shipping_rates;
                $('.wc-block-components-shipping-rates-control__package .wc-block-components-radio-control').html("");
                $('#mp-wc-pickup-point-shipping-block').hide();

                var html = '';
                shippingRates.forEach((method, index) => {

                    let price = method.price/100;

                    if (index === 0) {
                        html += '<label class="wc-block-components-radio-control__option wc-block-components-radio-control__option-checked wc-block-components-radio-control__option--checked-option-highlighted" for="radio-control-0-' + method.rate_id +'">';
                        html += '<input id="radio-control-0-'+ method.rate_id +'" class="wc-block-components-radio-control__input" type="radio" name="radio-control-0" aria-describedby="radio-control-0-'+ method.rate_id +'__secondary-label" aria-disabled="false" value="' + method.rate_id +'" checked="">';
                    } else {
                        html += '<label class="wc-block-components-radio-control__option" for="radio-control-0-' + method.rate_id +'">';
                        html += '<input id="radio-control-0-'+ method.rate_id +'" class="wc-block-components-radio-control__input" type="radio" name="radio-control-0" aria-describedby="radio-control-0-' + method.rate_id +'__secondary-label" aria-disabled="false" value="'+ method.rate_id + '">';
                    }
                    html += '<div class="wc-block-components-radio-control__option-layout">';
                    html += '<div class="wc-block-components-radio-control__label-group"><span id="radio-control-0-' + method.rate_id +'__label" class="wc-block-components-radio-control__label">' + method.name +'</span><span id="radio-control-0-'+ method.rate_id +'__secondary-label" class="wc-block-components-radio-control__secondary-label"><span class="wc-block-formatted-money-amount wc-block-components-formatted-money-amount">'+ price +' €</span></span></div>';
                    html += '</div>';
                    html += '</label>';

                });
                $('.wc-block-components-shipping-rates-control__package .wc-block-components-radio-control').append(html);

                // rebind click to new inputs
                // $('.wc-block-components-shipping-rates-control__package .wc-block-components-radio-control__input').off('click').on('click', function() {
                //     timedCount();
                // });
                //
                // find the checked input
                const firstChecked = $('.wc-block-components-shipping-rates-control__package .wc-block-components-radio-control__input:checked');

                // run your logic manually
                if (firstChecked.length) {
                    //firstChecked.trigger('click'); // or trigger('change')
                    initCheckoutWatcher();
                }

                // timedCount();
            },
            error: function(xhr, status, error) {
                console.error('Error updating customer via batch:', error, xhr.responseText);
            }
        });
    });

    $('#shipping-city').on('change', function() {
        window.multiparcels_selected_location = '';
        window.multiparcels_selected_location_text = '';
        initCheckoutWatcher();
    });

    $('#shipping-state').on('change', function() {
        window.multiparcels_selected_location = $('#mp-wc-pickup-point-shipping-select-block').val();
        window.multiparcels_selected_location_text = $('#mp-wc-pickup-point-shipping-select-block option:selected').text();
        initCheckoutWatcher();
    });

    $('#shipping-postcode').on('change', function() {
        window.multiparcels_selected_location = $('#mp-wc-pickup-point-shipping-select-block').val();
        window.multiparcels_selected_location_text = $('#mp-wc-pickup-point-shipping-select-block option:selected').text();
        initCheckoutWatcher();
    });

    if(!document.querySelector('.wc-block-checkout')) {
        // initializeClassicPickupPointsSelect();

        $('#billing_country, #shipping_country, .shipping_method').on('change', function() {

            initializeClassicPickupPointsSelect();
        });

        $('#billing_city, #shipping_city').on('change', function() {
            mp_ignore_checkout_update = true;
        });

        $(document).on('change', '.woocommerce-shipping-methods input[name^="shipping_method"]', function() {
            initializeClassicPickupPointsSelect();
        });
    }




});

function initializeClassicPickupPointsSelect() {
    const $ = jQuery;

    $('#mp-wc-pickup-point-shipping').hide();

    let placeholder_classic = multiparcels.text.please_select_pickup_point_location;

    var shipping_methods = {};

    $('select.shipping_method, input[name^="shipping_method"][type="radio"]:checked, input[name^="shipping_method"][type="hidden"]').each(function () {

        shipping_methods[$(this).data('index')] = $(this).val();

    });

    if ($.isEmptyObject(shipping_methods)) {
        $('input.shipping_method:checked').each(function () {
            shipping_methods[$(this).data('index')] = $(this).val();
        });
    }

    var show_title = false;



    if (multiparcels.display_pickup_location_title == 'yes') {

        show_title = true;

    }



    var rey_theme = $('body').hasClass('theme-rey') && $('.rey-checkout-shipping').length && $('.rey-checkout-shipping').is(":visible");



    if (!show_title) {

        $("#mp-wc-pickup-point-shipping .mp-please-select-location").hide();

    }



    $("#preferred_delivery_time_field").hide();



    $('.multiparcels-door-code').addClass('multiparcels-door-code-invisible').removeClass('multiparcels-door-code-visible');



    if (rey_theme && $('.multiparcels-rey-theme-modified-step').length) {

        var originalText = $('.multiparcels-rey-theme-modified-step').attr('data-original-text');



        $(".multiparcels-rey-theme-modified-step")

            .removeClass('multiparcels-rey-theme-modified-step')

            .attr('href', '#')

            .text(originalText)

            .addClass('__step-fwd');

    }



    if (Object.keys(shipping_methods).length > 0) {



        var shipping_methods_keys = Object.keys(shipping_methods);

        var shipping_method = $.trim(shipping_methods[shipping_methods_keys[0]]);

        $('#mp-wc-pickup-point-shipping').addClass('multiparcels-loading');



        var terminal_shipping_fields = $("");

        if (multiparcels.hide_not_required_terminal_fields === 'yes') {

            terminal_shipping_fields = $("#billing_address_1_field, #billing_address_2_field, #billing_city_field, #billing_postcode_field, #shipping_address_1_field, #shipping_address_2_field, #shipping_city_field, #shipping_postcode_field");

            terminal_shipping_fields.show();

        }





        var local_pickup_shipping_fields = $("");

        if (multiparcels.hide_not_required_local_pickup_fields === 'yes') {

            local_pickup_shipping_fields = $("#billing_address_1_field, #billing_address_2_field, #billing_city_field, #billing_postcode_field, #shipping_address_1_field, #shipping_address_2_field, #shipping_city_field, #shipping_postcode_field");

            local_pickup_shipping_fields.show();

        }





        // is MultiParcels and pickup location

        if (
            (shipping_method.indexOf("_siuntos_autobusais_courier") !== -1) ||
            (shipping_method.substr(0, 12) === 'multiparcels' && shipping_method.indexOf("_bus_station") !== -1) ||
            (shipping_method.substr(0, 12) === 'multiparcels' && shipping_method.indexOf("_pickup_point") !== -1) ||

            (shipping_method.substr(0, 12) === 'multiparcels' && shipping_method.indexOf("_terminal") !== -1) ||

            (shipping_method.substr(0, 12) === 'multiparcels' && shipping_method.indexOf("post_lv_pickup_point") !== -1 && shipping_method.split(':')[0].endsWith('_post')) ||

            (shipping_method.substr(0, 12) === 'multiparcels' && shipping_method.indexOf("multiparcels_post_lv_post") !== -1 && shipping_method.split(':')[0].endsWith('_post'))) {



            // Reset selected

            $("#mp-selected-pickup-point-info-wrapper").hide();

            $("#mp-map-preview").hide();

            // $("#mp-wc-pickup-point-shipping-select").html('');

            if(!mp_ignore_checkout_update) {
                $("#mp-wc-pickup-point-shipping-select").html('');
            }

            $(".mp-selected-pickup-point-info").html('');

            var latvian_post = false;



            if (shipping_method.indexOf("post_lv") !== -1) {

                latvian_post = true;

            }



            if (!latvian_post) {

                terminal_shipping_fields.hide();

            }



            if (rey_theme && $('.rey-checkoutPage-form .__step[data-step="shipping"]').is(':visible')) {

                var step = $('.rey-checkoutPage-form .__step[data-step="shipping"]')

                    .find('.__step-footer .btn-primary');

                var originalText = step.text();

                step.attr('data-original-text', originalText);



                $('.rey-checkoutPage-form .__step[data-step="shipping"]')

                    .find('.__step-footer .btn-primary')

                    .removeClass('__step-fwd')

                    .addClass('multiparcels-rey-theme-modified-step')

                    .attr('href', 'javascript:;')

                    .text(multiparcels.text.please_select_pickup_point_location);

            }



            $('#mp-wc-pickup-point-shipping').show();

            $.ajax({

                type: 'POST',

                url: multiparcels.ajax_url,

                data: {

                    'action': 'multiparcels_checkout_get_pickup_points',
                    'nonce': multiparcels.nonce

                },

                dataType: 'json',

                success: function (points) {

                    window.multiparcels_select_points_by_identifier = points.by_identifier;



                    $('#mp-wc-pickup-point-shipping').removeClass('multiparcels-loading');



                    $('#mp-wc-pickup-point-shipping-select').html("");

                    // Add an empty <option> for placeholder
                    // $('#mp-wc-pickup-point-shipping-select').html('<option></option>');



                    if (typeof jQuery.fn.selectWoo === "function") {

                        let currentRequest = null;

                        $('#mp-wc-pickup-point-shipping-select').selectWoo({
                            placeholder: multiparcels.placeholder_text || 'Please select pickup point',
                            allowClear: true,
                            width: '100%',
                            minimumInputLength: 0,
                            ajax: {
                                url: multiparcels.ajax_url,
                                type: 'POST',
                                dataType: 'json',
                                delay: 250,
                                transport: function(params, success, failure) {
                                    if (currentRequest) currentRequest.abort();
                                    currentRequest = $.ajax(params);
                                    currentRequest.then(success);
                                    currentRequest.fail(function(jqXHR, textStatus) {
                                        if (textStatus !== 'abort') failure();
                                    });
                                    return currentRequest;
                                },
                                data: function(params) {
                                    return {
                                        action: 'multiparcels_checkout_get_pickup_points_classic',
                                        nonce: multiparcels.nonce,
                                        q: params.term || '',
                                        page: params.page || 1
                                    };
                                },
                                processResults: function(data, params) {
                                    params.page = params.page || 1;

                                    let results = Array.isArray(data.results) ? data.results.map(function(cityGroup) {
                                        return {
                                            text: cityGroup.text || '',
                                            children: Array.isArray(cityGroup.children) ? cityGroup.children.map(function(item) {
                                                return {
                                                    id: item.id || '',
                                                    text: item.text || '',
                                                    first_line: item.first_line || '',
                                                    second_line: item.second_line || ''
                                                };
                                            }) : []
                                        };
                                    }) : [];

                                    return {
                                        results: results,
                                        pagination: { more: data.pagination?.more || false }
                                    };
                                },
                                cache: true
                            },
                            language: {
                                noResults: function() {
                                    return multiparcels.text.pickup_location_not_found || 'No pickup locations found';
                                }
                            },
                            templateResult: function(option) {
                                if (!option.id) return option.text; // group or placeholder
                                if (option.first_line) {
                                    return $('<span>' + option.first_line + ' <small>' + option.second_line + '</small></span>');
                                }
                                return $('<span>' + option.text + '</span>');
                            },
                            templateSelection: function(data) {
                                if (!data.id) { // placeholder or empty
                                    return multiparcels.placeholder_text || 'Please select pickup point';
                                }
                                return data.second_line ? data.first_line + ' (' + data.second_line + ')' : data.first_line || data.text;
                            }
                        });





                        // select first element by default
                        // $('#mp-wc-pickup-point-shipping-select').click();
                        // $('#mp-wc-pickup-point-shipping-select').selectWoo({
                        //
                        //     data: points.all,
                        //
                        //     placeholderOption: 'first',
                        //
                        //     width: '100%',
                        //
                        //     language: {
                        //
                        //         noResults: function () {
                        //
                        //             return multiparcels.text.pickup_location_not_found;
                        //
                        //         }
                        //
                        //     },
                        //
                        //     templateResult: function (option) {
                        //
                        //         if (typeof option.first_line == 'string') {
                        //
                        //             return $(
                        //
                        //                 '<span>' + option.first_line + ' <small>' + option.second_line + '</small></span>'
                        //
                        //             );
                        //
                        //         }
                        //
                        //
                        //
                        //         return $('<span>' + option.text + '</span>');
                        //
                        //     },
                        //
                        //     templateSelection: function (data, container) {
                        //
                        //         if (typeof data.second_line != 'undefined' && data.second_line) {
                        //
                        //             return data.first_line + ' (' + data.second_line + ')';
                        //
                        //         }
                        //
                        //
                        //
                        //         return data.first_line;
                        //
                        //     }
                        //
                        // })
                        //
                        //     // add search placeholder
                        //
                        //     .data('select2')
                        //
                        //     .$dropdown
                        //
                        //     .find(':input.select2-search__field')
                        //
                        //     .attr('placeholder', multiparcels.placeholder_text);

                    } else {

                        var items = '';



                        $.each(points.all, function (key, value) {

                            if (typeof value.children === 'object') {

                                items += "<optgroup label='" + value.text + "'>";

                                $.each(value.children, function (key2, value2) {

                                    items += '<option value=' + value2.id + '>' + value2.text + '</option>';

                                });

                                items += "</optgroup>";

                            } else {

                                items += '<option value=' + value.id + '>' + value.text + '</option>';

                            }

                        });



                        $('#mp-wc-pickup-point-shipping-select').html(items);

                    }



                    // if (typeof window.multiparcels_selected_location != 'undefined' && window.multiparcels_selected_location) {
                    //
                    //     // check if it actually exists
                    //
                    //     if ($('#mp-wc-pickup-point-shipping-select').find("option[value='" + window.multiparcels_selected_location + "']").length) {
                    //
                    //         $('#mp-wc-pickup-point-shipping-select').val(window.multiparcels_selected_location).trigger('change');
                    //
                    //     }
                    //
                    // }

                }

            });

        } else if (shipping_method.substr(0, 12) === 'multiparcels') {

            if (shipping_method.indexOf("venipak") !== -1) {

                var cities = multiparcels.preferred_delivery_time_cities;

                var city = transliterate($("#billing_city").val().toLowerCase());

                var is_big_city = cities.indexOf(city) != -1;



                if (is_big_city) {

                    $.ajax({

                        type: 'POST',

                        url: multiparcels.ajax_url,

                        data: {

                            'action': 'multiparcels_is_preferred_delivery_time_available',
                            'nonce': multiparcels.nonce

                        },

                        dataType: 'json',

                        success: function (response) {

                            if (response.success == true) {

                                var options = '';



                                response.times.forEach(function (value, key) {

                                    options += "<option value='" + key + "'>" + value + "</option>";

                                });



                                $("#preferred_delivery_time_field select").html(options);

                                $("#preferred_delivery_time_field").show();



                                if (typeof jQuery.fn.selectWoo === "function") {

                                    $("#preferred_delivery_time").selectWoo();

                                }



                                if (typeof window.multiparcels_selected_delivery_time != 'undefined' && window.multiparcels_selected_delivery_time) {

                                    $('#preferred_delivery_time').val(window.multiparcels_selected_delivery_time).trigger('change');

                                }

                            }

                        }

                    });

                }



                $.ajax({

                    type: 'POST',

                    url: multiparcels.ajax_url,

                    data: {

                        'action': 'multiparcels_venipak_door_code',
                        'nonce': multiparcels.nonce

                    },

                    dataType: 'json',

                    success: function (response) {

                        if (response.success) {

                            $('.multiparcels-door-code').addClass('multiparcels-door-code-visible').removeClass('multiparcels-door-code-invisible');

                        }

                    }

                });



                // Reset any previous selection

                $('#mp-wc-pickup-point-shipping-select').val('');

            }

        } else if(shipping_method.split(':')[0] == 'local_pickup') {

            local_pickup_shipping_fields.hide();



            // Reset any previous selection

            $('#mp-wc-pickup-point-shipping-select').val('');

        } else {

            // Reset any previous selection

            $('#mp-wc-pickup-point-shipping-select').val('');

        }

    }
}

jQuery(document).on('updated_checkout cfw_updated_checkout', function (e, data) {
    initializeClassicPickupPointsSelect();
});

// jQuery(document).on('updated_checkout cfw_updated_checkout', function (e, data) {
//     const $ = jQuery;
//
//     $('#mp-wc-pickup-point-shipping').hide();
//
//     let placeholder_classic = multiparcels.text.please_select_pickup_point_location;
//
//     var shipping_methods = {};
//
//     $('select.shipping_method, input[name^="shipping_method"][type="radio"]:checked, input[name^="shipping_method"][type="hidden"]').each(function () {
//
//         shipping_methods[$(this).data('index')] = $(this).val();
//
//     });
//
//
//
//     var show_title = false;
//
//
//
//     if (multiparcels.display_pickup_location_title == 'yes') {
//
//         show_title = true;
//
//     }
//
//
//
//     var rey_theme = $('body').hasClass('theme-rey') && $('.rey-checkout-shipping').length && $('.rey-checkout-shipping').is(":visible");
//
//
//
//     if (!show_title) {
//
//         $("#mp-wc-pickup-point-shipping .mp-please-select-location").hide();
//
//     }
//
//
//
//     $("#preferred_delivery_time_field").hide();
//
//
//
//     $('.multiparcels-door-code').addClass('multiparcels-door-code-invisible').removeClass('multiparcels-door-code-visible');
//
//
//
//     if (rey_theme && $('.multiparcels-rey-theme-modified-step').length) {
//
//         var originalText = $('.multiparcels-rey-theme-modified-step').attr('data-original-text');
//
//
//
//         $(".multiparcels-rey-theme-modified-step")
//
//             .removeClass('multiparcels-rey-theme-modified-step')
//
//             .attr('href', '#')
//
//             .text(originalText)
//
//             .addClass('__step-fwd');
//
//     }
//
//
//
//     if (Object.keys(shipping_methods).length > 0) {
//
//
//
//         var shipping_methods_keys = Object.keys(shipping_methods);
//
//         var shipping_method = $.trim(shipping_methods[shipping_methods_keys[0]]);
//
//         $('#mp-wc-pickup-point-shipping').addClass('multiparcels-loading');
//
//
//
//         var terminal_shipping_fields = $("");
//
//         if (multiparcels.hide_not_required_terminal_fields === 'yes') {
//
//             terminal_shipping_fields = $("#billing_address_1_field, #billing_address_2_field, #billing_city_field, #billing_postcode_field, #shipping_address_1_field, #shipping_address_2_field, #shipping_city_field, #shipping_postcode_field");
//
//             terminal_shipping_fields.show();
//
//         }
//
//
//
//
//
//         var local_pickup_shipping_fields = $("");
//
//         if (multiparcels.hide_not_required_local_pickup_fields === 'yes') {
//
//             local_pickup_shipping_fields = $("#billing_address_1_field, #billing_address_2_field, #billing_city_field, #billing_postcode_field, #shipping_address_1_field, #shipping_address_2_field, #shipping_city_field, #shipping_postcode_field");
//
//             local_pickup_shipping_fields.show();
//
//         }
//
//
//
//
//
//         // is MultiParcels and pickup location
//         console.log(shipping_method);
//
//         if (
//             (shipping_method.indexOf("_siuntos_autobusais_courier") !== -1) ||
//             (shipping_method.substr(0, 12) === 'multiparcels' && shipping_method.indexOf("_bus_station") !== -1) ||
//             (shipping_method.substr(0, 12) === 'multiparcels' && shipping_method.indexOf("_pickup_point") !== -1) ||
//
//             (shipping_method.substr(0, 12) === 'multiparcels' && shipping_method.indexOf("_terminal") !== -1) ||
//
//             (shipping_method.substr(0, 12) === 'multiparcels' && shipping_method.indexOf("post_lv_pickup_point") !== -1 && shipping_method.split(':')[0].endsWith('_post')) ||
//
//             (shipping_method.substr(0, 12) === 'multiparcels' && shipping_method.indexOf("multiparcels_post_lv_post") !== -1 && shipping_method.split(':')[0].endsWith('_post'))) {
//
//
//
//             // Reset selected
//
//             $("#mp-selected-pickup-point-info-wrapper").hide();
//
//             $("#mp-map-preview").hide();
//
//             $("#mp-wc-pickup-point-shipping-select").html('');
//
//             $(".mp-selected-pickup-point-info").html('');
//
//             var latvian_post = false;
//
//
//
//             if (shipping_method.indexOf("post_lv") !== -1) {
//
//                 latvian_post = true;
//
//             }
//
//
//
//             if (!latvian_post) {
//
//                 terminal_shipping_fields.hide();
//
//             }
//
//
//
//             if (rey_theme && $('.rey-checkoutPage-form .__step[data-step="shipping"]').is(':visible')) {
//
//                 var step = $('.rey-checkoutPage-form .__step[data-step="shipping"]')
//
//                     .find('.__step-footer .btn-primary');
//
//                 var originalText = step.text();
//
//                 step.attr('data-original-text', originalText);
//
//
//
//                 $('.rey-checkoutPage-form .__step[data-step="shipping"]')
//
//                     .find('.__step-footer .btn-primary')
//
//                     .removeClass('__step-fwd')
//
//                     .addClass('multiparcels-rey-theme-modified-step')
//
//                     .attr('href', 'javascript:;')
//
//                     .text(multiparcels.text.please_select_pickup_point_location);
//
//             }
//
//
//
//             $('#mp-wc-pickup-point-shipping').show();
//
//             $.ajax({
//
//                 type: 'POST',
//
//                 url: multiparcels.ajax_url,
//
//                 data: {
//
//                     'action': 'multiparcels_checkout_get_pickup_points'
//
//                 },
//
//                 dataType: 'json',
//
//                 success: function (points) {
//
//                     window.multiparcels_select_points_by_identifier = points.by_identifier;
//
//
//
//                     $('#mp-wc-pickup-point-shipping').removeClass('multiparcels-loading');
//
//
//
//                     $('#mp-wc-pickup-point-shipping-select').html("");
//
//                     // Add an empty <option> for placeholder
//                     // $('#mp-wc-pickup-point-shipping-select').html('<option></option>');
//
//
//
//                     if (typeof jQuery.fn.selectWoo === "function") {
//
//                         let currentRequest = null;
//
//                         $('#mp-wc-pickup-point-shipping-select').selectWoo({
//                             placeholder: multiparcels.placeholder_text || 'Please select pickup point',
//                             allowClear: true,
//                             allowClear: true,
//                             width: '100%',
//                             minimumInputLength: 0,
//                             ajax: {
//                                 url: multiparcels.ajax_url,
//                                 type: 'POST',
//                                 dataType: 'json',
//                                 delay: 250,
//                                 transport: function(params, success, failure) {
//                                     // Abort previous request
//                                     if (currentRequest) {
//                                         currentRequest.abort();
//                                     }
//
//                                     // New request
//                                     currentRequest = $.ajax(params);
//
//                                     currentRequest.then(success);
//                                     currentRequest.fail(function(jqXHR, textStatus) {
//                                         // Ignore if request was aborted
//                                         if (textStatus !== 'abort') {
//                                             failure();
//                                         }
//                                     });
//
//                                     return currentRequest;
//                                 },
//                                 data: function(params) {
//                                     return {
//                                         action: 'multiparcels_checkout_get_pickup_points_classic',
//                                         q: params.term || '',
//                                         page: params.page || 1
//                                     };
//                                 },
//                                 processResults: function(data, params) {
//                                     params.page = params.page || 1;
//                                     // return {
//                                     //     results: data.items.map(function(item) {
//                                     //         return {
//                                     //             id: item.id,
//                                     //             text: item.first_line + (item.second_line ? ' (' + item.second_line + ')' : ''),
//                                     //             first_line: item.first_line,
//                                     //             second_line: item.second_line
//                                     //         };
//                                     //     }),
//                                     //     pagination: { more: (params.page * 20) < data.total_count }
//                                     // };
//                                     let results = data.items.map(function(item) {
//                                         return {
//                                             id: item.id,
//                                             text: item.first_line + (item.second_line ? ' (' + item.second_line + ')' : ''),
//                                             first_line: item.first_line,
//                                             second_line: item.second_line
//                                         };
//                                     });
//
//                                     return { results: results, pagination: { more: (params.page * 20) < data.total_count } };
//                                 },
//                                 cache: true
//                             },
//                             language: {
//                                 noResults: function() {
//                                     return multiparcels.text.pickup_location_not_found;
//                                 }
//                             },
//                             templateResult: function(option) {
//                                 if (option.first_line) {
//                                     return $('<span>' + option.first_line + ' <small>' + option.second_line + '</small></span>');
//                                 }
//                                 return $('<span>' + option.text + '</span>');
//                             },
//                             templateSelection: function(data) {
//                                 if (!data.id) { // empty value, show placeholder
//                                     return multiparcels.placeholder_text || 'Please select pickup point';
//                                 }
//                                 // return data.second_line ? data.first_line + ' (' + data.second_line + ')' : data.first_line;
//                                 return data.second_line ? data.first_line + ' (' + data.second_line + ')' : data.first_line;
//                             }
//                         });
//
//                         // select first element by default
//                         // $('#mp-wc-pickup-point-shipping-select').click();
//                         // $('#mp-wc-pickup-point-shipping-select').selectWoo({
//                         //
//                         //     data: points.all,
//                         //
//                         //     placeholderOption: 'first',
//                         //
//                         //     width: '100%',
//                         //
//                         //     language: {
//                         //
//                         //         noResults: function () {
//                         //
//                         //             return multiparcels.text.pickup_location_not_found;
//                         //
//                         //         }
//                         //
//                         //     },
//                         //
//                         //     templateResult: function (option) {
//                         //
//                         //         if (typeof option.first_line == 'string') {
//                         //
//                         //             return $(
//                         //
//                         //                 '<span>' + option.first_line + ' <small>' + option.second_line + '</small></span>'
//                         //
//                         //             );
//                         //
//                         //         }
//                         //
//                         //
//                         //
//                         //         return $('<span>' + option.text + '</span>');
//                         //
//                         //     },
//                         //
//                         //     templateSelection: function (data, container) {
//                         //
//                         //         if (typeof data.second_line != 'undefined' && data.second_line) {
//                         //
//                         //             return data.first_line + ' (' + data.second_line + ')';
//                         //
//                         //         }
//                         //
//                         //
//                         //
//                         //         return data.first_line;
//                         //
//                         //     }
//                         //
//                         // })
//                         //
//                         //     // add search placeholder
//                         //
//                         //     .data('select2')
//                         //
//                         //     .$dropdown
//                         //
//                         //     .find(':input.select2-search__field')
//                         //
//                         //     .attr('placeholder', multiparcels.placeholder_text);
//
//                     } else {
//
//                         var items = '';
//
//
//
//                         $.each(points.all, function (key, value) {
//
//                             if (typeof value.children === 'object') {
//
//                                 items += "<optgroup label='" + value.text + "'>";
//
//                                 $.each(value.children, function (key2, value2) {
//
//                                     items += '<option value=' + value2.id + '>' + value2.text + '</option>';
//
//                                 });
//
//                                 items += "</optgroup>";
//
//                             } else {
//
//                                 items += '<option value=' + value.id + '>' + value.text + '</option>';
//
//                             }
//
//                         });
//
//
//
//                         $('#mp-wc-pickup-point-shipping-select').html(items);
//
//                     }
//
//
//
//                     // if (typeof window.multiparcels_selected_location != 'undefined' && window.multiparcels_selected_location) {
//                     //
//                     //     // check if it actually exists
//                     //
//                     //     if ($('#mp-wc-pickup-point-shipping-select').find("option[value='" + window.multiparcels_selected_location + "']").length) {
//                     //
//                     //         $('#mp-wc-pickup-point-shipping-select').val(window.multiparcels_selected_location).trigger('change');
//                     //
//                     //     }
//                     //
//                     // }
//
//                 }
//
//             });
//
//         } else if (shipping_method.substr(0, 12) === 'multiparcels') {
//
//             if (shipping_method.indexOf("venipak") !== -1) {
//
//                 var cities = multiparcels.preferred_delivery_time_cities;
//
//                 var city = transliterate($("#billing_city").val().toLowerCase());
//
//                 var is_big_city = cities.indexOf(city) != -1;
//
//
//
//                 if (is_big_city) {
//
//                     $.ajax({
//
//                         type: 'POST',
//
//                         url: multiparcels.ajax_url,
//
//                         data: {
//
//                             'action': 'multiparcels_is_preferred_delivery_time_available'
//
//                         },
//
//                         dataType: 'json',
//
//                         success: function (response) {
//
//                             if (response.success == true) {
//
//                                 var options = '';
//
//
//
//                                 response.times.forEach(function (value, key) {
//
//                                     options += "<option value='" + key + "'>" + value + "</option>";
//
//                                 });
//
//
//
//                                 $("#preferred_delivery_time_field select").html(options);
//
//                                 $("#preferred_delivery_time_field").show();
//
//
//
//                                 if (typeof jQuery.fn.selectWoo === "function") {
//
//                                     $("#preferred_delivery_time").selectWoo();
//
//                                 }
//
//
//
//                                 if (typeof window.multiparcels_selected_delivery_time != 'undefined' && window.multiparcels_selected_delivery_time) {
//
//                                     $('#preferred_delivery_time').val(window.multiparcels_selected_delivery_time).trigger('change');
//
//                                 }
//
//                             }
//
//                         }
//
//                     });
//
//                 }
//
//
//
//                 $.ajax({
//
//                     type: 'POST',
//
//                     url: multiparcels.ajax_url,
//
//                     data: {
//
//                         'action': 'multiparcels_venipak_door_code'
//
//                     },
//
//                     dataType: 'json',
//
//                     success: function (response) {
//
//                         if (response.success) {
//
//                             $('.multiparcels-door-code').addClass('multiparcels-door-code-visible').removeClass('multiparcels-door-code-invisible');
//
//                         }
//
//                     }
//
//                 });
//
//
//
//                 // Reset any previous selection
//
//                 $('#mp-wc-pickup-point-shipping-select').val('');
//
//             }
//
//         } else if(shipping_method.split(':')[0] == 'local_pickup') {
//
//             local_pickup_shipping_fields.hide();
//
//
//
//             // Reset any previous selection
//
//             $('#mp-wc-pickup-point-shipping-select').val('');
//
//         } else {
//
//             // Reset any previous selection
//
//             $('#mp-wc-pickup-point-shipping-select').val('');
//
//         }
//
//     }
//
// });

jQuery(document).on('change', '#mp-wc-pickup-point-shipping-select', function () {
    const $ = jQuery;
    var val = $('#mp-wc-pickup-point-shipping-select').val();

    if (jQuery('body').hasClass('theme-Divi')) {

        $.ajax({
            url: multiparcels.ajax_url,
            type: 'POST',
            data: {
                action: 'multiparcels_set_terminal_value',
                nonce: multiparcels.nonce,
                selected_value: val,
            },
            dataType: 'json',
            success: function(response) {
            },
            error: function(error) {
                console.error('AJAX Error:', error);
            }
        });

    }



    $("#mp-selected-pickup-point-info-wrapper").hide();

    $("#mp-map-preview").hide();



    var show_information = false;



    if (multiparcels.display_selected_pickup_location_information == 'yes') {

        show_information = true;

    }



    // remember selected location

    window.multiparcels_selected_location = val;



    if (val == '' || !show_information) {

        $(".mp-selected-pickup-point-info").html('');

    } else {

        var location = window.multiparcels_select_points_by_identifier[val];



        // to prevent selecting location from a different carrier when switching between

        // shipping methods

        if (location) {

            $("#mp-selected-pickup-point-info-wrapper").show();



            var rey_theme = $('body').hasClass('theme-rey') && $('.rey-checkout-shipping').length && $('.rey-checkout-shipping').is(":visible");



            if (rey_theme && $('.multiparcels-rey-theme-modified-step').length) {

                var originalText = $('.multiparcels-rey-theme-modified-step').attr('data-original-text');



                $(".multiparcels-rey-theme-modified-step")

                    .removeClass('multiparcels-rey-theme-modified-step')

                    .attr('href', '#')

                    .text(originalText)

                    .addClass('__step-fwd');

            }



            var html = location['second_line'] + "<br/>";



            if (location.location.working_hours) {

                html += multiparcels.text.working_hours + ": <strong>" + location.location.working_hours + "</strong><br/>";

            }



            if (location.location.comment) {

                html += "<small>" + location.location.comment + "</small><br/>";

            }



            $(".mp-selected-pickup-point-info").html(html);



            if (typeof google === "object" && google.maps && location.location.latitude && location.location.longitude) {

                $("#mp-map-preview").show();



                var position = {

                    lat: parseFloat(location.location.latitude),

                    lng: parseFloat(location.location.longitude)

                };



                var map = new google.maps.Map(document.getElementById('mp-gmap'), {

                    center: position,

                    zoom: 15

                });



                new google.maps.Marker({

                    position: position,

                    map: map

                });

            }

        }

    }

});

/**

 * Remember selected delivery time

 */

jQuery(document).on('change', '#preferred_delivery_time', function () {
    const $ = jQuery;

    window.multiparcels_selected_delivery_time = $(this).val();

});

/**

 * AeroCheckout

 */

jQuery(document).on('change', '.wfacp_shipping_radio input', function () {
    const $ = jQuery;

    $(document).trigger('updated_checkout');

});

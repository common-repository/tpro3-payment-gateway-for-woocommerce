(function ($) {

    if (typeof window.tpro3_checkout != 'undefined') {

        if (!Date.now) {
            Date.now = function() { return new Date().getTime(); }
        }

        function decodeEntities(encodedString) {
            encodedString = (encodedString == 'undefined') ? '' : encodedString;
            var textArea = document.createElement('textarea');
            textArea.innerHTML = encodedString;
            return textArea.value;
        }

        // Get auth data
        var auth_session_id = tpro3_checkout.authSession;
        var auth_url = tpro3_checkout.authURL;
        var customer_name = tpro3_checkout.customerName;
        var customer_display_name = tpro3_checkout.customerDisplayName;
        var customer_email = tpro3_checkout.customerEmail;
        var create_registered = tpro3_checkout.createRegistered;
        var create_guest = tpro3_checkout.createGuest;
        var uniqueCustomerID = tpro3_checkout.uniqueCustomerID;

        // Submit checkout form
        $('form[name="checkout"]').on('checkout_place_order', function(event) {

            event.preventDefault();
            event.stopPropagation();

            var $button = $('[name="woocommerce_checkout_place_order"]');

            if ($button.data('get-session') != 1) {
                $button.data('get-session', 0);
            }

            // set variables

            var billing_company = $('[name="billing_company"]').val();
            var billing_first_name = $('[name="billing_first_name"]').val();
            var billing_last_name = $('[name="billing_last_name"]').val();
            var billing_street1 = $('[name="billing_address_1"]').val();
            var billing_street2 = $('[name="billing_address_2"]').val();
            var billing_city = $('[name="billing_city"]').val();
            var billing_state = $('[name="billing_state"]').find('option:selected').text();
            var billing_zip = $('[name="billing_postcode"]').val();
            var billing_country = $('[name="billing_country"]').val();
            if (billing_country == 'US') {
                billing_country = 'United States';
            } else if (billing_country == 'CA') {
                billing_country = 'Canada';
            }

            var cc_number = $('[name="tpro3-card-number"]').val().replace(/\s/g, '');
            var cc_exp = $('[name="tpro3-card-expiry"]').payment('cardExpiryVal');
            var cc_exp_month = cc_exp.month;
            var cc_exp_year = cc_exp.year;
            var cc_cvv = $('[name="tpro3-card-cvc"]').val();

            if (customer_display_name == '') {
                customer_display_name = (billing_company == '') ? decodeEntities(billing_first_name) + ' ' + decodeEntities(billing_last_name) : decodeEntities(billing_company);
            }

            // prepare XML
            var requestXML = "<request>"
                + "<authentication>"
                + "<sessionid>" + auth_session_id + "</sessionid>"
                + "</authentication>"
                + "<content continueonfailure='true'>"
                + "<update>"
                + "<customer refname='customer'>"
                + "<name>" + customer_name + "_" + uniqueCustomerID + "</name>"
                + "<displayname>" + customer_display_name + "</displayname>"
                + "</customer>"
                + "</update>"
                + "<if condition=\"{!customer.responsestatus!} != 'success'\">"
                + "<create>"
                + "<customer refname='customer'>"
                + "<name>" + customer_name + "_" + uniqueCustomerID + "</name>"
                + "<displayname>" + customer_display_name + "</displayname>"
                + "</customer>"
                + "</create>"
                + "</if>";
            if (create_guest == 1 || create_registered == 1) {
                requestXML = requestXML + "<if condition=\"{!customer.responsestatus!} = 'success'\">"
                    + "<update>"
                    + "<contact refname='billcontact'>"
                    + "<name>B_{!customer.name!}</name>"
                    + "<customer>{!customer.id!}</customer>"
                    + "<contacttype>billing</contacttype>"
                    + "<companyname>" + decodeEntities(billing_company) + "</companyname>"
                    + "<firstname>" + decodeEntities(billing_first_name) + "</firstname>"
                    + "<lastname>" + decodeEntities(billing_last_name) + "</lastname>"
                    + "<address1>" + decodeEntities(billing_street1) + "</address1>"
                    + "<address2>" + decodeEntities(billing_street2) + "</address2>"
                    + "<city>" + decodeEntities(billing_city) + "</city>"
                    + "<state>" + decodeEntities(billing_state) + "</state>"
                    + "<zipcode>" + decodeEntities(billing_zip) + "</zipcode>"
                    + "<country>" + decodeEntities(billing_country) + "</country>"
                    + "<phone1></phone1>"
                    + "<cellphone></cellphone>"
                    + "<email1>" + customer_email + "</email1>"
                    + "</contact>"
                    + "</update>"
                    + "<if condition=\"{!billcontact.responsestatus!} != 'success'\">"
                    + "<create>"
                    + "<contact refname='billcontact'>"
                    + "<name>B_{!customer.name!}</name>"
                    + "<customer>{!customer.id!}</customer>"
                    + "<contacttype>billing</contacttype>"
                    + "<companyname>" + decodeEntities(billing_company) + "</companyname>"
                    + "<firstname>" + decodeEntities(billing_first_name) + "</firstname>"
                    + "<lastname>" + decodeEntities(billing_last_name) + "</lastname>"
                    + "<address1>" + decodeEntities(billing_street1) + "</address1>"
                    + "<address2>" + decodeEntities(billing_street2) + "</address2>"
                    + "<city>" + decodeEntities(billing_city) + "</city>"
                    + "<state>" + decodeEntities(billing_state) + "</state>"
                    + "<zipcode>" + decodeEntities(billing_zip) + "</zipcode>"
                    + "<country>" + decodeEntities(billing_country) + "</country>"
                    + "<phone1></phone1>"
                    + "<cellphone></cellphone>"
                    + "<email1>" + customer_email + "</email1>"
                    + "</contact>"
                    + "</create>"
                    + "</if>"
                    + "</if>";
            }
            requestXML = requestXML + "<if condition=\"{!billcontact.responsestatus!} = 'success'\">"
                + "<create>"
                + "<storedaccount refname='sa'>"
                + "<name>{!customer.name!}</name>"
                + "<customer>{!customer.id!}</customer>"
                + "<contact>{!billcontact.id!}</contact>"
                + "<creditcard>"
                + "<keyed>"
                + "<cardholdernumber>" + cc_number +"</cardholdernumber>"
                + "<cardholdername>" + decodeEntities(billing_first_name) + " " + decodeEntities(billing_last_name) +"</cardholdername>"
                + "<accountholder>" + decodeEntities(billing_first_name) + " " + decodeEntities(billing_last_name) +"</accountholder>"
                + "<expiresmonth>" + cc_exp_month +"</expiresmonth>"
                + "<expiresyear>" + cc_exp_year +"</expiresyear>"
                + "<cvv>" + cc_cvv +"</cvv>"
                + "</keyed>"
                + "</creditcard>"
                + "</storedaccount>"
                + "</create>"
                + "</if>"
                + "</content>"
                + "</request>";

            // submit data

            $button.prop('disabled', true);

            if ($button.data('get-session') == 0) {
                $button.data('get-session', 2);
                $.ajax({
                    type: 'POST',
                    url: auth_url,
                    data: requestXML,
                    contentType: 'application/xml',
                    dataType: 'xml',
                    cache: false,
                    error: function() {
                        console.log('Error sending data to API');
                    },
                    success: function (xml) {
                        var customer_id = undefined;
                        var contact_id = undefined;
                        var customer_nodes = $(xml).find('customer');
                        for (var i = 0; i < customer_nodes.length; i++) {
                            if ($(customer_nodes[i]).find('id').text() != '') {
                                customer_id = $(customer_nodes[i]).find('id').text();
                            }
                        }
                        var contact_nodes = $(xml).find('contact');
                        for (var i = 0; i < contact_nodes.length; i++) {
                            if ($(contact_nodes[i]).find('id').text() != '') {
                                contact_id = $(contact_nodes[i]).find('id').text();
                            }
                        }
                        var session_id = $(xml).find('storedaccount').children('id').first().text();
                        var session_id = $(xml).find('storedaccount').children('id').first().text();
                        var $form = $('form[name="checkout"]');
                        $form
                            .append('<input type="hidden" name="tpro3_session" value="' + auth_session_id + '">')
                            .append('<input type="hidden" name="tpro3_payment_session" value="' + session_id + '">')
                            .append('<input type="hidden" name="tpro3_customerid" value="' + customer_id + '">')
                            .append('<input type="hidden" name="tpro3_contactid" value="' + contact_id + '">');

                        $button.data('get-session', 1);
                        $form.trigger('submit');
                    }
                });
            }
            if ($button.data('get-session') == 1) {
                $button.data('get-session', 3);
                $button.prop('disabled', false);

                return true;
            } else {
                return false;
            }

        });
    }
})(jQuery);
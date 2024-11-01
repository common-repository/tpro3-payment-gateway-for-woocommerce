<?php
/*
Plugin Name: WooCommerce TPro3 Payment Gateway
Plugin URI: http://www.2cpusa.com
Description: TPro3 Payment gateway for woocommerce
Version: 1.2.1
Author: 2C Processor USA
Author URI: http://www.2cpusa.com
 */

register_activation_hook( __FILE__, 'activate_tpro3cron' );
register_deactivation_hook( __FILE__, 'my_deactivation' );
add_action( 'plugins_loaded', 'woocommerce_tpro3_init', 0 );
add_action( 'tpro3cronjob', 'dotpro3CronJob' );
add_action( 'tpro3CronJobCustom', 'dotpro3CronJobCustom' );
add_action( 'admin_footer', 'my_action_javascript' );
add_action( 'wp_ajax_my_action', 'my_action_callback' );
add_action( 'wp_ajax_my_action_load_product', 'my_action_load_product' );
add_filter( 'cron_schedules', 'register_custom_schedule' );

function woocommerce_tpro3_init() {
	if ( ! class_exists( 'WC_Payment_Gateway_CC' ) ) {
		return;
	}



	class WC_tpro3 extends WC_Payment_Gateway_CC {



		public function __construct() {

			$this->version = '1.2.1';



			//ADD SUBCRIPTION SUPPORT

			$this->supports = array(

				'products',

				'subscriptions',

				'subscription_cancellation',

				'subscription_suspension',

				'subscription_reactivation',

				'subscription_amount_changes',

				'subscription_date_changes',

				'subscription_payment_method_change',

			);



			$this->id            = 'tpro3';

			$this->medthod_title = 'TPro3';

			$this->has_fields    = TRUE;

			$this->init_settings();

			$this->init_form_fields();

			$this->title                         = $this->get_option( 'title' );

			$this->description                   = $this->get_option( 'description' );

			$this->tpro3_gateway_name            = $this->get_option( 'tpro3_gateway_name' );

			$this->tpro3_email_address           = $this->get_option( 'tpro3_email_address' );

			$this->tpro3_password                = $this->get_option( 'tpro3_password' );

			$this->tpro3_account_name            = $this->get_option( 'tpro3_account_name' );

			$this->tpro3_default_customer        = $this->get_option( 'tpro3_default_customer' );

			$this->tpro3_create_registered       = $this->get_option( 'tpro3_create_registered' );

			$this->tpro3_create_guest            = $this->get_option( 'tpro3_create_guest' );

			$this->tpro3_warehouses              = $this->get_option( 'tpro3_warehouses' );

			$this->tpro3_categories              = $this->get_option( 'tpro3_categories' );

			$this->tpro3_synctiming              = $this->get_option( 'tpro3_synctiming' );

			$this->tpro3_defaultshippingclass    = $this->get_option( 'tpro3_defaultshippingclass' );

			$this->tpro3_salesdocumenttype       = $this->get_option( 'tpro3_salesdocumenttype' );

			$this->tpro3_transactiontype         = $this->get_option( 'tpro3_transactiontype' );

			$this->tpro3_customernamefield       = $this->get_option( 'tpro3_customernamefield' );

			$this->tpro3_overwriteskudescription = $this->get_option( 'tpro3_overwriteskudescription' );



			$this->msg['message'] = "";

			$this->msg['class']   = "";

			$this->supports[]     = 'default_credit_card_form';



			add_action( 'init', array( &$this, 'check_tpro3_response' ) );



			if ( version_compare( WOOCOMMERCE_VERSION, '2.0.0', '>=' ) ) {

				add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array(

					&$this,

					'process_admin_options'

				) );

			} else {

				add_action( 'woocommerce_update_options_payment_gateways', array(

					&$this,

					'process_admin_options'

				) );

			}

			add_action( 'woocommerce_receipt_tpro3', array(

				&$this,

				'receipt_page'

			) );

			//RECCURING PAYMENT HOOK

			add_action( 'woocommerce_scheduled_subscription_payment_' . $this->id, array(

				$this,

				'scheduled_subscription_payment'

			), 10, 1 );



            add_action( 'wp_enqueue_scripts', array( &$this, 'tpro3_checkout_scripts' ) );

		}



		function init_form_fields() {

			$fields_list            = array();

			$accounts_list          = array();

			$categories_list        = array();

			$warehouse_list         = array();

			$salesdocumenttype_list = array();



			if ( is_admin() ) {

				if ( ( isset( $this->settings['tpro3_gateway_name'] ) == TRUE ) && ( isset( $this->settings['tpro3_email_address'] ) == TRUE ) && ( isset( $this->settings['tpro3_password'] ) == TRUE ) ) {

					$xml = "<request>

                        <authentication>

                        <user>

                            <gateway>" . $this->settings['tpro3_gateway_name'] . "</gateway>

                            <emailaddress>" . $this->settings['tpro3_email_address'] . "</emailaddress>

                            <password>" . $this->settings['tpro3_password'] . "</password>

                            <application>WooCommerce</application>

                            <version>" . $this->version . "</version>

                        </user>

                        </authentication>

                        <content continueonfailure='true'>

                        <read object='positemtype' fields='id,name,modifyon' refname='positemtypes' />

                        <read object='poswarehouse' fields='id,name,modifyon' refname='poswarehouses' />

                        <read object='activeaccounts' fields='id,name' query=\"platformtype.name = 'Credit Card'\" refname='accounts' />

                        <read object='salesdocumenttype' fields='id,name' orderby='name' refname='salesdocumenttypes' />

                        <read object='fieldobject' fields='id,name,fieldlabel' orderby='fieldlabel' query=\"tableobject.name='salesdocument' AND type.name='text'\" refname='fields' />

                        </content>

                    </request>";



					$responseData = sendToTPro3( $xml );



					$node = NULL;

					findNodeByRefname( $responseData, 'fields', $node );

					if ( $node != NULL ) {

						$field = $node[ count( $node ) - 1 ];

						if ( $field != NULL ) {

							if ( getStatus( $field ) == TRUE ) {

								foreach ( $field as $k => $v ) {

									if ( $k == 'fieldobject' ) {

										foreach ( $v as $key => $val ) {

											if ( is_array( $val["fieldlabel"] ) == FALSE ) {

												$fields_list[ $val["name"] ] = $val["fieldlabel"];

											}

										}

									}

								}

							}

						}

					}



					$node = NULL;

					findNodeByRefname( $responseData, 'accounts', $node );

					$accounts_list[''] = '';

					if ( $node != NULL ) {

						foreach ( $node as $k => $v ) {

							foreach ( $v as $key => $value ) {

								if ( $key == 'activeaccounts' ) {

									if ( @$value["name"] != "" ) {

										$accounts_list[ $value["id"] ] = $value["name"];

									} else {

										foreach ( $value as $k2 => $v2 ) {

											$accounts_list[ $v2["id"] ] = $v2["name"];

										}

									}

								}

							}

						}

					}



					$node = NULL;

					findNodeByRefname( $responseData, 'positemtypes', $node );

					if ( $node != NULL ) {

						foreach ( $node as $k => $v ) {

							foreach ( $v as $key => $value ) {

								if ( $key == 'positemtype' ) {

									foreach ( $value as $k2 => $v2 ) {

										$categories_list[ $v2["name"] ] = $v2["name"];

									}

								}

							}

						}

					}



					$node = NULL;

					findNodeByRefname( $responseData, 'poswarehouses', $node );

					if ( $node != NULL ) {

						foreach ( $node as $k => $v ) {

							foreach ( $v as $key => $value ) {

								if ( $key == 'poswarehouse' ) {

									foreach ( $value as $k2 => $v2 ) {

										$warehouse_list[ $v2["id"] ] = $v2["name"];

									}

								}

							}

						}

					}



					$node = NULL;

					findNodeByRefname( $responseData, 'salesdocumenttypes', $node );

					if ( $node != NULL ) {

						foreach ( $node as $k => $v ) {

							foreach ( $v as $key => $value ) {

								if ( $key == 'salesdocumenttype' ) {

									foreach ( $value as $k2 => $v2 ) {

										$salesdocumenttype_list[ $v2["id"] ] = $v2["name"];

									}

								}

							}

						}

					}

				}

			}



			$this->form_fields = array(

				'enabled'                       => array(

					'title'   => __( 'Enable/Disable', 'tpro3' ),

					'type'    => 'checkbox',

					'label'   => __( 'Enable TPro3 Payment gateway.', 'tpro3' ),

					'default' => 'no'

				),

				'title'                         => array(

					'title'       => __( 'Title:', 'tpro3' ),

					'type'        => 'text',

					'description' => __( 'This controls the title which the user sees during checkout.', 'tpro3' ),

					'default'     => __( 'Credit Card', 'tpro3' )

				),

				'description'                   => array(

					'title'       => __( 'Description:', 'tpro3' ),

					'type'        => 'textarea',

					'description' => __( 'This controls the description which the user sees during checkout.', 'tpro3' ),

					'css'         => 'width:41%',

					'default'     => __( 'Pay securely by Credit or Debit card or internet banking through TPro3 Secure Servers.', 'tpro3' )

				),

				'tpro3_gateway_name'            => array(

					'title'       => __( 'Gateway Name', 'tpro3' ),

					'type'        => 'text',

					'description' => __( 'Provided by 2CP. Name of your TPro3 gateway.' )

				),

				'tpro3_email_address'           => array(

					'title'       => __( 'Email Address', 'tpro3' ),

					'type'        => 'text',

					'description' => __( 'Please enter your email address for your login to TPro3.' )

				),

				'tpro3_password'                => array(

					'title'       => __( 'Password', 'tpro3' ),

					'type'        => 'password',

					'description' => __( 'Please enter your password for your login.' )

				),

				'tpro3_account_name'            => array(

					'title'       => __( 'Account Name', 'tpro3' ),

					'type'        => 'select',

					'description' => __( 'Name of the TPro3 Account for credit card processing.' ),

					'options'     => $accounts_list

				),

				'tpro3_default_customer'        => array(

					'title'       => __( 'Default CustomerID', 'tpro3' ),

					'type'        => 'text',

					'default'     => 'DC001',

					'description' => __( 'Contacts and order will be created under this customer if consolidate is checked below.' )

				),

				'tpro3_customernamefield'       => array(

					'title'       => __( 'Customer Name Field:', 'tpro3' ),

					'type'        => 'select',

					'options'     => $fields_list,

					'description' => __( 'Customer name gets populated here when not creating customers in TPro3.' )

				),

				'tpro3_create_registered'       => array(

					'title'       => __( 'Create Registered Customers', 'tpro3' ),

					'type'        => 'checkbox',

					'default'     => 'yes',

					'description' => __( 'Create unique customers per order in TPro3. (Unchecked will put orders under default CustomerID without a contact)' )

				),

				'tpro3_create_guest'            => array(

					'title'       => __( 'Create Guest Customers', 'tpro3' ),

					'type'        => 'checkbox',

					'default'     => 'yes',

					'description' => __( 'Create unique guest customers per order in TPro3. (Unchecked will put orders under default CustomerID without a contact)' )

				),

				'tpro3_categories'              => array(

					'title'       => __( 'Categories', 'tpro3' ),

					'type'        => 'multiselect',

					'description' => __( 'Name of the Categories for items. Leave blank if none.' ),

					'css'         => 'width:41%',

					'options'     => $categories_list,

				),

				'tpro3_warehouses'              => array(

					'title'       => __( 'Warehouses Name', 'tpro3' ),

					'type'        => 'select',

					'description' => __( 'Name of the warehouse for items. Leave blank if none.' ),

					'options'     => $warehouse_list,

					'description' => __( 'Please fill, Gateway name, Email Address and password, to get warehouse list ' )

				),

				'tpro3_salesdocumenttype'       => array(

					'title'       => __( 'Sales Document Type', 'tpro3' ),

					'type'        => 'select',

					'options'     => $salesdocumenttype_list,

					'description' => __( 'Select the type of Sales Document you would like to create in TPro3.' )

				),

				'tpro3_transactiontype'         => array(

					'title'       => __( 'Transaction Type', 'tpro3' ),

					'type'        => 'select',

					'description' => __( 'Select the Transaction Type' ),

					'options'     => array(

						'sale'          => __( 'Sale', 'tpro3' ),

						'authorization' => __( 'Authorization', 'tpro3' )

					)

				),

				'tpro3_synctiming'              => array(

					'title'       => __( 'Sync Schedule:', 'tpro3' ),

					'type'        => 'select',

					'options'     => $this->scheduled_time_select_list(),

					'description' => __( 'Syncronization for products and inventory.' ),

				),

				'tpro3_defaultshippingclass'    => array(

					'title'       => __( 'Default Shipping Class', 'tpro3' ),

					'type'        => 'select',

					'description' => __( 'Select default shipping class for product sync' ),

					'options'     => $this->getShippingClasses()

				),

				'tpro3_overwriteskudescription' => array(

					'title'       => __( 'Overwrite SKU &amp; Description', 'tpro3' ),

					'type'        => 'checkbox',

					'label'       => __( 'Overwite SKU &amp; Description fields in products.', 'tpro3' ),

					'description' => __( 'If you check this option then yours product SKU and Description fields will be replaced by TPRO product\'s SKU &amp; Description' ),

					'default'     => 'no'

				),

			);

		}



		public function admin_options() {





			echo '<h3>' . __( 'TPro3 Payment Gateway', 'tpro3' ) . '</h3>';

			echo '<p>' . __( 'Now you can process orders through TPro3' ) . '</p>';

			echo '<table class="form-table">';

			// Generate the HTML For the settings form.

			$this->generate_settings_html();

			echo '</table>';



			echo '<h3>' . __( 'TPro3 Product Sync', 'tpro3' ) . '</h3>';

			echo '<a href="#" id="productSync" class="button-primary">Sync TPro3 Products Now <img id="ajaxloader" style="position:relative;top:3px;left:2px;display:none;" src="' . plugin_dir_url( __FILE__ ) . '/images/ajax-loader.gif" alt=""></a>';

		}



		/*

         * Register New cron hook

         *

         */





		function scheduled_time_select_list() {

			$timeList        = array();

			$timeList['0']   = '--Select Sync Time--';

			$timeList['15']  = '15 min';

			$timeList['30']  = '30 min';

			$timeList['45']  = '45 min';

			$timeList['60']  = '1 hour';

			$timeList['120'] = '2 hours';

			$timeList['180'] = '3 hours';

			$timeList['240'] = '4 hours';



			return $timeList;

		}





		/*

         * loadwarehouse list

         *

         */

		function loadwarehouse( $cgatewayname, $cgatewayemail, $cgatewaypassword ) {

			$xml = "<request>

                      <authentication>

                        <user>

                          <gateway>" . $cgatewayname . "</gateway>

                          <emailaddress>" . $cgatewayemail . "</emailaddress>

                          <password>" . $cgatewaypassword . "</password>

                          <application>WooCommerce</application>

                          <version>" . $this->version . "</version>

                        </user>

                      </authentication>

                      <content continueonfailure='true'>

                        <read object='poswarehouse' fields='id,name,modifyon' refname='poswarehouse' />

                      </content>

                    </request>";



			$responseData  = sendToTPro3( $xml );

			$warehouseList = array();



			$node = NULL;

			findNodeByRefname( $responseData, 'poswarehouse', $node );

			$poswarehouse = $node[ count( $node ) - 1 ];

			if ( $poswarehouse != NULL ) {

				if ( getStatus( $poswarehouse ) == TRUE ) {

					foreach ( $poswarehouse as $k => $v ) {

						if ( $k == 'poswarehouse' ) {

							foreach ( $v as $key => $val ) {

								$warehouseList[ $val["id"] ] = $val["name"];

							}

						}

					}

				}

			}



			return ( $warehouseList );

		}



		function loadInventory() {

			global $wpdb;



			$gatewayname     = $this->tpro3_gateway_name;

			$gatewayemail    = $this->tpro3_email_address;

			$gatewaypassword = $this->tpro3_password;

			$warehouse_name  = $this->tpro3_warehouses;





			$InventoryRequest['request'] = array(

				'authentication' => array(

					'user' => array(

						'gateway'      => $gatewayname,

						'emailaddress' => $gatewayemail,

						'password'     => $gatewaypassword,

						'application'  => 'WooCommerce',

						'version'      => $this->version

					)

				),

				'content'        => array(

					'continueonfailure' => 'True',

					'read'              => array(

						'object' => 'posinventory',

						'fields' => 'id,positem.name,poswarehouse.name,onhand,onhold,onorder',

						'query'  => "poswarehouse.id = '" . $warehouse_name . "' "

					)

				)

			);



			/*poswarehouse = "{!'.$warehouse_name.'!}"*/

			$data_string = json_encode( $InventoryRequest );

			$request     = curl_init();

			$options     = array

			(

				CURLOPT_URL            => 'http://api.tpro3.com/json',

				CURLOPT_POST           => TRUE,

				CURLOPT_POSTFIELDS     => $data_string,

				CURLOPT_RETURNTRANSFER => TRUE,

				CURLOPT_HEADER         => 'Content-Type: text/json',

				// CURLOPT_CAINFO => 'cacert.pem',

			);

			curl_setopt_array( $request, $options );



			// Execute request and get response and status code

			$response = curl_exec( $request );

			$data     = json_decode( urldecode( $response ) );

			/*echo '<pre>';

			print_r($data);

			echo '</pre>';*/



			///////////

			////Response recieved or not

			///////////////////////////



			if ( ( $data != NULL ) || ( $data->response != NULL ) ) {

				if ( property_exists( $data->response->content->read, 'posinventory' ) ) {

					$tpro3posinventory_data = $data->response->content->read->posinventory;

					$inventoryItems         = array();



					foreach ( $tpro3posinventory_data as $product_data ) {

						//echo $product_data->onhand;

						$onhand           = $product_data->onhand;

						$inventoryItems[] = $product_data->{'positem.name'};



						$sql = "select post_id from {$wpdb->prefix}postmeta where meta_key='_name' AND meta_value='{$product_data->{'positem.name'}}'";



						$existingProduct = $wpdb->get_results( $sql, ARRAY_A );



						///////////////////////////////

						//Updates the Existing Items in Woocommerce return from response

						////////////////////////////////////////



						if ( count( $existingProduct ) > 0 ) {

							$productPostID = $existing_ID = $existingProduct[0]['post_id'];

							update_post_meta( $productPostID, '_stock_status', 'instock' );

							update_post_meta( $productPostID, '_manage_stock', 'yes' );

							update_post_meta( $productPostID, '_stock', $onhand );

						} else {



						}

					}



					// removing this for now. it updated the non-inventory items. not sure that is what I want //

					//Updates the Existing Woocommerce Items to Zero if its not returned in Inventory Response

					/////////////////////////////////////////





					$sql                      = "select post_id from {$wpdb->prefix}postmeta where meta_key='_name' and meta_value NOT IN ('" . implode( "', '", $inventoryItems ) . "')";

					$inventoryItemNotINResult = $wpdb->get_results( $sql, ARRAY_A );

					if ( count( $inventoryItemNotINResult ) > 0 ) {

						foreach ( $inventoryItemNotINResult as $noInventryItem ) {

							$productPostID = $noInventryItem['post_id'];

							update_post_meta( $productPostID, '_stock_status', 'instock' );

							update_post_meta( $productPostID, '_manage_stock', 'no' );

							update_post_meta( $productPostID, '_stock', 0 );



						}

					}

				} else {

					/////////////////////////

					// This Update the woocommerce product to zero if response has no from ''posinventory' Node in it

					///////////////////////////////////



					// $sql = "select post_id from {$wpdb->prefix}postmeta where meta_key='_name'";

					// $inventoryItemNotINResult = $wpdb->get_results($sql, ARRAY_A);

					// if(count($inventoryItemNotINResult) > 0){

					//     foreach( $inventoryItemNotINResult as $noInventryItem){

					//         $productPostID = $noInventryItem['post_id'];

					//         update_post_meta( $productPostID, '_stock_status', 'outofstock');

					//         update_post_meta( $productPostID, '_manage_stock', 'no' );

					//         update_post_meta( $productPostID, '_stock', 0 );



					//     }

				}

			}

		}



		/**

         *  There are no payment fields for tpro_secure, but we want to show the description if set.

         * */

        /*function payment_fields(){



        if ( $this->supports( 'default_credit_card_form' ) ) {

        echo $this->async_process_card();

        }



        }*/



        /**

         * Payment form on checkout page.

         */

        public function payment_fields() {



            $description = $this->get_description();



            if ($description) {



                echo wpautop(wptexturize(trim($description)));



            }



            echo $this->async_process_card();



            parent::payment_fields();



        }



        function async_process_card() {



            // Get SessionID from TPro3

            $gateway = $this->tpro3_gateway_name;

            $email_address = $this->tpro3_email_address;

            $password = $this->tpro3_password;

            $version = $this->version;



            $xml = "<request>

                <authentication>

                <user>

                    <gateway>".$gateway."</gateway>

                    <emailaddress>".$email_address."</emailaddress>

                    <password>".$password."</password>

                    <application>WooCommerce</application>

                    <version>".$version."</version>

                </user>

                </authentication>

                <content continueonfailure='True'></content>

            </request>";



            $responseData = sendToTPro3( $xml );



            $tpro3_session_id = $responseData['authentication']['sessionid'];

            $_SESSION['tpro3_session_id'] = $tpro3_session_id;



            $create_registered = ($this->tpro3_create_registered == 'yes');

            $create_guest = ($this->tpro3_create_guest == 'yes');

            $order_id = 1; // TEMP VAR

            $user = wp_get_current_user();

            $customer_user = $user->ID;

            if (empty($customer_user)) {

                $user->user_email = '';

            }



            $customername        = '';

            $customerdisplayname = '';

            $customeremail = $user->user_email;



            if ( $customer_user == 0 ) {

                if ( $create_guest ) {

                    $customername = 'SCG' . str_pad( $order_id, 8, '0', STR_PAD_LEFT );

                    $customerdisplayname = '';

                } else {

                    $customername        = $this->settings['tpro3_default_customer'];

                    $customerdisplayname = 'Shopping Cart Customer';

                }

            } else {

                if ( $create_registered ) {

                    $customername = 'SCR' . str_pad( $customer_user, 8, '0', STR_PAD_LEFT );

                    $customerdisplayname = '';

                } else {

                    $customername        = $this->settings['tpro3_default_customer'];

                    $customerdisplayname = 'Shopping Cart Customer';

                }

            }



            $script_vars = 'authSession: "' . $tpro3_session_id . '",';

            $script_vars .= 'authURL: "https://api.tpro3.com/xml",';

            $script_vars .= 'customerName: "' . $customername . '",';

            $script_vars .= 'customerDisplayName: "' . $customerdisplayname . '",';

            $script_vars .= 'customerEmail: "' . $customeremail . '",';

            $script_vars .= 'createRegistered: "' . $create_registered . '",';

            $script_vars .= 'createGuest: "' . $create_guest . '",';

            $script_vars .= 'uniqueCustomerID: "' . uniqid() . '",';



            return "<script>(function( $ ) { window.tpro3_checkout = {" . $script_vars . "}; })( jQuery );</script>";

        }



        function tpro3_checkout_scripts() {



            wp_register_script( 'tpro3-checkout', plugin_dir_url( __FILE__ ) . 'js/tpro3.js', array( 'jquery' ), '1.0', true);

            wp_localize_script( 'tpro3-checkout', 'tpro3_vars', array( 'ajax_url' => admin_url('admin-ajax.php')) );

            wp_enqueue_script( 'tpro3-checkout' );



        }



        /**

         * Receipt Page

         * */

		function receipt_page( $order ) {

			echo '<p>' . __( 'Thank you for your order, please click the button below to pay with tpro_secure.', 'tpro3' ) . '</p>';

			echo $this->generate_tpro3_form( $order );

		}



		/**

         * Process the payment and return the result

         * */

		function process_payment( $order_id ) {

			global $woocommerce;



            $tpro3_session_id = '';



            if (isset($_POST['tpro3_session'])) {

                $tpro3_session_id = $_POST['tpro3_session'];

            } else {

                wc_add_notice( __( 'Something is wrong with the TPro3 auth session.' ), $notice_type = 'error' );

                return;

            }

            if (isset($_POST['tpro3_customerid'])) {

                $tpro3_customerid = $_POST['tpro3_customerid'];

            } else {

                wc_add_notice( __( 'Something is wrong with the TPro3 CustomerID.' ), $notice_type = 'error' );

                return;

            }

            if (isset($_POST['tpro3_contactid'])) {

                $tpro3_contactid = $_POST['tpro3_contactid'];

            } else {

                wc_add_notice( __( 'Something is wrong with the TPro3 ContactID.' ), $notice_type = 'error' );

                return;

            }



			$order           = new WC_Order( $order_id );

			$shipping        = $order->get_shipping_methods();

			$products        = $order->get_items();

			$total           = $order->get_total();

			$tax             = $order->get_total_tax();

			$shipping        = reset( $shipping );

			$shipping_method = $shipping['name'];

			$shipping_amount = $order->get_total_shipping();

			$customer_user   = $order->customer_user;



			/* card information */

			$card_number = str_replace( ' ', '', sanitize_text_field( $_POST['tpro3-card-number'] ) );



			if ( strlen( sanitize_text_field( $_POST['tpro3-card-expiry'] ) ) < 5 ) {

				wc_add_notice( __( 'Something is wrong with the expiration date on your card.' ), $notice_type = 'error' );



				return;

			}



			$month_year = explode( "/", sanitize_text_field( $_POST['tpro3-card-expiry'] ) );

			if ( count( $month_year ) != 2 ) {

				wc_add_notice( __( 'Something is wrong with the expiration date on your card.' ), $notice_type = 'error' );



				return;

			}





			$month = (int) str_replace( ' ', '', $month_year[0] );

			$year  = (int) str_replace( ' ', '', $month_year[1] );

			$year  = '20' . substr( $year, - 2 );

			$cvc   = sanitize_text_field( $_POST['tpro3-card-cvc'] );



			if ( strlen( $cvc ) == 0 ) {

				wc_add_notice( __( 'Card security code is required.' ), $notice_type = 'error' );



				return;

			}



			if (isset($_POST['tpro3_payment_session'])) {



                $tpro3_payment_session = $_POST['tpro3_payment_session'];



            } else {

                wc_add_notice( __( 'Something is wrong with the TPro3 payment session.' ), $notice_type = 'error' );



                return;

            }





			/* Billing information */

			$B_address     = $order->billing_address_1;

			$B_address2    = $order->billing_address_2;

			$B_postal_code = $order->billing_postcode;

			$B_country     = $order->billing_country;

			if ( $B_country == 'US' ) {

				$B_country = "United States";

			}

			if ( $B_country == 'CA' ) {

				$B_country = "Canada";

			}

			$B_first_name        = $order->billing_first_name;

			$B_last_name         = $order->billing_last_name;

			$B_organisation_name = $order->billing_company;

			$B_state             = $order->billing_state;

			$B_city              = $order->billing_city;

			$B_email             = $order->billing_email;

			$B_phone             = $order->billing_phone;



			/* Shipping information */

			$S_address     = $order->shipping_address_1;

			$S_address2    = $order->shipping_address_2;

			$S_postal_code = $order->shipping_postcode;

			$S_country     = $order->shipping_country;

			if ( $S_country == 'US' ) {

				$S_country = "United States";

			}

			if ( $S_country == 'CA' ) {

				$S_country = "Canada";

			}

			$S_first_name        = $order->shipping_first_name;

			$S_last_name         = $order->shipping_last_name;

			$S_organisation_name = $order->shipping_company;

			$S_state             = $order->shipping_state;

			$S_city              = $order->shipping_city;



			$xml = "<request>

                      <authentication>

                        <sessionid>" . $tpro3_session_id . "</sessionid>

                      </authentication>

                      <content continueonfailure='true'>";



			$customername        = '';

			$customerdisplayname = '';



			if ( $customer_user == 0 ) {

				if ( $this->tpro3_create_guest == 'yes' ) {

					$customername = 'SCG' . str_pad( $order_id, 8, '0', STR_PAD_LEFT );

					if ( strlen( trim( $B_organisation_name ) ) == 0 ) {

						$customerdisplayname = $B_first_name . ' ' . $B_last_name;

					} else {

						$customerdisplayname = $B_organisation_name;

					}

				} else {

					$customername        = $this->settings['tpro3_default_customer'];

					$customerdisplayname = 'Shopping Cart Customer';

				}

			} else {

				if ( $this->tpro3_create_registered == 'yes' ) {

					$customername = 'SCR' . str_pad( $customer_user, 8, '0', STR_PAD_LEFT );

					if ( strlen( trim( $B_organisation_name ) ) == 0 ) {

						$customerdisplayname = $B_first_name . ' ' . $B_last_name;

					} else {

						$customerdisplayname = $B_organisation_name;

					}

				} else {

					$customername        = $this->settings['tpro3_default_customer'];

					$customerdisplayname = 'Shopping Cart Customer';

				}

			}





			if ( ( strlen( $customername ) == 0 ) || ( strlen( $customerdisplayname ) == 0 ) ) {

				wc_add_notice( __( 'Whoops. The setup for the TPro3 payment is gateway is incorrect. The default customerid must be set.' ), $notice_type = 'error' );



				return;

			}



            $xml = $xml . "<read refname='customer_read' object='customer' fields='id' query=\"name = '".$customername."'\"/>

                           <if condition=\"ISNULL({!customer_read.id!},'') = '')\">

			                 <update>

                               <customer refname='customer'>

                                 <name>" . $customername . "</name>

                                 <displayname>" . $customerdisplayname . "</displayname>

                                 <id>" . $tpro3_customerid . "</id>

                               </customer>

                             </update>

                           </if>";

            if ( ( $this->tpro3_create_guest == 'yes' ) || ( $this->tpro3_create_registered == 'yes' ) ) {

				$xml = $xml . "<read refname='contact_read' object='contact' fields='id' query=\"name = 'B_".$customername."'\"/>

                               <if condition=\"ISNULL({!contact_read.id!},'') = '')\">

                              <update>

                                <contact refname=\"billcontact\">

                                  <id>" . $tpro3_contactid . "</id>

                                  <name>B_".$customername."</name>

                                  <customer>".$customername."</customer>

                                  <contacttype>billing</contacttype>

                                  <companyname>" . $B_organisation_name . "</companyname>

                                  <firstname>" . $B_first_name . "</firstname>

                                  <lastname>" . $B_last_name . "</lastname>

                                  <address1>" . $B_address . "</address1>

                                  <address2>" . $B_address2 . "</address2>

                                  <city>" . $B_city . "</city>

                                  <state>" . $B_state . "</state>

                                  <zipcode>" . $B_postal_code . "</zipcode>

                                  <country>" . $B_country . "</country>

                                  <phone1>" . $B_phone . "</phone1>

                                  <cellphone></cellphone>

                                  <email1>" . $B_email . "</email1>

                                </contact>

                              </update>

                            </if>";



                // Move Stored Account to existing customer/contact if needed //

                $xml = $xml."<update>

                               <storedaccount refname='sa'>

                                 <customer>".$customername."</customer>

                                 <contact>B_".$customername."</contact>

                                 <id>".$tpro3_payment_session."</id>

                               </storedaccount>

                             </update>";



				if ( ( isset( $_POST['ship_to_different_address'] ) ) && ( sanitize_text_field( $_POST['ship_to_different_address'] ) == '1' ) ) {

					$xml = $xml . "<if condition=\"{!customer.responsestatus!} = 'success'\">

								  <update>

									<contact refname=\"shipcontact\">

									  <id>" . $tpro3_contactid . "</id>

                                      <name>S_".$customername."}</name>

									  <customer>{!customer.id!}</customer>

									  <contacttype>shipping</contacttype>

									  <companyname>" . $S_organisation_name . "</companyname>

									  <firstname>" . $S_first_name . "</firstname>

									  <lastname>" . $S_last_name . "</lastname>

									  <address1>" . $S_address . "</address1>

									  <address2>" . $S_address2 . "</address2>

									  <city>" . $S_city . "</city>

									  <state>" . $S_state . "</state>

									  <zipcode>" . $S_postal_code . "</zipcode>

									  <country>" . $S_country . "</country>

									  <phone1>" . $B_phone . "</phone1>

									  <cellphone></cellphone>

									  <email1>" . $B_email . "</email1>

									</contact>

								  </update>

								</if>";

				}

			}



			$xml = $xml . "<create>

                             <salesdocument refname=\"invoice\">

                               <name>SC" . str_pad( $order_id, 8, '0', STR_PAD_LEFT ) . "</name>

                               <salesdocumenttype>" . $this->settings['tpro3_salesdocumenttype'] . "</salesdocumenttype>

                               <customer>".$customername."</customer>

                               <dueon>" . gmdate( 'c', time() ) . "</dueon>";



			if ( $this->tpro3_customernamefield != "" ) {

				$cnf = $B_first_name . " " . $B_last_name;

				if ( $B_organisation_name != "" ) {

					$cnf = $cnf . ", " . $B_organisation_name;

				}

				$xml = $xml . "<" . $this->tpro3_customernamefield . ">" . $cnf . "</" . $this->tpro3_customernamefield . ">";

			}



			$xml = $xml . "<lineitems>";



			foreach ( $products as $item_id => $product ) {

				$_line_subtotal = wc_get_order_item_meta( $item_id, '_line_subtotal' );

				$product_id = $product['product_id'];

				$wc_product = new WC_Product( $product_id );



				if ( $wc_product->is_type( 'subscription' ) || $wc_product->is_type( 'variable-subscription' ) ) {

					if ( is_user_logged_in() ) {



					} else {

						wc_add_notice( 'Failure. Please Login to buy subscription products.', $notice_type = 'error' );

					}

				}



				$order_item = $order->get_product_from_item( $product );



                $product_name = $order_item->get_attribute( 'Name' );

                if ($product_name == '')

                    $product_name = 'WP'.$product_id;

				$xml = $xml . "<lineitem>
                               <itemname>" . $product_name . "</itemname>
                               <itemdisplayname>" . $product['name'] . "</itemdisplayname>
                               <quantity>" . $product->get_quantity() . "</quantity>
			                   <price>" . $order_item->get_price() . "</price>";
                               //<price>".$_line_subtotal."</price>
                               //<price>" . $order->get_product_price( $wc_product ) . "</price>

                if ( $this->tpro3_warehouses != "" ) {

					$xml = $xml . "<poswarehouse>" . $this->tpro3_warehouses . "</poswarehouse>";

				}

				if ( $order_item->get_attribute( 'PosItemID' ) != "" ) {

					$xml = $xml . "<positem>" . $order_item->get_attribute( 'PosItemID' ) . "</positem>";

				}

				$xml = $xml . "</lineitem>";

			}

			$xml = $xml . "</lineitems>";

			if ( ( $tax > 0 ) || ( $shipping_amount > 0 ) ) {

				$xml = $xml . "<subtotals>";

			}

			if ( $tax > 0 ) {

				$xml = $xml . "<subtotal>

                               <subtotaltype>Tax</subtotaltype>

                               <amount>" . $tax . "</amount>

                             </subtotal>";

			}

			if ( $shipping_amount > 0 ) {

				$xml = $xml . "<subtotal>

                               <subtotaltype>Shipping</subtotaltype>

                               <amount>" . $shipping_amount . "</amount>

                             </subtotal>";

			}

			if ( ( $tax > 0 ) || ( $shipping_amount > 0 ) ) {

				$xml = $xml . "</subtotals>";

			}



			$xml = $xml . "         </salesdocument>

                             </create>

                             <if condition=\"{!invoice.responsestatus!} = 'success'\">

                               <create>

                                 <transaction refname='auth'>

                                   <account>" . $this->settings['tpro3_account_name'] . "</account>

                                   <salesdocument>{!invoice.id!}</salesdocument>

                                   <amount>" . $order->get_total() . "</amount>

                                   <transactiontype>" . $this->settings['tpro3_transactiontype'] . "</transactiontype>

                                   <description>Shopping cart order</description>

                                   <customer>".$customername."</customer>";



			if ( ( ( $this->tpro3_create_guest == 'yes' ) && ( $customer_user == 0 ) ) || ( ( $this->tpro3_create_registered == 'yes' ) && ( $customer_user != 0 ) ) ) {

				$xml = $xml . "<contact>B_".$customername."</contact>\n";

			}



			$xml = $xml . "<storedaccount>

                                    <customer>".$customername."</customer>

                                    <contact>B_".$customername."</contact>

                                    <id>".$tpro3_payment_session."</id>

                                  </storedaccount>

                                 </transaction>

                               </create>

                             </if>

                             <if condition=\"{!auth.responsestatus!} = 'success'\">

                               <update>

                                 <salesdocument>

                                   <id>{!invoice.id!}</id>

                                   <status>Finalized</status>

                                 </salesdocument>

                               </update>

                             </if>

                             <if condition=\"{!auth.responsestatus!} != 'success'\">

                               <delete>

                                 <salesdocument>

                                   <id>{!invoice.id!}</id>

                                 </salesdocument>

                               </delete>

                             </if>

                             <delete>

                               <storedaccount>

                                 <id>".$tpro3_payment_session."</id>

                               </storedaccount>

                             </delete>

                           </content>

                         </request>";



			$responseData = sendToTPro3( $xml );



			//STORE TPRO CUSTOMER ID TO USERMETA FOR RECURRING PAYMENTS

			$user_id = $order->customer_user;

			$node    = NULL;

			findNodeByRefname( $responseData, 'customer', $node );

			$customer_id = $node[ count( $node ) - 1 ];

            if ( $customer_id != NULL ) {

                $customer_id = $customer_id['id'];

                $meta_key    = 'tpro_customer_id';

                $meta_value  = $customer_id;

                update_user_meta( $user_id, $meta_key, $meta_value );

            }



			$node = NULL;

			findNodeByNodename( $responseData, 'authentication', $node );

			$authenticationnode = $node[ count( $node ) - 1 ];

			if ( $authenticationnode != NULL ) {

				if ( getStatus( $authenticationnode ) != TRUE ) {

					getErrors( $authenticationnode );

				}

			}



			// check customer create/update //

			$node = NULL;

			findNodeByRefname( $responseData, 'customer', $node );

			$customernode = $node[ count( $node ) - 1 ];

			if ( $customernode != NULL ) {

				if ( getStatus( $customernode ) != TRUE ) {

					getErrors( $customernode );

				}

			}



			// check contact billing create/update //

			$node = NULL;

			findNodeByRefname( $responseData, 'billcontact', $node );

			$billcontactnode = $node[ count( $node ) - 1 ];

			if ( $billcontactnode != NULL ) {

				if ( getStatus( $billcontactnode ) != TRUE ) {

					getErrors( $billcontactnode );

				}

			}



			// check create salesdocument //

			$node = NULL;

			findNodeByRefname( $responseData, 'invoice', $node );

			$salesdocnode = $node[0];

			if ( $salesdocnode != NULL ) {

				if ( getStatus( $salesdocnode ) != TRUE ) {

					$sdErrs = getErrors( $salesdocnode, FALSE );

					foreach ( $sdErrs as $e ) {

						$invoicenumber = "SC" . str_pad( $order_id, 8, '0', STR_PAD_LEFT );

						if ( strpos( $e, $invoicenumber . ' already exists in object salesdocument' ) === 0 ) {

							wc_add_notice( 'Failure. Please Retry.', $notice_type = 'error' );

							$xml = "<request>

                                      <authentication>

                                        <user>

                                          <gateway>" . $this->settings['tpro3_gateway_name'] . "</gateway>

                                          <emailaddress>" . $this->settings['tpro3_email_address'] . "</emailaddress>

                                          <password>" . $this->settings['tpro3_password'] . "</password>

                                          <application>WooCommerce</application>

                                          <version>" . $this->version . "</version>

                                        </user>

                                      </authentication>

                                      <content continueonfailure='true'>

                                        <read object='salesdocument' fields='id' query=\"((status.name='Finalized') AND (name='" . $invoicenumber . "'))\" refname='sdoc'>

                                        <delete>

                                          <salesdocument refname='sd'>

                                            <id>{!sdoc.id!}</id>

                                          </salesdocument>

                                        </delete>

                                        </read>

                                      </content>

                                    </request>";



							$responseData = sendToTPro3( $xml );





							$node = NULL;

							findNodeByNodename( $responseData, 'sd', $node );

							$sddeletes = $node[ count( $node ) - 1 ];

							if ( $sddeletes != NULL ) {

								if ( getStatus( $sddeletes ) != TRUE ) {

									getErrors( $sddeletes );

								}

							}



						}

					}

				}

			}



			// check create transaction //

			$node = NULL;

			findNodeByRefname( $responseData, 'auth', $node );



			$transactionnode = $node[0];

			if ( $transactionnode != NULL ) {

				if ( getStatus( $transactionnode ) == TRUE ) {

					wc_add_notice( __( 'Successfully Created Transaction.<br/>Authorization Code: ' . $transactionnode["authorizationcode"] ), $notice_type = 'success' );



					$order->add_order_note( sprintf( __( '<b>Payment Approved</b><br/>ID: %s<br/>Auth Code: %s<br/>AVS: %s<br/>CVV: %s', 'woocommerce' ), $transactionnode["id"], $transactionnode["authorizationcode"], $transactionnode["avsresponse"], $transactionnode["cvvresponse"] ) );



					$order->payment_complete( $transactionnode["id"] );

					$order->update_status( 'completed', __( 'Transaction successful', 'woocommerce' ) );



					return array(

						'result'   => 'success',

						'redirect' => $this->get_return_url( $order )

					);

				} else {

					getErrors( $transactionnode );

				}

			}

		}



		function tpro_Item_load() {

			global $wpdb;



			$gatewayname                 = $this->tpro3_gateway_name;

			$gatewayemail                = $this->tpro3_email_address;

			$gatewaypassword             = $this->tpro3_password;

			$categoriesList_to_pull_data = $this->tpro3_categories;



			if ( $gatewayname != '' &&

			     $gatewayemail != '' &&

			     $gatewaypassword != ''

			) {



				$SendRequest['request'] = array(

					'authentication' => array(

						'user' => array(

							'gateway'      => $gatewayname,

							'emailaddress' => $gatewayemail,

							'password'     => $gatewaypassword,

							'application'  => 'WooCommerce Cron',

							'version'      => $this->version

						)

					),

					'content'        => array(

						'continueonfailure' => 'True',

						'read'              => array(

							'object' => 'positem',

							'fields' => 'id,name,positemtype.name,isnoninventory,modifyon,price,status.name,displayname,description,istaxable,sku',

							'query'  => " positemtype.name IN ('" . implode( "','", $categoriesList_to_pull_data ) . "')"

						)

					)

				);



				$data_string = json_encode( $SendRequest );

				$request     = curl_init();

				$options     = array

				(

					CURLOPT_URL            => 'https://api.tpro3.com/json',

					CURLOPT_POST           => TRUE,

					CURLOPT_POSTFIELDS     => $data_string,

					CURLOPT_RETURNTRANSFER => TRUE,

					CURLOPT_HEADER         => 'Content-Type: text/json',

					// CURLOPT_CAINFO => 'cacert.pem',

				);

				curl_setopt_array( $request, $options );



				// Execute request and get response and status code

				$response   = curl_exec( $request );

				$data       = json_decode( urldecode( $response ) );

				$tpro3_data = $data->response->content->read->positem;

				$i          = 1;



				$productlist = array();



				foreach ( $tpro3_data as $product_data ) {

					$productlist[] = $product_data->name;





					if ( $product_data->{'status.name'} == 'Active' ) {

						$postStatus = 'publish';

					} else {

						//$postStatus = 'draft';

					}



					$sql = "select post_id from {$wpdb->prefix}postmeta where meta_key='_tproID' ANd meta_value='{$product_data->id}'";



					$existingProduct = $wpdb->get_results( $sql, ARRAY_A );

					$existing_ID     = $existingProduct[0]['post_id'];



					$postStatus = 'publish';

					if ( $this->tpro3_overwriteskudescription == 'yes' ) {

						$product_post = array(

							'post_title'   => $product_data->displayname,

							'post_content' => $product_data->description,

							'post_status'  => $postStatus,

							'post_author'  => 1,

							'post_type'    => 'product'

						);

					} else {

						$product_post = array(

							'post_title'  => $product_data->displayname,

							'post_status' => $postStatus,

							'post_author' => 1,

							'post_type'   => 'product'

						);

					}

					$productPostID = '';

					if ( $existing_ID ) {

						$product_post['ID'] = $existing_ID;

						wp_update_post( $product_post );

						$productPostID = $existing_ID;

					} else {

						$productPostID = wp_insert_post( $product_post );

					}



					if ( $product_data->isnoninventory == 'True' ) {

						$manage_stock = 'no';

						$stock_status = 'instock';

					} else {

						$manage_stock = 'yes';

						$stock_status = 'instock';

					}



					if ( $product_data->istaxable == 'True' ) {

						$tax_status = 'taxable';

					} else {

						$tax_status = 'no';

					}

					wp_set_object_terms( $productPostID, array( $product_data->{'positemtype.name'} ), 'product_cat' );

					//UPDATE SKU AND NAME OF PRODUCT IF SKU & NAME OPTION IS TURN ON ON BACKEND

					if ( $this->tpro3_overwriteskudescription == 'yes' ) {

						update_post_meta( $productPostID, '_sku', $product_data->sku );

					}

					update_post_meta( $productPostID, '_name', $product_data->name );

					update_post_meta( $productPostID, '_visibility', 'visible' );

					update_post_meta( $productPostID, '_stock_status', $stock_status );

					update_post_meta( $productPostID, 'total_sales', '0' );

					update_post_meta( $productPostID, '_regular_price', $product_data->price );

					update_post_meta( $productPostID, '_price', $product_data->price );

					update_post_meta( $productPostID, '_manage_stock', $manage_stock );

					update_post_meta( $productPostID, '_tproID', $product_data->id );

					update_post_meta( $productPostID, '_tax_status', $tax_status );

					//update_post_meta( $productPostID, '_shipping_class', $this->tpro3_defaultshippingclass);

					wp_set_object_terms( $productPostID, $this->tpro3_defaultshippingclass, 'product_shipping_class' );



					/*Update Attribute values*/

					$product_positemid_attribute_metadata = array(

						'positemid' => array(

							'name'         => 'PosItemID',

							'value'        => $product_data->id,

							'position'     => 0,

							'is_visible'   => 1,

							'is_variation' => 0,

							'is_taxonomy'  => 0

						),

						'name'      => array(

							'name'         => 'Name',

							'value'        => $product_data->name,

							'position'     => 0,

							'is_visible'   => 0,

							'is_variation' => 0,

							'is_taxonomy'  => 0

						),

					);

					update_post_meta( $productPostID, '_product_attributes', $product_positemid_attribute_metadata );

				}





				/*disabled the products that are not in the call*/



				$sql                   = "Select post_id from {$wpdb->prefix}postmeta where meta_key='_name' and meta_value NOT IN('" . implode( "','", $productlist ) . "')";

				$disabled_product_list = $wpdb->get_results( $sql );

				$plist                 = array();



				foreach ( $disabled_product_list as $list ) {

					$plist[] = $list->post_id;

				}

				$sql = "Update {$wpdb->prefix}posts set post_status='draft' where ID IN('" . implode( "','", $plist ) . "')";

				//$wpdb->query( $sql );



				echo '<div class="updated">

				<p>' . __( count( $tpro3_data ) . ' Product Added', 'tpro3' ) . ' </p>

			  	</div>';



			}





		}



		function getShippingClasses() {

			$ship                = new WC_Shipping();

			$shippers            = $ship->get_shipping_classes();

			$shippingclasses     = array();

			$shippingclasses[''] = 'None';

			foreach ( $shippers as $key => $method ) {

				$shippingclasses[ $method->name ] = $method->name; //$method->term_id

			}



			return ( $shippingclasses );

		}

	}/* WC_tpro3 Class */



	/**

     * Add the Gateway to WooCommerce

     * */

	function woocommerce_add_tpro3_gateway( $methods ) {

		$methods[] = 'WC_tpro3';



		return $methods;

	}



	add_filter( 'woocommerce_payment_gateways', 'woocommerce_add_tpro3_gateway' );

}



function activate_tpro3cron() {

	global $wpdb;

	if ( ! wp_next_scheduled( 'tpro3cronjob' ) ) {

		wp_schedule_event( time(), 'hourly', 'tpro3cronjob' );

	}



	if ( ! wp_next_scheduled( 'tpro3CronJobCustom' ) ) {

		wp_schedule_event( time(), 'customtime', 'tpro3CronJobCustom' );

	}





}



function dotpro3CronJob() {



	$tproObject = new WC_tpro3;

	$tproObject->tpro_Item_load();

	$tproObject->loadInventory();



}



function my_deactivation() {

	wp_clear_scheduled_hook( 'tpro3cronjob' );

	wp_clear_scheduled_hook( 'tpro3CronJobCustom' );



}



function my_action_javascript() {

?>

  <script type="text/javascript">

      jQuery(document).ready(function ($) {



          jQuery("#woocommerce_tpro3_password").blur(function (e) {



              var warehousesCount = jQuery('#woocommerce_tpro3_tpro3warehouses option').length;



              if (warehousesCount == 0) {

                  var gatewayname = jQuery("#woocommerce_tpro3_gateway_name").val();

                  var gatewayemail = jQuery("#woocommerce_tpro3_email_address").val();

                  var gatewaypassword = jQuery("#woocommerce_tpro3_password").val();



                  var data = {

                      'action': 'my_action',

                      'gatewayname': gatewayname,

                      'gatewayemail': gatewayemail,

                      'gatewaypassword': gatewaypassword

                  };



                  // since 2.8 ajaxurl is always defined in the admin header and points to admin-ajax.php

                  jQuery.post(ajaxurl, data, function (response) {

                      //console.log(response);

                      jQuery('#woocommerce_tpro3_tpro3warehouses').html(response);

                      //alert('Got this from the server: ' + response);

                  });

              }





          });





          /*Sync Product Button Action*/

          jQuery("#productSync").click(function (e) {

              e.preventDefault();

              jQuery("#ajaxloader").show();

              var gatewayname = jQuery("#woocommerce_tpro3_tpro3_gateway_name").val();

              var gatewayemail = jQuery("#woocommerce_tpro3_tpro3_email_address").val();

              var gatewaypassword = jQuery("#woocommerce_tpro3_tpro3_password").val();



              var data = {

                  'action': 'my_action_load_product',

                  'gatewayname': gatewayname,

                  'gatewayemail': gatewayemail,

                  'gatewaypassword': gatewaypassword

              };



              // since 2.8 ajaxurl is always defined in the admin header and points to admin-ajax.php

              jQuery.post(ajaxurl, data, function (response) {

                  //console.log(response);

                  jQuery("#ajaxloader").hide();

                  //alert('Got this from the server: ' + response);

              });



          });



      });

  </script>

	<?php

}



function my_action_callback() {



	$gatewayname     = $_POST['gatewayname'];

	$gatewayemail    = $_POST['gatewayemail'];

	$gatewaypassword = $_POST['gatewaypassword'];



	if ( $gatewaypassword != '' && $gatewayemail != '' && $gatewaypassword != '' ) {



		$tpro3          = new WC_tpro3;

		$wharehouseList = $tpro3->loadwarehouse( $gatewayname, $gatewayemail, $gatewaypassword );



		$warehouseOptions = '';

		foreach ( $wharehouseList as $key => $warehouse ) {

			$warehouseOptions .= '<option value="' . $key . '">' . $warehouse . '</option>';

		}

		echo $warehouseOptions;

	}

	wp_die(); // this is required to terminate immediately and return a proper response

}



function my_action_load_product() {



	$gatewayname     = $_POST['gatewayname'];

	$gatewayemail    = $_POST['gatewayemail'];

	$gatewaypassword = $_POST['gatewaypassword'];



	if ( $gatewaypassword != '' && $gatewayemail != '' && $gatewaypassword != '' ) {



		$tpro3 = new WC_tpro3;

		$tpro3->tpro_Item_load();

		$tpro3->loadInventory();

	}

	wp_die(); // this is required to terminate immediately and return a proper response

}



function dotpro3CronJobCustom() {

	$tpro3 = new WC_tpro3;

	$tpro3->tpro_Item_load();

	$tpro3->loadInventory();

}



function register_custom_schedule( $schedules ) {

	$tpro3_synctiming = get_option( 'woocommerce_tpro3_settings' );

	if ( ( isset( $tpro3_synctiming['tpro3_synctiming'] ) == FALSE ) || ( $tpro3_synctiming['tpro3_synctiming'] == '' ) ) {

		@$ctime = 60 * 60;

	} else {

		@$ctime = 60 * $tpro3_synctiming['tpro3_synctiming'];

	}

	$schedules['customtime'] = array(

		'interval' => $ctime,

		'display'  => __( 'TPro3 Custom Interval' )

	);



	return $schedules;



}



function findNodeByNodename( $data, $nodename, &$node ) {

	foreach ( $data as $myObjectKey => $myObjectValue ) {

		if ( ( (string) $myObjectKey ) == $nodename ) {

			if ( is_array( $myObjectValue ) ) {

				$node[] = $myObjectValue;

			}

		} elseif ( is_array( $myObjectValue ) ) {

			foreach ( $myObjectValue as $key => $val ) {

				if ( is_array( $val ) ) {

					findNodeByNodename( $val, $nodename, $node );

				}

			}

		}

	}



	return ( FALSE );

}



function findNodeByRefname( $data, $refname, &$node ) {

	foreach ( $data as $myObjectKey => $myObjectValue ) {

		if ( is_array( $myObjectValue ) ) {

			foreach ( $myObjectValue as $key => $val ) {

				if ( is_array( $val ) ) {

					if ( $key == '@attributes' ) {

						foreach ( $val as $key => $val ) {

							if ( ( $key == 'refname' ) && ( $val == $refname ) ) {

								$node[] = $myObjectValue;

							}

						}

					}

				}

			}

			findNodeByRefname( $myObjectValue, $refname, $node );

		}

	}



	return ( FALSE );

}



function getStatus( $data ) {

	foreach ( $data as $key => $val ) {

		if ( is_array( $val ) ) {

			if ( $key == '@attributes' ) {

				foreach ( $val as $key => $val ) {

					if ( ( $key == 'responsestatus' ) && ( $val == 'success' ) ) {

						return ( TRUE );

					}

				}

			}

		}

	}



	return ( FALSE );

}



function getErrors( $data, $addnotice = FALSE ) {

	foreach ( $data as $key => $val ) {

		if ( is_array( $val ) ) {

			if ( $key == 'errors' ) {

				if ( is_array( $val ) ) {

					foreach ( $val as $key => $val ) {

						if ( is_array( $val ) ) {

							if ( $key == 'error' ) {

								if ( is_array( $val ) ) {

									$errnum  = '';

									$errdesc = '';

									foreach ( $val as $key => $val ) {

										if ( $key == 'number' ) {

											$errnum = $val;

										} elseif ( $key == 'description' ) {

											$errdesc = $val;

										}

									}

									if ( $addnotice == TRUE ) {

										wc_add_notice( __( $errdesc . ' (' . $errnum . ')' ), $notice_type = 'error' );

									}

									$errs[] = $errdesc . ' (' . $errnum . ')';

								}

							}

						}

					}

				}

			}

		}

	}



	return ( $errs );

}



function sendToTPro3( $xml ) {

	$ch = curl_init();

	curl_setopt( $ch, CURLOPT_URL, 'https://api.tpro3.com/xml' );

	curl_setopt( $ch, CURLOPT_HEADER, 1 );

	curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, 0 );

	curl_setopt( $ch, CURLOPT_SSL_VERIFYHOST, 0 );

	curl_setopt( $ch, CURLOPT_RETURNTRANSFER, 1 );

	curl_setopt( $ch, CURLOPT_CUSTOMREQUEST, 'POST' );

	curl_setopt( $ch, CURLOPT_POST, 1 );

	curl_setopt( $ch, CURLOPT_POSTFIELDS, $xml );

	curl_setopt( $ch, CURLOPT_HTTPHEADER, array(

		'Content-type: application/xml',

		'Content-length: ' . strlen( $xml )

	) );

	try {

		$output = curl_exec( $ch );

	}
    catch ( Exception $e ) {

		wc_add_notice( 'Exception Occurred: ' . $e->getMessage(), $notice_type = 'error' );



		return;

	}

	curl_close( $ch );



	preg_match_all( '/<response>(.*?)<\/response>/s', $output, $matches );

	$data         = @$matches[1][0];

	$xmlData      = "<response>" . $data . "</response>";

	$resxml       = simplexml_load_string( $xmlData );

	$json         = json_encode( $resxml );

	$responseData = json_decode( $json, TRUE );



	return ( $responseData );

}



function logme( $val ) {

	wc_add_notice( $val, $notice_type = 'error' );

}



//AUTO RENEWAL FUNCTION

function scheduled_subscription_payment( $amount_to_charge, $renewal_order ) {

	$user_id           = $renewal_order->user_id;

	$tpro3_customer_id = get_user_meta( $user_id, 'tpro3_customer_id', TRUE );

	$response          = $this->process_recurring_payment( $renewal_order, $tpro3_customer_id );



	/*if ( is_wp_error( $response ) ) {

    $renewal_order->update_status( 'failed', sprintf( __( 'TPRO Transaction Failed (%s)', 'woocommerce-gateway-transaction-pro' ), $response->get_error_message() ) );

	}else{



	}*/

}





/**

 * Process the recurring payment and return the result

 * */

function process_recurring_payment( $order, $tpro3_customer_id ) {

	global $woocommerce;



	//$order = new WC_Order($order_id);

	$shipping        = $order->get_shipping_methods();

	$products        = $order->get_items();

	$total           = $order->get_total();

	$tax             = $order->get_total_tax();

	$shipping        = reset( $shipping );

	$shipping_method = $shipping['name'];

	$shipping_amount = $order->get_total_shipping();

	$customer_user   = $order->customer_user;



	/* card information */

	/*$card_number = str_replace(' ', '', sanitize_text_field($_POST['tpro3-card-number']));



    if (strlen(sanitize_text_field($_POST['tpro3-card-expiry'])) < 5) {

    wc_add_notice(__('Something is wrong with the expiration date on your card.'), $notice_type = 'error' );

    return;

    }



    $month_year = explode("/", sanitize_text_field($_POST['tpro3-card-expiry']));

    if (count($month_year) != 2) {

    wc_add_notice(__('Something is wrong with the expiration date on your card.'), $notice_type = 'error' );

    return;

    }





    $month = (int) str_replace(' ', '', $month_year[0]);

    $year = (int) str_replace(' ', '', $month_year[1]);

    $year = '20'.substr($year, -2);

    $cvc = sanitize_text_field($_POST['tpro3-card-cvc']);



    if (strlen($cvc) == 0) {

    wc_add_notice(__('Card security code is required.'), $notice_type = 'error' );

    return;

    }*/





	/* Billing information */

	$B_address     = $order->billing_address_1;

	$B_address2    = $order->billing_address_2;

	$B_postal_code = $order->billing_postcode;

	$B_country     = $order->billing_country;

	if ( $B_country == 'US' ) {

		$B_country = "United States";

	}

	if ( $B_country == 'CA' ) {

		$B_country = "Canada";

	}

	$B_first_name        = $order->billing_first_name;

	$B_last_name         = $order->billing_last_name;

	$B_organisation_name = $order->billing_company;

	$B_state             = $order->billing_state;

	$B_city              = $order->billing_city;

	$B_email             = $order->billing_email;



	/* Shipping information */

	$S_address     = $order->shipping_address_1;

	$S_address2    = $order->shipping_address_2;

	$S_postal_code = $order->shipping_postcode;

	$S_country     = $order->shipping_country;

	if ( $S_country == 'US' ) {

		$S_country = "United States";

	}

	if ( $S_country == 'CA' ) {

		$S_country = "Canada";

	}

	$S_first_name        = $order->shipping_first_name;

	$S_last_name         = $order->shipping_last_name;

	$S_organisation_name = $order->shipping_company;

	$S_state             = $order->shipping_state;

	$S_city              = $order->shipping_city;

	$S_email             = $order->shipping_email;



	$xml = "<request>

                      <authentication>

                        <user>

                          <gateway>" . $this->tpro3_gateway_name . "</gateway>

                          <emailaddress>" . $this->tpro3_email_address . "</emailaddress>

                          <password>" . $this->tpro3_password . "</password>

                          <application>WooCommerce</application>

                          <version>" . $this->version . "</version>

                        </user>

                      </authentication>

                      <content continueonfailure='true'>";



	$customername        = '';

	$customerdisplayname = '';



	if ( $customer_user == 0 ) {

		if ( $this->tpro3_create_guest == 'yes' ) {

			$customername = 'SCG' . str_pad( $order_id, 8, '0', STR_PAD_LEFT );

			if ( strlen( trim( $B_organisation_name ) ) == 0 ) {

				$customerdisplayname = $B_first_name . ' ' . $B_last_name;

			} else {

				$customerdisplayname = $B_organisation_name;

			}

		} else {

			$customername        = $this->settings['tpro3_default_customer'];

			$customerdisplayname = 'Shopping Cart Customer';

		}

	} else {

		if ( $this->tpro3_create_registered == 'yes' ) {

			$customername = 'SCR' . str_pad( $customer_user, 8, '0', STR_PAD_LEFT );

			if ( strlen( trim( $B_organisation_name ) ) == 0 ) {

				$customerdisplayname = $B_first_name . ' ' . $B_last_name;

			} else {

				$customerdisplayname = $B_organisation_name;

			}

		} else {

			$customername        = $this->settings['tpro3_default_customer'];

			$customerdisplayname = 'Shopping Cart Customer';

		}

	}





	if ( ( strlen( $customername ) == 0 ) || ( strlen( $customerdisplayname ) == 0 ) ) {

		wc_add_notice( __( 'Whoops. The setup for the TPro3 payment is gateway is incorrect. The default customerid must be set.' ), $notice_type = 'error' );



		return;

	}
	$xml = $xml . "<if condition=\"{!customer.responsestatus!} = 'success'\">

                           <create>

                             <salesdocument refname=\"invoice\">

                               <name>SC" . str_pad( $order_id, 8, '0', STR_PAD_LEFT ) . "</name>

                               <salesdocumenttype>" . $this->settings['tpro3_salesdocumenttype'] . "</salesdocumenttype>

                               <customer>{!customer.id!}</customer>

                               <dueon>" . gmdate( 'c', time() ) . "</dueon>";



	if ( $this->tpro3_customernamefield != "" ) {

		$cnf = $B_first_name . " " . $B_last_name;

		if ( $B_organisation_name != "" ) {

			$cnf = $cnf . ", " . $B_organisation_name;

		}

		$xml = $xml . "<" . $this->tpro3_customernamefield . ">" . $cnf . "</" . $this->tpro3_customernamefield . ">";

	}



	$xml = $xml . "<lineitems>";



	foreach ( $products as $item_id => $product ) {
		$_line_subtotal = wc_get_order_item_meta( $item_id, '_line_subtotal' );
		$product_id = $product['product_id'];

		$wc_product = new WC_Product( $product_id );

		$order_item = $order->get_product_from_item( $product );



		$xml = $xml . "<lineitem>

                               <itemname>" . $order_item->get_attribute( 'Name' ) . "</itemname>

                               <itemdisplayname>" . $product['name'] . "</itemdisplayname>																

                               <price>" . $_line_subtotal . "</price>

                               <quantity>" . $product['item_meta']['_qty'][0] . "</quantity>";



		if ( $this->tpro3_warehouses != "" ) {

			$xml = $xml . "<poswarehouse>" . $this->tpro3_warehouses . "</poswarehouse>";

		}

		if ( $order_item->get_attribute( 'PosItemID' ) != "" ) {

			$xml = $xml . "<positem>" . $order_item->get_attribute( 'PosItemID' ) . "</positem>";

		}

		$xml = $xml . "</lineitem>";

	}

	$xml = $xml . "</lineitems>";

	if ( ( $tax > 0 ) || ( $shipping_amount > 0 ) ) {

		$xml = $xml . "<subtotals>";

	}

	if ( $tax > 0 ) {

		$xml = $xml . "<subtotal>

                               <subtotaltype>Tax</subtotaltype>

                               <amount>" . $tax . "</amount>

                             </subtotal>";

	}

	if ( $shipping_amount > 0 ) {

		$xml = $xml . "<subtotal>

                               <subtotaltype>Shipping</subtotaltype>

                               <amount>" . $shipping_amount . "</amount>

                             </subtotal>";

	}

	if ( ( $tax > 0 ) || ( $shipping_amount > 0 ) ) {

		$xml = $xml . "</subtotals>";

	}



	$xml = $xml . "        </salesdocument>

                               </create>

                             </if>

                             <if condition=\"{!invoice.responsestatus!} = 'success'\">

                               <create>

                                 <transaction refname='auth'>

                                   <account>" . $this->settings['tpro3_account_name'] . "</account>

                                   <salesdocument>{!invoice.id!}</salesdocument>

                                   <amount>" . $order->get_total() . "</amount>

                                   <transactiontype>" . $this->settings['tpro3_transactiontype'] . "</transactiontype>

                                   <description>Shopping cart order</description>

                                   <customer>{!customer.id!}</customer>

								   <customercode>" . $tpro3_customer_id . "</customercode>";



	if ( ( ( $this->tpro3_create_guest == 'yes' ) && ( $customer_user == 0 ) ) || ( ( $this->tpro3_create_registered == 'yes' ) && ( $customer_user != 0 ) ) ) {

		$xml = $xml . "<contact>{!billcontact.id!}</contact>\n";

	}



	$xml = $xml . "		   

                                 </transaction>

                               </create>

                             </if>

                             <if condition=\"{!auth.responsestatus!} = 'success'\">

                               <update>

                                 <salesdocument>

                                   <id>{!invoice.id!}</id>

                                   <status>Finalized</status>

                                 </salesdocument>

                               </update>

                             </if>

                             <if condition=\"{!auth.responsestatus!} != 'success'\">

                               <delete>

                                 <salesdocument>

                                   <id>{!invoice.id!}</id>

                                 </salesdocument>

                               </delete>

                             </if>                             

                           </content>

                         </request>";



	$responseData = sendToTPro3( $xml );



	$node = NULL;

	findNodeByNodename( $responseData, 'authentication', $node );

	$authenticationnode = $node[ count( $node ) - 1 ];

	if ( $authenticationnode != NULL ) {

		if ( getStatus( $authenticationnode ) != TRUE ) {

			getErrors( $authenticationnode );

		}

	}



	// check customer create/update //

	$node = NULL;

	findNodeByRefname( $responseData, 'customer', $node );

	$customernode = $node[ count( $node ) - 1 ];

	if ( $customernode != NULL ) {

		if ( getStatus( $customernode ) != TRUE ) {

			getErrors( $customernode );

		}

	}



	// check contact billing create/update //

	$node = NULL;

	findNodeByRefname( $responseData, 'billcontact', $node );

	$billcontactnode = $node[ count( $node ) - 1 ];

	if ( $billcontactnode != NULL ) {

		if ( getStatus( $billcontactnode ) != TRUE ) {

			getErrors( $billcontactnode );

		}

	}



	// check create salesdocument //

	$node = NULL;

	findNodeByRefname( $responseData, 'invoice', $node );

	$salesdocnode = $node[0];

	if ( $salesdocnode != NULL ) {

		if ( getStatus( $salesdocnode ) != TRUE ) {

			$sdErrs = getErrors( $salesdocnode, FALSE );

			foreach ( $sdErrs as $e ) {

				$invoicenumber = "SC" . str_pad( $order_id, 8, '0', STR_PAD_LEFT );

				if ( strpos( $e, $invoicenumber . ' already exists in object salesdocument' ) === 0 ) {

					wc_add_notice( 'Failure. Please Retry.', $notice_type = 'error' );

					$xml = "<request>

                                      <authentication>

                                        <user>

                                          <gateway>" . $this->settings['tpro3_gateway_name'] . "</gateway>

                                          <emailaddress>" . $this->settings['tpro3_email_address'] . "</emailaddress>

                                          <password>" . $this->settings['tpro3_password'] . "</password>

                                          <application>WooCommerce</application>

                                          <version>" . $this->version . "</version>

                                        </user>

                                      </authentication>

                                      <content continueonfailure='true'>

                                        <read object='salesdocument' fields='id' query=\"((status.name='Finalized') AND (name='" . $invoicenumber . "'))\" refname='sdoc'>



                                        <delete>

                                          <salesdocument refname='sd'>

                                            <id>{!sdoc.id!}</id>

                                          </salesdocument>

                                        </delete>

                                        </read>

                                      </content>

                                    </request>";



					$responseData = sendToTPro3( $xml );



					$node = NULL;

					findNodeByNodename( $responseData, 'sd', $node );

					$sddeletes = $node[ count( $node ) - 1 ];

					if ( $sddeletes != NULL ) {

						if ( getStatus( $sddeletes ) != TRUE ) {

							getErrors( $sddeletes );

						}

					}



				}

			}

		}

	}



	// check create transaction //

	$node = NULL;

	findNodeByRefname( $responseData, 'auth', $node );

	$transactionnode = $node[0];

	if ( $transactionnode != NULL ) {

		if ( getStatus( $transactionnode ) == TRUE ) {

			wc_add_notice( __( 'Successfully Created Transaction.<br/>Authorization Code: ' . $transactionnode["authorizationcode"] ), $notice_type = 'success' );



			$order->add_order_note( sprintf( __( '<b>Payment Approved</b><br/>ID: %s<br/>Auth Code: %s<br/>AVS: %s<br/>CVV: %s', 'woocommerce' ), $transactionnode["id"], $transactionnode["authorizationcode"], $transactionnode["avsresponse"], $transactionnode["cvvresponse"] ) );



			$order->payment_complete( $transactionnode["id"] );

			$order->update_status( 'completed', __( 'Transaction successful', 'woocommerce' ) );



			return array(

				'result'   => 'success',

				'redirect' => $this->get_return_url( $order )

			);

		} else {

			getErrors( $transactionnode );

		}

	}

}




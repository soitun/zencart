<?php

/*
 * 
 * $Id$
 * 
 * Dwolla off-set payment gateway implementation for ZenCart
 * https://www.dwolla.com
 * 
 * Copyright (c) 2013 Dwolla
 * @author Michael Schonfeld, Gordon Zheng
 * @contact michael@dwolla.com, gordon@dwolla.com
 * @version 3.1.1
 * 
 */
class dwolla {
	// Module specific fields
	var $code;
	var $title;
	var $description;
	var $enabled;
	
	// Order specific fields
	var $order_status;

	/**
	 * Default constructor
	 *
	 */
	function dwolla() {
		global $order;
		
		// Set module specific fields
		$this->code = "dwolla";
		$this->api_version = '3.1';
		$this->signature = 'dwolla|dwolla|3.1|3.1';
		$this->title = MODULE_PAYMENT_DWOLLA_TEXT_TITLE;
		$this->public_title = MODULE_PAYMENT_DWOLLA_TEXT_PUBLIC_TITLE;
		$this->description = MODULE_PAYMENT_DWOLLA_TEXT_DESCRIPTION;
		$this->sort_order = MODULE_PAYMENT_DWOLLA_SORT_ORDER;
		$this->enabled = ((MODULE_PAYMENT_DWOLLA_STATUS == "True") ? true : false);
		$this->form_action_url = "https://doscommerce.herokuapp.com/";
		$this->states = array(
			'AL'=>'Alabama', 
			'AK'=>'Alaska', 
			'AZ'=>'Arizona', 
			'AR'=>'Arkansas', 
			'CA'=>'California', 
			'CO'=>'Colorado', 
			'CT'=>'Connecticut', 
			'DE'=>'Delaware', 
			'DC'=>'District Of Columbia', 
			'FL'=>'Florida', 
			'GA'=>'Georgia', 
			'HI'=>'Hawaii', 
			'ID'=>'Idaho', 
			'IL'=>'Illinois', 
			'IN'=>'Indiana', 
			'IA'=>'Iowa', 
			'KS'=>'Kansas', 
			'KY'=>'Kentucky', 
			'LA'=>'Louisiana', 
			'ME'=>'Maine', 
			'MD'=>'Maryland', 
			'MA'=>'Massachusetts', 
			'MI'=>'Michigan', 
			'MN'=>'Minnesota', 
			'MS'=>'Mississippi', 
			'MO'=>'Missouri', 
			'MT'=>'Montana',
			'NE'=>'Nebraska',
			'NV'=>'Nevada',
			'NH'=>'New Hampshire',
			'NJ'=>'New Jersey',
			'NM'=>'New Mexico',
			'NY'=>'New York',
			'NC'=>'North Carolina',
			'ND'=>'North Dakota',
			'OH'=>'Ohio', 
			'OK'=>'Oklahoma', 
			'OR'=>'Oregon', 
			'PA'=>'Pennsylvania', 
			'RI'=>'Rhode Island', 
			'SC'=>'South Carolina', 
			'SD'=>'South Dakota',
			'TN'=>'Tennessee', 
			'TX'=>'Texas', 
			'UT'=>'Utah', 
			'VT'=>'Vermont', 
			'VA'=>'Virginia', 
			'WA'=>'Washington', 
			'WV'=>'West Virginia', 
			'WI'=>'Wisconsin', 
			'WY'=>'Wyoming'
			);

  	// Set order specific fields
    if ((int)MODULE_PAYMENT_DWOLLA_ORDER_STATUS_ID > 0) {
      $this->order_status = MODULE_PAYMENT_DWOLLA_ORDER_STATUS_ID;
    }

    if (is_object($order)) $this->update_status();
  }


	/**
	 * Update the enabled status
	 *
	 */
	function update_status() {
		global $db, $order;
		
		if ($this->enabled == true && (int)MODULE_PAYMENT_DWOLLA_ZONE > 0) {
			$check_flag = false;
			$check = $db->Execute(sprintf("SELECT zone_id 
				FROM %s 
				WHERE geo_zone_id = '%s' and 
				zone_country_id = '%s' 
				ORDER BY zone_id",
				TABLE_ZONES_TO_GEO_ZONES,
				MODULE_PAYMENT_DWOLLA_ZONE,
				$order->billing['country']['id']));

			while (!$check->EOF) {
				if ($check->fields["zone_id"] < 1) {
					$check_flag = true;
					
					break;
				}else if ($check->fields["zone_id"] == $order->billing["zone_id"]) {
					$check_flag = true;
					
					break;
				}

				$check->MoveNext();
			}
			
			if ($check_flag == false)
				$this->enabled = false;
		}
	}
	

	/**
	 * Not used
	 * TODO use to require account id & payment key
	 */
	function javascript_validation() {
		return false;
	}
	
	
	/**
	 * Specify fields for payment selection
	 * @return array
	 */
	function selection() {
		global $db;

		if ($_SESSION['cart_Dwolla_ID']) {
			$order_id = substr($_SESSION['cart_Dwolla_ID'], strpos($_SESSION['cart_Dwolla_ID'], '-')+1);

			$check_query = $db->Execute('select orders_id from ' . TABLE_ORDERS_STATUS_HISTORY . ' where orders_id = "' . (int)$order_id . '" limit 1');

			if ($check_query->RecordCount() < 1) {
				$db->Execute('delete from ' . TABLE_ORDERS . ' where orders_id = "' . (int)$order_id . '"');
				$db->Execute('delete from ' . TABLE_ORDERS_TOTAL . ' where orders_id = "' . (int)$order_id . '"');
				$db->Execute('delete from ' . TABLE_ORDERS_STATUS_HISTORY . ' where orders_id = "' . (int)$order_id . '"');
				$db->Execute('delete from ' . TABLE_ORDERS_PRODUCTS . ' where orders_id = "' . (int)$order_id . '"');
				$db->Execute('delete from ' . TABLE_ORDERS_PRODUCTS_ATTRIBUTES . ' where orders_id = "' . (int)$order_id . '"');
				$db->Execute('delete from ' . TABLE_ORDERS_PRODUCTS_DOWNLOAD . ' where orders_id = "' . (int)$order_id . '"');

				unset($_SESSION['cart_Dwolla_ID']);
			}
		}

		return array(
			"id" => $this->code, 
			"module" => "<img src='https://dzencart.herokuapp.com/logo.png' style='width:70px;height:39px' alt='Dwolla' /> Dwolla Secure Payment",
			'icon' => "<img src='https://dzencart.herokuapp.com/logo.png' style='width:70px;height:39px' alt='Dwolla' /> Dwolla Secure Payment"
			);
	}
	
	
	/**
	 * Requests the payment key before moving onto the confirmation page
	 * Use with standard, 4-page checkout & OnePage checkout
	 * @return boolean
	 */
	function pre_confirmation_check() {
		if (empty($_SESSION['cart']->cartID)) {
			$_SESSION['cartID'] = $_SESSION['cart']->cartID = $_SESSION['cart']->generate_cart_id();
		}

		if (!$_SESSION['cartID']) {
			$_SESSION['cartID'] = $cartID;
		}
	}
	
	/**
	 * Generates the confirmation form
	 * @return array
	 */
	function confirmation() {
		global $db, $order;

		if ($_SESSION['cartID']) {
			$insert_order = false;

			if ($_SESSION['cart_Dwolla_ID']) {
				$order_id = substr($_SESSION['cart_Dwolla_ID'], strpos($_SESSION['cart_Dwolla_ID'], '-')+1);

				$curr = $db->Execute("select currency from " . TABLE_ORDERS . " where orders_id = '" . (int)$order_id . "'");

				if ( ($curr->fields['currency'] != $order->info['currency']) || ($_SESSION['cartID'] != substr($_SESSION['cart_Dwolla_ID'], 0, strlen($_SESSION['cartID']))) ) {
					$check_query = $db->Execute('select orders_id from ' . TABLE_ORDERS_STATUS_HISTORY . ' where orders_id = "' . (int)$order_id . '" limit 1');

					if ($check_query->RecordCount() < 1) {
						$db->Execute('delete from ' . TABLE_ORDERS . ' where orders_id = "' . (int)$order_id . '"');
						$db->Execute('delete from ' . TABLE_ORDERS_TOTAL . ' where orders_id = "' . (int)$order_id . '"');
						$db->Execute('delete from ' . TABLE_ORDERS_STATUS_HISTORY . ' where orders_id = "' . (int)$order_id . '"');
						$db->Execute('delete from ' . TABLE_ORDERS_PRODUCTS . ' where orders_id = "' . (int)$order_id . '"');
						$db->Execute('delete from ' . TABLE_ORDERS_PRODUCTS_ATTRIBUTES . ' where orders_id = "' . (int)$order_id . '"');
						$db->Execute('delete from ' . TABLE_ORDERS_PRODUCTS_DOWNLOAD . ' where orders_id = "' . (int)$order_id . '"');
					}

					$insert_order = true;
				}
			} else {
				$insert_order = true;
			}

			if ($insert_order == true) {
				$order_totals = array();
				if (is_array($_SESSION['order_total_modules']->modules)) {
					reset($_SESSION['order_total_modules']->modules);
					while (list(, $value) = each($_SESSION['order_total_modules']->modules)) {
						$class = substr($value, 0, strrpos($value, '.'));
						if ($GLOBALS[$class]->enabled) {
							for ($i=0, $n=sizeof($GLOBALS[$class]->output); $i<$n; $i++) {
								if (zen_not_null($GLOBALS[$class]->output[$i]['title']) && zen_not_null($GLOBALS[$class]->output[$i]['text'])) {
									$order_totals[] = array('code' => $GLOBALS[$class]->code,
										'title' => $GLOBALS[$class]->output[$i]['title'],
										'text' => $GLOBALS[$class]->output[$i]['text'],
										'value' => $GLOBALS[$class]->output[$i]['value'],
										'sort_order' => $GLOBALS[$class]->sort_order);
								}
							}
						}
					}
				}

				$sql_data_array = array('customers_id' => $_SESSION['customer_id'],
					'customers_name' => $order->customer['firstname'] . ' ' . $order->customer['lastname'],
					'customers_company' => $order->customer['company'],
					'customers_street_address' => $order->customer['street_address'],
					'customers_suburb' => $order->customer['suburb'],
					'customers_city' => $order->customer['city'],
					'customers_postcode' => $order->customer['postcode'],
					'customers_state' => $order->customer['state'],
					'customers_country' => $order->customer['country']['title'],
					'customers_telephone' => $order->customer['telephone'],
					'customers_email_address' => $order->customer['email_address'],
					'customers_address_format_id' => $order->customer['format_id'],
					'delivery_name' => $order->delivery['firstname'] . ' ' . $order->delivery['lastname'],
					'delivery_company' => $order->delivery['company'],
					'delivery_street_address' => $order->delivery['street_address'],
					'delivery_suburb' => $order->delivery['suburb'],
					'delivery_city' => $order->delivery['city'],
					'delivery_postcode' => $order->delivery['postcode'],
					'delivery_state' => $order->delivery['state'],
					'delivery_country' => $order->delivery['country']['title'],
					'delivery_address_format_id' => $order->delivery['format_id'],
					'billing_name' => $order->billing['firstname'] . ' ' . $order->billing['lastname'],
					'billing_company' => $order->billing['company'],
					'billing_street_address' => $order->billing['street_address'],
					'billing_suburb' => $order->billing['suburb'],
					'billing_city' => $order->billing['city'],
					'billing_postcode' => $order->billing['postcode'],
					'billing_state' => $order->billing['state'],
					'billing_country' => $order->billing['country']['title'],
					'billing_address_format_id' => $order->billing['format_id'],
					'payment_method' => $order->info['payment_method'],
					'cc_type' => $order->info['cc_type'],
					'cc_owner' => $order->info['cc_owner'],
					'cc_number' => $order->info['cc_number'],
					'cc_expires' => $order->info['cc_expires'],
					'date_purchased' => 'now()',
					'orders_status' => $order->info['order_status'],
					'currency' => $order->info['currency'],
					'currency_value' => $order->info['currency_value']);

				zen_db_perform(TABLE_ORDERS, $sql_data_array);

				$insert_id = $db->Insert_ID();

				for ($i=0, $n=sizeof($order_totals); $i<$n; $i++) {
					$sql_data_array = array('orders_id' => $insert_id,
						'title' => $order_totals[$i]['title'],
						'text' => $order_totals[$i]['text'],
						'value' => $order_totals[$i]['value'],
						'class' => $order_totals[$i]['code'],
						'sort_order' => $order_totals[$i]['sort_order']);

					zen_db_perform(TABLE_ORDERS_TOTAL, $sql_data_array);
				}

				for ($i=0, $n=sizeof($order->products); $i<$n; $i++) {
					$sql_data_array = array('orders_id' => $insert_id,
						'products_id' => zen_get_prid($order->products[$i]['id']),
						'products_model' => $order->products[$i]['model'],
						'products_name' => $order->products[$i]['name'],
						'products_price' => $order->products[$i]['price'],
						'final_price' => $order->products[$i]['final_price'],
						'products_tax' => $order->products[$i]['tax'],
						'products_quantity' => $order->products[$i]['qty']);

					zen_db_perform(TABLE_ORDERS_PRODUCTS, $sql_data_array);

					$order_products_id = $db->Insert_ID();

					$attributes_exist = '0';
					if (isset($order->products[$i]['attributes'])) {
						$attributes_exist = '1';
						for ($j=0, $n2=sizeof($order->products[$i]['attributes']); $j<$n2; $j++) {
							if (DOWNLOAD_ENABLED == 'true') {
								$attributes_query = "select popt.products_options_name, poval.products_options_values_name, pa.options_values_price, pa.price_prefix, pad.products_attributes_maxdays, pad.products_attributes_maxcount , pad.products_attributes_filename
								from " . TABLE_PRODUCTS_OPTIONS . " popt, " . TABLE_PRODUCTS_OPTIONS_VALUES . " poval, " . TABLE_PRODUCTS_ATTRIBUTES . " pa
								left join " . TABLE_PRODUCTS_ATTRIBUTES_DOWNLOAD . " pad
								on pa.products_attributes_id=pad.products_attributes_id
								where pa.products_id = '" . $order->products[$i]['id'] . "'
								and pa.options_id = '" . $order->products[$i]['attributes'][$j]['option_id'] . "'
								and pa.options_id = popt.products_options_id
								and pa.options_values_id = '" . $order->products[$i]['attributes'][$j]['value_id'] . "'
								and pa.options_values_id = poval.products_options_values_id
								and popt.language_id = '" . $_SESSION['languages_id'] . "'
								and poval.language_id = '" . $_SESSION['languages_id'] . "'";
								$attributes_values = $db->Execute($attributes_query);
							} else {
								$attributes_values = $db->Execute("select popt.products_options_name, poval.products_options_values_name, pa.options_values_price, pa.price_prefix from " . TABLE_PRODUCTS_OPTIONS . " popt, " . TABLE_PRODUCTS_OPTIONS_VALUES . " poval, " . TABLE_PRODUCTS_ATTRIBUTES . " pa where pa.products_id = '" . $order->products[$i]['id'] . "' and pa.options_id = '" . $order->products[$i]['attributes'][$j]['option_id'] . "' and pa.options_id = popt.products_options_id and pa.options_values_id = '" . $order->products[$i]['attributes'][$j]['value_id'] . "' and pa.options_values_id = poval.products_options_values_id and popt.language_id = '" . $_SESSION['languages_id'] . "' and poval.language_id = '" . $_SESSION['languages_id'] . "'");
							}

							$sql_data_array = array('orders_id' => $insert_id,
								'orders_products_id' => $order_products_id,
								'products_options' => $attributes_values->fields['products_options_name'],
								'products_options_values' => $attributes_values->fields['products_options_values_name'],
								'options_values_price' => $attributes_values->fields['options_values_price'],
								'price_prefix' => $attributes_values->fields['price_prefix']);

							zen_db_perform(TABLE_ORDERS_PRODUCTS_ATTRIBUTES, $sql_data_array);

							if ((DOWNLOAD_ENABLED == 'true') && isset($attributes_values->fields['products_attributes_filename']) && zen_not_null($attributes_values->fields['products_attributes_filename'])) {
								$sql_data_array = array('orders_id' => $insert_id,
									'orders_products_id' => $order_products_id,
									'orders_products_filename' => $attributes_values->fields['products_attributes_filename'],
									'download_maxdays' => $attributes_values->fields['products_attributes_maxdays'],
									'download_count' => $attributes_values->fields['products_attributes_maxcount']);

								zen_db_perform(TABLE_ORDERS_PRODUCTS_DOWNLOAD, $sql_data_array);
							}
						}
					}
				}

				$_SESSION['cart_Dwolla_ID'] = $_SESSION['cartID'] . '-' . $insert_id;
			}
		}

		return false;
	}


	/**
	 * Resend the Dwolla account ID
	 * Used with standard 4 page checkout & OnePage checkout
	 * @return string HTML
	 */
	function process_button() {
		global $order, $db;

		$orderID = substr($_SESSION['cart_Dwolla_ID'], strpos($_SESSION['cart_Dwolla_ID'], '-')+1);

		$items = array();
		foreach($order->products as $item) {
			$attvalues = '';

			if($item['qty'] > 0) {
				for ($j=0, $n2=sizeof($item['attributes']); $j<$n2; $j++) {
					$attvalues = $attvalues . '(' . ($item['attributes'][$j]['option'] ? ($item['attributes'][$j]['option'] . ": " . $item['attributes'][$j]['value']) : 'No Add-On') . ') '; 
				}

				$items[] = array(
					'Description'	=> $item['name'] . ' / Model: ' . ($item['model'] ? $item['model'] : 'N/A') . ' ' . $attvalues,
					'Name'			=> $item['name'],
					'Price'			=> round($item['final_price'], 2),
					'Quantity'		=> $item['qty']
					);
			}
		}
		
		// Round off figures
		$total = round($order->info['total'], 2);
		$subtotal = round($order->info['subtotal'], 2);
		$shipping = round($order->info['shipping_cost'], 2);
		$tax = round($order->info['tax'], 2);
		$discount = round(($total - $subtotal - $tax - $shipping), 2);

		// This can be discounts, low order fees, etc
		// When the value is negative, it means that
		// theres a discount(s) applied.
		// When the value is positive, there's a 
		// fee applied.
		if($discount > 0) {
			$items[] = array(
				'Description'  => 'Extra Merchant Fees',
				'Name'         => 'Fee',
				'Price'        => $discount,
				'Quantity'     => 1
				);
		}

		$dwollaJson = array(
			'key'		=> MODULE_PAYMENT_DWOLLA_API_KEY,
			'secret'	=> MODULE_PAYMENT_DWOLLA_API_SECRET,
			'callback'	=> zen_href_link('ext/modules/payment/dwolla/callback.php', '' , 'SSL', true, true, true),
			'redirect'	=> zen_href_link(FILENAME_CHECKOUT_PROCESS, 'referer=dwolla', 'SSL'),
			'orderId'	=> $orderID,
			'test'		=> (MODULE_PAYMENT_DWOLLA_SERVER == 'Test') ? 'true' : 'false',
			'allowFundingSources' => (MODULE_PAYMENT_DWOLLA_GUEST_CHECKOUT == 'True') ? 'true' : 'false',
			'purchaseOrder'    => array(
				'customerInfo' => array(
					'firstName' => $order->customer['firstname'],
					'lastName'  => $order->customer['lastname'],
					'email'     => $order->customer['email_address']
					),
				'destinationId' => MODULE_PAYMENT_DWOLLA_DESTINATION_ID,
				'discount'		=> $discount,
				'shipping'		=> $shipping,
				'tax'			=> $tax,
				'total'			=> $total,
				'orderItems'	=> $items,
				'notes'			=> 'Order from OSCommerce. Session Name ' . zen_session_name() . ' / ID #' . $order->info['orders_id']
				)
			);

		$state_abbr = array_search($order->customer['state'], $this->states);
		if($state_abbr !== false) {
			$dwollaJson['purchaseOrder']['customerInfo']['city'] = $order->customer['city'];
			$dwollaJson['purchaseOrder']['customerInfo']['state'] = $state_abbr;
			$dwollaJson['purchaseOrder']['customerInfo']['zip'] = $order->customer['postcode'];
		}

		$ch = curl_init("https://www.dwolla.com/payment/request");
		curl_setopt($ch, CURLOPT_HTTPHEADER, array("Content-Type: application/json")); 
		curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($dwollaJson));
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		$output = json_decode(curl_exec($ch), TRUE);
		curl_close($ch);

		// Make sure we got a checkout ID
		if(!$output['CheckoutId']) {
			echo "<div style='padding: 10px 0;'><h3 style='color: red;'>There was a problem completing this checkout. Dwolla said: {$output['Message']}</h3></div>";
			
			$post_request = $dwollaJson;
	  		// remove secret from post request dump:
	  		unset($post_request['secret']);

			$errorJson = array(
				'request'   => json_encode($post_request),
				'response'  => json_encode($output),
				'cart'      => json_encode($order->info),
				'session'   => zen_session_name(),
				'orderId'   => $order->info['orders_id'],
				'platform'	=> 'ZenCart'
			);

			$ch = curl_init('http://redalert.dwollalabs.com');
			curl_setopt($ch, CURLOPT_HTTPHEADER, array("Content-Type: application/json")); 
			curl_setopt($ch, CURLOPT_POST, 1);
			curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($errorJson));
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			$errlog = curl_exec($ch);
			curl_close($ch);
		}

		// Redirect to offsite gateway
		$dwollaUrl = "https://www.dwolla.com/payment/checkout/{$output['CheckoutId']}";

		return zen_draw_hidden_field('url', $dwollaUrl);
	}
	
	
	/**
	 * Confirm the payment w/ payment key
	 * Used with standard, 4-page checkout
	 */
	function before_process() {
	    // do next step:
		$this->after_process();
	    // clear cart:
		$_SESSION['cart']->reset(true);
	    // redirect to success page
		zen_redirect(zen_href_link(FILENAME_CHECKOUT_SUCCESS, '', 'SSL'));
	}
	
	
	/**
	 * Not used
	 *
	 */
	function after_process() {
		return false;
	}
	

	/**
	 * Not used
	 *
	 */
	function get_error() {
		return false;
	}
	
	
	/**
	 * Check module status
	 * @return int
	 */
	function check() {
		global $db;

		if (!isset($this->_check)) {
			$check_query = $db->Execute(sprintf(
				"SELECT configuration_value FROM %s WHERE configuration_key = '%s'", 
				TABLE_CONFIGURATION, 
				'MODULE_PAYMENT_DWOLLA_STATUS'
				));
			$this->_check = $check_query->RecordCount();
		}
		return $this->_check;
	}

	
	/**
	 * Install the Dwolla module
	 *
	 */
	function install() {
		global $db;

		$db->Execute("insert into " . TABLE_CONFIGURATION . " 
			(configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values 
			('Enable Dwolla', 'MODULE_PAYMENT_DWOLLA_STATUS', 'False', 'Do you want to accept Dwolla payments?', '6', '0', 'zen_cfg_select_option(array(\'True\', \'False\'), ', now())");

		$db->Execute("insert into " . TABLE_CONFIGURATION . " 
			(configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values 
			('Destination ID', 'MODULE_PAYMENT_DWOLLA_DESTINATION_ID', '', 'The Dwolla account to send the money to', '6', '0', now())");

		$db->Execute("insert into " . TABLE_CONFIGURATION . " 
			(configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values 
			('API Key', 'MODULE_PAYMENT_DWOLLA_API_KEY', '', 'The key used for the Dwolla API', '6', '0', now())");

		$db->Execute("insert into " . TABLE_CONFIGURATION . " 
			(configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values 
			('API Secret', 'MODULE_PAYMENT_DWOLLA_API_SECRET', '', 'The secret used for the Dwolla API', '6', '0', now())");

		$db->Execute("insert into " . TABLE_CONFIGURATION . " 
			(configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values 
			('Dwolla Server', 'MODULE_PAYMENT_DWOLLA_SERVER', 'Live', 'Perform transactions on the live or test server. The test server will only work for developers with Dwolla test accounts.', '6', '0', 'zen_cfg_select_option(array(\'Live\', \'Test\'), ', now())");

		$db->Execute("insert into " . TABLE_CONFIGURATION . " 
			(configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values 
			('Sort order of display.', 'MODULE_PAYMENT_DWOLLA_SORT_ORDER', '0', 'Sort order of display. Lowest is displayed first.', '6', '0', now())");

		$db->Execute("insert into " . TABLE_CONFIGURATION . " 
			(configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, use_function, set_function, date_added) values 
			('Payment Zone', 'MODULE_PAYMENT_DWOLLA_ZONE', '0', 'If a zone is selected, only enable this payment method for that zone.', '6', '2', 'zen_get_zone_class_title', 'zen_cfg_pull_down_zone_classes(', now())");

		$db->Execute("insert into " . TABLE_CONFIGURATION . " 
			(configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, use_function, date_added) values 
			('Set New Order Status', 'MODULE_PAYMENT_DWOLLA_NEW_ORDER_STATUS_ID', '1', 'Set the status of new orders made with Dwolla to this value', '6', '0', 'zen_cfg_pull_down_order_statuses(', 'zen_get_order_status_name', now())");

		$db->Execute("insert into " . TABLE_CONFIGURATION . " 
			(configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, use_function, date_added) values 
			('Set Successfully Processed Order Status', 'MODULE_PAYMENT_DWOLLA_SUCCESS_ORDER_STATUS_ID', '2', 'Set the status of orders with successful Dwolla payments to this value', '6', '0', 'zen_cfg_pull_down_order_statuses(', 'zen_get_order_status_name', now())");

		$db->Execute("insert into " . TABLE_CONFIGURATION . " 
			(configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, use_function, date_added) values 
			('Set Failed Order Status', 'MODULE_PAYMENT_DWOLLA_FAILED_ORDER_STATUS_ID', '1', 'Set the status of orders with failed Dwolla payments to this value', '6', '0', 'zen_cfg_pull_down_order_statuses(', 'zen_get_order_status_name', now())");
		$db->Execute("insert into " . TABLE_CONFIGURATION . " 
     		 (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values 
     		 ('Enable Guest Checkout', 'MODULE_PAYMENT_DWOLLA_GUEST_CHECKOUT', 'True', 'Do you want to allow non-Dwolla users to checkout?', '6', '0', 'zen_cfg_select_option(array(\'True\', \'False\'), ', now())");

	}


	/**
	 * Remove the Dwolla module
	 *
	 */
	function remove() {
		global $db;

		$db->Execute("delete from " . TABLE_CONFIGURATION . " where configuration_key in ('" . implode("', '", $this->keys()) . "')");
	}
	
	
	/**
	 * Define the available keys
	 * @return array
	 */
	function keys() {
		return array(
			'MODULE_PAYMENT_DWOLLA_STATUS', 
			'MODULE_PAYMENT_DWOLLA_API_KEY', 
			'MODULE_PAYMENT_DWOLLA_API_SECRET', 
			'MODULE_PAYMENT_DWOLLA_DESTINATION_ID',
			'MODULE_PAYMENT_DWOLLA_SERVER', 
			'MODULE_PAYMENT_DWOLLA_SORT_ORDER', 
			'MODULE_PAYMENT_DWOLLA_ZONE', 
			'MODULE_PAYMENT_DWOLLA_NEW_ORDER_STATUS_ID',
			'MODULE_PAYMENT_DWOLLA_SUCCESS_ORDER_STATUS_ID',
			'MODULE_PAYMENT_DWOLLA_FAILED_ORDER_STATUS_ID',
			'MODULE_PAYMENT_DWOLLA_GUEST_CHECKOUT'
			);
	}	
}
<?php

/*
 * ***********************************************************************
 Copyright [2013] [PagSeguro Internet Ltda.]

 Licensed under the Apache License, Version 2.0 (the "License");
 you may not use this file except in compliance with the License.
 You may obtain a copy of the License at

 http://www.apache.org/licenses/LICENSE-2.0

 Unless required by applicable law or agreed to in writing, software
 distributed under the License is distributed on an "AS IS" BASIS,
 WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 See the License for the specific language governing permissions and
 limitations under the License.
 * ***********************************************************************
 */

include_once(IS_ADMIN_FLAG === true ? DIR_FS_CATALOG_MODULES : DIR_WS_MODULES) . '/payment/pagseguro/PagSeguroLibrary/PagSeguroLibrary.php';
include_once(IS_ADMIN_FLAG === true ? DIR_FS_CATALOG_MODULES : DIR_WS_MODULES) . '/payment/pagseguro/PagSeguroOrderStatusTranslation.php';

class pagseguro extends base
{

	/**
	 * string representing the payment method
	 * @var string
	 */
	var $code;

	/**
	 * $title is the displayed name for this payment method
	 * @var string
	 */
	var $title;

	/**
	 * $description is a soft name for this payment method
	 * @var string
	 */
	var $description;

	/**
	 * $enabled determines whether this module shows or not... in catalog.
	 * @var boolean
	 */
	var $enabled;

	/**
	 * @var PagSeguro Payment Request
	 */
	private $_pagSeguroPaymentRequestObject;

	/**
	 * @var string
	 */
	private $_pagSeguroResponseUrl;

	/**
	 * Construct
	 */
	function pagseguro()
	{

		global $order;

		$this->code = 'pagseguro';
		$this->codeVersion = '1.0';

		if (IS_ADMIN_FLAG === TRUE) {
			$this->title = MODULE_PAYMENT_PAGSEGURO_TEXT_TITLE;
		} else {
			$this->title = MODULE_PAYMENT_PAGSEGURO_TEXT_PUBLIC_TITLE;
		}

		$this->description = MODULE_PAYMENT_PAGSEGURO_TEXT_DESCRIPTION;
		$this->enabled = ((MODULE_PAYMENT_PAGSEGURO_STATUS == 'True') ? true : false);
		$this->sort_order = MODULE_PAYMENT_PAGSEGURO_SORT_ORDER;

		if ((int) MODULE_PAYMENT_PAGSEGURO_ORDER_STATUS_ID > 0)
			$this->order_status = MODULE_PAYMENT_PAGSEGURO_ORDER_STATUS_ID;

		if (is_object($order))
			$this->update_status();

		$this->email_footer = 'There is no charge for this order.';

		$this->_pagSeguroPaymentRequestObject = new PagSeguroPaymentRequest();
	}

	/**
	 * calculate zone matches and flag settings to determine whether this module should display to customers or not
	 */
	function update_status()
	{
		global $order, $db;

		if (($this->enabled == true) && ((int) MODULE_PAYMENT_PAGSEGURO_ZONE > 0)) {

			$check_flag = false;
			$check_query = $db->Execute("SELECT zone_id
                                        FROM " . TABLE_ZONES_TO_GEO_ZONES . " WHERE geo_zone_id = '" . MODULE_PAYMENT_PAGSEGURO_ZONE . "' AND zone_country_id = '" . $order->billing['country']['id'] . "' order by zone_id");

			while (!$check_query->EOF) {
				if ($check_query->fields['zone_id'] < 1) {
					$check_flag = true;
					break;
				} else
					if ($check_query->fields['zone_id'] == $order->billing['zone_id']) {
						$check_flag = true;
						break;
					}

				$check_query->MoveNext();
			}

			if ($check_flag == false) {
				$this->enabled = false;
			}
		}
	}

	/**
	 * JS validation which does error-checking of data-entry if this module is selected for use
	 * (Number, Owner, and CVV Lengths)
	 * @return string
	 */
	function javascript_validation()
	{
		return false;
	}

	/**
	 * Proccess any data when user will start checkout process
	 * @return boolean
	 */
	function checkout_initialization_method()
	{
		return false;
	}

	/**
	 * Displays payment method name along with Credit Card Information Submission Fields (if any) on the Checkout Payment Page
	 * @return array
	 */
	function selection()
	{

		if ($this->_currencyValidation())
			return array('id' => $this->code, 'module' => $this->title);
	}

	/**
	 * Normally evaluates the Credit Card Type for acceptance and the validity of the Credit Card Number & Expiration Date
	 * Since paypal module is not collecting info, it simply skips this step.
	 * @return boolean
	 */
	function pre_confirmation_check()
	{
		if (empty($_SESSION['cart']->cartID)) {
			$_SESSION['cartID'] = $_SESSION['cart']->cartID = $_SESSION['cart']->generate_cart_id();
		}
	}

	/**
	 * Display Credit Card Information on the Checkout Confirmation Page
	 * Since none is collected for paypal before forwarding to paypal site, this is skipped
	 * @return boolean
	 */
	function confirmation()
	{
		return array('title'	 => $this->title . ': ',
			'fields' => array(
				array('title'	 => MODULE_PAYMENT_PAGSEGURO_TEXT_OUTSIDE,
					'field'	 => "")));
	}

	/**
	 * Build the data and actions to process when the "Submit" button is pressed on the order-confirmation screen.
	 * This sends the data to the payment gateway for processing.
	 * (These are hidden fields on the checkout confirmation page)
	 * @return string
	 */
	function process_button()
	{
		return false;
	}

	/**
	 * Store transaction info to the order and process any results that come back from the payment gateway
	 */
	function before_process()
	{

		// perform currency validation
		if (!$this->_currencyValidation())
			zen_redirect(zen_href_link(FILENAME_CHECKOUT_PAYMENT, 'error_message=' . stripslashes(MODULE_PAYMENT_PAGSEGURO_TEXT_CURRENCY_ERROR), 'SSL'));

		$this->_pagSeguroPaymentRequestObject = $this->_generatePagSeguroPaymentRequestObject();
	}

	/**
	 * Generate PagSeguro Payment Request Object
	 * @return \PagSeguroPaymentRequest
	 */
	private function _generatePagSeguroPaymentRequestObject()
	{

		$paymentRequest = new PagSeguroPaymentRequest();
		$paymentRequest->setCurrency(PagSeguroCurrencies::getIsoCodeByName("REAL"));
		$paymentRequest->setExtraAmount($this->_generateExtraAmount());
		$paymentRequest->setRedirectURL($this->_getPagSeguroRedirectUrl());
		$paymentRequest->setNotificationURL($this->_getPagSeguroNotificationUrl());
		$paymentRequest->setItems($this->_generatePagSeguroProductsData());
		$paymentRequest->setSender($this->_generatepagSeguroSenderDataObject());
		$paymentRequest->setShipping($this->_generatePagSeguroShippingDataObject());

		return $paymentRequest;
	}

	/**
	 * Generate Extra Amount
	 * @global type $order
	 * @return type
	 */
	private function _generateExtraAmount()
	{
		global $order;

		$coupon_discont = $this->_couponDiscont();
		$tax_amount = PagSeguroHelper::decimalFormat((float) $order->info['tax']);

		return $tax_amount + $coupon_discont;
	}

	/**
	 * Coupon Discont
	 * @global type $order
	 * @global type $db
	 * @return type
	 */
	private function _couponDiscont()
	{
		global $order, $db;
		$discount = 0;

		if ($order->info['coupon_code'] != null) {
			$sql = "SELECT coupon_type, coupon_amount ";
			$sql .= " FROM " . TABLE_COUPONS;
			$sql .= " WHERE coupon_code = " . '\'' . $order->info['coupon_code'] . '\'';

			$coupon = $db->Execute($sql);

			if (!$coupon->EOF) {

				$amount = $coupon->fields['coupon_amount'];

				if ($coupon->fields['coupon_type'] == 'F') { // amount off
					$discount = $amount;
				} else
					if ($coupon->fields['coupon_type'] == 'P') { // percentage off
						$sub_total = $order->info['subtotal'];
						$discount = round(($sub_total * PagSeguroHelper::decimalFormat((float) $amount)) / 100, 2);
					} else
						if ($coupon->fields['coupon_type'] == 'O') { // amount off and free shipping
							$discount = 0;
						} else
							if ($coupon->fields['coupon_type'] == 'E') { // percentage off and free shipping
								$discount = 0;
							} else
								if ($coupon->fields['coupon_type'] == 'S') { // free shipping
									$discount = 0;
								}
			}
		}
		return ($discount > 0) ? PagSeguroHelper::decimalFormat($discount) * -1 : 0;
	}

	/**
	 * PagSeguro Redirect Url
	 * @return string
	 */
	private function _getPagSeguroRedirectUrl()
	{
		$configurations = $this->_retrievePagSeguroConfiguration();
		$url = trim($configurations['MODULE_PAYMENT_PAGSEGURO_REDIRECT_URL']);
		return (!empty($url)) ? $url : $this->_getDefaultRedirectURL();
	}

	/**
	 * Gets the default return url
	 * @return string
	 */
	private function _getDefaultRedirectURL()
	{
		return HTTP_SERVER . DIR_WS_CATALOG . 'index.php?main_page=checkout_success';
	}
	/**
	 * PagSeguro Notification Url
	 * @return string
	 */
	private function _getPagSeguroNotificationUrl()
	{
		$configurations = $this->_retrievePagSeguroConfiguration();
		$url = trim($configurations['MODULE_PAYMENT_PAGSEGURO_NOTIFICATION']);
		return (!empty($url)) ? $url : $this->_notificationUrl();
	}

	/**
	 * PagSeguro Products Data
	 * @global type $order
	 * @return array
	 */
	private function _generatePagSeguroProductsData()
	{
		global $order;
		$pagSeguroItems = array();

		$products = $order->products;

		$cont = 1;
		foreach ($products as $product) {

			$pagSeguroItem = new PagSeguroItem();
			$pagSeguroItem->setId($cont++);
			$pagSeguroItem->setDescription($this->_truncateValue($product['name'], 255));
			$pagSeguroItem->setQuantity($product['qty']);
			$pagSeguroItem->setAmount($product['final_price']);
			$pagSeguroItem->setWeight($product['weight'] * 1000); // defines weight in gramas

			if (isset($product['additional_shipping_cost']) && $product['additional_shipping_cost'] > 0)
				$pagSeguroItem->setShippingCost($product['additional_shipping_cost']);

			array_push($pagSeguroItems, $pagSeguroItem);
		}

		return $pagSeguroItems;
	}

	/**
	 * Get errors
	 * @return boolean
	 */
	function get_error()
	{
		return FALSE;
	}

	/**
	 * Truncate Value
	 * @param string $string
	 * @param type $limit
	 * @param type $endchars
	 * @return string
	 */
	private function _truncateValue($string, $limit, $endchars = '...')
	{

		if (!is_array($string) || !is_object($string)) {

			$stringLength = strlen($string);
			$endcharsLength = strlen($endchars);

			if ($stringLength > (int) $limit) {
				$cut = (int) ($limit - $endcharsLength);
				$string = substr($string, 0, $cut) . $endchars;
			}
		}
		return $string;
	}

	/**
	 * Generate PagSeguro Sender Data Object
	 * @global type $order
	 * @return \PagSeguroSender
	 */
	private function _generatepagSeguroSenderDataObject()
	{
		global $order;

		$sender = new PagSeguroSender();
		$customer = $order->customer;

		if (isset($customer) && !is_null($customer)) {
			$sender->setEmail($customer['email_address']);
			$sender->setName($customer['firstname'] . ' ' . $customer['lastname']);
		}

		return $sender;
	}

	/**
	 * generate PagSeguro Shipping Data Object
	 * @global type $order
	 * @return \PagSeguroShipping
	 */
	private function _generatePagSeguroShippingDataObject()
	{
		global $order;

		$shipping = new PagSeguroShipping();
		$shipping->setAddress($this->_generatePagSeguroShippingAddressDataObject());
		$shipping->setType($this->_generatePagSeguroShippingTypeObject());
		$shipping->setCost(PagSeguroHelper::decimalFormat((float) $order->info['shipping_cost']));

		return $shipping;
	}

	/**
	 * Generate PagSeguro Shipping Address Data Object
	 * @global type $order
	 * @return \PagSeguroAddress
	 */
	private function _generatePagSeguroShippingAddressDataObject()
	{
		global $order;

		$address = new PagSeguroAddress();

		$deliveryAddress = $order->delivery;

		if (!is_null($deliveryAddress)) {
			$address->setCity($deliveryAddress['city']);
			$address->setPostalCode($deliveryAddress['postcode']);
			$address->setStreet($deliveryAddress['street_address']);
			$address->setDistrict($deliveryAddress['suburb']);
			$address->setCountry($deliveryAddress['country']['iso_code_3']);
		}

		return $address;
	}

	/**
	 * Generate PagSeguro Shipping Type Object
	 * @return \PagSeguroShippingType
	 */
	private function _generatePagSeguroShippingTypeObject()
	{
		$shippingType = new PagSeguroShippingType();
		$shippingType->setByType('NOT_SPECIFIED');

		return $shippingType;
	}

	/**
	 * Post-processing activities
	 * When the order returns from the processor, if PDT was successful, this stores the results in order-status-history and logs data for subsequent reference
	 * @return boolean
	 */
	function after_process()
	{

		global $insert_id;

		$language_code = $this->_getCurrentCodeLanguage();
		$order_status_id = $this->_getOrderStatusID(PagSeguroOrderStatusTranslation::getStatusTranslation('WAITING_PAYMENT', $language_code));
		$this->updateOrderStatus($insert_id, $order_status_id);
		$this->_pagSeguroPaymentRequestObject->setReference((int) $insert_id);
		$this->_performPagSeguroRequest($this->_pagSeguroPaymentRequestObject);
		$_SESSION['cart']->reset(true);

		zen_redirect($this->_pagSeguroResponseUrl);
	}

	/**
	 * Perform PagSeguro Request
	 * @param PagSeguroPaymentRequest $paymentRequest
	 */
	private function _performPagSeguroRequest(PagSeguroPaymentRequest $paymentRequest)
	{

		try
		{

			// retrieving PagSeguro configurations
			$configurations = $this->_retrievePagSeguroConfiguration();

			// setting configurations to PagSeguro API
			$this->_setPagSeguroConfiguration($configurations['MODULE_PAYMENT_PAGSEGURO_CHARSET'], ($configurations['MODULE_PAYMENT_PAGSEGURO_LOG_ACTIVE'] == 'True'), $configurations['MODULE_PAYMENT_PAGSEGURO_LOG_FILELOCATION']);

			// retrieving PagSeguro zencart module version
			$this->_retrievePagSeguroModuleVersion();

			// set cms version
			$this->_setCmsVersion();

			// performing request
			$credentials = new PagSeguroAccountCredentials($configurations['MODULE_PAYMENT_PAGSEGURO_EMAIL'], $configurations['MODULE_PAYMENT_PAGSEGURO_TOKEN']);

			$this->_pagSeguroResponseUrl = $paymentRequest->register($credentials);
		}
		catch (PagSeguroServiceException $e)
		{
			die($e->getMessage());
		}
	}

	/**
	 * Retrive PagSeguro Configuration
	 * @global type $db
	 * @return type
	 */
	private function _retrievePagSeguroConfiguration()
	{
		global $db;

		$queryResult = $db->Execute("SELECT *
                                      FROM " . TABLE_CONFIGURATION . "
                                      WHERE configuration_key
                                      LIKE 'MODULE_PAYMENT_PAGSEGURO%'");

		$configurations = array();
		while (!$queryResult->EOF) {
			$configurations[$queryResult->fields['configuration_key']] = $queryResult->fields['configuration_value'];
			$queryResult->MoveNext();
		}

		return $configurations;
	}

	/**
	 * Set PagSeguro Configuration
	 * @param type $charset
	 * @param type $activeLog
	 * @param type $fileLocation
	 */
	private function _setPagSeguroConfiguration($charset, $activeLog = FALSE, $fileLocation = NULL)
	{

		// setting configurated default charset
		PagSeguroConfig::setApplicationCharset($charset);

		// setting configurated default log info
		if ($activeLog) {
			$this->_verifyLogFile(DIR_FS_CATALOG . $fileLocation);
			PagSeguroConfig::activeLog(DIR_FS_CATALOG . $fileLocation);
		}
	}

	/**
	 * Verify Log File
	 * @param type $file
	 */
	private function _verifyLogFile($file)
	{

		try
		{
			$f = fopen($file, "a");
			fclose($f);
		}
		catch (Exception $e)
		{
			die($e);
		}
	}

	/**
	 * Retrive PagSeguro Module Version
	 */
	private function _retrievePagSeguroModuleVersion()
	{
		PagSeguroLibrary::setModuleVersion('zencart' . ':' . $this->codeVersion);
	}

	/**
	 * Set Cms Version
	 */
	private function _setCmsVersion()
	{
		try
		{
			PagSeguroLibrary::setCMSVersion('zencart' . ':' . PROJECT_VERSION_MAJOR . '.' . PROJECT_VERSION_MINOR);
		}
		catch (Exception $exc)
		{
			echo $exc->getMessage();
		}
	}

	/**
	 * Current Code Language
	 * @global type $languages_id
	 * @global type $db
	 * @return type
	 */
	private function _getCurrentCodeLanguage()
	{
		global $languages_id, $db;

		$languageCode = $db->Execute("SELECT code
                                     FROM " . TABLE_LANGUAGES . "
                                     WHERE languages_id = " . (int) $languages_id);

		return $languageCode->fields['code'];
	}

	/**
	 * Order Status Id
	 * @global type $db
	 * @param type $orderStatus
	 * @return type
	 */
	private function _getOrderStatusID($orderStatus)
	{
		global $db;

		$orderStatusId = $db->Execute("SELECT orders_status_id
                                     FROM " . TABLE_ORDERS_STATUS . "
                                     WHERE orders_status_name = '" . $orderStatus . "' limit 1");

		return $orderStatusId->fields["orders_status_id"];
	}

	/**
	 * Update Order Status
	 * @global type $db
	 * @param type $order_id
	 * @param type $order_status_id
	 */
	public function updateOrderStatus($order_id, $order_status_id)
	{
		global $db;

		$sql_data_array = array(array('fieldName' => 'orders_id', 'value' => $order_id, 'type' => 'integer'),
			array('fieldName' => 'orders_status_id', 'value' => $order_status_id, 'type' => 'integer'),
			array('fieldName' => 'date_added', 'value' => 'now()', 'type' => 'noquotestring'),
			array('fieldName' => 'customer_notified', 'value' => 1, 'type' => 'integer'),
			array('fieldName' => 'comments', 'value' => 'STATUS ATUALIZADO', 'type' => 'string'));
		$db->perform(TABLE_ORDERS_STATUS_HISTORY, $sql_data_array);
	}

	/**
	 * Used to display error message details
	 * @return boolean
	 */
	function output_error()
	{
		return false;
	}

	/**
	 * Check if actual currency is brazilian Real (BRL)
	 * @global Object $currency
	 * @return type
	 */
	private function _currencyValidation()
	{
		// return PagSeguroCurrencies::checkCurrencyAvailabilityByIsoCode($_SESSION['currency']);
		return TRUE;
	}

	/**
	 * Check to see whether module is installed
	 * @return boolean
	 */
	function check()
	{
		global $db;

		if (!isset($this->_check)) {
			$check_query = $db->Execute("SELECT configuration_value
                                       FROM " . TABLE_CONFIGURATION . "
                                       WHERE configuration_key = 'MODULE_PAYMENT_PAGSEGURO_STATUS'");
			$this->_check = $check_query->RecordCount();
		}
		return $this->_check;
	}

	/**
	 * Install the payment module and its configuration settings
	 */
	function install()
	{
		$this->_createConfiguration();
		$this->_createOrderStatus();
	}

	/**
	 * Remove the module and all its settings
	 */
	function remove()
	{
		global $db;
		$db->Execute("delete from " . TABLE_CONFIGURATION . " where configuration_key in ('" . implode("', '", $this->keys()) . "')");
	}

	/*
	 *  Create configuration settings
	 */
	private function _createConfiguration()
	{
		global $db, $messageStack;

		if (define('MODULE_PAYMENT_PAGSEGURO_STATUS')) {
			$messageStack->add_session('PagSeguro) module already installed.', 'error');
			zen_redirect(zen_href_link(FILENAME_MODULES, 'set=payment&module=pagseguro', 'NONSSL'));
			return 'failed';
		}

		$db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values ('ATIVAR M&#211;DULO', 'MODULE_PAYMENT_PAGSEGURO_STATUS', 'True', 'Deseja habilitar o m&#243;dulo?', '6', '0', 'zen_cfg_select_option(array(\'True\', \'False\'), ', now())");
		$db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('ORDEM DE EXIBI&#199;&#195;O', 'MODULE_PAYMENT_PAGSEGURO_SORT_ORDER', '1', 'Informe a ordem em que o PagSeguro deve aparecer no checkout de sua loja.', '6', '0',now())");
		$db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('E-MAIL', 'MODULE_PAYMENT_PAGSEGURO_EMAIL', '" . PagSeguroConfig::getData('credentials', 'email') . "', 'N&#227;o tem conta no PagSeguro? Clique <a href=\"https://pagseguro.uol.com.br/registration/registration.jhtml?ep=9&tipo=cadastro#!vendedor\" target=\"_blank\"><strong>aqui</strong></a> e cadastre-se gr&#225;tis.', '6', '0', now())");
		$db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('TOKEN', 'MODULE_PAYMENT_PAGSEGURO_TOKEN', '" . PagSeguroConfig::getData('credentials', 'token') . "', 'N&#227;o tem ou n&#227;o sabe seu token? Clique <a href=\"https://pagseguro.uol.com.br/integracao/token-de-seguranca.jhtml\" target=\"_blank\"><strong>aqui</strong></a> para gerar um novo.', '6', '0', now())");
		$db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('URL DE REDIRECIONAMENTO', 'MODULE_PAYMENT_PAGSEGURO_REDIRECT_URL', '" . $this->_getDefaultRedirectURL() . "', 'Seu cliente ser&#225; redirecionado de volta para sua loja ou para a URL que voc&#234; informar neste campo. Clique <a href=\"https://pagseguro.uol.com.br/integracao/pagamentos-via-api.jhtml\" target=\"_blank\"><strong>aqui</strong></a> para ativar.', '6', '0', now())");
		$db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('URL DE NOTIFICA&#199;&#195;O', 'MODULE_PAYMENT_PAGSEGURO_NOTIFICATION', '" . $this->_notificationUrl() . "', 'Sempre que uma transa&#231;&#227;o mudar de status, o PagSeguro envia uma notifica&#231;&#227;o para sua loja ou para a URL que voc&#234; informar neste campo.', '6', '0',now())");
		$db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values ('CHARSET', 'MODULE_PAYMENT_PAGSEGURO_CHARSET', '" . PagSeguroConfig::getData('application', 'charset') . "', 'Defina o charset de acordo com a codifica&#231;&#227;o do seu sistema.', '6', '0', 'zen_cfg_select_option(array(\'ISO-8859-1\', \'UTF-8\'), ', now())");
		$db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values ('LOG', 'MODULE_PAYMENT_PAGSEGURO_LOG_ACTIVE', 'False', 'Criar arquivo de log?', '6', '0', 'zen_cfg_select_option(array(\'True\', \'False\'), ', now())");
		$db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('DIRET&#211;RIO', 'MODULE_PAYMENT_PAGSEGURO_LOG_FILELOCATION', '" . PagSeguroConfig::getData('log', 'fileLocation') . "', 'Caminho para o arquivo de log.', '6', '0', now())");

		$this->notify('NOTIFY_PAYMENT_PAGSEGURO_INSTALLED');
	}

	/**
	 * Notification url
	 * @return url notification
	 */
	private function _notificationUrl()
	{
		return HTTP_SERVER . DIR_WS_CATALOG . 'pagseguro_notification.php';
	}

	/**
	 * Create Order Status
	 * @global type $db
	 */
	private function _createOrderStatus()
	{
		global $db;

		$insert = "INSERT into " . TABLE_ORDERS_STATUS . " (orders_status_id, language_id, orders_status_name) values ";

		try
		{
			$id_language_br = $this->_idLanguage('br');
			$id_language_en = $this->_idLanguage();

			if ($id_language_br)
				$this->_saveStatus($id_language_br, $insert, 'br');

			if ($id_language_en)
				$this->_saveStatus($id_language_en, $insert);
		}
		catch (Exception $exc)
		{
			echo $exc->getTraceAsString();
		}
	}

	/**
	 * Save Status
	 * @global type $db
	 * @param type $id_language
	 * @param type $insert
	 * @param type $code_language
	 */
	private function _saveStatus($id_language, $insert, $code_language = 'en')
	{
		global $db;
		$id = $this->_maxIdOrder($id_language) + 1;

		foreach ($this->arrayStatus() as $status) {
			if ($this->_checkStatus($status[$code_language])) {

				$insert_br = $insert . "( " . $id . ", " . $id_language . ", " . ' \'' . $status[$code_language] . '\'' . " )";
				$db->Execute($insert_br);
				$id++;
			}
		}
	}

	/**
	 * Max ID Order
	 * @global type $db
	 * @param type $id_language
	 * @return int
	 */
	private function _maxIdOrder($id_language)
	{
		global $db;

		$max_id = $db->Execute("SELECT orders_status_id,
                                    MAX(orders_status_id)
                                    FROM " . TABLE_ORDERS_STATUS . "
                                    WHERE language_id = " . $id_language);

		if ($max_id->EOF)
			return 0;

		return (int) $max_id->fields["MAX(orders_status_id)"];
	}

	/**
	 * Id Language
	 * @global type $db
	 * @param type $language
	 * @return type
	 */
	private function _idLanguage($language = 'en')
	{
		global $db;
		$languages_id = '';

		$query = $db->Execute("SELECT languages_id
                                  FROM " . TABLE_LANGUAGES . "
                                  WHERE code = " . ' \'' . $language . '\'');

		if (!$query->EOF)
			$languages_id = $query->fields['languages_id'];

		return (int) $languages_id;
	}

	/**
	 * Check Status
	 * @global type $db
	 * @param type $status
	 * @return boolean
	 */
	private function _checkStatus($status)
	{
		global $db;

		$save = false;

		$sql = "SELECT orders_status_id ";
		$sql .= " FROM " . TABLE_ORDERS_STATUS;
		$sql .= " WHERE orders_status_name = " . ' \'' . $status . '\'';

		$query = $db->Execute($sql);

		if ($query->EOF)
			$save = true;

		return $save;
	}

	/**
	 * Array Status
	 * @return type
	 */
	public function arrayStatus()
	{
		return array(
			0 => array("br" => "Iniciado", "en" => "Initiated"),
			1 => array("br" => "Aguardando pagamento", "en" => "Waiting payment"),
			2 => array("br" => "Em análise", "en" => "In analysis"),
			3 => array("br" => "Paga", "en" => "Paid"),
			4 => array("br" => "Disponível", "en" => "Available"),
			5 => array("br" => "Em disputa", "en" => "In dispute"),
			6 => array("br" => "Devolvida", "en" => "Refunded"),
			7 => array("br" => "Cancelada", "en" => "Cancelled"));
	}

	/**
	 * Internal list of configuration keys used for configuration of the module
	 * @return array
	 */
	function keys()
	{
		return array('MODULE_PAYMENT_PAGSEGURO_STATUS', 'MODULE_PAYMENT_PAGSEGURO_SORT_ORDER', 'MODULE_PAYMENT_PAGSEGURO_EMAIL', 'MODULE_PAYMENT_PAGSEGURO_TOKEN', 'MODULE_PAYMENT_PAGSEGURO_REDIRECT_URL', 'MODULE_PAYMENT_PAGSEGURO_NOTIFICATION', 'MODULE_PAYMENT_PAGSEGURO_CHARSET', 'MODULE_PAYMENT_PAGSEGURO_LOG_ACTIVE', 'MODULE_PAYMENT_PAGSEGURO_LOG_FILELOCATION');
	}

}
?>

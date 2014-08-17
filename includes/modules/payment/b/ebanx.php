<?php
	

class ebanx {
  /**
   * $code determines the internal 'code' name used to designate "this" payment module
   *
   * @var string
   */
  var $code;
  /**
   * $title is the displayed name for this payment method
   *
   * @var string
   */
  var $title;
  /**
   * $description is a soft name for this payment method
   *
   * @var string
   */
  var $description;
  /**
   * $enabled determines whether this module shows or not... in catalog.
   *
   * @var boolean
   */
  var $enabled;
  /**
   * log file folder
   *
   * @var string
   */
  var $payment;
  /**
   * vars
   */
  // var $gateway_mode;
  // var $reportable_submit_data;
  // var $authorize;
  // var $auth_code;
  // var $transaction_id;
  // var $order_status;

  function ebanx() {

    global $order;
    $this->code = "ebanx";
    //$this->signature = "ebanx|ebanx|0.1|0.1";
    $this->title = MODULE_PAYMENT_EBANX_TEXT_TITLE;
    //$this->public_title = MODULE_PAYMENT_EBANX_TEXT_PUBLIC_TITLE;
 	$this->description = MODULE_PAYMENT_EBANX_TEXT_DESCRIPTION;
	$this->sort_order = MODULE_PAYMENT_EBANX_SORT_ORDER;
	$this->enabled = ((MODULE_PAYMENT_EBANX_STATUS == "True") ? true : false);   

}

function update_status(){}


function javascript_validation(){
	return false;
}

function selection(){
	      return array('id' => $this->code,
                   'module' => $this->title);
}

function pre_confirmation_check(){return false;}

function confirmation(){return false;}

function process_button(){return false;}

function before_process(){return false;}

function after_process(){return false;}

function after_order_create(){return false;}

function get_error(){return false;}

function check() {
      global $db;
      if (!isset($this->_check)) {
        $check_query = $db->Execute("SELECT configuration_value FROM " . TABLE_CONFIGURATION . "WHERE configuration_key = 'MODULE_PAYMENT_EBANX_STATUS'");
        $this->_check = $check_query->RecordCount();
      }
      return $this->_check;
    }

function install(){
      global $db, $messageStack;
      if (defined('MODULE_PAYMENT_BITCOIN_STATUS')) {
        $messageStack->add_session('Ebanx module already installed.', 'error');
        zen_redirect(zen_href_link(FILENAME_MODULES, 'set=payment&module=ebanx', 'NONSSL'));
        return 'failed';
       }
    //   $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values ('Enable EBANX Module', 'MODULE_PAYMENT_EBANX_STATUS', 'True', 'Do you want to accept EBANX payments?', '6', '1', 'zen_cfg_select_option(array(\'True\', \'False\'), ', now());");
    //   $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Host Address', 'MODULE_PAYMENT_BITCOIN_HOST', 'localhost:8332', 'The host address for Bitcoin RPC', '6', '0', now())");
    //   $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Username', 'MODULE_PAYMENT_BITCOIN_LOGIN', 'testing', 'The Username for Bitcoin RPC', '6', '0', now())");
    //   $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added, set_function, use_function) values ('Password', 'MODULE_PAYMENT_BITCOIN_PASSWORD', '', 'The Password for Bitcoin RPC', '6', '25', now(), 'zen_cfg_password_input(', 'zen_cfg_password_display')");
    //   $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Sort order of display.', 'MODULE_PAYMENT_BITCOIN_SORT_ORDER', '0', 'Sort order of display. Lowest is displayed first.', '6', '0', now())");
    //   $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, use_function, set_function, date_added) values ('Payment Zone', 'MODULE_PAYMENT_BITCOIN_ZONE', '0', 'If a zone is selected, only enable this payment method for that zone.', '6', '2', 'zen_get_zone_class_title', 'zen_cfg_pull_down_zone_classes(', now())");
    //   $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, use_function, date_added) values ('Set Order Status', 'MODULE_PAYMENT_BITCOIN_ORDER_STATUS_ID', '0', 'Set the status of orders made with this payment module to this value', '6', '0', 'zen_cfg_pull_down_order_statuses(', 'zen_get_order_status_name', now())");
    // }

		// global $db;

		$db->Execute("insert into " . TABLE_CONFIGURATION . " 
			(configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values 
			('Enable Ebanx', 'MODULE_PAYMENT_EBANX_STATUS', 'False', 'Do you want to accept EBANX payments?', '6', '0', 'zen_cfg_select_option(array(\'True\', \'False\'), ', now())");

		$db->Execute("insert into " . TABLE_CONFIGURATION . " 
			(configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values 
			('Integration Key', 'MODULE_PAYMENT_EBANX_INTEGRATIONKEY', '', 'Your EBANX unique integration key', '6', '0', now())");

		$db->Execute("insert into " . TABLE_CONFIGURATION . " 
			(configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values 
			('Test Mode', 'MODULE_PAYMENT_EBANX_TESTMODE', '', 'Test Mode?', '6', '0', 'zen_cfg_select_option(array(\'True\', \'False\'), ', now())");

		$db->Execute("insert into " . TABLE_CONFIGURATION . " 
			(configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values 
			('Installments'   , 'MODULE_PAYMENT_EBANX_INSTALLMENTS', '', 'Enable Installments?', '6', '0', 'zen_cfg_select_option(array(\'True\', \'False\'), ', now())");


		$db->Execute("insert into " . TABLE_CONFIGURATION . " 
			(configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values 
			('Maximum Installments Enabled', 'MODULE_PAYMENT_EBANX_MAXINSTALLMENTS', '', 'Maximum Installments Number', '6', '0', now())");

		$db->Execute("insert into " . TABLE_CONFIGURATION . " 
			(configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values 
			('Installments rate', 'MODULE_PAYMENT_EBANX_INSTALLMENTSRATE', '', 'Installments Rate', '6', '0', now())");


		$db->Execute("insert into " . TABLE_CONFIGURATION . " 
     		 (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values 
     		 ('Enable Boleto Method', 'MODULE_PAYMENT_EBANX_BOLETO', 'True', 'Enable Boleto Payment Method?', '6', '0', 'zen_cfg_select_option(array(\'True\', \'False\'), ', now())");

		$db->Execute("insert into " . TABLE_CONFIGURATION . " 
     		 (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values 
     		 ('Enable Credit Card Method', 'MODULE_PAYMENT_EBANX_CCARD', 'True', 'Enable Credit Card Payment Method?', '6', '0', 'zen_cfg_select_option(array(\'True\', \'False\'), ', now())");
		
		$db->Execute("insert into " . TABLE_CONFIGURATION . " 
     		 (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values 
     		 ('Enable TEF Method', 'MODULE_PAYMENT_EBANX_TEF', 'True', 'Enable TEF Payment Method?', '6', '0', 'zen_cfg_select_option(array(\'True\', \'False\'), ', now())");

	}

function remove(){return false;}

function keys(){


    return array(
    	  'MODULE_PAYMENT_EBANX_STATUS'
    	, 'MODULE_PAYMENT_EBANX_INTEGRATIONKEY'
    	, 'MODULE_PAYMENT_EBANX_TESTMODE'
    	, 'MODULE_PAYMENT_EBANX_INSTALLMENTS'
    	, 'MODULE_PAYMENT_EBANX_MAXINSTALLMENTS'
    	, 'MODULE_PAYMENT_EBANX_INSTALLMENTSRATE'
    	, 'MODULE_PAYMENT_EBANX_BOLETO'
    	, 'MODULE_PAYMENT_EBANX_CCARD'
    	, 'MODULE_PAYMENT_EBANX_TEF');
  

}


?>

<?php

/*
 ************************************************************************
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
 ************************************************************************
 */

require_once('includes/application_top.php');

class Notification
{

	/**
	 * object credential
	 * @var Credential
	 */
	private $_objCredential;

	/**
	 * object PagSeguro
	 * @var PagSeguro
	 */
	private $_objPagSeguro;

	/**
	 * @var Notification type
	 */
	private $_objNotificationType;

	/**
	 * @var Transaction
	 */
	private $_objTransaction;

	/**
	 * @var notification type
	 */
	private $_notification_type;

	/**
	 * @var notification code
	 */
	private $_notification_code;

	/**
	 * @var post
	 */
	private $_post;

	/**
	 * @var reference
	 */
	private $_reference;

	/**
	 * @var array status
	 */
	private $_arraySt;

	/**
	 * @var type
	 */
	private $_db;

	public function send($post)
	{

		global $db;

		$this->_db = $db;

		$this->_post = $post;

		$this->_addClass();

		$this->_addPagSeguroLibrary();

		$this->_initializePagSeguro();

		$this->_createCredential();

		$this->_createNotification();

		$this->_createNotificationType();

		if ($this->_objNotificationType->getValue() == $this->_notification_type ) {
			$this->_createTransaction();
			$this->_updateCms();
		}
	}

	private function _addPagSeguroLibrary()
	{
		include_once(IS_ADMIN_FLAG === true ? DIR_FS_CATALOG_MODULES : DIR_WS_MODULES) . '/payment/pagseguro/PagSeguroLibrary/PagSeguroLibrary.php';
	}

	private function _addClass()
	{
		include_once(IS_ADMIN_FLAG === true ? DIR_FS_CATALOG_MODULES : DIR_WS_MODULES) . '/payment/pagseguro.php';
	}

	/**
	 * Initialize PagSeguro
	 */
	private function _initializePagSeguro()
	{
		$this->_objPagSeguro = new pagseguro();
		$this->_arraySt = $this->_objPagSeguro->arrayStatus();
	}

	/**
	 * Create Credential
	 */
	private function _createCredential()
	{

		$configurations = $this->_retrievePagSeguroConfiguration();

		$this->_objCredential = new PagSeguroAccountCredentials($configurations['MODULE_PAYMENT_PAGSEGURO_EMAIL'], $configurations['MODULE_PAYMENT_PAGSEGURO_TOKEN']);
	}

	/**
	 * Retrive PagSeguro Configuration
	 * @global type $db
	 * @return type
	 */
	private function _retrievePagSeguroConfiguration()
	{

		$queryResult = $this->_db->Execute("SELECT *
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
	 * Create Notification
	 */
	private function _createNotification()
	{
		$this->_notification_type = (isset($this->_post['notificationType']) && trim($this->_post['notificationType']) != "") ? $this->_post['notificationType'] : null;
		$this->_notification_code = (isset($this->_post['notificationCode']) && trim($this->_post['notificationCode']) != "") ? $this->_post['notificationCode'] : null;
	}

	/**
	 * Create notification type
	 */
	private function _createNotificationType()
	{
		$this->_objNotificationType = new PagSeguroNotificationType();
		$this->_objNotificationType->setByType('TRANSACTION');
	}

	/**
	 * Create transaction
	 */
	private function _createTransaction()
	{
		$this->_objTransaction = PagSeguroNotificationService::checkTransaction($this->_objCredential, $this->_notification_code);
		$this->_reference = $this->_objTransaction->getReference();
	}

	/**
	 * Update cms
	 */
	private function _updateCms()
	{
		$_arrayValue = $this->_arraySt[$this->_objTransaction->getStatus()->getValue()];
		$_idStatus = $this->_returnIdByOrderStatusPagSeguro($_arrayValue);
		$this->_updateOrders($_idStatus);
	}

	/**
	 * Return id by order status pagseguro
	 * @global type $db
	 * @param type $value
	 * @return id order status
	 */
	private function _returnIdByOrderStatusPagSeguro($value)
	{

		$query = "SELECT orders_status_id
                        FROM " . TABLE_ORDERS_STATUS . "
                        WHERE orders_status_name = ";

		$query_br = ($query . ' \'' . $value['br'] . '\'');
		$query_en = ($query . ' \'' . $value['en'] . '\'');

		$result = $this->_db->Execute($query_br);

		if ($result->EOF ) {
			$result = $this->_db->Execute($query_en);
		}

		return (int) $result->fields['orders_status_id'];
	}

	/**
	 * Update orders
	 * @param type $idStatus
	 */
	private function _updateOrders($idStatus)
	{

		$query = "UPDATE " . TABLE_ORDERS . "
                        SET orders_status = " . $idStatus . "
                        WHERE orders_id = " . $this->_reference;

		try
		{

			$this->_db->Execute($query);
			$this->_updateOrderStatus($idStatus);

		}
		catch (Exception $exc)
		{
			echo $exc->getTraceAsString();
		}

	}

	/**
	 * Update order status when the order process is being finalized
	 * @param type $order_id
	 * @param type $order_status_id
	 */
	private function _updateOrderStatus($order_status_id)
	{

		$sql_data_array = array(array('fieldName' => 'orders_id', 'value' => $this->_reference, 'type' => 'integer'),
			array('fieldName' => 'orders_status_id', 'value' => $order_status_id, 'type' => 'integer'),
			array('fieldName' => 'date_added', 'value' => 'now()', 'type' => 'noquotestring'),
			array('fieldName' => 'customer_notified', 'value' => 1, 'type' => 'integer'),
			array('fieldName' => 'comments', 'value' => 'STATUS ATUALIZADO', 'type' => 'string'));

		$this->_db->perform(TABLE_ORDERS_STATUS_HISTORY, $sql_data_array);
	}
}
?>
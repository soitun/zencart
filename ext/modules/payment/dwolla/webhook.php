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
 * @version 3.1.0
 * 
 */
  chdir('../../../../');
  require('includes/application_top.php');
  global $db;
  
  # check Dwolla signature:
  $rawPOSTBody = file_get_contents("php://input");
  // get request headers:
  if (!function_exists('getallheaders')) { 
      function getallheaders() { 
          $headers = ''; 
         foreach ($_SERVER as $name => $value) { 
             if (substr($name, 0, 5) == 'HTTP_') { 
                 $headers[str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))))] = $value; 
             } 
         } 
         return $headers; 
      } 
  }
  $headers = getallheaders();
  $receivedSignature = $headers['X-Dwolla-Signature'];
  $expectedSignature = hash_hmac('sha1', $rawPOSTBody, MODULE_PAYMENT_DWOLLA_API_SECRET);

  $verified = $receivedSignature == $expectedSignature;

  # decode JSON:
  $parsed_data = json_decode($rawPOSTBody);

  # extract data:
  $status = $parsed_data->Value;
  $transactionId = $parsed_data->Id;
  $timestamp = $parsed_data->Triggered;

  # lookup corresponding order:
  $order = $db->Execute("select orders_status, currency, currency_value, orders_id from " . TABLE_ORDERS . " where cc_number = '" . $transactionId . "'");
  $order_status_id = (int) $order->fields['orders_status'];
  $orderId = (int) $order->fields['orders_id'];

  # update order:
  if ($verified) {
    if ($status == "processed") {
      # update order status to reflect processed status:
      $db->Execute("update " . TABLE_ORDERS . " set orders_status = '" . MODULE_PAYMENT_DWOLLA_SUCCESS_ORDER_STATUS_ID . "', last_modified = now() where orders_id = '" . $orderId . "'");

      # update order status history:
      $sql_data_array = array('orders_id' => $orderId,
                              'orders_status_id' => MODULE_PAYMENT_DWOLLA_SUCCESS_ORDER_STATUS_ID,
                              'date_added' => 'now()',
                              'customer_notified' => '0',
                              'comments' => 'Dwolla Transaction Processed! [Transaction ID: ' . $transactionId . ']');
      zen_db_perform(TABLE_ORDERS_STATUS_HISTORY, $sql_data_array);
    }
    else if ($status == "pending") {
      # update order status history to show transaction pending:
      $sql_data_array = array('orders_id' => $orderId,
                              'orders_status_id' => $order_status_id,
                              'date_added' => 'now()',
                              'customer_notified' => '0',
                              'comments' => 'Dwolla Transaction Status: ' . $status
                              );

      zen_db_perform(TABLE_ORDERS_STATUS_HISTORY, $sql_data_array);
    }
    else {
      # if status not processed or pending, it must be 'cancelled', 'failed', or 'reclaimed' which is a failure, so record this:
      $db->Execute("update " . TABLE_ORDERS . " set orders_status = '" . MODULE_PAYMENT_DWOLLA_FAILED_ORDER_STATUS_ID . "', last_modified = now() where orders_id = '" . $orderId . "'");

      # update order status history:
      $sql_data_array = array('orders_id' => $orderId,
                              'orders_status_id' => MODULE_PAYMENT_DWOLLA_FAILED_ORDER_STATUS_ID,
                              'date_added' => 'now()',
                              'customer_notified' => '0',
                              'comments' => 'Dwolla Transaction Status: ' . $status . ' [Transaction ID: ' . $transactionId . ']');
      zen_db_perform(TABLE_ORDERS_STATUS_HISTORY, $sql_data_array);
    }
  }
  else {
    # if signature verification failed, update the provided order's history to log this
    $sql_data_array = array('orders_id' => $orderId,
                              'orders_status_id' => $order_status_id,
                              'date_added' => 'now()',
                              'customer_notified' => '0',
                              'comments' => 'Dwolla Webhook Signature failed to verify!'
                              );

    zen_db_perform(TABLE_ORDERS_STATUS_HISTORY, $sql_data_array);
  }
  require('includes/application_bottom.php'); 
?>

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

  # parse JSON:
  $rawPOSTBody = file_get_contents("php://input");
  $parsed_data = json_decode($rawPOSTBody);

  # extract data:
  $orderId = (int)$parsed_data->OrderId;
  $checkoutId = $parsed_data->CheckoutId;
  $transactionId = $parsed_data->TransactionId;
  $amount = number_format($parsed_data->Amount, 2);

  # verify supplied signature:
  $receivedSignature = $parsed_data->Signature;
  $expectedSignature = hash_hmac('sha1', "{$checkoutId}&{$amount}", MODULE_PAYMENT_DWOLLA_API_SECRET);
  $verified = $receivedSignature == $expectedSignature;

  # lookup corresponding order:
  $order = $db->Execute("select orders_status, currency, currency_value from " . TABLE_ORDERS . " where orders_id = '" . $orderId . "'");
  $order_status_id = (int) $order->fields['orders_status'];
  
  # update order:
  if ($verified) {
    $status = $parsed_data->Status;
    if ($status == "Completed") {
      # update order status to reflect Completed status and store transaction ID in field cc_number:
      $new_order_status_id = MODULE_PAYMENT_DWOLLA_NEW_ORDER_STATUS_ID;
      $db->Execute("update " . TABLE_ORDERS . " set orders_status = '" . $new_order_status_id . "', cc_number = '" . $transactionId . "', last_modified = now() where orders_id = '" . $orderId . "'");

      # update order status history:
      $sql_data_array = array('orders_id' => $orderId,
                              'orders_status_id' => $new_order_status_id,
                              'date_added' => 'now()',
                              'customer_notified' => '0',
                              'comments' => 'Dwolla Transaction Submitted Successfully [Transaction ID: ' . $transactionId . ', Checkout ID: ' . $checkoutId .']');
      zen_db_perform(TABLE_ORDERS_STATUS_HISTORY, $sql_data_array);
    }
    else {
      # update order status and order status history to show checkout failed:
      $error = $parsed_data->Error;
      $failed_order_status_id = MODULE_PAYMENT_DWOLLA_FAILED_ORDER_STATUS_ID;

      $db->Execute("update " . TABLE_ORDERS . " set orders_status = '" . $failed_order_status_id . "', last_modified = now() where orders_id = '" . $orderId . "'");

      $sql_data_array = array('orders_id' => $orderId,
                              'orders_status_id' => $failed_order_status_id,
                              'date_added' => 'now()',
                              'customer_notified' => '0',
                              'comments' => 'Dwolla Checkout Failed: ' . $error
                              );

      zen_db_perform(TABLE_ORDERS_STATUS_HISTORY, $sql_data_array);
    }
  }
  else {
    # if signature verification failed, update the provided order's history to log this
    $sql_data_array = array('orders_id' => $orderId,
                              'orders_status_id' => $order_status_id,
                              'date_added' => 'now()',
                              'customer_notified' => '0',
                              'comments' => 'Dwolla Callback Signature failed to verify!'
                              );

    zen_db_perform(TABLE_ORDERS_STATUS_HISTORY, $sql_data_array);
  }
  require('includes/application_bottom.php'); 
?>

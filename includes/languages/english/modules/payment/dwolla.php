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
$webhookURL = zen_href_link('ext/modules/payment/dwolla/webhook.php', '' , 'SSL', true, true, true);
// remove admin site directory from URL:
$webhookURL_parts = explode('/', $webhookURL);
unset($webhookURL_parts[count($webhookURL_parts) - 6]);
$webhookURL_parts = array_values($webhookURL_parts);
$webhookURL = implode('/', $webhookURL_parts);
  define('MODULE_PAYMENT_DWOLLA_TEXT_TITLE', 'Dwolla');
  define('MODULE_PAYMENT_DWOLLA_TEXT_PUBLIC_TITLE', "<p><img src='https://dzencart.herokuapp.com/logo.png' style='width:70px;height:39px' alt='Dwolla' /><br/>Dwolla Secure Payment</p><p><i>(You will be taken to Dwolla's secure checkout upon confirming this order)</i></p>");
  define('MODULE_PAYMENT_DWOLLA_TEXT_DESCRIPTION', '<p>A safer, easier and faster way to move, access and earn money starts here. Welcome to the Dwolla network.</p><img src="images/icon_popup.gif" border="0">&nbsp;<a href="https://www.dwolla.com/applications" target="_blank" style="text-decoration: underline; font-weight: bold;">Generate Dwolla API Credentials</a><p>When registering a Dwolla Application for this site, you will need to input this <b>Webhook URL</b>:<br />' . $webhookURL . '</p>');

  define('MODULE_PAYMENT_DWOLLA_ACCOUNT_ID', 'Dwolla Account ID (xxx-xxx-xxxx):');
  define('MODULE_PAYMENT_DWOLLA_PAYMENT_KEY', 'Dwolla Payment Key (sent to email used with your Dwolla account):');
?>

MODULE_PAYMENT_DWOLLA_TEXT_TITLE
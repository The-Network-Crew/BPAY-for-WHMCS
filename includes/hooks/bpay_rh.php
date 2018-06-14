<?php

/**
 * BPay Manager Hook Function
 *
 * This hook is used to manage the invoice management for the BPay crm system
 *
 * @package    WHMCS
 * @author     Relentless Hosting Pty Ltd <admin@relentlesshosting.com.au>
 * @copyright  Copyright (c) Relentless Hosting Pty Ltd 2005-2016
 * @license    http://www.whmcs.com/license/ WHMCS Eula
 * @version    1.4
 * @link       http://www.relentlesshosting.com.au/
 */


if (!defined("WHMCS"))
    die("This file cannot be accessed directly");

if (file_exists('modules/addons/bpay_rh/bpay_rh_hooks.php')) {
  require_once('modules/addons/bpay_rh/bpay_rh_hooks.php');
  call_hooks();
}else if (file_exists('../modules/addons/bpay_rh/bpay_rh_hooks.php')) {
  require_once('../modules/addons/bpay_rh/bpay_rh_hooks.php');
  call_hooks();
}else if (file_exists('../../modules/addons/bpay_rh/bpay_rh_hooks.php')) {
  require_once('../../modules/addons/bpay_rh/bpay_rh_hooks.php');
  call_hooks();  
}else if (file_exists('../../../modules/addons/bpay_rh/bpay_rh_hooks.php')) {
  require_once('../../../modules/addons/bpay_rh/bpay_rh_hooks.php');
  call_hooks();
}else if (file_exists('../../../../modules/addons/bpay_rh/bpay_rh_hooks.php')) {
  require_once('../../../../modules/addons/bpay_rh/bpay_rh_hooks.php');
  call_hooks();
}else{
  // failed - but dont crash system, just dont run this hook

	$current = file_get_contents("bpay_rh_debug.txt");
	// Append a new person to the file
	$current .= getcwd()."\n";
	// Write the contents back to the file
	file_put_contents($file, $current);
}


    


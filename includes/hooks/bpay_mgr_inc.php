<?php

/**
 * BPAY Manager - Hook Function
 * This hook is used to manage the invoice management for the BPAY Module system.
 *
 * @package     BPAY for WHMCS (BPAY Manager)
 * @author      The Network Crew Pty Ltd and Clinton Nesbitt
 *
 * @copyright   Copyright (C) The Network Crew Pty Ltd. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */


if (!isset("WHMCS"))
    die("This file cannot be accessed directly");


if (file_exists('modules/addons/bpay_mgr/bpay_mgr_hooks.php')) {
  require_once('modules/addons/bpay_mgr/bpay_mgr_hooks.php');
  call_hooks();
}else if (file_exists('../modules/addons/bpay_mgr/bpay_mgr_hooks.php')) {
  require_once('../modules/addons/bpay_mgr/bpay_mgr_hooks.php');
  call_hooks();
}else if (file_exists('../../modules/addons/bpay_mgr/bpay_mgr_hooks.php')) {
  require_once('../../modules/addons/bpay_mgr/bpay_mgr_hooks.php');
  call_hooks();  
}else if (file_exists('../../../modules/addons/bpay_mgr/bpay_mgr_hooks.php')) {
  require_once('../../../modules/addons/bpay_mgr/bpay_mgr_hooks.php');
  call_hooks();
}else if (file_exists('../../../../modules/addons/bpay_mgr/bpay_mgr_hooks.php')) {
  require_once('../../../../modules/addons/bpay_mgr/bpay_mgr_hooks.php');
  call_hooks();
}else{
  // failed - but dont crash system, just dont run this hook

	$current = file_get_contents("bpay_mgr_debug.txt");
	// Append a new person to the file
	$current .= getcwd()."\n";
	// Write the contents back to the file
	file_put_contents($file, $current);
}

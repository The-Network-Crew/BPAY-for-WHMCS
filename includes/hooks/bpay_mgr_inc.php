<?php

// BPAY for WHMCS - /includes/hooks/bpay_mgr_inc.php
// https://github.com/LEOPARD-host/BPAY-for-WHMCS/

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


if (!defined("WHMCS")) {
  die("This file cannot be accessed directly");
}

if (file_exists(ROOTDIR . '/modules/addons/bpay_mgr/bpay_mgr_hooks.php')) {
  require_once(ROOTDIR . '/modules/addons/bpay_mgr/bpay_mgr_hooks.php');
  call_hooks();
}else if (file_exists('modules/addons/bpay_mgr/bpay_mgr_hooks.php')) {
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
  logActivity("DID NOT CALL BPAY HOOKS - reached debug step instead", 0);
  // $debugfile = dirname( dirname( __DIR__ ) ) . DIRECTORY_SEPARATOR . 'modules' . DIRECTORY_SEPARATOR . 'addons' . DIRECTORY_SEPARATOR . 'bpay_mgr' . DIRECTORY_SEPARATOR . 'bpay_mgr_debug.txt';
  // Failed - but don't crash, just log the CWD to debug later
  // $debuglog = file_get_contents($debugfile);
  // Append the dir onto the file contents
  // $debuglog .= "BPAY Hooks not called, as file not located.\n";
  // $debuglog .= getcwd()."\n";
  // Write the contents back
  // file_put_contents($debugfile, $debuglog);
}
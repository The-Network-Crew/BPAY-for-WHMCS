<?php 

// BPAY for WHMCS - /modules/addons/bpay_mgr/bpay_mgr_hooks.php
// https://github.com/LEOPARD-host/BPAY-for-WHMCS/

if (!defined("WHMCS"))
  die("This file cannot be accessed directly");
$conn;

connect_hook_DB();


function connect_hook_DB(){
  GLOBAL $conn;
  // load DB connection for global access
  if (file_exists('configuration.php')) {require("configuration.php");}else if (file_exists(ROOTDIR.'/configuration.php')) {require(ROOTDIR."/configuration.php");}else{die('Error - no database found');}

  // Create connection
  $conn = new mysqli($db_host, $db_username, $db_password, $db_name);

  // Check connection
  if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
  } 
}


function bpay_hook_version(){
  return "2.2.0";
}


function is_hook_out_dated(){
  if(get_hooks_latest_version() > bpay_hook_version()){
    return "<br><span style='float:right;'><b>BPAY Manager Hooks is outdated: <a style='color:red' href='https://github.com/LEOPARD-host/BPAY-for-WHMCS'>Download new version!</a></span>";
  }
}


function get_hooks_latest_version(){
  $ch = curl_init();
  curl_setopt($ch, CURLOPT_URL, "https://raw.githubusercontent.com/LEOPARD-host/BPAY-for-WHMCS/master/version");
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  $result = curl_exec($ch);
  curl_close ($ch);

  return str_replace("\n", "", $result);
}


/////////////////////////////////////////////
///////////// HOOKS FOR WHMCS ///////////////
/////////////////////////////////////////////


// This is used to register hooks via ~/includes/hooks/bpay_mgr_inc.php
function call_hooks(){

  GLOBAL $conn;

  $sql = "select * from mod_bpay_display";
  $result = $conn->query($sql);

  // GOING THROUGH THE DATA
  if($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
      if($row['option'] == "global_search" && $row['value'] == "1")
        add_hook("AdminAreaHeadOutput",1,"bpay_global_search");
      else if($row['option'] == "adminInvoicePage" && $row['value'] == "1")
        add_hook("ViewInvoiceDetailsPage",1,"adminViewInvoice");
      else if($row['option'] == "adminSummaryPage" && $row['value'] == "1")
        add_hook("AdminAreaClientSummaryPage",1,"clientSummaryAdminArea");
    }
  }

  // WHMCS Hook Index: https://developers.whmcs.com/hooks/hook-index/
  add_hook("InvoiceCreationPreEmail",1,"invoiceCreated");
  add_hook("InvoiceUnpaid",1,"unpaidInvoice");
  add_hook("ClientAdd",1,"createClientCRN");
  // ClientAreaPageViewInvoice
  // ClientAreaPage

}


function adminViewInvoice($var){
  GLOBAL $conn;
  $crn = getCRN($var['invoiceid']);

  echo '<script>
  setTimeout(function() { 
    $image = $("<img id='."'".'BpayAdminViewInvoice'."'".' src='."'".'../modules/gateways/bpay.php?cust_id='.$crn."'".' width='."'".'300px'."'".' style='."'".'margin-top:-20px;'."'".' />");
    $image.insertBefore($(\'select[name=tplname]\').parent());
    }, 500);
    </script>';
}


// HOOK FIRES in Admin Area (head)
function bpay_global_search($vars){
  GLOBAL $conn;

  $sql = 'select value 
  from mod_bpay_display 
  where `option`="global_search"';

  $result = $conn->query($sql) ;

  // GOING THROUGH THE DATA
  if($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
      if($row['value'] == 1){
        break;
      }else{
        return false; //search not enabled
      }
    }
  }else{
    return false; // search no specified thus default off
  }

  if($vars['addon_modules']['bpay_mgr'] != "BPAY Manager"){
    return false; //user does not have access to BPAY addon
  }

  // send token to validate user is currently login
  $tkval = array_key_exists("tkval", $_SESSION) ? $_SESSION['tkval'] : null;
  $CRFS = sha1($tkval . session_id() . ":whmcscrsf");
  $html = '<script>
  var bpayResults = "";
  var searchCheck = false;

  function addBPAYresults(){
    if(searchCheck === false){
      searchCheck = true;
      $("#searchresultsscroller").prepend(bpayResults);
    }
  }

  $(document).ready(function(){
    $("#searchresultsscroller").bind("DOMNodeInserted DOMNodeRemoved", function(event) {
      var search = $("#searchresultsscroller").html();

      if(search.indexOf("supporttickets.php") !== -1 || search.indexOf("clientssummary.php") !== -1 || search.indexOf("clientscontacts.php") !== -1 || search.indexOf("clientshosting.php") !== -1 || search.indexOf("invoices.php") !== -1){
        console.log("whmcs results");
        if(search.indexOf("BPAY clients") === -1){
          searchCheck = false;
          addBPAYresults();
        }
      }else if(search.indexOf("No Matches Found!") !== -1){
        console.log("whmcs - No Matches Found!");
        if(bpayResults != ""){
          console.log("search: "+searchCheck);
          if(searchCheck === false){
            if(search.indexOf("BPAY clients") === -1){
              searchCheck = true;
              $("#searchresultsscroller").html(bpayResults);
            }
          }
        }
      }else{
        addBPAYresults();
      }
    });

    $("#frmintellisearch").submit(function(e) {
      e.preventDefault();

      searchCheck = false;
      bpayResults = "";

        // TRIGGER YOUR FUNCTION
      var post_data = {
        "value": $("#intellisearchval").val(),
        "token": "'.$CRFS.'"
      };
      $.ajax({
        type: "POST",
        url: "addonmodules.php?module=bpay_mgr&searchGlobal=1",
        dataType: "json",
        data: post_data,
        success: function(response)
        {
          bpayResults = response;
            // console.log("success");
            // console.log(response);
          if($("#searchresultsscroller").html().indexOf("No Matches Found!") !== -1){
            if(response != ""){
                // console.log("search: "+searchCheck);
              if(searchCheck === false){
                if($("#searchresultsscroller").html().indexOf("BPAY") === -1){
                  searchCheck = true;
                  $("#searchresultsscroller").html(response);
                }
              }
            }
          }else if($("#searchresultsscroller").html().indexOf("BPAY") === -1){
            searchCheck = false;
            $("#searchresultsscroller").prepend(bpayResults);
          }
          return true;
        },
        error: function(failure,failmessage,error)
        { 
                // console.log("error");
          return false;
        }
      });
    });
  });
</script>';

  return $html;
}


// HOOK FIRES on Invoice Creation
function invoiceCreated($var){
  GLOBAL $conn;

  $sql = "Select `userid` from `tblinvoices` where `id` = ".$var['invoiceid']."'";
  $result = $conn->query($sql);

  // GOING THROUGH THE DATA
  if($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
      $userID = $row['userid'];
      break;
    }

    // create crn in DB
    createCRN($userID, $var['invoiceid']);
  }
}


// HOOK FIRES on Invoice Unpaid State
function unpaidInvoice($var){
  GLOBAL $conn;

  $sql = "Select `crn` from `mod_bpay_record` where `invoiceID` = '".$var['invoiceid']."'";
  $result = $conn->query($sql);

  // GOING THROUGH THE DATA
  if($result->num_rows == 0) {
    invoiceCreated($var); //run create invoice function
  }
}


// generate crn and store in db if not already exisitng 
function createCRN($clientID, $invoiceID = false){
  GLOBAL $conn;

  if (file_exists('modules/gateways/bpay.php')) {include_once("modules/gateways/bpay.php");}else if (file_exists(ROOTDIR.'/modules/gateways/bpay.php')) {include_once(ROOTDIR."/modules/gateways/bpay.php");}else{die('Error - no BPAY gateway found');}

  if($invoiceID){
    // check if already exists
    if(!checkRef("invoice", $invoiceID)){
      // generate CRN for invoice and store
      $crn = generateBpayRef($invoiceID);
      $sql = "INSERT INTO `mod_bpay_record` (`crn`, `clientID`, `invoiceID`, `crn_type`) VALUES ('".$crn."', '".$clientID."', '$invoiceID', 'Invoice');";
      $conn->query($sql);
    }

    // check if alread exists
    if(!checkRef("client", $clientID)){
      // generate crn for client and store
      $crn = generateBpayRef($clientID);
      $sql = "INSERT INTO `mod_bpay_record` (`crn`, `clientID`, `crn_type`) VALUES ('".$crn."', '".$clientID."', 'Client ID');";
      $conn->query($sql);
    }
  }else{
    // client ID only so create crn if not exists
    // check if alread exists
    if(!checkRef("client", $clientID)){
      // generate crn for client and store
      $crn = generateBpayRef($clientID);
      $sql = "INSERT INTO `mod_bpay_record` (`crn`, `clientID`, `crn_type`) VALUES ('".$crn."', '".$clientID."', 'Client ID');";
      $conn->query($sql);
    }
  }
}


// Check if client/invoice ID present in DB already
function checkRef($type, $key){
  GLOBAL $conn;

  if($type == "client"){
    $sql = "SELECT crn from mod_bpay_record where crn_type = 'Client ID' AND clientID = ".$key;
  }else{
    $sql = "SELECT crn from mod_bpay_record where invoiceID = ".$key;
  }

  $result = $conn->query($sql) or die($conn->error);

  // GOING THROUGH THE DATA
  if($result->num_rows > 0) {
    return true;
  }
  return false;
}


// Get CRN based on the Invoice
function getCRN($invoiceID){
  GLOBAL $conn;

  $settings = db_hook_access("settings");

  if($settings['crnMethod'] == "Customer ID"){
    $sql = "Select mod_bpay_record.crn from tblinvoices 
    LEFT JOIN mod_bpay_record ON mod_bpay_record.clientID = tblinvoices.userid
    where mod_bpay_record.crn_type = 'Client ID' AND tblinvoices.id = ".$invoiceID;

    $result = $conn->query($sql);

    // GOING THROUGH THE DATA
    if($result->num_rows > 0) {
      while($row = $result->fetch_assoc()) {
        return $row['crn'];
      }
    }
  }else{
    $sql = "SELECT crn from mod_bpay_record where invoiceID = ".$invoiceID;
    $result = $conn->query($sql);

    // GOING THROUGH THE DATA
    if($result->num_rows > 0) {
      while($row = $result->fetch_assoc()) {
        return $row['crn'];
      }
    }else{
      createCRN(getClientID($invoiceID),$invoiceID);
      return getCRN($invoiceID);
    }
  }
}


function getClientID($invoiceID){
  GLOBAL $conn;

  $sql = "SELECT userid from tblinvoices where id = '$invoiceID'";

  $result = $conn->query($sql) or die($conn->error);

  // GOING THROUGH THE DATA
  if($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
      return $row['userid'];
    }
  }
  return false;
}


// HOOK FIRES on Client Create in WHMCS
function createClientCRN($var){
  createCRN($var['userid']);
}


// Admin GUI: Show BPAY Ref on client-basis if configured
function clientSummaryAdminArea($var){
  GLOBAL $conn;
  $settings = db_hook_access("settings");;
  $crn = "";
  if($settings['crnMethod'] == "Customer ID"){

    $sql = "Select crn from mod_bpay_record where crn_type = 'Client ID' and clientID = ".$var['userid'];

    $result = $conn->query($sql);

    // GOING THROUGH THE DATA
    if($result->num_rows > 0) {
      while($row = $result->fetch_assoc()) {
        $crn = $row['crn'];
      }
    // echo "<img src='../modules/gateways/bpay.php?cust_id=".$crn."' width='300px' />";

    echo "<script>
    setTimeout(function() { 
      console.log($('textarea[name=adminnotes]').parent().parent().parent().html());

      $(".'"'."</div><div id='BpayClientSummaryAdminArea' class='clientssummarybox'><div class='title'>BPAY Details</div><img src='../modules/gateways/bpay.php?cust_id=".$crn."' width='100%' align='middle' />".'"'.")
      .insertBefore($('textarea[name=adminnotes]').parent().parent().parent());

      // .insertAfter($('#clientsummarycontainer div div:eq(20)').children().next());/**/
      // $image = $('<img src='../modules/gateways/bpay.php?cust_id=".$crn."' width='300px' />');
      // $image.insertBefore( $('#tab1 table td form'));
    }, 500);
    </script>";

    echo "<style>
    #BpayClientSummaryAdminArea {
      color=green;
    }
    </style>";
    }
  }
}


function db_hook_access($action, $key = 0, $display_errors = false){
  GLOBAL $conn;

  if($action == "settings"){
    /*******************************************/
    $sql = "SELECT * FROM `mod_bpay_display`";
    $result = $conn->query($sql);

    if($result->num_rows > 0) {

      $list = array();
      while($row = $result->fetch_assoc()) {
        $list[$row['option']] = $row['value'];
      }

      return $list;
    }
    return false; 
    /******************************************/
  }

  if($action == "update_local"){
    $settings = db_hook_access("settings");

    if($settings){
      if(isset($settings['localKey'])){
        $sql = "UPDATE `mod_bpay_display` SET `value`='".$key."' WHERE `option`='localKey';";
        $query = $conn->query($sql);
        if(!$query){
          $error = true;
        } 
      }else{
        if(!isset($key)){
          $key = "0";
          $sql = "INSERT INTO `mod_bpay_display` (`option`, `value`) VALUES ('localKey', '".$key."');";
          $query = $conn->query($sql);
        }
        if(!$query){
          $error = true;
        } 
      }
    }
  }
}

/////////////////////////////////////////////
///////////// HOOKS FOR WHMCS ///////////////
/////////////////////////////////////////////

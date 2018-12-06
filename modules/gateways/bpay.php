<?php
function bpay_config() {
  $configarray = array(
   "FriendlyName" => array("Type" => "System", "Value"=>"BPAY"),
   "version" => array("FriendlyName" => "Version Number", "Description" => gate_bpay_version().gateway_check_version(), ),
   "instructions" => array("FriendlyName" => "Test Link", "Description" => "<a href='../modules/gateways/bpay.php?cust_id=12345' target='_blank'>View Test Image</a>", ),
   );
  return $configarray;
}


function bpay_link($params) {

  # Invoice Variables
  $invoiceNumb = $params['invoicenum'];
  $customer_id = $params['clientdetails']['userid'];

  # Client Variables
  $customerID = $params['clientdetails']['id'];
  $billerID = $params['BillerCode'];
  $CRN_length = $params['CRNLength'];
  // $key = $params['key'];
  
  $settings = gate_db_access("settings");
  $crnMethod = $settings['crnMethod'];

  $data = new stdClass();
  $data->CRNLength = $CRNLength;
  $data->crnMethod = $crnMethod;//"Customer ID""Invoice Number"
  $data->client = $customer_id;
  $data->inv = $invoiceNumb;

  //get CRN ref from db
  $crn = gate_db_access("get_crn_ref", $data);

  if(!$crn)
    return false;

  // if($crnMethod == "Customer ID"){
  //   $crn = $customerID;
  // }else{
  //   $crn = $invoiceid;
  // }

  # Enter your code submit to the gateway...
  $code = null;

 return $code;
}

function gate_bpay_version(){
  return "2.1.8";
}

function gateway_check_version(){
  $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "https://raw.githubusercontent.com/beanonymous/whmcsBPAY/master/version");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $remoteVersion = curl_exec($ch);
    curl_close ($ch);

    $remoteVersion = str_replace("\n", "", $remoteVersion);

  if($remoteVersion > gate_bpay_version()){
    return " - <b><a style='color:red' href='https://github.com/beanonymous/whmcsBPAY'>Download New Update!</a></b>";
  }else if($remoteVersion == gate_bpay_version()){
    return " - <font color='green'>Curent Version</font>";
  }
  return "";
}

function gate_db_access($action,$key = 0, $display_errors = true){
  if(!isset($db_host))
  if (file_exists(ROOTDIR.'/configuration.php')) {require_once(ROOTDIR."/configuration.php");}elseif(file_exists('configuration.php')) {require("configuration.php");}elseif(file_exists('../configuration.php')) {require_once("../configuration.php");}elseif(file_exists('../../configuration.php')) {require_once("../../configuration.php");}else{echo "No configuration.php file found."; return;}

  $servername = $db_host;
  $username = $db_username;
  $password = $db_password;
  $db = $db_name;

    // Create connection
  $connGate = new mysqli($servername, $username, $password,$db);

    // Check connection
  if ($connGate->connect_error) {
   if($display_errors == true){show_error_message("Connection failed: " . $connGate->connect_error);}
 } 
   //echo "Connected successfully";
 
  //////////////////////////////
  if($action == "settings"){

    $queryz = "SELECT `option`, `value` FROM `mod_bpay_display`";

    $result = $connGate->query($queryz) or die($connGate->error);
    if($result->num_rows > 0) {
      $list = array();
      while($row = $result->fetch_assoc()) {
          $list[$row['option']] = $row['value'];
      }
      return $list;
    }
    return false; 

    // // GOING THROUGH THE DATA
    // if($result->num_rows > 0) {
    //   $list;
    //   while($row = mysqli_fetch_assoc($result)) {
    //     $list[$row["setting"]] = $row["value"];
    //   }
    //   $list["db_host"] = $db_host;
    //   $list["db_username"] = $db_username;
    //   $list["db_password"] = $db_password;
    //   $list["db_name"] = $db_name;
    //   return $list;
    // }else{
    //   return null;
    // }
  }elseif($action == "manager_settings"){
    $query = "SELECT * FROM `mod_bpay_display`";

    $result = $connGate->query($query) or die($connGate->error);

      // GOING THROUGH THE DATA

    if($result->num_rows > 0) {
      $list;
      while($row = $result->fetch_assoc()) {
        $list[$row['option']] = $row['value'];
      }
      return $list;
    }else{
      return null;
    }
  }elseif($action == "pdf_display_details"){
    $query = "SELECT value FROM `mod_bpay_display` WHERE `option` = 'PDF_display'";

    $result = $connGate->query($query) or die($connGate->error);

    if($result->num_rows > 0) {
      while($row = $result->fetch_assoc()) {
        return json_decode($row['value']);
      }
    }else{
      return null;
    }
  }elseif($action == "ref_inuse_check"){
    $query = "Select crn from mod_bpay_record where crn = '$key'";
    $result = $connGate->query($query) or die($connGate->error);
    if($result->num_rows > 0) {
      return true; // CRN in use
    }else{
      return false;
    }
  }elseif($action == "get_crn_ref"){
    if($key->crnMethod == "Customer ID"){
      $query = "Select crn from mod_bpay_record where clientID = '".$key->client."' and crn_type = 'Client ID'";
    }else{//"Invoice Number"
      $query = "Select crn from mod_bpay_record where invoiceID = '".$key->inv."' and crn_type = 'Invoice'";
    }

    $result = $connGate->query($query) or die($connGate->error);
    if($result->num_rows > 0) {
      while($row = $result->fetch_assoc()) {
        return $row['crn'];
      }
    }else{
      return null;
    }
  }elseif($action == "add_crn_ref"){

    if(isset($key->crn)){
      if($key->crnMethod == "Customer ID")
        $crnMethod = "Client ID";
      else
        $crnMethod = "Invoice";

      $query = "INSERT INTO `mod_bpay_record` (`crn`, `clientID`, `invoiceID`, `crn_type`) VALUES ('".$key->crn."', '".$key->client."', '".$key->inv."', '".$crnMethod."')";
    }else{
      return false;
    }

    $result = $connGate->query($query) or die($connGate->error);
    if($result->num_rows > 0) {
      return $key->crn;
    }else{
      return null;
    }
  }else if($action == "update_local"){
    $settings = gate_db_access("settings");

    if($settings){
      if(isset($settings['localKey'])){
        $sql = "UPDATE `mod_bpay_display` SET `value`='".$key."' WHERE `option`='localKey';";
        $query = $connGate->query($sql);
        if(!$query){$error = true;} 
      }else{
        if(!isset($key))
          $key = "0";
        $sql = "INSERT INTO `mod_bpay_display` (`option`, `value`) VALUES ('localKey', '".$key."');";
        $query = $connGate->query($sql);
        if(!$query){$error = true;} 
      }
    }
  }elseif($action == "get_img_type"){
    $query = "SELECT value FROM `mod_bpay_display` WHERE `option`='imgType'";

    $result = $connGate->query($query) or die($connGate->error);
    if($result->num_rows > 0) {
      while($row = $result->fetch_assoc()) {
        return $row['value'];
      }
    }else{
      return null;
    }
  }


  // CLOSE CONNECTION
  // mysqli_close($connGate);
  ////////////////////////
}

//REF: http://www.acnenomor.com/4611685p1/luhncalc-and-bpay-mod10-version-5
//REF: http://stackoverflow.com/questions/11024309/luhncalc-and-bpay-mod10-version-5118680014797721
function generateBpayRef($number) {
  $data = gate_db_access("manager_settings");
    
    /*******************************************
    *** start custom code for UBC Web Design ***
    *******************************************/
    //total length - check digit added after
    // if(strlen("1001".$number) < 9){
    //   do{
    //     $number = "0".$number;
    //   }while(strlen("1001".$number) < 9);
    // }

    // //add number at start of crn
    // $number = "1001".$number;

    /*******************************************
    ***  end custom code for UBC Web Design  ***
    *******************************************/

  if(is_array($data)){
    $modVersion = $data['mod10type']; //gate_db_access("modVersion");
    $CRNLength = $data['CRNLength'];
    $num_padding = $data['num_padding'];
    $Merchant_settings = $data['Merchant_settings'];
    $prefix = $data['prefix'];

    if($num_padding == "before"){
      if(strlen($number) < ((int)$CRNLength-1)){
        do{
          $number = "0".$number;
        }while(strlen($number) < ((int)$CRNLength-1));
      }
    }

    // Add prefix to start of CRN
    if($Merchant_settings == "ezidebit")
      $number = $prefix.substr($number, (strlen($prefix)));
    //END CUSTOM CODE



    // trim string to be CRN length - 1 digit at the end to allow for check digit to be added later
    $number = substr($number, 0, ((int)$CRNLength)-1);

    /******************************************************/
    /********************** MOD10v1 ***********************/
    /******************************************************/

    // https://github.com/fontis/fontis_australia/blob/master/src/app/code/community/Fontis/Australia/Model/Payment/Bpay.php
    if($modVersion == 'MOD10v1'){
      //Mod 10 v1
      $number = preg_replace("/\D/", "", $number);

      // The seed number needs to be numeric
      if(!is_numeric($number)) return false;
      // Must be a positive number
      if($number <= 0) return false;

        $revstr = strrev(intval($number));
        $total = 0;
        for ($i = 0; $i < strlen($revstr); $i++) {
            if ($i % 2 == 0) {
                $multiplier = 2;
            } else {
                $multiplier = 1;
            }
            $subtotal = intval($revstr[$i]) * $multiplier;
            if ($subtotal >= 10) {
                $temp = (string)$subtotal;
                $subtotal = intval($temp[0]) + intval($temp[1]);
            }
            $total += $subtotal;
        }
        $checkDigit = (10 - ($total % 10)) % 10;
      // $BpayMemberNo  = $stringMemberNo . $iSum ;

      if($num_padding == "after"){
        $separator = "";
        $crn = str_pad(ltrim($number, "0"), $CRNLength - 1, 0, STR_PAD_LEFT) . $separator . $checkDigit;
      }else{
        $crn = $number.$checkDigit;
      }

      // check if # already in use if so generate random to overcome issue
      if(gate_db_access("ref_inuse_check", $crn))
        return generateBpayRef(substr(rand(10000000000000, 9999999999999999), 0, ((int)$CRNLength)));
      // echo " New: $BpayMemberNo ";
      return ($crn);
    }else{ 
    /******************************************************/
    /********************** MOD10v5 ***********************/
    /******************************************************/

      $number = preg_replace("/\D/", "", $number);

      // The seed number needs to be numeric
      if(!is_numeric($number)) 
        return false;

      // Must be a positive number
      if($number <= 0) return false;

      // Get the length of the seed number
      $length = strlen($number);

      $total = 0;

      // For each character in seed number, sum the character multiplied by its one based array position (instead of normal PHP zero based numbering)
      for($i = 0; $i < $length; $i++) $total += $number{$i} * ($i + 1);

      // The check digit is the result of the sum total from above mod 10
      $checkdigit = fmod($total, 10);

      // Return the original seed plus the check digit
      $crn = $number . $checkdigit;

      if($num_padding == "after"){
        if(strlen($crn) < ((int)$CRNLength-1)){
          do{
            $crn = "0".$crn;
          }while(strlen($crn) < ((int)$CRNLength));
        }
      }
      // check if # already in use if so generate random to overcome issue
      if(gate_db_access("ref_inuse_check", $crn))
        return generateBpayRef(substr(rand(10000000000000, 9999999999999999), 0, ((int)$CRNLength)));

      return $crn;
    }
  }
}

function setup_resource_dir($dir_base){
    if (!file_exists( $dir_base.'bpay'))
      mkdir( $dir_base.'bpay', 0755, true);

    if (!file_exists( $dir_base.'bpay/img-bpay-biller-code-credit-horizontal.jpg')) {
      $ch = curl_init('https://raw.githubusercontent.com/beanonymous/whmcsBPAY/master/modules/gateways/bpay/img-bpay-biller-code-credit-horizontal.jpg');
      $fp = fopen( $dir_base.'bpay/img-bpay-biller-code-credit-horizontal.jpg', 'wb');
      curl_setopt($ch, CURLOPT_FILE, $fp);
      curl_setopt($ch, CURLOPT_HEADER, 0);
      curl_exec($ch);
      curl_close($ch);
      fclose($fp); 
    }

    if (!file_exists( $dir_base.'bpay/img-bpay-biller-code-credit-vertical.jpg')) {
      $ch = curl_init('https://raw.githubusercontent.com/beanonymous/whmcsBPAY/master/modules/gateways/bpay/img-bpay-biller-code-credit-vertical.jpg');
      $fp = fopen( $dir_base.'bpay/img-bpay-biller-code-credit-vertical.jpg', 'wb');
      curl_setopt($ch, CURLOPT_FILE, $fp);
      curl_setopt($ch, CURLOPT_HEADER, 0);
      curl_exec($ch);
      curl_close($ch);
      fclose($fp); 
    }

    if (!file_exists( $dir_base.'bpay/img-bpay-biller-code-fixed-payments.jpg')) {
      $ch = curl_init('https://raw.githubusercontent.com/beanonymous/whmcsBPAY/master/modules/gateways/bpay/img-bpay-biller-code-fixed-payments.jpg');
      $fp = fopen( $dir_base.'bpay/img-bpay-biller-code-fixed-payments.jpg', 'wb');
      curl_setopt($ch, CURLOPT_FILE, $fp);
      curl_setopt($ch, CURLOPT_HEADER, 0);
      curl_exec($ch);
      curl_close($ch);
      fclose($fp); 
    }

    if (!file_exists( $dir_base.'bpay/img-bpay-biller-code-horizontal.jpg')) {
      $ch = curl_init('https://raw.githubusercontent.com/beanonymous/whmcsBPAY/master/modules/gateways/bpay/img-bpay-biller-code-horizontal.jpg');
      $fp = fopen( $dir_base.'bpay/img-bpay-biller-code-horizontal.jpg', 'wb');
      curl_setopt($ch, CURLOPT_FILE, $fp);
      curl_setopt($ch, CURLOPT_HEADER, 0);
      curl_exec($ch);
      curl_close($ch);
      fclose($fp); 
    }

    if (!file_exists( $dir_base.'bpay/img-bpay-biller-code-no-credit-horizontal.jpg')) {
      $ch = curl_init('https://raw.githubusercontent.com/beanonymous/whmcsBPAY/master/modules/gateways/bpay/img-bpay-biller-code-no-credit-horizontal.jpg');
      $fp = fopen( $dir_base.'bpay/img-bpay-biller-code-no-credit-horizontal.jpg', 'wb');
      curl_setopt($ch, CURLOPT_FILE, $fp);
      curl_setopt($ch, CURLOPT_HEADER, 0);
      curl_exec($ch);
      curl_close($ch);
      fclose($fp); 
    }

    if (!file_exists( $dir_base.'bpay/img-bpay-biller-code-no-credit-vertical.jpg')) {
      $ch = curl_init('https://raw.githubusercontent.com/beanonymous/whmcsBPAY/master/modules/gateways/bpay/img-bpay-biller-code-no-credit-vertical.jpg');
      $fp = fopen( $dir_base.'bpay/img-bpay-biller-code-no-credit-vertical.jpg', 'wb');
      curl_setopt($ch, CURLOPT_FILE, $fp);
      curl_setopt($ch, CURLOPT_HEADER, 0);
      curl_exec($ch);
      curl_close($ch);
      fclose($fp);
    }

    if (!file_exists( $dir_base.'bpay/img-bpay-biller-code-vertical.jpg')) {
      $ch = curl_init('https://raw.githubusercontent.com/beanonymous/whmcsBPAY/master/modules/gateways/bpay/img-bpay-biller-code-vertical.jpg');
      $fp = fopen( $dir_base.'bpay/img-bpay-biller-code-vertical.jpg', 'wb');
      curl_setopt($ch, CURLOPT_FILE, $fp);
      curl_setopt($ch, CURLOPT_HEADER, 0);
      curl_exec($ch);
      curl_close($ch);
      fclose($fp);
    }

    // Add index.html to directorys for security/bots
    if (!file_exists( $dir_base.'bpay/index.php'))
      fopen( $dir_base.'bpay/index.php', "w");

    if (!file_exists( $dir_base.'bpay/customers/index.php'))
      fopen( $dir_base.'bpay/customers/index.php', "w");

    if (!file_exists( $dir_base.'bpay/invoices/index.php'))
      fopen( $dir_base.'bpay/invoices/index.php', "w");
}

function generateImage($biller_code, $CRN, $output_type = "image", $output_location = false){
  ///////////////////////////////////////////////////////////////////////////
  /// START BPay Image Generation 
  ///////////////////////////////////////////////////////////////////////////
  //REF: http://stackoverflow.com/questions/11333196/gd-library-image-style-imagettftext
  if($output_type == "image")
    header('Content-Type: image/jpeg'); // setting the content-type

  if(basename($_SERVER['SCRIPT_FILENAME']) == "dl.php")
    $dir_base = 'modules/gateways/';
  else if(basename($_SERVER['SCRIPT_FILENAME']) == "cron.php"){
    if (file_exists(ROOTDIR.'/configuration.php')) {
      require_once(ROOTDIR."/configuration.php");
      if(isset($crons_dir) && $crons_dir != '')
        $dir_base = $crons_dir;
      else
        $dir_base = ROOTDIR.'/modules/gateways/';
    }else
      $dir_base = ROOTDIR.'/modules/gateways/';
  }
  else
    $dir_base = '';

  setup_resource_dir($dir_base);
  $image_type = "horizontal";  
  $image_type = gate_db_access('get_img_type');

  $file   =  $dir_base."bpay/img-bpay-biller-code-".$image_type.".jpg";
  $image  = imagecreatefromjpeg($file); // creating the image
  $font   =  $dir_base."bpay/arial.ttf";
  $size   = 40; //pixels
  $color  = imagecolorallocate($image, 0, 0, 0); //white color

  switch ($image_type) {
    case 'credit-horizontal':
      imagettftext($image, $size, 0, 630, 98, $color, $font, $biller_code); // Biller Code Number
      imagettftext($image, $size, 0, 485, 145, $color, $font, $CRN); // Customer Reference Number
      break;
    case 'credit-vertical':
      imagettftext($image, $size, 0, 513, 127, $color, $font, $biller_code); // Biller Code Number
      imagettftext($image, $size, 0, 335, 188, $color, $font, $CRN); // Customer Reference Number
      break;
    case 'fixed-payments':
      imagettftext($image, $size, 0, 513, 127, $color, $font, $biller_code); // Biller Code Number
      imagettftext($image, $size, 0, 335, 188, $color, $font, $CRN); // Customer Reference Number
      break;
    case 'horizontal':
      imagettftext($image, $size, 0, 630, 98, $color, $font, $biller_code); // Biller Code Number
      imagettftext($image, $size, 0, 485, 145, $color, $font, $CRN); // Customer Reference Number
      break;
    case 'no-credit-horizontal':
      imagettftext($image, $size, 0, 630, 98, $color, $font, $biller_code); // Biller Code Number
      imagettftext($image, $size, 0, 485, 145, $color, $font, $CRN); // Customer Reference Number
      break;
    case 'no-credit-vertical':
      imagettftext($image, $size, 0, 513, 127, $color, $font, $biller_code); // Biller Code Number
      imagettftext($image, $size, 0, 335, 188, $color, $font, $CRN); // Customer Reference Number
      break;
    case 'vertical':
      imagettftext($image, $size, 0, 520, 135, $color, $font, $biller_code); // Biller Code Number
      imagettftext($image, $size, 0, 335, 198, $color, $font, $CRN); // Customer Reference Number
      break;
    
    default:
      imagettftext($image, $size, 0, 630, 98, $color, $font, $biller_code); // Biller Code Number
      imagettftext($image, $size, 0, 485, 145, $color, $font, $CRN); // Customer Reference Number
      break;
  }
  
  if($output_type == "image")
    imagepng($image); // outputting the image
  else{
    imagepng($image, $output_location, 0, NULL);// save the image
    imagedestroy($image);
  } 
  ///////////////////////////////////////////////////////////////////////////
  /// END BPay Image Generation 
  ///////////////////////////////////////////////////////////////////////////
}

function show_error_message($message){
  // If not in gateway config display error
  if(basename($_SERVER['PHP_SELF'], '.php') != "configgateways")
    echo($message);
}

// get whmcs base url
function getBaseURL(){
  $string = $_SERVER['HTTP_HOST'].$_SERVER['PHP_SELF'];

  $count = strpos($string, '/modules');
  if($count != 0){
    $string = substr($string,0, $count);
  }

  $count = strpos($string, '/dl.php');
  if($count != 0){
    $string = substr($string,0, $count);
  }

  $count = strpos($string, '/admin/');
  if($count != 0){
    $string = substr($string,0, $count);
  }

  $count = strpos($string, '/clientarea.php');
  if($count != 0){
    $string = substr($string,0, $count);
  }

  $count = strpos($string, '/viewinvoice.php');
  if($count != 0){
    $string = substr($string,0, $count);
  }

  $count = strpos($string, '/cart.php');
  if($count != 0){
    $string = substr($string,0, $count);
  }

  if(strlen($string) == 0)
    $string = $_SERVER['HTTP_HOST'];

  return $string;
}

///////////////////////////////////////////////////////////////////////////
/// START BPay Generator
///////////////////////////////////////////////////////////////////////////

function BPAY_PDF($customer_id, $invoiceNumb = 0, $BillerCode = null, $CRNLength = null, $genCustNum = false){

  $output = array();
$output["img"] = "EMPTY";
  if(!$_SERVER['SCRIPT_FILENAME']){

    if(!$BillerCode){
      $BillerCode = $CRNLength = "";
      $BPAY_enabled = false;
      // $table = "mod_bpay_display";
      // $fields = "option,value";
      // $result = select_query($table,$fields,$where);
      $result = gate_db_access("settings");

      if(is_array($result)){
        $BPAY_enabled = true;
        foreach ($result as $key => $value) {
          if($key == "BillerCode"){
            $BillerCode = $value;
          }else if($key == "CRNLength"){
            $CRNLength = $value;
          }else if($key == "crnMethod"){
            $crnMethod = $value;
          }
        } //end for each
      }
    }

    if($BillerCode == ""){
      $BillerCode = 0;
    }
    $dir = "";
    // check if images exist, if not create and store
    // if($BPAY_enabled == true){
      if (file_exists(ROOTDIR.'/modules/gateways/bpay.php')) {
       $dir = ROOTDIR.'/modules/gateways/'; 
      }else if (file_exists('modules/gateways/bpay.php')) {
       $dir = "modules/gateways/";
      }else if (file_exists('../modules/gateways/bpay.php')) {
       $dir = "../modules/gateways/";
      }else if (file_exists('../../modules/gateways/bpay.php')) {
       $dir = "../../modules/gateways/";
      }else if (file_exists('../../../modules/gateways/bpay.php')) {
       $dir = "../../../modules/gateways/";
      }else{
        die('Error - no database found');
      }

      if (!file_exists($dir.'bpay')) {
        mkdir($dir.'bpay', 0755, true);
        setup_resource_dir(ROOTDIR.'/modules/gateways/bpay/');
        // $ch = curl_init('https://relentlesshosting.com.au/members/images/BPAY.jpg');
        // $fp = fopen($dir.'bpay/BPay.jpg', 'wb');
        // curl_setopt($ch, CURLOPT_FILE, $fp);
        // curl_setopt($ch, CURLOPT_HEADER, 0);
        // curl_exec($ch);
        // curl_close($ch);
        // fclose($fp); 

        // $ch = curl_init('https://relentlesshosting.com.au/members/images/arial.ttf');
        // $fp = fopen($dir.'bpay/arial.ttf', 'wb');
        // curl_setopt($ch, CURLOPT_FILE, $fp);
        // curl_setopt($ch, CURLOPT_HEADER, 0);
        // curl_exec($ch);
        // curl_close($ch);
        // fclose($fp); 

        // Add index.html to directorys for security
        fopen($dir."bpay/index.php", "w") ;
      }

      $data = new stdClass();
      $data->CRNLength = $CRNLength;
      $data->crnMethod = $crnMethod;//"Customer ID""Invoice Number"
      $data->client = $customer_id;
      $data->inv = $invoiceNumb;

      //get CRN ref from db
      $crnNumber = gate_db_access("get_crn_ref", $data);

      if(!$crnNumber){
        if($crnMethod == "Customer ID")
          $number = $customer_id;
        else
          $number = $invoiceNumb;

        $crnNumber = $data->crn = generateBpayRef($number);
        if(isset($data->crn))
          gate_db_access("add_crn_ref", $data);
        else
          return false;
      }

      // check if images directory are there and if not create them
      if($crnMethod == "Customer ID" || $genCustNum == true){
        if (!file_exists($dir.'bpay/customers/')) {
          mkdir($dir.'bpay/customers/', 0755, true); 
          fopen($dir.'bpay/customers/index.php', "w");
        }
        $img = ROOTDIR.'/modules/gateways/bpay/customers/'.$customer_id.'.jpg';
      }else{
        if (!file_exists($dir.'bpay/invoices/')) {
          mkdir($dir.'bpay/invoices/', 0755, true); 
          fopen($dir.'bpay/invoices/index.php', "w");
        }
        $img = ROOTDIR.'/modules/gateways/bpay/invoices/'.$invoiceNumb.'.jpg';
      }

      if($BillerCode == 0 || $BillerCode == ""){$BillerCode="0";}

      // determine if file already exists from previous generate
      if(!file_exists($img)){
        generateImage($BillerCode, $crnNumber, $output_type = "file", $img);
      }

      // return $img;
      //$pdf->Image(ROOTDIR.'/modules/gateways/bpay/customers/'.$clientsdetails["id"].'.jpg',126,45,50);
      
      if($crnMethod == "Customer ID" || $genCustNum == true){$output["mode"] = 1;}else{$output["mode"] = 2;}

      $db_results = gate_db_access("pdf_display_details");

      // if($db_results->pdf_display->enabled != 1){
      //   return false; //bpay in invoice display not active
      // }
      // return serialize( $db_results);
      $output["Xaxis"] = $db_results->pdf_display->Xaxis;
      $output["Yaxis"] = $db_results->pdf_display->Yaxis;
      $output["size"] = $db_results->pdf_display->size;
      $output["img"] = $img;
      return $output;
    // }else{
    //   return false;
    // }
  }else{

    return false;
  }
}
///////////////////////////////////////////////////////////////////////////
/// END BPay Generator
///////////////////////////////////////////////////////////////////////////

if(isset($_GET['CRNMethod'])){
  echo gate_db_access("crnMethod");
}

///////////////////////////////////////////////////////////////////////////
/// START BPAY Image Generator
///////////////////////////////////////////////////////////////////////////

else if(isset($_GET['cust_id'])){


    $data = gate_db_access("manager_settings");
    if(is_array($data)){
      $biller_code = $data['BillerCode'];
      if($biller_code == ""){
        $biller_code = 0;
      }
      
      $crn_l = $data['CRNLength'];

      if(is_numeric($_GET['cust_id']) && is_numeric($biller_code) && is_numeric($crn_l)){
        //required details to generate CRN
        $crn_input_id = $_GET['cust_id'];

        $CRN_legth = $crn_l; //Customer Reference Number (CRN) Length
        //if(!is_numeric($_GET['biller_id']) || is_null($_GET['biller_id']) || $_GET['biller_id'] == NULL || empty($_GET['biller_id']) || $_GET['biller_id'] == ""){$BillerCode="0";}else{$biller_code = $_GET['biller_id'];} //BPay Biller Code
      }else{
        die("Not a number");
      }
    }
    

    ///////////////////////////////////////////////////////////////////////////
    /// START Customer Reference Number(CRN) Generation 
    ///////////////////////////////////////////////////////////////////////////

    //GET CRN
    if (strlen($crn_input_id) > $CRN_legth) {
      // address overflow by removing numbers that spill over
      $crn_input_id = substr($crn_input_id, ((strlen($crn_input_id)) - $CRN_legth));
    }

    $CRN = $crn_input_id; //generateBpayRef();

    // pad zeros to reach length
    if(strlen($CRN) < $CRN_legth){
      $spacer = "";
      for($i = 0; $i < ($CRN_legth-strlen($CRN)); $i++)
        $spacer .= "0";
      $CRN = $spacer.$CRN;
    }

    //OUTPUT
    //echo $CRN;

    ///////////////////////////////////////////////////////////////////////////
    /// END Customer Reference Number(CRN) Generation 
    ///////////////////////////////////////////////////////////////////////////

    if(isset($_GET['ref'])){
      if($_GET['ref'] == "clientSummary"){
        if(gateway_crnMethod() != "Customer ID"){
          $CRN = "BPAY CRN Error! See setup!";
        }
      }
    }

    die(generateImage($biller_code, $CRN));
}

///////////////////////////////////////////////////////////////////////////
/// END BPay Generator
///////////////////////////////////////////////////////////////////////////

if(isset($_GET['crn_check'])){
  die(generateBpayRef($_GET['crn_check']));
}else if(isset($_GET['crn_img'])){
  die(generateImage("23456", $_GET['crn_img']));
}else if(!defined("WHMCS") && basename($_SERVER["SCRIPT_FILENAME"], '.php') == "bpay"){
  die("Access Denied");
}

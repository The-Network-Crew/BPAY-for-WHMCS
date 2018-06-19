<?php
##### notes to do still #####
// RH control panel to manage clients / view details remotely for RH support staff. (JSON feed Completed)
// install page needs same settings normal page

//commbank - MOD10v5 - pad 0 before gen
//westpac - MOD10v5 - pad 0 before gen
//nab -  Mod10v1 - pad 0 before gen


/******* Shortcuts for debug **********
*
* Bypass initialized with out need to go through install again. (need active WHMCS seesion)
* http://../admin/addonmodules.php?module=bpay_rh&initialise_record_bypass=1
* 
* */
$conn;
if(!defined($conn))
    connect_DB();
else if(!mysqli_ping($conn))
    connect_DB();

function connect_DB(){
  GLOBAL $conn;
  // load DB connection for global access
  if (file_exists('configuration.php')) {require("configuration.php");}else if (file_exists(ROOTDIR.'/configuration.php')) {require(ROOTDIR."/configuration.php");}else{echo_die('Error - no database found');}

  // Create connection
  $conn = new mysqli($db_host, $db_username, $db_password, $db_name);

  // Check connection
  if ($conn->connect_error) {
    echo_die("Connection failed: " . $conn->connect_error);
  } 
}

function con_sanitize($data)
  {
    GLOBAL $conn;
    $search = array(
    '@<script[^>]*?>.*?</script>@si',   // Strip out javascript
    '@<[\/\!]*?[^<>]*?>@si',            // Strip out HTML tags
    '@<style[^>]*?>.*?</style>@siU',    // Strip style tags properly
    '@<![\s\S]*?--[ \t\n\r]*>@'         // Strip multi-line comments
  );
 
    $output = preg_replace($search, '', $data);

    $output = $conn->real_escape_string($output);
    return $output;
}

function bpay_rh_config() {
    $configarray = array(
        "name" => "BPAY Manager",
        "description" => "Manager your BPAY gateway settings, appearance on WHMCS and search for invoices that relate to BPAY references received from your bank.".is_bpay_out_dated(),
        "version" => bpay_version(),
        "author" => "<a href='https://www.linkedin.com/in/clinton-nesbitt/'>Clinton Nesbitt</a>",
        "fields" => array(
            )
        );
return $configarray;
}

function bpay_rh_activate() {
    GLOBAL $conn;

    # Create Custom DB Table
    $sql = "CREATE TABLE `mod_bpay_record` (`id` INT NOT NULL AUTO_INCREMENT, `crn` VARCHAR(20) NOT NULL, `clientID` INT NULL,`invoiceID` INT NULL,`crn_type` VARCHAR(50) NOT NULL, `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP, `updated_at` TIMESTAMP, PRIMARY KEY (`id`))";
    $result = $conn->query($sql);

    // need to run secondary table create another way as whmcs activate will only run once query
    if($result){
        GLOBAL $conn;

        $sql = "CREATE TABLE `mod_bpay_display` (
        `id` INT NOT NULL AUTO_INCREMENT,
        `option` VARCHAR(50) NOT NULL DEFAULT '0',
        `value` TEXT NOT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`)
        )";
        $result = $conn->query($sql);

        if($result){
            $sql = "INSERT INTO `mod_bpay_display` (`option`, `value`) VALUES
            ('global_search', '1'),
            ('PDF_display', '".'{"pdf_display": {"enabled": "1","Xaxis": "12","Yaxis": "5","size": "50"}}'."'),
            ('Installed', '0'),
            ('adminInvoicePage', '1'),
            ('adminSummaryPage', '1'),
            ('BillerCode', ''),
            ('CRNLength', '8'),
            ('crnMethod', 'Customer ID'),
            ('mod10type', 'MOD10v5'),
            ('num_padding', 'before'),
            ('Merchant_settings', 'manual'),
            ('prefix', '');";
            $result = $conn->query($sql);
        }

        $sql = "ALTER TABLE `mod_bpay_record` ADD UNIQUE INDEX `crn` (`crn`), ADD UNIQUE INDEX `invoiceID` (`invoiceID`);";
        $result = $conn->query($sql);
    }

    # Return Result
    if($result){
        return array('status'=>'success','description'=>'Activation of this module was successful. Next we need to open BPAY Manager and go through installation setup.');
    }else{
        return array('status'=>'error','description'=>'There was an issue with creating DB table. Please ensure you have permission to create tables on your DB.');
    }

    # left over example code
    // return array('status'=>'info','description'=>'You can use the info status return to display
    //  a message to the user');
}

function bpay_rh_deactivate() {
    GLOBAL $conn;

    # Create Custom DB Table
    $sql = "DROP TABLE `mod_bpay_record`;";
    $result = $conn->query($sql);

    $sql = "DROP TABLE `mod_bpay_display`;";
    $result = $conn->query($sql);

    # Return Result
    if($result){
        return array('status'=>'success','description'=>'Deactivate of this module was successful.');
    }else{
        return array('status'=>'error','description'=>'There was an issue with deleting the DB table. Please ensure you have permission to delete tables on your DB.');
    }
}

// https://developers.whmcs.com/addon-modules/upgrades/
function bpay_rh_upgrade($vars) {
    GLOBAL $conn;
    $version = $vars['version'];

    # Run SQL Updates for V2.1.3 to V2.1.4
    if ($version < '2.1.4') {
       db_access("sqlQuery", "ALTER TABLE `mod_bpay_display`    ALTER `value` DROP DEFAULT");
       db_access("sqlQuery", "ALTER TABLE `mod_bpay_display`    CHANGE COLUMN `value` `value` TEXT NOT NULL AFTER `option`;");
    }

    # Run SQL Updates for V2.1.3 to V2.1.4
    if ($version < '2.1.5') {
        // update the invoicepdf.tpl script to support image on multiple pages.
        insertInvoiceFunc(true);
    }

    # Run SQL Updates for V2.1.3 to V2.1.4
    if ($version < '2.1.5') {
        // update the invoicepdf.tpl script to support image on multiple pages.
        $data = db_access("settings");
        if($data['mod10type'] == "MOD10v1"){
            wipe_image_files();
            initialise_record_table();
        }
    }
    
    if ($version < '2.1.7') {// add preconfig - param
        db_access("sqlQuery", "INSERT INTO `mod_bpay_display` (`option`, `value`) VALUES ('Merchant_settings', 'manual');");
        db_access("sqlQuery", "INSERT INTO `mod_bpay_display` (`option`, `value`) VALUES ('prefix', '');");
    }

    if ($version < '2.1.8') {// add preconfig - param
        db_access("sqlQuery", "INSERT INTO `mod_bpay_display` (`option`, `value`) VALUES ('imgType', 'vertical');");
    }

}

function bpay_rh_output($vars) {
    
    // $modulelink = $vars['modulelink'];
    // $version = $vars['version'];
    // $option1 = $vars['option1'];
    // $option2 = $vars['option2'];
    // $option3 = $vars['option3'];
    // $option4 = $vars['option4'];
    // $option5 = $vars['option5'];

    $column_count = 0;
    $searchKey = new stdClass();
    $searchKey->limit = "10";
    $searchKey->offset = "0";

    $paginationLink = $HTML_Output = "";

    $install_state = db_access("get_install_state");

    /////////////////
    /// START Install / every day use functions
    /////////////////

    if(isset($_GET['create_cust_dir'])){
        if (!file_exists(ROOTDIR.'/modules/gateways/bpay/customers/')) {mkdir(ROOTDIR.'/modules/gateways/bpay/customers/', 0755, true); fopen(ROOTDIR."/modules/gateways/bpay/customers/index.php", "w");}
        if (file_exists(ROOTDIR.'/modules/gateways/bpay/customers/')) {
            $HTML_Output .= '<div class="successbox"><strong><span class="title">Folder Created Successfully!</span></strong><br>You have created the folder "customers" successfully.</div>';
        }else{
            $HTML_Output .= '<div class="errorbox"><strong><span class="title">Error making Folder!</span></strong><br>The folder was not created. Please try and create the folder your self.<br>Note: The folder needs to be created in the following directory: "/modules/gateways/bpay/", name the folder "customers".</div>';
        }
    }
    if(isset($_GET['create_inv_dir'])){
        if (!file_exists(ROOTDIR.'/modules/gateways/bpay/invoices/')) {mkdir(ROOTDIR.'/modules/gateways/bpay/invoices/', 0755, true); fopen(ROOTDIR."/modules/gateways/bpay/invoices/index.php", "w");}
        if (file_exists(ROOTDIR.'/modules/gateways/bpay/invoices/')) {
            $HTML_Output .= '<div class="successbox"><strong><span class="title">Folder Created Successfully!</span></strong><br>You have created the folder "invoices" successfully.</div>';
        }else{
            $HTML_Output .= '<div class="errorbox"><strong><span class="title">Error making Folder!</span></strong><br>The folder was not created. Please try and create the folder your self.<br>Note: The folder needs to be created in the following directory: "/modules/gateways/bpay/", name the folder "invoices".</div>';
        }
    }

    if(isset($_GET['bpay_perm_fix'])){
        if (file_exists(ROOTDIR.'/modules/gateways/bpay.php')){
            if (chmod(ROOTDIR."/modules/gateways/bpay.php", 0644))
                $HTML_Output .= '<div class="successbox"><strong><span class="title">File Permissions Fixed Successfully!</span></strong></div>';
            else
                $HTML_Output .= '<div class="errorbox"><strong><span class="title">Error changing File Permissions!</span></strong><br>The file Permissions were not changed. Please manually change the file permissions for the file "bpay.php". This is located in the following directory: "/modules/gateways/", Please change this to "644".</div>';
        }else{
            $HTML_Output .= '<div class="errorbox"><strong><span class="title">Error File Missing!</span></strong><br>The file "bpay.php" is missing. Please manually upload the file "bpay.php". This is to be uploaded to the directory: "/modules/gateways/", Please change files permissions to "644".</div>';
        }
    }

    if(isset($_GET['jpg_perm_fix'])){
        if (file_exists(ROOTDIR.'/modules/gateways/bpay/BPay.jpg')){
            if (chmod(ROOTDIR."/modules/gateways/bpay/BPay.jpg", 0644))
                $HTML_Output .= '<div class="successbox"><strong><span class="title">File Permissions Fixed Successfully!</span></strong></div>';
            else
                $HTML_Output .= '<div class="errorbox"><strong><span class="title">Error changing File Permissions!</span></strong><br>The file Permissions were not changed. Please manually change the file permissions for the file "BPay.jpg". This is located in the following directory: "/modules/gateways/bpay/", Please change files permissions to "644".</div>';
        }else{
            $HTML_Output .= '<div class="errorbox"><strong><span class="title">Error File Missing!</span></strong><br>The file "BPay.jpg" is missing. Please manually upload the file "BPay.jpg". This is to be uploaded to the directory: "/modules/gateways/bpay/", Please change the files permissions to "644".</div>';
        }
    }

    if(isset($_GET['ttf_perm_fix'])){
        if (file_exists(ROOTDIR.'/modules/gateways/bpay/arial.ttf')){
            if (chmod(ROOTDIR."/modules/gateways/bpay/arial.ttf", 0644))
                $HTML_Output .= '<div class="successbox"><strong><span class="title">File Permissions Fixed Successfully!</span></strong></div>';
            else
                $HTML_Output .= '<div class="errorbox"><strong><span class="title">Error changing File Permissions!</span></strong><br>The file Permissions were not changed. Please manually change the file permissions for the file "arial.ttf". This is located in the following directory: "/modules/gateways/bpay/", Please change files permissions to "644".</div>';
        }else{
            $HTML_Output .= '<div class="errorbox"><strong><span class="title">Error File Missing!</span></strong><br>The file "arial.ttf" is missing. Please manually upload the file "arial.ttf". This is to be uploaded to the directory: "/modules/gateways/bpay/", Please change files permissions to "644".</div>';
        }
    }

    if(isset($_GET['bpay_hook_perm_fix'])){
        if (file_exists(ROOTDIR.'/modules/addons/bpay_rh/bpay_rh_hooks.php')){
            if (chmod(ROOTDIR."/modules/addons/bpay_rh/bpay_rh_hooks.php", 0644))
                $HTML_Output .= '<div class="successbox"><strong><span class="title">File Permissions Fixed Successfully!</span></strong></div>';
            else
                $HTML_Output .= '<div class="errorbox"><strong><span class="title">Error changing File Permissions!</span></strong><br>The file Permissions were not changed. Please manually change the file permissions for the file "bpay_rh_hooks.php". This is located in the following directory: "/modules/addons/bpay_rh/", Please change files permissions to "644".</div>';
        }else{
            $HTML_Output .= '<div class="errorbox"><strong><span class="title">Error File Missing!</span></strong><br>The file "bpay_rh_hooks.php" is missing. Please manually upload the file "bpay_rh_hooks.php". This is to be uploaded to the directory: "/modules/addons/bpay_rh/", Please change files permissions to "644".</div>';
        }
    }

    if(isset($_GET['bpay_inc_hook_perm_fix'])){
        if (file_exists(ROOTDIR.'/includes/hooks/bpay_rh.php')){
            if (chmod(ROOTDIR."/includes/hooks/bpay_rh.php", 0644))
                $HTML_Output .= '<div class="successbox"><strong><span class="title">File Permissions Fixed Successfully!</span></strong></div>';
            else
                $HTML_Output .= '<div class="errorbox"><strong><span class="title">Error changing File Permissions!</span></strong><br>The file Permissions were not changed. Please manually change the file permissions for the file "bpay_rh.php". This is located in the following directory: "/includes/hooks/", Please change files permissions to "644".</div>';
        }else{
            $HTML_Output .= '<div class="errorbox"><strong><span class="title">Error File Missing!</span></strong><br>The file "bpay_rh.php" is missing. Please manually upload the file "bpay_rh.php". This is to be uploaded to the directory: "/includes/hooks/", Please change files permissions to "644".</div>';
        }
    }

    if(isset($_POST['settings'])){
        // validate inputs
        $error = false;

        if(isset($_POST['BillerCode']) && isset($_POST['CRNLength']) && isset($_POST['crnMethod']) && isset($_POST['mod10type']) && isset($_POST['mod10type']) && isset($_POST['key']) && isset($_POST['Merchant_settings'])){
            // all data submitted not time to validate each field posted 

            if(!isset($_POST['Merchant_settings']) && $_POST['Merchant_settings'] != "manual" && $_POST['Merchant_settings'] != "ezidebit"){
                $Merchant_settings_Error = 'class="alert-danger"';
                $error = true;
            }else if($_POST['Merchant_settings'] == "ezidebit"){
                $_POST['crnMethod'] = "Customer ID";
                $_POST['mod10type'] = "MOD10v1";
                $_POST['num_padding'] = "before";
            }else if($_POST['Merchant_settings'] == "cba"){
                $_POST['mod10type'] = "MOD10v5";
                $_POST['num_padding'] = "before";
            }else if($_POST['Merchant_settings'] == "nab"){
                $_POST['mod10type'] = "MOD10v1";
                $_POST['num_padding'] = "before";
            }else if($_POST['Merchant_settings'] == "westpac"){
                $_POST['mod10type'] = "MOD10v5";
                $_POST['num_padding'] = "before";
            }else{
                // if no pre-config merchant set, validate user input
                if($_POST['crnMethod'] != "Customer ID" && $_POST['crnMethod'] != "Invoice Number"){
                    $error = true;
                    $crnGenBy = 'alert alert-danger';
                }
                if($_POST['mod10type'] != "MOD10v1" && $_POST['mod10type'] != "MOD10v5"){
                    $error = true;
                    $crnMethodError = 'alert-danger';
                }
                if($_POST['num_padding'] != "before" && $_POST['num_padding'] != "after"){
                    $error = true;
                    $num_paddingError = ' alert-danger';
                }
            }

            if(!is_numeric($_POST['BillerCode'])){
               $billerCodeError = 'class="alert-danger"';
               $error = true;
            }

            if(!is_numeric($_POST['CRNLength']) && $_POST['CRNLength'] > 19 || $_POST['CRNLength'] < 3){
                $error = true;
                $crnLengthError = 'alert-danger';
            }

            
            if(!isset($_POST['key'])){
                $licenceKeyError = 'class="alert-danger"';
                $error = true;
            }

            
            
                
        }else{
            $error = true;
        }

                // update DB
        if($error == false){
            $error = db_access("settings_update", $_POST);
        }

                // return status message 

        if($error == false){
            $HTML_Output .= '<div class="successbox"><strong><span class="title">Changes Saved Successfully!</span></strong><br>Your changes have been saved.</div>';
        }else{
            $HTML_Output .= '<div class="errorbox"><strong><span class="title">Error making Changes!</span></strong><br>Your changes were not saved.</div>';
        }
    }else if(isset($_POST['appearance'])){
        $appearanceUpdate = new stdClass();

        $error = "";
        if(isset($_POST['global_search'])){
            $appearanceUpdate->global_search = 1;
        }

        if(isset($_POST['adminInvoicePage'])){
            $appearanceUpdate->adminInvoicePage = 1;
        }

        if(isset($_POST['adminSummaryPage'])){
            $appearanceUpdate->adminSummaryPage = 1;
        }

        if(isset($_POST['invoicePDF'])){
            $appearanceUpdate->invoicePDF = 1;
            if(isset($_POST['PDFx-axis'])){
                if(is_numeric($_POST['PDFx-axis'])){
                    $appearanceUpdate->Xaxis = $_POST['PDFx-axis'];
                }else{
                    $error .= "<li>PDF Image X-axis - is not a number</li>";
                }
            }else{
                $error .= "<li>PDF Image X-axis - is not set</li>";
            }

            if(isset($_POST['PDFy-axis'])){
                if(is_numeric($_POST['PDFy-axis'])){
                    $appearanceUpdate->Yaxis = $_POST['PDFy-axis'];
                }else{
                    $error .= "<li>PDF Image Y-axis - is not a number</li>";
                }
            }else{
                $error .= "<li>PDF Image Y-axis - is not set</li>";
            }

            if(isset($_POST['PDFsize'])){
                if(is_numeric($_POST['PDFsize'])){
                    $appearanceUpdate->size = $_POST['PDFsize'];
                }else{
                    $error .= "<li>PDF Image Size - is not a number</li>";
                }
            }else{
                $error .= "<li>PDF Image Size - is not set</li>";
            }   
        }

        if(isset($_POST['imgType'])){
            $appearanceUpdate->imgType = $_POST['imgType'];
        }else{
            $appearanceUpdate->imgType = "horizontal";
        }

        if($error){
            $HTML_Output .= '<div class="errorbox"><strong><span class="title">Error saving appearance settings!</span></strong><br>The following errors occured:<ul>'.$error.'</ul></div>';
        }else{

            if(db_access("updateAppearanceData", $appearanceUpdate))
                $HTML_Output .= '<div class="successbox"><strong><span class="title">Appearance Settings Updated Successfully!</span></strong><br>Your appearance settings have been updated successfully.</div>';
            else
                $HTML_Output .= '<div class="errorbox"><strong><span class="title">Error saving appearance settings!</span></strong><br>Your settings were not saved.<br>Please try again.</div>';
        }
    }else if(isset($_POST['get_biller_code'])){
        if(isset($_POST['billerCode']) && is_numeric($_POST['billerCode'])){
            $postfields["billerCode"] = $_POST['billerCode'];
            die(getBillerName($postfields));
    }
        die("Invalid");
        
    }

    /////////////////
    /// END Install / every day use functions
    /////////////////
    
    $settingsData = db_access("settings");

    $crnMethod = $settingsData['crnMethod'];
    $CRNLength = $settingsData['CRNLength'];
    $install_state = $settingsData['Installed'];

    if(isset($_GET['initialise_record']) && $install_state != "1" || isset($_GET['initialise_record_bypass'])){
        if(db_access("wipe_crn_db")){
            if(wipe_image_files()){
                if(initialise_record_table()){
                    db_access("get_install_state", "1"); // change state to installed
                    if($install_state === "0"){
                        $install_state = "1"; 
                        $HTML_Output .= '<div class="successbox"><strong><span class="title">BPAY is now setup!</span></strong><br>Your BPAY database is now setup successfully.<br>Installation is now Complete!<br>Enjoy using BPAY Manager</div>';
                    }else{
                        $HTML_Output .= '<div class="successbox"><strong><span class="title">BPAY Ref Generation was Successfully!</span></strong><br>Your BPAY Reference database is now up to date.</div>';
                    }
                    
                }else{
                    $HTML_Output .= '<div class="errorbox"><strong><span class="title">Error Initializing CRN records in database!</span></strong><br>We were unable to initialize all existing clients from WHMCS with a CRN ID as your CRN Length is to short. To make it, possible to provide all clients with a BPAY CRN.<br>We suggest you contact your BPAY bank manager and arrange to have this CRN length extended to accommodated for all your existing and future WHMCS customers.</div>';
                }
            }
        }
    }
    
    

    if($install_state === "0"){
        // first time opening page
        installPhase($HTML_Output);
    }else{
        // $LANG = $vars['_lang'];
        if($_REQUEST){
            if(isset($_GET['searchGlobal'])){
                // check that token is valid
                $tkval = array_key_exists("tkval", $_SESSION) ? $_SESSION['tkval'] : null;
                $CRFS = sha1($tkval . session_id() . ":whmcscrsf");

                if($CRFS != $_POST['token'])
                    echo_die(); //not valid token

                if($crnMethod == "Customer ID"){
                    $search_type = "search_client";
                }else if($crnMethod == "Invoice Number"){
                    $search_type = "search_inv";
                }
                $searchKey->limit = "100";
                $searchKey->offset = "0";
                $searchKey->crn = $_POST['value'];
                $globalSearch = db_access($search_type,$searchKey);

                $value = con_sanitize($_POST['value']);

                // $sql = "SELECT crm_resources.id, crm_resources.name, crm_resources.email, crm_resources_types.name as 'status', crm_resources_types.color FROM crm_resources LEFT JOIN crm_resources_types ON crm_resources_types.id = crm_resources.type_id WHERE crm_resources.id = '$value' OR crm_resources.name LIKE '%$value%' OR email LIKE '%$value%' OR email LIKE '%$value%'";

                // $result = $conn->query($sql);
                if(count($globalSearch) > 0) {
                    $output = '<div class="searchresultheader rhBpaySearch">BPAY clients</div>';
                    foreach ($globalSearch as $value) {
                        # code...
                        $link = "";
                        if($search_type == "search_client"){
                             $link = "clientssummary.php?userid=".$value['clientID'];
                        }elseif ($search_type == "search_inv") {
                            $link = "invoices.php?action=edit&id=".$value['invoiceID'];
                        }
                    
                        $output .= '<div class="searchresult rhBpaySearchClient">
                        <a href="'.$link.'">
                        <strong>'.$value['firstname']." ".$value['lastname'].'</strong>
                        #'.$value['clientID'];

                        // $output .= '<span class="label active">'.$value['status'].'</span><br />
                        // <span class="desc">'.$value['email'].'</span></a>
                        // </div>';
                        if($search_type == "search_client"){
                            $status = "";

                            if($value['cstatus'] == "Closed"){
                                $status = "terminated";
                            }elseif($value['cstatus'] == "Active"){
                                $status = "active";
                            }elseif($value['cstatus'] == "Inactive"){
                                $status = "inactive";
                            }else{
                                $status = "active";
                            }

                            $output .= '<span class="label '.$status.'">'.$value['cstaus'].'</span><br />';
                            $output .= '<span class="desc">BPAY ref: '.$value['crn'].'</span></a>';
                        }elseif ($search_type == "search_inv") {
                            $output .= " - BPAY ref: ".$value['crn'];
                            $status = "";

                            if($value['status'] == "Cancelled"){
                                $status = "inactive";
                            }elseif($value['status'] == "Paid"){
                                $status = "active";
                            }elseif($value['status'] == "Unpaid"){
                                $status = "terminated";
                            }elseif($value['status'] == "Refunded"){
                                $status = "pending";
                            }else{
                                $status = "active";
                            }

                            $output .= '<span class="label '.$status.'">'.$value['status'].'</span><br />';
                            $output .= '<span class="desc">Total: $'.$value['total'].' - Invoice ID: #'.$value['invoiceID'].'</span></a>';
                        }
                        $output .= '</div>';
                    }
                }
                echo json_encode($output);
                echo_die();
            }else if(isset($_POST['insertInvoiceFunc'])){
                insertInvoiceFunc();
                echo_die();
            }//elseif(isset($_REQUEST['search'])){

        $HTML_search .= "<table width='100%'class='datatable' border='0' cellspaceomg='1' cellpadding='3'>".
        "<tbody><tr><th>Client ID</th><th>Customer Name</th>";
        if($crnMethod == "Customer ID"){
            $HTML_search .= "<th>CRN</th><th>Total Invoices Due / Overdue</th><th>View Invoices</th>"; // some button - clientssummary.php?userid=1112
            $column_count = 5;
            $search_type = "search_client";
            $searchKey->crn = $_REQUEST['crn'];
            $searchKey->limit = (isset($_REQUEST['limit']))?$_REQUEST['limit']:"10";
            $searchKey->offset = (isset($_REQUEST['offset']))?$_REQUEST['offset']:"0";
            $paginationLink = "addonmodules.php?module=bpay_rh&search=1&crn=".$searchKey->crn;
        }
        else if($crnMethod == "Invoice Number"){
            $HTML_search .= "<th>CRN</th><th>Invoice Number</th><th>Total</th><th>Status</th><th>View Invoices</th>"; // some button - clientssummary.php?userid=1112
            $column_count = 7;
            $search_type = "search_inv";
            $searchKey->crn = $_REQUEST['crn'];
            $searchKey->searchBy = $_REQUEST['searchBy'];
            $searchKey->limit = (isset($_REQUEST['limit']))?$_REQUEST['limit']:"10";
            $searchKey->offset = (isset($_REQUEST['offset']))?$_REQUEST['offset']:"0";
            $paginationLink = "addonmodules.php?module=bpay_rh&search=1&crn=".$searchKey->crn."&searchBy=".$searchKey->searchBy;
        }

        // $paginationLink &limit=".$searchKey->limit."&offset=".$searchKey->offset.";

        $range10 = $range25 = $range50 = $range100 = "";

        $range10link = $paginationLink."&limit=10&offset=".$searchKey->offset;
        $range25link = $paginationLink."&limit=25&offset=".$searchKey->offset;
        $range50link = $paginationLink."&limit=50&offset=".$searchKey->offset;
        $range100link = $paginationLink."&limit=100&offset=".$searchKey->offset;

        if($searchKey->limit == 10){
            $range10 = ' active';
            $range10link = "#";
        }else if($searchKey->limit == 25){
            $range25 = ' active';
            $range25link = "#";
        }else if($searchKey->limit == 50){
            $range50 = ' active';
            $range50link = "#";
        }else if($searchKey->limit == 100){
            $range100 = ' active';
            $range100link = "#";
        }else{ //in client cheats or validate failes fall back to 25
            $range10 = ' active';
            $searchKey->limit = "10";
            $range10link = "#";
        }


        $HTML_search .= "</tr>";

        // Run search function and return results
        $SearchData = db_access($search_type,$searchKey);
        $dataCount = db_access($search_type."_count",$searchKey);

        $searchStats = calculate_page_results($searchKey->offset,$searchKey->limit,$dataCount);

        // loop through data and display on page
        if(is_array($SearchData)){
            foreach ($SearchData as $value) {
                if($search_type == "search_client"){
                    $total_due = db_access("client_total_invoices_due", $value['clientID']);

                    $HTML_search .= "<tr><td><a href='clientssummary.php?userid=".$value['clientID']."'>".$value['clientID']."</a></td><td><a href='clientssummary.php?userid=".$value['clientID']."'>".$value['firstname']." ".$value['lastname']."</a></td><td>".$value['crn']."</td><td align='center'><a href='clientsinvoices.php?userid=".$value['clientID']."&status=Unpaid'>".$total_due."</a></td><td><a href='clientsinvoices.php?userid=".$value['clientID']."&status=Unpaid'>VIEW INVOICES</a></td></tr>";
                }elseif ($search_type == "search_inv") {
                    $HTML_search .= "<tr><td><a href='clientssummary.php?userid=".$value['clientID']."'>".$value['clientID']."</a></td><td><a href='clientssummary.php?userid=".$value['clientID']."'>".$value['firstname']." ".$value['lastname']."</a></td><td>".$value['crn']."</td><td><a href='invoices.php?action=edit&id=".$value['invoiceID']."'>".$value['invoiceID']."</a></td><td><a href='invoices.php?action=edit&id=".$value['invoiceID']."'>".$value['total']."</a></td><td><a href='invoices.php?action=edit&id=".$value['invoiceID']."'>".$value['status']."</a></td><td><a href='invoices.php?action=edit&id=".$value['invoiceID']."'>VIEW INVOICES</a></td></tr>";
                }
            }
        }else{
            // no results display message to indicate that...
            $HTML_search .= "<tr><td colspan='$column_count'>No results found.</td></tr>";
        }

        $HTML_search .= "</table>";

        // page display per page
        $HTML_search .= "<span class='ng-binding'>Display 

        <div class='btn-group btn-group-xs btn-group-solid' role='group' >
        <a href='$range10link' class='btn$range10'>10</a>
        <a href='$range25link' class='btn$range25'>25</a>
        <a href='$range50link' class='btn$range50'>50</a>
        <a href='$range100link' class='btn$range100'>100</a>
        </div>

        per page. Showing ".($searchKey->offset+1)." to ".($searchKey->limit*$searchStats->currentPage)." of $dataCount entries

        </span>";

        // display pagination
        $HTML_search .= "<div class='pull-right ng-isolate-scope'>
        <nav class='ng-scope'>
        <ul class='pagination'>";
        if($searchStats->pageTotal == 1){
            $HTML_search .= "<li class='ng-scope active'><a href='".$paginationLink."&limit=".$searchKey->limit."&offset=".($i*$searchKey->limit)."'>1</a></li>";
        }else{
            // add next in pagination
            if($searchStats->currentPage != 1)
                $HTML_search .= "<li class='ng-scope'><a href='".$paginationLink."&limit=".$searchKey->limit."&offset=".(($searchStats->currentPage-2)*$searchKey->limit)."'><span aria-hidden='true'>&laquo;</span></a></li>";

            for ($i=1; $i <= ($searchStats->pageTotal); $i++) {

                if($i == $searchStats->currentPage){
                    $HTML_search .= "<li class='ng-scope active'><a href='".$paginationLink."&limit=".$searchKey->limit."&offset=".($i*$searchKey->limit)."'>$i</a></li>";
                }else{
                    if($searchStats->pageTotal > 5){
                        if($i >= ($searchStats->currentPage-5) && $i < $searchStats->currentPage || $i <= ($searchStats->currentPage+5) && $i > $searchStats->currentPage){
                            $HTML_search .= "<li class='ng-scope'><a href='".$paginationLink."&limit=".$searchKey->limit."&offset=".(($i-1)*$searchKey->limit)."'>$i</a></li>";
                        }
                    }else{
                        $HTML_search .= "<li class='ng-scope'><a href='".$paginationLink."&limit=".$searchKey->limit."&offset=".(($i-1)*$searchKey->limit)."'>$i</a></li>";
                    }                        
                }
            }

            // add next in pagination
            if($searchStats->currentPage != $searchStats->pageTotal)
                $HTML_search .= "<li class='ng-scope'><a href='".$paginationLink."&limit=".$searchKey->limit."&offset=".(($searchStats->currentPage)*$searchKey->limit)."'><span aria-hidden='true'>&raquo;</span></a></li>";

        }

        $HTML_search .= "</ul>
        </nav>
        </div>";
            //} // END search results
        }
        if(isset($_REQUEST['crn'])){
            $crn_input = $_REQUEST['crn'];
        }

        // ///////////////////////////////////////////////////////////////////////////////////////
        // START settings Lookup /////
        

        // $BillerCode = $CRNLength = $crnMethod = $mod10type = $key = $crnform = $crnMethodCust = $crnMethodInv = $MOD10v1 = $MOD10v5 = "";
        if(is_array($settingsData)){

            if(isset($_POST['BillerCode']))
                $BillerCode = $_POST['BillerCode'];
            else
                $BillerCode = $settingsData['BillerCode'];

            $BillerName = getBillerName($BillerCode);

            if(isset($_POST['CRNLength'])){
                if(is_numeric($_POST['CRNLength']) && $_POST['CRNLength'] >= 3 || $_POST['CRNLength'] <= 19){
                    $CRNLength = $_POST['CRNLength'];
                }
            }else{
                $CRNLength = $settingsData['CRNLength'];
            }

            if(isset($_POST['crnMethod']))
                $crnMethod = $_POST['crnMethod'];
            else
                $crnMethod = $settingsData['crnMethod'];

            if(isset($_POST['mod10type']))
                $mod10type = $_POST['mod10type'];
            else
                $mod10type = $settingsData['mod10type'];

            if(isset($_POST['key']))
                $key = $_POST['key'];
            else
                $key = $settingsData['key'];

            if(isset($_POST['num_padding']))
                $num_padding = $_POST['num_padding'];
            else
                $num_padding = $settingsData['num_padding'];

            if(isset($_POST['Merchant_settings']))
                $Merchant_settings = $_POST['Merchant_settings'];
            else
                $Merchant_settings = $settingsData['Merchant_settings'];

            if(isset($_POST['prefix']))
                $prefix = $_POST['prefix'];
            else
                $prefix = $settingsData['prefix'];
        }

        if($crnMethod == "Customer ID")
            $crnMethodCust = 'checked="checked"';
        elseif($crnMethod == "Invoice Number")
            $crnMethodInv = 'checked="checked"';

        if($mod10type == "MOD10v1")
            $MOD10v1 = 'selected="selected"';
        elseif($mod10type == "MOD10v5")
            $MOD10v5 = 'selected="selected"';

        for ($i=3; $i < 20; $i++) { 
            if($CRNLength == $i)
                $crnform .= "<option selected='selected' value='$i'>$i</option>";
            else
                $crnform .= "<option value='$i'>$i</option>";
        }

        if($Merchant_settings == "ezidebit"){
            $Merchant_settings_form = "<option value='manual'>Manual</option><option value='ezidebit' selected='selected'>EziDebit.com.au</option><option value='cba'>Commonwealth Bank of Australia</option><option value='nab'>National Australia Bank</option><option value='westpac'>Westpac</option>";
            $Merchant_settings_hide = $CRN_Generated_via = "style='display: none;'";
            $show_prefix = "";
        }
        elseif($Merchant_settings == "cba"){
            $Merchant_settings_form = "<option value='manual'>Manual</option><option value='ezidebit'>EziDebit.com.au</option><option value='cba' selected='selected'>Commonwealth Bank of Australia</option><option value='nab'>National Australia Bank</option><option value='westpac'>Westpac</option>";
            $Merchant_settings_hide = "style='display: none;'";
            $show_prefix = "style='display: none;'";
            $CRN_Generated_via = "";
        }
        elseif($Merchant_settings == "westpac"){
            $Merchant_settings_form = "<option value='manual'>Manual</option><option value='ezidebit'>EziDebit.com.au</option><option value='cba'>Commonwealth Bank of Australia</option><option value='nab'>National Australia Bank</option><option value='westpac' selected='selected'>Westpac</option>";
            $Merchant_settings_hide = "style='display: none;'";
            $show_prefix = "style='display: none;'";
            $CRN_Generated_via = "";
        }
        elseif($Merchant_settings == "nab"){
            $Merchant_settings_form = "<option value='manual'>Manual</option><option value='ezidebit'>EziDebit.com.au</option><option value='cba'>Commonwealth Bank of Australia</option><option value='nab' selected='selected'>National Australia Bank</option><option value='westpac'>Westpac</option>";
            $Merchant_settings_hide = "style='display: none;'";
            $show_prefix = "style='display: none;'";
            $CRN_Generated_via = "";
        }
        else{
            $Merchant_settings_form = "<option value='manual' selected='selected'>Manual</option><option value='ezidebit'>EziDebit.com.au</option><option value='cba'>Commonwealth Bank of Australia</option><option value='nab'>National Australia Bank</option><option value='westpac'>Westpac</option>";
            $show_prefix = "style='display: none;'";
            $CRN_Generated_via = "";
        }

        if($num_padding == "before")
            $num_padding_before = 'selected="selected"';
        elseif($num_padding == "after")
            $num_padding_after = 'selected="selected"';
        // END settings Lookup //////

        if($crnMethod == "Invoice Number"){
            if($_REQUEST['searchBy'] == "all"){
                $searchByListItems = "<option selected='selected' value='all'>All invoices</option><option value='unpaid'>Unpaid Invoices</option><option value='paid'>Paid invoices</option>";
            }else if($_REQUEST['searchBy'] == "unpaid"){
                $searchByListItems = "<option value='all'>All invoices</option><option selected='selected' value='unpaid'>Unpaid Invoices</option><option value='paid'>Paid invoices</option>";
            }else if($_REQUEST['searchBy'] == "paid"){
                $searchByListItems = "<option value='all'>All invoices</option><option value='unpaid'>Unpaid Invoices</option><option selected='selected' value='paid'>Paid invoices</option>";
            }else{
                $searchByListItems = "<option selected='selected' value='all'>All invoices</option><option value='unpaid'>Unpaid Invoices</option><option value='paid'>Paid invoices</option>";
            }
            $searchByList = "<tr><td width='20%' class='fieldlabel'><strong>Search for</strong></td><td class='fieldarea'><select name='searchBy' class='form-control select-inline'>'.$searchByListItems.'</select></td></tr>";
        }

        // FILE Permission STATS
        $bpay_file_status = (file_exists(ROOTDIR.'/modules/gateways/bpay.php')) ? "<font color='green'>Found</font>" : "<font color='red'>Missing</font>";
        $bpay_file_permission = substr(sprintf('%o', fileperms(ROOTDIR.'/modules/gateways/bpay.php')), -4);

        $arial_file_status = (file_exists(ROOTDIR.'/modules/gateways/bpay/arial.ttf')) ? "<font color='green'>Found</font>" : "<font color='red'>Missing</font>";
        $arial_file_permission = substr(sprintf('%o', fileperms(ROOTDIR.'/modules/gateways/bpay/arial.ttf')), -4);

        $bpay_image_status = (file_exists(ROOTDIR.'/modules/gateways/bpay/BPay.jpg')) ? "<font color='green'>Found</font>" : "<font color='red'>Missing</font>";
        $bpay_image_permission = substr(sprintf('%o', fileperms(ROOTDIR.'/modules/gateways/bpay/BPay.jpg')), -4);

        $customers_dir_status = (file_exists(ROOTDIR.'/modules/gateways/bpay/customers/')) ? "<font color='green'>Found</font>" : "<font color='red'>Missing</font>";
        $customers_dir_permission = substr(sprintf('%o', fileperms(ROOTDIR.'/modules/gateways/bpay/customers/')), -4);

        $invoices_dir_status = (file_exists(ROOTDIR.'/modules/gateways/bpay/invoices/')) ? "<font color='green'>Found</font>" : "<font color='red'>Missing</font>";
        $invoices_dir_permission = substr(sprintf('%o', fileperms(ROOTDIR.'/modules/gateways/bpay/invoices/')), -4);


        $bpay_hooks_core_file_status = (file_exists(ROOTDIR.'/modules/addons/bpay_rh/bpay_rh_hooks.php')) ? "<font color='green'>Found</font>" : "<font color='red'>Missing</font>";
        $bpay_hooks_core_file_permission = substr(sprintf('%o', fileperms(ROOTDIR.'/modules/addons/bpay_rh/bpay_rh_hooks.php')), -4);

        $bpay_hooks_include_file_status = (file_exists(ROOTDIR.'/includes/hooks/bpay_rh.php')) ? "<font color='green'>Found</font>" : "<font color='red'>Missing</font>";
        $bpay_hooks_include_file_permission = substr(sprintf('%o', fileperms(ROOTDIR.'/includes/hooks/bpay_rh.php')), -4);

        if($bpay_file_status == "<font color='red'>Missing</font>" || $bpay_file_permission > 644){
            $bpay_file_error = 'class="alert alert-danger"';
            $bpay_file_download = "<a class='btn btn-primary' target='_self' href='addonmodules.php?module=bpay_rh&bpay_perm_fix=".rand()."#info'>Resolve</a>";
        }

        if($arial_file_status == "<font color='red'>Missing</font>" || $arial_file_permission > 644){
            $arial_file_error = 'class="alert alert-danger"';
            $arial_file_fix = "<a class='btn btn-primary' target='_self' href='addonmodules.php?module=bpay_rh&ttf_perm_fix=".rand()."#info'>Resolve</a>";
        }

        if($bpay_image_status == "<font color='red'>Missing</font>" || $bpay_image_permission > 644){
            $bpay_image_error = 'class="alert alert-danger"';
            $bpay_image_file_fix = "<a class='btn btn-primary' target='_self' href='addonmodules.php?module=bpay_rh&jpg_perm_fix=".rand()."#info'>Resolve</a>";
        }

        if($customers_dir_status == "<font color='red'>Missing</font>" || $customers_dir_permission > 755){
            if($crnMethod != "Invoice Number")
                $customers_dir_error = 'class="alert alert-danger"';
            $create_cust_dir = "<a class='btn btn-primary' href='addonmodules.php?module=bpay_rh&create_cust_dir=".rand()."#info'>Resolve</a>";
        }

        if($invoices_dir_status == "<font color='red'>Missing</font>" || $invoices_dir_permission > 755){
            if($crnMethod != "Customer ID")
                $invoices_dir_error = 'class="alert alert-danger"';
            $create_inv_dir = "<a class='btn btn-primary' href='addonmodules.php?module=bpay_rh&create_inv_dir=".rand()."#info'>Resolve</a>";
        }

        if($bpay_hooks_core_file_status == "<font color='red'>Missing</font>" || $bpay_hooks_core_file_permission > 644){
            $bpay_hooks_core_error = 'class="alert alert-danger"';
            $bpay_hooks_core_fix = "<a class='btn btn-primary' target='_self' href='addonmodules.php?module=bpay_rh&bpay_hook_perm_fix=".rand()."#info'>Resolve</a>";
        }

        if($bpay_hooks_include_file_status == "<font color='red'>Missing</font>" || $bpay_hooks_include_file_permission > 644){
            $bpay_hooks_include_error = 'class="alert alert-danger"';
            $bpay_hooks_include_fix = "<a class='btn btn-primary' target='_self' href='addonmodules.php?module=bpay_rh&bpay_inc_hook_perm_fix=".rand()."#info'>Resolve</a>";
        }

        if($bpay_file_error || $arial_file_error || $bpay_image_error || $customers_dir_error || $invoices_dir_error || $bpay_hooks_core_error || $bpay_hooks_include_error)
            $file_permission_error_icon = "<span class='glyphicon glyphicon-warning-sign'></span> ";

            // Environment STATS
        $system_manager_version = bpay_version();
        $current_version = get_bpay_lastest_version();
        if($system_manager_version < $current_version){
            $update_manager_needed = "<a style='color:red' href='https://github.com/beanonymous/whmcsBPAY/'>Download New Update!</a>";
            $environment_error_icon = "<span class='glyphicon glyphicon-warning-sign'></span> ";
            $bpay_manager_error = 'class="alert alert-danger"';
        }

        include_once("bpay_rh_hooks.php");
        $system_hooks_version = bpay_hook_version();
        $current_hooks_version = get_hooks_lastest_version();
        if($system_hooks_version < $current_hooks_version){
            $update_hooks_needed = "<a style='color:red' href='https://github.com/beanonymous/whmcsBPAY/'>Download New Update!</a>";
            $environment_error_icon = "<span class='glyphicon glyphicon-warning-sign'></span> ";
            $bpay_hooks_error = 'class="alert alert-danger"';
        }

        include_once(ROOTDIR."/modules/gateways/bpay.php");
        $system_gateway_version = gate_bpay_version();
        $current_gateway_version = gateway_check_version();
        if($system_gateway_version < $current_gateway_version){
            $update_gateway_needed = "<a style='color:red' href='https://github.com/beanonymous/whmcsBPAY/'>Download New Update!</a>";
            $environment_error_icon = "<span class='glyphicon glyphicon-warning-sign'></span> ";
            $bpay_gateway_error = 'class="alert alert-danger"';
        }

        if($file_permission_error_icon){
            $info_icon = $file_permission_error_icon;
            $permission_expand = " in";
        }elseif($environment_error_icon){
            $info_icon = $environment_error_icon;
            $environment_expand = " in";
        }else{
            $permission_expand = " in";
        }


        $invoice_preview = db_access("getFirstInvoiceID");
        if($invoice_preview !== NULL && is_numeric($invoice_preview)){
            $invoice_preview = "<div class='col-md-8'><strong><a class='btn btn-primary' href='../dl.php?type=i&id=".$invoice_preview."'>Preview</a> (Click save prior to viewing)</strong></div>";
        }

        
        $HTML_Output .= '<style>
.successbox, .nav, .tab-content, input, .select-inline {
    font-family: Arial, "Helvetica Neue", Helvetica, sans-serif;
    font-style: normal;
    font-variant: normal;

}
</style>';
        $HTML_Output .= '  <!-- Nav tabs -->
        <ul class="nav nav-tabs" role="tablist">
        <li role="presentation" class="active"><a href="#search" aria-controls="search" role="tab" data-toggle="tab">Search</a></li>
        <li role="presentation"><a href="#info" aria-controls="info" role="tab" data-toggle="tab">'.$info_icon.'Info</a></li>
        <li role="presentation"><a href="#appearance" aria-controls="appearance" role="tab" data-toggle="tab">Appearance</a></li>
        <li role="presentation"><a href="#settings" aria-controls="settings" role="tab" data-toggle="tab">Settings</a></li>
        <li role="presentation"><a href="#change_log" aria-controls="change_log" role="tab" data-toggle="tab">Change log</a></li>
        </ul>';

        // search Tab
        
        $HTML_search_form .= "<form method='post' action='?module=bpay_rh#search'>
        <input type='hidden' name='search' value='true' />
        <table class='form' width='100%' border='0' cellspacing='2' cellpadding='3'>
        <tr><td class='fieldlabel'>Search for client based on the BPAY CRN:</td></tr>
        <tr><td width='20%' class='fieldlabel'><strong>Customer Reference Number (CRN)</strong></td><td class='fieldarea'><input class='form-control select-inline' type='numbers' name='crn' size='20' value='".$crn_input."' /> (Leave blank to show all CRN results)</td></tr>
        $searchByList 
        </table>
        <div class='btn-container'>
        <input type='submit' value='Search' class='btn btn-primary' id='searchButton' data-loading-text='Searching...''  />
        </div>
        
        <input name='limit' type='hidden' value='".$searchKey->limit."' />
        </form>";

        // Settings Tab
        $HTML_Settings = "<form method='post' action='?module=bpay_rh#settings' onsubmit='return mySettings()'>
        <input type='hidden' name='settings' value='true' />";

        $HTML_Settings .= '
        <table width="100%" class="form" border="0" cellspacing="2" cellpadding="3">
        <tbody>
        <tr><td class="fieldlabel">BPay Merchant</td><td class="fieldarea"><select name="Merchant_settings" class="form-control select-inline" '.$Merchant_settings_Error.' id="Merchant_settings" onchange="merchantChange()">'.$Merchant_settings_form.'</select> Either use pre-config merchant settings or manually setup settings.</td></tr>
        <tr><td class="fieldlabel">Biller Code</td><td class="fieldarea"><input class="form-control select-inline" id="BillerCode"  name="BillerCode" type="number" size="20" value="'.$BillerCode.'" '.$billerCodeError.' id="billerCode"> Your biller code ID provided by your bank<br/>Biller name:  <span id="billerName">'.$BillerName.'</span></td></tr>
        <tr><td class="fieldlabel">Customer Reference Number (CRN) Length</td><td class="fieldarea"><select name="CRNLength" class="form-control select-inline '.$crnLengthError.'" id="CRNLength">'.$crnform.'</select> Enter length of CRN specified by your bank</td></tr>
        <tr '.$show_prefix.' id="show_prefix"><td class="fieldlabel">CRN Prefix</td><td class="fieldarea"><input class="form-control select-inline"  name="prefix" type="number" size="20" value="'.$prefix.'" '.$prefixCodeError.' id="prefixCode"> Enter your prefix to be at the start of your CRN, as required by EziDebit</td></tr>
        <tr '.$CRN_Generated_via.' id="crnGenBy"><td class="fieldlabel">CRN Generated via</td><td class="fieldarea '.$crnGenBy.'"><label class="radio-inline"><input name="crnMethod" type="radio" value="Customer ID" '.$crnMethodCust.' > Customer ID</label><br><label class="radio-inline"><input name="crnMethod" type="radio" '.$crnMethodInv.' value="Invoice Number"> Invoice Number</label><br></div></td></tr>
        <tr '.$Merchant_settings_hide.' id="MOD10"><td class="fieldlabel">Check Digit MOD10 Version</td><td class="fieldarea"><select id="mod10type" name="mod10type" class="form-control select-inline"'.$crnMethodError.'"><option '.$MOD10v5.' value="MOD10v5">MOD10v5</option><option '.$MOD10v1.' value="MOD10v1">MOD10v1</option></select> <p class="help-block">CRN encoding algorythm check digit. Most banks use MOD10v5, check with your bank before changing.</p></td></tr>
        <tr '.$Merchant_settings_hide.' id="num_padding"><td class="fieldlabel">Pad zero&#39s calculation process for CRN</td><td class="fieldarea"><select id="num_padding" name="num_padding" class="form-control select-inline'.$num_paddingError.'" ><option '.$num_padding_before.' value="before">Before generating Ref number</option><option '.$num_padding_after.' value="after">After generating Ref number</option></select><p class="help-block">If a BPAY CRN number length is smaller than the CRN length, &#39;0&#39; are added to the overall CRN number. Depending on your bank will determine the way you need the CRN generated.</p></td></tr>
        </div>
        <tr><td class="fieldlabel"></td><td class="fieldarea"><strong>Example BPAY image</strong><br><img src="../modules/gateways/bpay.php?cust_id='.generateBpayRef('12345').'" width="300px" /></td></tr>
        
        </tbody>
        </table>';
        $HTML_Settings .= "<div class='btn-container'>
        <input type='submit' value='Save' class='btn btn-success' />

        </form>";

        $HTML_Settings .= "<script>
        function mySettings(){            
            if($('#CRNLength option:selected').text() != ".$CRNLength."){

                if (confirm(\"The CRN length has changed from existing settings. \\nIf you still wish to change the CRN length, all your existing CRN numbers will be removed and recreated. \\nThis could cause issues matching old CRN payments with WHMCS later.\\nDo you wish to continue?\")) {
                    // lets do it
                } else {
                    return false;
                }
            }else if($('#mod10type option:selected').text() != '".$mod10type."'){

                if (confirm(\"The CRN Check Digit MOD10 Version has changed from existing settings. \\nIf you still wish to change the Check Digit MOD10 Version, all your existing CRN numbers will be removed and recreated. \\nThis could cause issues matching old CRN payments with WHMCS later.\\nDo you wish to continue?\")) {
                    // lets do it
                } else {
                    return false;
                }
            }else if($('#num_padding option:selected').val() != '".$num_padding."'){

                if (confirm(\"The Pad zero's calculation process for CRN has changed from existing settings. \\nIf you still wish to change the Pad zero's calculation process for CRN, all your existing CRN numbers will be removed and recreated. \\nThis could cause issues matching old CRN payments with WHMCS later.\\nDo you wish to continue?\")) {
                    // lets do it
                } else {
                    return false;
                }
            }else if(document.getElementById('Merchant_settings').options[document.getElementById('Merchant_settings').selectedIndex].value != '".$Merchant_settings."'){
                if (confirm(\"The BPay Merchant settings has changed from existing settings. \\nIf you still wish to change the BPay Merchant Settings, all your existing CRN numbers will be removed and recreated. \\nThis could cause issues matching old CRN payments with WHMCS later.\\nDo you wish to continue?\")) {
                    // lets do it
                } else {
                    return false;
                }
            }

        }
        function merchantChange(){
            var Merchant_settings = document.getElementById('Merchant_settings').options[document.getElementById('Merchant_settings').selectedIndex].value;
            if($('#Merchant_settings option:selected').text() == 'Manual'){
                document.getElementById('num_padding').setAttribute('style', '');
                document.getElementById('MOD10').setAttribute('style', '');
                document.getElementById('crnGenBy').setAttribute('style', '');
                document.getElementById('show_prefix').setAttribute('style', 'display: none;');
            }
            else if($('#Merchant_settings option:selected').text() == 'EziDebit.com.au'){
                document.getElementById('num_padding').setAttribute('style', 'display: none;');
                document.getElementById('MOD10').setAttribute('style', 'display: none;');
                document.getElementById('crnGenBy').setAttribute('style', 'display: none;');
                document.getElementById('show_prefix').setAttribute('style', '');
            }
            else if($('#Merchant_settings option:selected').text() == 'Commonwealth Bank of Australia'){
                document.getElementById('num_padding').setAttribute('style', 'display: none;');
                document.getElementById('MOD10').setAttribute('style', 'display: none;');
                document.getElementById('crnGenBy').setAttribute('style', '');
                document.getElementById('show_prefix').setAttribute('style', 'display: none;');
            }
            else if($('#Merchant_settings option:selected').text() == 'Westpac'){
                document.getElementById('num_padding').setAttribute('style', 'display: none;');
                document.getElementById('MOD10').setAttribute('style', 'display: none;');
                document.getElementById('crnGenBy').setAttribute('style', '');
                document.getElementById('show_prefix').setAttribute('style', 'display: none;');
            }
            else if($('#Merchant_settings option:selected').text() == 'National Australia Bank'){
                document.getElementById('num_padding').setAttribute('style', 'display: none;');
                document.getElementById('MOD10').setAttribute('style', 'display: none;');
                document.getElementById('crnGenBy').setAttribute('style', '');
                document.getElementById('show_prefix').setAttribute('style', 'display: none;');
            }
        }
        //num_padding, MOD10, crnGenBy
        $('#BillerCode').on('focusout', function () {
        $.post('addonmodules.php?module=bpay_rh',
            {
                billerCode: $('#BillerCode').val(),
                get_biller_code: true
            },
            function(data, status){
                $('#billerName').html(data);
            });
        });
        </script>
        </div>";

        // INFO tab
        $HTML_info .= '
        <div class="panel-body">
            <div class="panel-group" id="accordion" role="tablist" aria-multiselectable="true">
            <div class="panel panel-default">
            <div class="panel-heading" role="tab" id="headingAdmin">
            <h4 class="panel-title">
            <a role="button" data-toggle="collapse" data-parent="#accordion" href="#collapsePermission" aria-expanded="true" aria-controls="collapsePermission">
            '.$file_permission_error_icon.'File Permissions
            </a>
            </h4>
            </div>
            <div id="collapsePermission" class="panel-collapse collapse'.$permission_expand.'" role="tabpanel" aria-labelledby="headingAdmin">
            <div class="panel-body">
            <table class="table table-condensed" style="margin-bottom: 0">
            <tbody>
            <tr>
            <th><strong>File / Directory Name</strong></th>
            <th><strong>File Type</strong></th>
            <th><strong>Status</strong></th>
            <th><strong>Permission Level</strong></th>
            <th>Action</th>
            </tr>
            <tr '.$bpay_file_error.'>
            <td>bpay.php</td>
            <td>PHP (Personal Home Page)</td>
            <td>'.$bpay_file_status.'</td>
            <td>'.$bpay_file_permission.'</td>
            <td>'.$bpay_file_download.'</td>
            </tr>
            <tr '.$arial_file_error.'>
            <td>arial.ttf</td>
            <td>TTF (TrueType Font)</td>
            <td>'.$arial_file_status.'</td>
            <td>'.$arial_file_permission.'</td>
            <td>'.$arial_file_fix.'</td>
            </tr>
            <tr '.$bpay_image_error.'>
            <td>bpay.jpg</td>
            <td>JPG (Joint Photographic Group)</td>
            <td>'.$bpay_image_status.'</td>
            <td>'.$bpay_image_permission.'</td>
            <td>'.$bpay_image_file_fix.'</td>
            </tr>
            <tr '.$bpay_hooks_core_error.'>
            <td>bpay_rh_hooks.php</td>
            <td>PHP (Personal Home Page)</td>
            <td>'.$bpay_hooks_core_file_status.'</td>
            <td>'.$bpay_hooks_core_file_permission.'</td>
            <td>'.$bpay_hooks_core_fix.'</td>
            </tr>
            <tr '.$bpay_hooks_include_error.'>
            <td>/includes/hooks/bpay_rh.php</td>
            <td>PHP (Personal Home Page)</td>
            <td>'.$bpay_hooks_include_file_status.'</td>
            <td>'.$bpay_hooks_include_file_permission.'</td>
            <td>'.$bpay_hooks_include_fix.'</td>
            </tr>
            <tr '.$customers_dir_error.'>
            <td>Customers</td>
            <td>Directory</td>
            <td>'.$customers_dir_status.'</td>
            <td>'.$customers_dir_permission.'</td>
            <td>'.$create_cust_dir.'</td>
            </tr>
            <tr '.$invoices_dir_error.'>
            <td>Invoices</td>
            <td>Directory</td>
            <td>'.$invoices_dir_status.'</td>
            <td>'.$invoices_dir_permission.'</td>
            <td>'.$create_inv_dir.'</td>
            </tr>
            </tbody>
            </table>
            </div>
            </div>
            </div>
            <!--END File Permissions -->
            <!--Start Environment -->
            <div class="panel panel-default">
            <div class="panel-heading" role="tab" id="headingAdmin">
            <h4 class="panel-title">
            <a role="button" data-toggle="collapse" data-parent="#accordion" href="#collapseEnvironment" aria-expanded="true" aria-controls="collapseEnvironment">
            '.$environment_error_icon.'System Environment
            </a>
            </h4>
            </div>
            <div id="collapseEnvironment" class="panel-collapse collapse'.$environment_expand.'" role="tabpanel" aria-labelledby="headingAdmin">
            <div class="panel-body">
            <table class="table table-condensed" style="margin-bottom: 0">
            <tbody>
            <tr>
            <th><strong>Environment Type</strong></th>
            <th><strong>Result</strong></th>
            <th></th>
            <th></th>
            <th></th>
            </tr>
            <tr>
            <td>Server OS</td>
            <td>'.PHP_OS.'</td>
            <td></td>
            <td></td>
            <td></td>
            </tr>
            <tr>
            <td>Web Server Software</td>
            <td>'.$_SERVER['SERVER_SOFTWARE'].'</td>
            <td></td>
            <td></td>
            <td></td>
            </tr>
            <tr>
            <td>WHMCS Version</td>
            <td>'.db_access('getWHMCSVersion').'</td>
            <td></td>
            <td></td>
            <td></td>
            </tr>
            <tr>
            <td>IonCube Version</td>
            <td>'.ioncube_loader_version_information().'</td>
            <td></td>
            <td></td>
            <td></td>
            </tr>
            <tr '.$bpay_manager_error.'>
            <td>BPAY Manage Addon Version</td>
            <td>'.$system_manager_version.'</td>
            <td>'.$update_manager_needed.'</td>
            <td></td>
            <td></td>
            </tr>
            <tr '.$bpay_hooks_error.'>
            <td>BPAY Manage Hooks Version</td>
            <td>'.$system_hooks_version.'</td>
            <td>'.$update_hooks_needed.'</td>
            <td></td>
            <td></td>
            </tr>
            <tr '.$bpay_gateway_error.'>
            <td>BPAY Gateway Version</td>
            <td>'.$system_gateway_version.'</td>
            <td>'.$update_gateway_needed.'</td>
            <td></td>
            <td></td>
            </tr>
            </tbody>
            </table>
            </div>
            </div>
            </div>
            <!--End Environment -->
            </div>
        </div>';

        $appearance = $settingsData;

        if($appearance){
            $global_search = $adminInvoicePage = $adminSummaryPage = $invoicePDF = $showInvoiceSpec = $Xaxis = $Yaxis = $size = $credit_horizontal = $credit_vertica = $fixed_payments = $horizontal = $no_credit_horizontal = $no_credit_vertical = $vertical = $horizontal = "";

            if(isset($appearance['global_search']))
                if($appearance['global_search'] === "1")
                    $global_search = "checked='checked'";

            if(isset($appearance['adminInvoicePage']))
                if($appearance['adminInvoicePage'] === "1")
                    $adminInvoicePage = "checked='checked'";

            if(isset($appearance['adminSummaryPage']))
                if($appearance['adminSummaryPage'] === "1")
                    $adminSummaryPage = "checked='checked'";

            if(isset($appearance['PDF_display'])){
                $appearanceData = json_decode($appearance['PDF_display']);
                if($appearanceData->pdf_display->enabled == "1"){
                    $invoicePDF = "checked='checked'";
                }else{
                    $showInvoiceSpec = "class='hidden'";
                }
                $Xaxis = $appearanceData->pdf_display->Xaxis;
                $Yaxis = $appearanceData->pdf_display->Yaxis;
                $size = $appearanceData->pdf_display->size;
            }

            if(isset($appearance['imgType'])){
                switch ($appearance['imgType']) {
                    case 'credit-horizontal':
                      $credit_horizontal = 'checked="checked"';
                      break;
                    case 'credit-vertical':
                      $credit_vertical = 'checked="checked"';
                      break;
                    case 'fixed-payments':
                      $fixed_payments = 'checked="checked"';
                      break;
                    case 'horizontal':
                      $horizontal = 'checked="checked"';
                      break;
                    case 'no-credit-horizontal':
                      $no_credit_horizontal = 'checked="checked"';
                      break;
                    case 'no-credit-vertical':
                      $no_credit_vertical = 'checked="checked"';
                    case 'vertical':
                      $vertical = 'checked="checked"';
                      break;
                    default:
                      $horizontal = 'checked="checked"';
                      break;
                  }
            }

            // Appearance tab
            $HTML_appearence .= '<form method="post" action="?module=bpay_rh#appearance">
            <input type="hidden" name="appearance" value="true" />
            ';

            $HTML_appearence .= '<div class="panel-body">
            <div class="panel-group" id="accordion" role="tablist" aria-multiselectable="true">
            <div class="panel panel-default">
            <div class="panel-heading" role="tab" id="headingAdmin">
            <h4 class="panel-title">
            <a role="button" data-toggle="collapse" data-parent="#accordion" href="#collapseGlobal" aria-expanded="true" aria-controls="collapseGlobal">
            <strong>Global Functions</strong>
            </a>
            </h4>
            </div>
            <div id="collapseGlobal" class="panel-collapse collapse in" role="tabpanel" aria-labelledby="headingAdmin">
            <div class="panel-body">

            <table width="100%" class="form" border="0" cellspacing="2" cellpadding="3">
            <tbody>
            <tr><td class="fieldarea"><div class="row"><div class="col-md-6"><label><input name="global_search" type="checkbox" value="1" '.$global_search.'> WHMCS Global Search</label><p class="help-block">Use WHMCS own global search bar to search BPAY CRN details quickly.</p></div></div></td></tr>
            <tr><td class="fieldarea"><div class="row"><div class="col-md-6"><label><input name="adminInvoicePage" type="checkbox" value="1" '.$adminInvoicePage.'> Admin Invoice Page</label><p class="help-block">Display BPAY details when viewing an invoice from the admin area </p></div></div></td></tr>
            <tr><td class="fieldarea"><div class="row"><div class="col-md-6"><label><input name="adminSummaryPage" type="checkbox" value="1" '.$adminSummaryPage.'> Admin Client Summary Page</label><p class="help-block">Display BPAY details when viewing admin client summary page.<br><i>Note: This will only appear if BPAY settings are set to display Customer ID not Invoice Number</i></p></div></div></td></tr>
            
            </tbody>
            </table>
            </div>
            </div>';

            $HTML_appearence.= '

           <div class="panel panel-default">
            <div class="panel-heading" role="tab" id="headingAdmin">
            <h4 class="panel-title">
            <a role="button" data-toggle="collapse" data-parent="#accordion" href="#collapseInvoice" aria-expanded="true" aria-controls="collapseInvoice">
            <strong>Invoice Positioning</strong>
            </a>
            </h4>
            </div>
            <div id="collapseInvoice" class="panel-collapse collapse" role="tabpanel" aria-labelledby="headingAdmin">
            <div class="panel-body">
            <table width="100%" class="form" border="0" cellspacing="2" cellpadding="3">
            <tbody>
            <tr><td class="fieldarea"><div class="row"><div class="col-md-6"><label><input name="invoicePDF" type="checkbox" value="1" id="invoicePDF" '.$invoicePDF.' onclick="invoicePDFbox()"> Invoice Download</label><p class="help-block">Display BPAY details in invoice download</p></div></div>
            <div id="invoicePDFdetails" '.$showInvoiceSpec.'>
            <br>
            <div class="col-md-3"><strong>PDF Image X-axis: (Left to Right)</strong> <input class="form-control" name="PDFx-axis" type="text" value="'.$Xaxis.'"> <span class="help-block">Display BPAY image in invoice PDF on x-axis position (Horizontal)</span></div>
            <div class="col-md-3"><strong>PDF Image Y-axis: (Down to Up)</strong> <input class="form-control" name="PDFy-axis" type="text" value="'.$Yaxis.'"> <span class="help-block">Display BPAY image in invoice PDF on y-axis position (Vertical)</span></div>
            <div class="col-md-3"><strong>PDF Image Size: </strong> <input class="form-control" name="PDFsize" type="text" value="'.$size.'"> <span class="help-block">Display BPAY image in invoice PDF size scale</span></div>
            </div>'.$invoice_preview.'
            </td></tr>
            </tbody>
            </table>
            </div>
            </div>';

            $HTML_appearence.= '<div class="panel panel-default">
            <div class="panel-heading" role="tab" id="headingAdmin">
            <h4 class="panel-title">
            <a role="button" data-toggle="collapse" data-parent="#accordion" href="#collapseImage" aria-expanded="true" aria-controls="collapseImage">
            <strong>Biller Code Artwork Format</strong>
            </a>
            </h4>
            </div>
            <div id="collapseImage" class="panel-collapse collapse" role="tabpanel" aria-labelledby="headingAdmin">
            <div class="panel-body">
            Please choose the image you wish to use:
            <table width="100%" class="form" border="0" cellspacing="2" cellpadding="3">
            <tbody>

            <tr><td class="fieldarea"><div class="row"><div class="col-md-6"><label><input name="imgType" type="radio" value="horizontal" '.$horizontal.'>Horizontal</label>
            <p class="help-block"><img src="https://raw.githubusercontent.com/beanonymous/whmcsBPAY/master/modules/gateways/bpay/img-bpay-biller-code-horizontal.jpg" width="300" /></p>
            </div></div></td></tr>

            <tr><td class="fieldarea"><div class="row"><div class="col-md-6"><label><input name="imgType" type="radio" value="vertical" '.$vertical.'>Vertical</label>
            <p class="help-block"><img src="https://raw.githubusercontent.com/beanonymous/whmcsBPAY/master/modules/gateways/bpay/img-bpay-biller-code-vertical.jpg" width="300" /></p>
            </div></div></td></tr>
            
            <tr><td class="fieldarea"><div class="row"><div class="col-md-6"><label><input name="imgType" type="radio" value="credit-horizontal" '.$credit_horizontal.'>Accepts Credit Cards - Horizontal</label>
            <p class="help-block"><img src="https://raw.githubusercontent.com/beanonymous/whmcsBPAY/master/modules/gateways/bpay/img-bpay-biller-code-credit-horizontal.jpg" width="300" /></p>
            </div></div></td></tr>

            <tr><td class="fieldarea"><div class="row"><div class="col-md-6"><label><input name="imgType" type="radio" value="credit-vertical" '.$credit_vertical.'>Accepts Credit Cards - Vertical</label>
            <p class="help-block"><img src="https://raw.githubusercontent.com/beanonymous/whmcsBPAY/master/modules/gateways/bpay/img-bpay-biller-code-credit-vertical.jpg" width="300" /></p>
            </div></div></td></tr>

            <tr><td class="fieldarea"><div class="row"><div class="col-md-6"><label><input name="imgType" type="radio" value="no-credit-horizontal" '.$no_credit_horizontal.'>Does Not Accept Credit Cards - Horizontal</label>
            <p class="help-block"><img src="https://raw.githubusercontent.com/beanonymous/whmcsBPAY/master/modules/gateways/bpay/img-bpay-biller-code-no-credit-horizontal.jpg" width="300" /></p>
            </div></div></td></tr>

            <tr><td class="fieldarea"><div class="row"><div class="col-md-6"><label><input name="imgType" type="radio" value="no-credit-vertical" '.$no_credit_vertical.'>Does Not Accept Credit Cards - Vertical</label>
            <p class="help-block"><img src="https://raw.githubusercontent.com/beanonymous/whmcsBPAY/master/modules/gateways/bpay/img-bpay-biller-code-no-credit-vertical.jpg" width="300" /></p>
            </div></div></td></tr>

            <tr><td class="fieldarea"><div class="row"><div class="col-md-6"><label><input name="imgType" type="radio" value="fixed-payments" '.$fixed_payments.'>Fixed Payments</label>
            <p class="help-block"><img src="https://raw.githubusercontent.com/beanonymous/whmcsBPAY/master/modules/gateways/bpay/img-bpay-biller-code-fixed-payments.jpg" width="300" /></p>
            </div></div></td></tr>

            </tbody>
            </table>
            </div>
            </div>
            </div>
            </div>
            </div>
            </div>
            </div>';

            $HTML_appearence .= "<div class='btn-container'>
            <input type='submit' value='Save' class='btn btn-success' />
            </div>
            </form>";



            $HTML_appearence .= "<script>
            function invoicePDFbox(){
                if($('#invoicePDF').is(':checked')){
                    $('#invoicePDFdetails').removeClass('hidden');
                }else{
                    $('#invoicePDFdetails').addClass('hidden');
                }
            }
            </script>";
        }else{
            // error cause no settings returned for appearance 
            $HTML_Output .= '<div class="errorbox"><strong><span class="title">Error getting appearance settings</span></strong><br>There was an error connecting to BPAY appearance settings on your database.</div>';
        }

        // JS HERE
        $HTML_Output .= "<script>
        $(function(){
            var hash = window.location.hash;
            hash && $('ul.nav a[href=".'"'."' + hash + '".'"'."]').tab('show');

            $('.nav-tabs a').click(function (e) {
                $(this).tab('show');
                var scrollmem = $('body').scrollTop() || $('html').scrollTop();
                window.location.hash = this.hash;
                $('html,body').scrollTop(scrollmem);
            });
        });
        </script>";

        $HTML_Output .= health_check();

        $change_log = "<h1>Change Logs</h1>".change_log();

        $HTML_Output .= "<div class='tab-content'>
        <div role='tabpanel' class='tab-pane active' id='search'>".$HTML_search_form.$HTML_search."</div>
        <div role='tabpanel' class='tab-pane' id='info'>".$HTML_info."</div>
        <div role='tabpanel' class='tab-pane' id='appearance'>".$HTML_appearence."</div>
        <div role='tabpanel' class='tab-pane' id='settings'>".$HTML_Settings."</div>
        <div role='tabpanel' class='tab-pane' id='change_log'>".$change_log."</div>
        </div>";

        echo $HTML_Output;
    }
}

function bpay_version()
{    return "2.1.8";
}

function is_bpay_out_dated(){
    if(get_bpay_lastest_version() > bpay_version()){
        return "<br><span style='float:right;'><b>BPAY Manager is out of Date: <a style='color:red' href='https://github.com/beanonymous/whmcsBPAY'>Download New Update!</a></span>";
    }
}

function get_bpay_lastest_version(){
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "https://raw.githubusercontent.com/beanonymous/whmcsBPAY/master/version");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $result = curl_exec($ch);
    curl_close ($ch);

    return str_replace("\n", "", $result);
}

// get licence data and check if licence is valid
function db_access($action, $key = 0, $display_errors = false){
    // connect_DB();
    GLOBAL $conn;

    //echo "Connected successfully";

    //////////////////////////////
    if($action == "search_client_count"){
        $crn = con_sanitize($key->crn);
 
        $query = "SELECT COUNT(mod_bpay_record.crn)
        FROM `mod_bpay_record` 
        JOIN tblclients ON mod_bpay_record.clientID=tblclients.id
        WHERE mod_bpay_record.crn LIKE '%$crn%'
        AND mod_bpay_record.crn_type = 'Client ID'";

        $result = $conn->query($query) or echo_die($conn->error);

        if($result->num_rows > 0) {

            $list = array();
            while($row = $result->fetch_assoc()) {
                return $row['COUNT(mod_bpay_record.crn)'];
            }
        }
    }

    if($action == "search_client"){ //5 column
        $crn = con_sanitize($key->crn);
        $query = "SELECT mod_bpay_record.crn, mod_bpay_record.clientID, mod_bpay_record.crn_type, tblclients.firstname, tblclients.lastname
        FROM `mod_bpay_record` 
        JOIN tblclients ON mod_bpay_record.clientID=tblclients.id
        WHERE mod_bpay_record.crn LIKE '%$crn%'
        AND mod_bpay_record.crn_type = 'Client ID'";

        if(!$key->limit){
            $query .=" LIMIT 25";
        }else if($key->limit == "10" || $key->limit == "25" || $key->limit == "50" || $key->limit == "100"){
            $query .=" LIMIT ".con_sanitize($key->limit);
        }else{
            $query .=" LIMIT 25";
        }

        if(!isset($key->offset)){
            $query .=" OFFSET 0";
        }else if(is_numeric($key->offset)){
            $query .=" OFFSET ".con_sanitize($key->offset);
        }else{
            $query .=" OFFSET 0";
        }
        // LIMIT 20 OFFSET 10

        $result = $conn->query($query) or echo_die($conn->error);

          // GOING THROUGH THE DATA

        if($result->num_rows > 0) {
            $list;
            while($row = $result->fetch_assoc()) {
                $list = combin_invoice_results($list, $row);
            }
            return $list;
        }else{
            return null;
        }
    } //END search_client

    if($action == "search_inv_count"){ 
        $crn = con_sanitize($key->crn);
        $query = "SELECT COUNT(mod_bpay_record.crn)
        FROM `mod_bpay_record` 
        JOIN tblclients ON mod_bpay_record.clientID=tblclients.id
        JOIN tblinvoices ON mod_bpay_record.invoiceID=tblinvoices.id
        WHERE mod_bpay_record.crn LIKE '%$crn%'
        AND mod_bpay_record.crn_type = 'Invoice'";
        if($key->searchBy == "unpaid")
            $query .=" AND tblinvoices.status = 'Unpaid'";
        else if($key->searchBy == "paid")
            $query .=" AND tblinvoices.status = 'Paid'";

        $result = $conn->query($query) or echo_die($conn->error);
        if($result->num_rows > 0) {

            $list = array();
            while($row = $result->fetch_assoc()) {
                return $row['COUNT(mod_bpay_record.crn)'];
            }
        }
    }

    if($action == "search_inv"){ //7 column

        $crn = con_sanitize($key->crn);
        $query = "SELECT mod_bpay_record.crn, mod_bpay_record.clientID, mod_bpay_record.invoiceID, mod_bpay_record.crn_type, tblclients.firstname, tblclients.lastname, tblinvoices.total, tblinvoices.status, tblclients.status AS 'cstaus'
        FROM `mod_bpay_record` 
        JOIN tblclients ON mod_bpay_record.clientID=tblclients.id
        JOIN tblinvoices ON mod_bpay_record.invoiceID=tblinvoices.id
        WHERE mod_bpay_record.crn LIKE '%$crn%'
        AND mod_bpay_record.crn_type = 'Invoice'";

        if($key->searchBy == "unpaid")
            $query .=" AND tblinvoices.status = 'Unpaid'";
        else if($key->searchBy == "paid")
            $query .=" AND tblinvoices.status = 'Paid'";

        if(!$key->limit){
            $query .=" LIMIT 25";
        }else if($key->limit == "10" || $key->limit == "25" || $key->limit == "50" || $key->limit == "100"){
            $query .=" LIMIT ".con_sanitize($key->limit);
        }else{
            $query .=" LIMIT 25";
        }

        if(!$key->offset){
            $query .=" OFFSET 0";
        }else if(is_numeric($key->offset)){
            $query .=" OFFSET ".con_sanitize($key->offset);
        }else{
            $query .=" OFFSET 0";
        }
        // LIMIT 20 OFFSET 10

        $result = $conn->query($query) or echo_die($conn->error);

          // GOING THROUGH THE DATA

        if($result->num_rows > 0) {
            $list;
            while($row = $result->fetch_assoc()) {
                $list[] = $row;
            }
            return $list;
        }else{
            return null;
        }
    } //END search_inv

    if($action == "settings"){
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
    }

    if($action == "countActiveClients"){
        $query = "SELECT * FROM tblclients where status = 'Active'";

        $result = $conn->query($query) or echo_die($conn->error);

        return $result->num_rows;
    }

    if($action == "countUnpaidInvoices"){
        $query = "SELECT * FROM tblinvoices where status = 'Unpaid'";

        $result = $conn->query($query) or echo_die($conn->error);

        return $result->num_rows;
    }

    // get payment BPAY gateway settings
    if($action == "gate_settings"){
        $query = "SELECT * FROM `tblpaymentgateways` WHERE gateway = 'bpay'";

        $result = $conn->query($query) or echo_die($conn->error);

          // GOING THROUGH THE DATA

        if($result->num_rows > 0) {
            $list;
            while($row = $result->fetch_assoc()) {
                $list[$row['setting']] = $row['value'];
            }
            return $list;
        }else{
            return null;
        }
    }

    if($action == "settings_update"){
        $settings = db_access("settings");

        if($settings){
            if(isset($settings['BillerCode'])){
                $sql = "UPDATE `mod_bpay_display` SET `value`='".$key['BillerCode']."' WHERE `option`='BillerCode';";
                $query = $conn->query($sql);
                if(!$query){$error = true;} 
            }else{
                if(!isset($key['BillerCode']))
                    $key['BillerCode'] = "0";
                $sql = "INSERT INTO `mod_bpay_display` (`option`, `value`) VALUES ('BillerCode', '".$key['BillerCode']."');";
                $query = $conn->query($sql);
                if(!$query){$error = true;} 
            }

            if(isset($settings['CRNLength'])){
                $sql = "UPDATE `mod_bpay_display` SET `value`='".$key['CRNLength']."' WHERE `option`='CRNLength';";
                $query = $conn->query($sql);
                if(!$query){$error = true;} 
            }else{
                if(!isset($key['CRNLength']))
                    $key['CRNLength'] = "0";
                $sql = "INSERT INTO `mod_bpay_display` (`option`, `value`) VALUES ('CRNLength', '".$key['CRNLength']."');";
                $query = $conn->query($sql);
                if(!$query){$error = true;} 
            }

            if(isset($settings['crnMethod'])){
                $sql = "UPDATE `mod_bpay_display` SET `value`='".$key['crnMethod']."' WHERE `option`='crnMethod';";
                $query = $conn->query($sql);
                if(!$query){$error = true;} 
            }else{
                if(!isset($key['crnMethod']))
                    $key['crnMethod'] = "0";
                $sql = "INSERT INTO `mod_bpay_display` (`option`, `value`) VALUES ('crnMethod', '".$key['crnMethod']."');";
                $query = $conn->query($sql);
                if(!$query){$error = true;} 
            }

            if(isset($settings['mod10type'])){
                $sql = "UPDATE `mod_bpay_display` SET `value`='".$key['mod10type']."' WHERE `option`='mod10type';";
                $query = $conn->query($sql);
                if(!$query){$error = true;} 
            }else{
                if(!isset($key['mod10type']))
                    $key['mod10type'] = "0";
                $sql = "INSERT INTO `mod_bpay_display` (`option`, `value`) VALUES ('mod10type', '".$key['mod10type']."');";
                $query = $conn->query($sql);
                if(!$query){$error = true;} 
            }

            if(isset($settings['key'])){
                $sql = "UPDATE `mod_bpay_display` SET `value`='".$key['key']."' WHERE `option`='key';";
                $query = $conn->query($sql);
                if(!$query){$error = true;} 
            }else{
                if(!isset($key['key']))
                    $key['key'] = "";
                $sql = "INSERT INTO `mod_bpay_display` (`option`, `value`) VALUES ('key', '".$key['key']."');";
                $query = $conn->query($sql);
                if(!$query){$error = true;} 
            }

            if(isset($settings['num_padding'])){
                $sql = "UPDATE `mod_bpay_display` SET `value`='".$key['num_padding']."' WHERE `option`='num_padding';";
                $query = $conn->query($sql);
                if(!$query){$error = true;} 
            }else{
                if(!isset($key['num_padding']))
                    $key['num_padding'] = "after";
                $sql = "INSERT INTO `mod_bpay_display` (`option`, `value`) VALUES ('num_padding', '".$key['num_padding']."');";
                $query = $conn->query($sql);
                if(!$query){$error = true;} 
            }

            if(isset($settings['Merchant_settings'])){
                $sql = "UPDATE `mod_bpay_display` SET `value`='".$key['Merchant_settings']."' WHERE `option`='Merchant_settings';";
                $query = $conn->query($sql);
                if(!$query){$error = true;} 
            }else{
                if(!isset($key['Merchant_settings']))
                $sql = "INSERT INTO `mod_bpay_display` (`option`, `value`) VALUES ('Merchant_settings', '".$key['Merchant_settings']."');";
                $query = $conn->query($sql);
                if(!$query){$error = true;} 
            }

            if(isset($settings['prefix'])){
                $sql = "UPDATE `mod_bpay_display` SET `value`='".$key['prefix']."' WHERE `option`='prefix';";
                $query = $conn->query($sql);
                if(!$query){$error = true;} 
            }else{
                if(!isset($key['prefix']))
                $sql = "INSERT INTO `mod_bpay_display` (`option`, `value`) VALUES ('prefix', '".$key['prefix']."');";
                $query = $conn->query($sql);
                if(!$query){$error = true;} 
            }
        }

        $error = false;

        // if length gets shortanted wipe and regenerate db table

        $error_message_initialise = '<div class="errorbox"><strong><span class="title">Error Initializing CRN records in database!</span></strong><br>We were unable to initialize all existing clients from WHMCS with a CRN ID as your CRN Length is to short. To make it possible to provide all clients with a BPAY CRN.<br>We suggest you contact your BPAY bank manager and arrange to have this CRN length extended to accommodated for all your existing and future WHMCS customers.</div>';
        if($settings['Merchant_settings'] != $key['Merchant_settings'] && $settings['CRNLength'] != $key['CRNLength'] || $settings['Merchant_settings'] != $key['Merchant_settings'] && $settings['mod10type'] != $key['mod10type'] || $settings['Merchant_settings'] != $key['Merchant_settings'] && $settings['num_padding'] != $key['num_padding'])
            if(db_access("wipe_crn_db"))
                if(wipe_image_files())
                    if(!initialise_record_table())
                        echo $error_message_initialise;

        if($settings['CRNLength'] != $key['CRNLength'])
            if(db_access("wipe_crn_db"))
                if(wipe_image_files())
                    if(!initialise_record_table())
                        echo $error_message_initialise;

        if($settings['mod10type'] != $key['mod10type'])
            if(db_access("wipe_crn_db"))
                if(wipe_image_files())
                    if(!initialise_record_table())
                        echo $error_message_initialise;

        if($settings['num_padding'] != $key['num_padding'])
            if(db_access("wipe_crn_db"))
                if(wipe_image_files())
                    if(!initialise_record_table())
                        echo $error_message_initialise;



        if(!$error)
            return false;
        else
            return ture;
    }

    if($action == "updateAppearanceData"){
        // $key->invoicePDF = 1;$key->size = $_POST['PDFsize'];$key->Yaxis = $_POST['PDFy-axis'];$key->Xaxis = $_POST['PDFx-axis'];

        $appearance = db_access("settings");

        if($appearance){

            if(isset($appearance['global_search'])){
                $query = "UPDATE `mod_bpay_display` SET `value`='".$key->global_search."' WHERE `option`='global_search';";
                $query = $conn->query($query);
                if(!$query){$error = true;} 
            }else{
                if(!isset($key->global_search))
                    $key->global_search = "0";
                $query = "INSERT INTO `mod_bpay_display` (`option`, `value`) VALUES ('global_search', '".$key->global_search."');";
                $query = $conn->query($query);
                if(!$query){$error = true;} 
            }
                
            if(isset($appearance['adminInvoicePage'])){
                $query = "UPDATE `mod_bpay_display` SET `value`='".$key->adminInvoicePage."' WHERE `option`='adminInvoicePage';";
                $query = $conn->query($query);
                if(!$query){$error = true;} 
            }else{
                if(!isset($key->adminInvoicePage))
                    $key->adminInvoicePage = "0";
                $query = "INSERT INTO `mod_bpay_display` (`option`, `value`) VALUES ('adminInvoicePage', '".$key->adminInvoicePage."');";
                $query = $conn->query($query);
                if(!$query){$error = true;} 
            }
                

            if(isset($appearance['adminSummaryPage'])){
                $query = "UPDATE `mod_bpay_display` SET `value`='".$key->adminSummaryPage."' WHERE `option`='adminSummaryPage';";
                $query = $conn->query($query);
                if(!$query){$error = true;} 
            }else{
                if(!isset($key->adminSummaryPage))
                    $key->adminSummaryPage = "0";
                $query = "INSERT INTO `mod_bpay_display` (`option`, `value`) VALUES ('adminSummaryPage', '".$key->adminSummaryPage."');";
                $query = $conn->query($query);
                if(!$query){$error = true;} 
            }

            if(isset($appearance['PDF_display'])){
                $PDF_display = json_decode($appearance['PDF_display']);
                if(!isset($key->invoicePDF))
                        $key->invoicePDF = "0";

                    if(!isset($key->Xaxis))
                        $key->Xaxis = $PDF_display->pdf_display->Xaxis;

                    if(!isset($key->Yaxis))
                        $key->Yaxis = $PDF_display->pdf_display->Yaxis;

                    if(!isset($key->size))
                        $key->size = $PDF_display->pdf_display->size;

                $temp = new stdClass();
                $temp->pdf_display->enabled = $key->invoicePDF;
                $temp->pdf_display->Xaxis = $key->Xaxis;
                $temp->pdf_display->Yaxis = $key->Yaxis;
                $temp->pdf_display->size = $key->size;

                $query = "UPDATE `mod_bpay_display` SET `value`='".json_encode($temp)."' WHERE `option`='PDF_display';";
                $query = $conn->query($query);
                if(!$query){$error = true;} 
            }else{
                if(isset($key->invoicePDF)){
                    if(!isset($key->invoicePDF))
                        $key->invoicePDF = "0";

                    if(!isset($key->Xaxis))
                        $key->Xaxis = "126";

                    if(!isset($key->Yaxis))
                        $key->Yaxis = "45";

                    if(!isset($key->size))
                        $key->size = "50";

                    $temp = new stdClass();
                    $temp->pdf_display->enabled = $key->invoicePDF;
                    $temp->pdf_display->Xaxis = $key->Xaxis;
                    $temp->pdf_display->Yaxis = $key->Yaxis;
                    $temp->pdf_display->size = $key->size;

                    $query = "INSERT INTO `mod_bpay_display` (`option`, `value`) VALUES ('PDF_display', '".json_encode($temp)."');";
                    $query = $conn->query($query);
                    if(!$query){$error = true;} 
                }
               
            }

            if(isset($appearance['imgType'])){
                $query = "UPDATE `mod_bpay_display` SET `value`='".$key->imgType."' WHERE `option`='imgType';";
                $query = $conn->query($query);
                if(!$query){
                    $query = "INSERT INTO `mod_bpay_display` (`option`, `value`) VALUES ('imgType', '".$key->imgType."');";
                    $query = $conn->query($query);
                    if(!$query){$error = true;} 
                } 
            }

            if($error)
                return false;
            else
                return true;
        }
    }

    if($action == "getWHMCSVersion"){

        $query = 'select value from `tblconfiguration` where setting = "Version"';
        $result = $conn->query($query);

        if($result->num_rows > 0) {
            while($row = $result->fetch_assoc()) {
                $version = explode("-", $row['value']);
                return $version[0];
            }
        }
        return "NULL";
    }


    if($action == "getWHMCSTemplate"){

        $query = 'select value from `tblconfiguration` where setting = "Template"';
        $result = $conn->query($query);

        if($result->num_rows > 0) {
            while($row = $result->fetch_assoc()) {
                $version = explode("-", $row['value']);
                return $version[0];
            }
        }
        return "NULL";
    }

    if($action == "getInvoiceClientID"){

        $query = "SELECT * FROM `tblinvoices` WHERE id='".con_sanitize($key->invoiceID)."';";
        $result = $conn->query($query);

        if($result->num_rows > 0) {
            while($row = $result->fetch_assoc()) {
                return $row['userid'];
            }
        }
        return "NULL";
    }

    if($action == "insertInvoice"){

        $query = "INSERT INTO `mod_bpay_record` (`crn`, `clientID`, `invoiceID`, `crn_type`, `created_at`, `updated_at`) VALUES ('".con_sanitize($key->crn)."', '".con_sanitize($key->clientID)."', '".con_sanitize($key->invoiceID)."', '".con_sanitize($key->crn_type)."', '".con_sanitize($key->created_at)."', '".con_sanitize($key->updated_at)."');";
        return $conn->query($query);
    }

    if($action == "doesCRNexist"){

        $query = "SELECT * FROM `mod_bpay_record` WHERE 'crn' = '".con_sanitize($key->crn)."' AND 'crn_type' = '".con_sanitize($key->crn_type)."';";
        $result = $conn->query($query);

        if($result->num_rows > 0) {
            while($row = $result->fetch_assoc()) {
                $version = explode("-", $row['value']);
                return $version[0];
            }
        }
        return "NULL";
    }

    if($action == "whmcs_config_template"){

        $query = "SELECT value FROM `tblconfiguration` WHERE 'setting' = 'Template';";
        $result = $conn->query($query);

        if($result->num_rows > 0) {
            while($row = $result->fetch_assoc()) {
                return $row['value'];
            }
        }
        return false;
    }

    if($action == "get_install_state"){
        if($key === 0){
           $query = "SELECT value FROM `mod_bpay_display` WHERE `option` = 'Installed';";
            $result = $conn->query($query);

            if($result->num_rows > 0) {
                while($row = $result->fetch_assoc()) {
                    return $row['value'];
                }
            }
            return false; 
        }else{
            $query = "UPDATE `mod_bpay_display` SET `value`='1' WHERE `option`='Installed';";
            $query = $conn->query($query);
            if(!$query){return true;}else{return false;}
        }  
    }

    if($action == "get_appearence_settings"){
        return db_access("settings");
    }

    if($action == "wipe_crn_db"){
        $query = "TRUNCATE TABLE `mod_bpay_record`";
        $result = $conn->query($query);
        if(!$result)
            return false; 

        return true;
    }

    if($action == "update_local"){
        $settings = db_access("settings");

        if($settings){
            if(isset($settings['localKey'])){
                $sql = "UPDATE `mod_bpay_display` SET `value`='".$key."' WHERE `option`='localKey';";
                $query = $conn->query($sql);
                if(!$query){$error = true;} 
            }else{
                if(!isset($key))
                    $key = "0";
                $sql = "INSERT INTO `mod_bpay_display` (`option`, `value`) VALUES ('localKey', '".$key."');";
                $query = $conn->query($sql);
                if(!$query){$error = true;} 
            }
        }
    }

    if($action == "client_total_invoices_due"){
        $total = 0;
        $overdue = 0;
        $query = "SELECT total FROM `tblinvoices` WHERE `status` = 'Unpaid' AND `userid` = '$key';";

        $result = $conn->query($query);

        if($result->num_rows > 0) {
            while($row = $result->fetch_assoc()) {
                $total++;
                $overdue += $row['total'];
            }
        }
        if($total != "0")
            return "<font color='red'>".$total." # of Invoices - $".$overdue."</font>";
        return "-";
    }

    if($action == "getFirstInvoiceID"){

        $query = 'SELECT `id` FROM `tblinvoices`';
        $result = $conn->query($query);

        if($result->num_rows > 0) {
            while($row = $result->fetch_assoc()) {
                $version = explode("-", $row['value']);
                return $row['id'];
            }
        }
        return "NULL";
    }

    if($action == "sqlQuery"){
        return $conn->query($key);
    }

    // CLOSE CONNECTION
    // mysqli_close($conn);
    ////////////////////////
}

function wipe_image_files(){
    if(file_exists(ROOTDIR.'/modules/gateways/bpay/invoices/')){
        $dir = ROOTDIR.'/modules/gateways/bpay/invoices';
        array_map('unlink', glob($dir."/*"));
    }

    if(file_exists(ROOTDIR.'/modules/gateways/bpay/customers/')){
        $dir = ROOTDIR.'/modules/gateways/bpay/customers/';
        array_map('unlink', glob($dir."/*"));
    }      

    return true;
}

function combin_invoice_results($list, $row){

    $found = false;
    foreach ($list as $key => $value) {
        if($value['clientID'] == $row['clientID']){                 
            if($row['status'] == "Unpaid"){
                $list[$key]['total'] = $value['total'] + $row['total'];
            }
            $found = true;
            break;
        }
    }

    // no duplicate found so add to list
    if($found == false){
        $list[] = $row;
    }

    return $list;
}

function health_check(){

    $results = "";

    // check addon version is current
    if(get_bpay_lastest_version() > bpay_version()){
        $results .= '<div class="infobox"><strong><span class="title">A new update is available!</span></strong><br>There is a new version of BPAY Manager and gateway is available to download.<br><br><a class="btn btn-primary" href="https://github.com/beanonymous/whmcsBPAY/" role="button" target="_blank">Download New Update!</a></div>';
    }

    // check if bpay files for hooks exist
    if (!file_exists(ROOTDIR.'/modules/addons/bpay_rh/bpay_rh_hooks.php')){
        // File Missing, need to re download file and add to DIR
        $results .= '<div class="errorbox"><strong><span class="title">BPAY Hooks Manager file is missing</span></strong><br>The BPAY Hooks Manager file is missing<br>Please re-upload both the BPAY Manager Hooks file to resolve this issue. (/modules/addons/bpay_rh/bpay_rh_hooks.php)</div>';
        
    }else{
        // file exist check its version
        include_once(ROOTDIR."/modules/addons/bpay_rh/bpay_rh_hooks.php");
        if(get_hooks_lastest_version() > bpay_hook_version()){
            // Need to download latest version
            // Not needed to display as first constraint checks and displays message for manager and constraint checks for version mismatch
            $results .= '<div class="infobox"><strong><span class="title">A new update is available!</span></strong><br>There is a new version of BPAY Manager Hooks are available to download.<br><br><a class="btn btn-primary" href="https://github.com/beanonymous/whmcsBPAY/" role="button" target="_blank">Download New Update!</a></div>';
            
        }
    }


    // check if bpay files for hooks exist
    if (!file_exists(ROOTDIR.'/includes/hooks/bpay_rh.php')){
        // File Missing, need to re download file and add to DIR
        $results .= '<div class="errorbox"><strong><span class="title">BPAY Hooks file is missing</span></strong><br>The BPAY Hooks file is missing<br>Please re-upload both the BPAY Hooks file to resolve this issue. (/includes/hooks/bpay_rh.php)</div>';
        
    }else{
        // file exist check its version
        include_once(ROOTDIR."/modules/addons/bpay_rh/bpay_rh_hooks.php");
        if(get_hooks_lastest_version() > bpay_hook_version()){
            // Need to download latest version
            // Not needed to display as first constraint checks and displays message for manager and constraint checks for version mismatch
            $results .= '<div class="infobox"><strong><span class="title">A new update is available!</span></strong><br>There is a new version of BPAY Manager Hooks are available to download.<br><br><a class="btn btn-primary" href="https://github.com/beanonymous/whmcsBPAY/" role="button" target="_blank">Download New Update!</a></div>';
            
        }
    }

    // check if bpay files for gateway exist
    if (!file_exists(ROOTDIR.'/modules/gateways/bpay.php')){
        // File Missing, need to re download file and add to DIR
        $results .= '<div class="errorbox"><strong><span class="title">BPAY Gateway file is missing</span></strong><br>The BPAY Gateway file is missing<br>Please re-upload both the BPAY Gateway file to resolve this issue. (/modules/gateways/bpay.php)</div>';
        
    }else{
        // file exist check its version
        include_once(ROOTDIR."/modules/gateways/bpay.php");
        if(get_bpay_lastest_version() > gate_bpay_version()){
            // Need to download latest version
            // Not needed to display as first constraint checks and displays message for manager and constraint checks for version mismatch
            $results .= '<div class="infobox"><strong><span class="title">A new update is available!</span></strong><br>There is a new version of BPAY Gateway is available to download.<br><br><a class="btn btn-primary" href="https://github.com/beanonymous/whmcsBPAY/" role="button" target="_blank">Download New Update!</a></div>';
            
        }

        if(bpay_version() != gate_bpay_version()){
            // manager and gateway files are different versions.
            $results .= '<div class="errorbox"><strong><span class="title">BPAY Manager and BPAY Gateway are not the same version</span></strong><br>The BPAY Manager is NOT the same version as the BPAY Gateway. <br>Please re-upload both the BPAY Manager and the BPAY Gateway to resolve this issue.</div>';
        }

        // check if gateway is active and viable 
        $data_gate = db_access("gate_settings");
        if(is_array($data_gate)){
            if($data_gate['visible'] == "on"){
                // all is right with the world
            }else{
                $results .= '<div class="errorbox"><strong><span class="title">BPAY is hidden to clients!</span></strong><br>BPAY is not visible to your clients. Please go to "Payment Gateways" to show this gateway.<br><br><a class="btn btn-danger" href="configgateways.php" role="button" target="_blank">Payment Gateways</a></div>';
                // BPAY is not visible to clients
            }
        }else{
            // BPAY is not installed on gateway
            $results .= '<div class="errorbox"><strong><span class="title">BPAY is NOT an activate GATEWAY!</span></strong><br>BPAY has not been activated on "Payment Gateway". Please go to "Payment Gateways" and add BPAY to the gateway.<br><br><a class="btn btn-danger" href="configgateways.php" role="button" target="_blank">Payment Gateways</a></div>';
        }

        
    } // END BPAY Gateway check

    // check if license is valid
    $date_manager = db_access("settings");
    $install_state = db_access("get_install_state");
    if(!is_array($date_manager)){
    }

    return $results;
} // } = health_check()

function calculate_page_results($offset,$limit,$count){
    $results = new stdClass();
    // pages 
    $results->pageTotal = ceil(($count / $limit));
    if($results->pageTotal == "0")
        $results->pageTotal = "1";

    $results->currentPage = ceil((($offset+1) / $limit));

    if($results->currentPage == "0")
        $results->currentPage = "1";

    return $results;
}

function ioncube_loader_version_information(){
    if (function_exists('ioncube_loader_iversion')) {
        $liv = ioncube_loader_iversion();
        return str_replace("0", ".", $liv);
    }
    return "NULL";
}

function initialise_record_table(){

    if(health_check())
        return false; // error health check failed

    //sneak install in initialise of records
    insertInvoiceFunc();

    GLOBAL $conn;
    require_once(ROOTDIR.'/modules/gateways/bpay.php');

    $data = db_access("settings");
    // $data['crnMethod'] = "Invoice Number" = "Customer ID"
    $CRNLength = $data['CRNLength'];
    $sql = "";
    $crn_record_array = array();
    // Generate both client and invoice CRN for system
    $query = "select tblclients.id
    from tblclients
    LEFT JOIN mod_bpay_record on mod_bpay_record.clientID = tblclients.id
    WHERE mod_bpay_record.id IS NULL";
    // AND tblclients.status = 'Active'";
    $result = $conn->query($query);

    if($result->num_rows > 0) {
        while($row = $result->fetch_assoc()) {
            $crn = generateBpayRef($row['id']);
            $crn_record_array["$crn"] = $crn;
            $sql .= "INSERT INTO `mod_bpay_record` (`crn`, `clientID`, `crn_type`) VALUES ('".$crn."', '".$row['id']."', 'Client ID');";
        }
    }else{
        // No clients found

    }

    $query = "select tblinvoices.id, tblinvoices.userid
    from tblinvoices
    LEFT JOIN mod_bpay_record on mod_bpay_record.invoiceID = tblinvoices.id
    WHERE tblinvoices.status = 'Unpaid'
    AND mod_bpay_record.id IS NULL";
    $result = $conn->query($query);

    if($result->num_rows > 0) {
        while($row = $result->fetch_assoc()) {
            $crn = generateBpayRef($row['id']);
            if(!empty($crn_record_array["$crn"])){
                $counter = 0;
                do{
                    if($counter != 20){
                        $crn = generateBpayRef(rand(10000000000000, 9999999999999999));
                    }else{
                        return false; // clearly not possible to match all existing customers crn with crn length set
                    }
                    $counter++;
                }while(!empty($crn_record_array["$crn"]));
            }
            $crn_record_array["$crn"] = $crn;
            $sql .= "INSERT INTO `mod_bpay_record` (`crn`, `clientID`, `invoiceID`, `crn_type`) VALUES ('".$crn."', '".$row['userid']."', '".$row['id']."', 'Invoice');";
        }
    }else{
        // No invoices found
    }

    if($sql){
        // REF: http://stackoverflow.com/questions/14715889/strict-standards-mysqli-next-result-error-with-mysqli-multi-query
        if(mysqli_multi_query($conn,$sql)){
            do{
                $cumulative_rows+=mysqli_affected_rows($conn);
            } while(mysqli_more_results($conn) && mysqli_next_result($conn));
        }
        if($error_mess=mysqli_error($conn)){return false; /*echo "Error: $error_mess";*/}
        // echo "Cumulative Affected Rows: $cumulative_rows";
        }
    return true; //processing done
}

function insertInvoiceFunc($replace = false){
    $insertString = '///////////////////////////////////////////////////////////////////////////
/// START BPAY Generator v2.1.5
///////////////////////////////////////////////////////////////////////////
    if (file_exists(ROOTDIR."/modules/gateways/bpay.php")) {
        require_once(ROOTDIR."/modules/gateways/bpay.php");
        $output = BPAY_PDF($clientsdetails["id"],$invoicenum);

        $pagecount = $pdf->getNumPages();
        for($i = 1; $i <= $pagecount; $i++){
            $pdf->setPage($i);
            if($output["mode"] == 1){
                $pdf->Image(ROOTDIR."/modules/gateways/bpay/customers/".$clientsdetails["id"].".jpg",$output["Xaxis"],$output["Yaxis"],$output["size"]);
            }else if($output["mode"] == 2){
                $pdf->Image(ROOTDIR."/modules/gateways/bpay/invoices/".$invoicenum.".jpg",$output["Xaxis"],$output["Yaxis"],$output["size"]);
            }
        }
    }
///////////////////////////////////////////////////////////////////////////
/// END BPAY Generator
///////////////////////////////////////////////////////////////////////////';

    $template_name = db_access("getWHMCSTemplate");
    $file = ROOTDIR."/templates/".$template_name."/invoicepdf.tpl";

    $invoiceTemplateFile = file_get_contents($file);
    if($replace == false){
        $pos = strpos($invoiceTemplateFile, "START BPAY Generator");

        if ($pos !== false) {
            return false; //bpay function exists dont continue
        }else{
            file_put_contents($file, $insertString, FILE_APPEND);
            return true;
        }
    }else{
        // replace old invoice code if found
        $remove_line = false;
        $file_string = "";

        $pos = strpos($invoiceTemplateFile, "START BPAY Generator");

        if ($pos !== false) {
            $lines = file($file);
            foreach ($lines as $lineNumber => $line) {
                if (strpos($line, "START BPAY Generator") !== false) {
                    $remove_line = ture;
                }
                if (strpos($line, "END BPAY Generator") !== false) {
                    $remove_line = false;
                }
                if(!$remove_line)
                    $file_string .= $line."\n";
            }

            $file_string .= $insertString;

            file_put_contents($file, $file_string);
        }

    }
}

function installPhase($HTML_Output){

    echo ' <!-- Nav tabs -->
    <ul class="nav nav-tabs hidden" role="tablist">
    <li role="presentation" class="active"><a href="#welcome" aria-controls="welcome" role="tab" data-toggle="tab">Intro</a></li>
    <li role="presentation"><a href="#step1" aria-controls="step1" role="tab" data-toggle="tab">Step 1</a></li>
    <li role="presentation"><a href="#settings" aria-controls="settings" role="tab" data-toggle="tab">Settings</a></li>
    <li role="presentation"><a href="#confirm" aria-controls="confirm" role="tab" data-toggle="tab">Confirm</a></li>
    </ul>';
    /////////////////////////////////////
    /// START System environment Stats //
    /////////////////////////////////////

        $bpay_file_status = (file_exists(ROOTDIR.'/modules/gateways/bpay.php')) ? "<font color='green'>Found</font>" : "<font color='red'>Missing</font>";
        $bpay_file_permission = substr(sprintf('%o', fileperms(ROOTDIR.'/modules/gateways/bpay.php')), -4);

        $arial_file_status = (file_exists(ROOTDIR.'/modules/gateways/bpay/arial.ttf')) ? "<font color='green'>Found</font>" : "<font color='red'>Missing</font>";
        $arial_file_permission = substr(sprintf('%o', fileperms(ROOTDIR.'/modules/gateways/bpay/arial.ttf')), -4);

        $bpay_image_status = (file_exists(ROOTDIR.'/modules/gateways/bpay/BPay.jpg')) ? "<font color='green'>Found</font>" : "<font color='red'>Missing</font>";
        $bpay_image_permission = substr(sprintf('%o', fileperms(ROOTDIR.'/modules/gateways/bpay/BPay.jpg')), -4);

        $customers_dir_status = (file_exists(ROOTDIR.'/modules/gateways/bpay/customers/')) ? "<font color='green'>Found</font>" : "<font color='red'>Missing</font>";
        $customers_dir_permission = substr(sprintf('%o', fileperms(ROOTDIR.'/modules/gateways/bpay/customers/')), -4);

        $invoices_dir_status = (file_exists(ROOTDIR.'/modules/gateways/bpay/invoices/')) ? "<font color='green'>Found</font>" : "<font color='red'>Missing</font>";
        $invoices_dir_permission = substr(sprintf('%o', fileperms(ROOTDIR.'/modules/gateways/bpay/invoices/')), -4);

        if($bpay_file_status == "<font color='red'>Missing</font>" || $bpay_file_permission > 644){
            $bpay_file_error = 'class="alert alert-danger"';
            $bpay_file_download = "<a class='btn btn-primary' target='_blank' href='addonmodules.php?module=bpay_rh&bpay_perm_fix=1#step1'>Resolve</a>";
        }

        if($arial_file_status == "<font color='red'>Missing</font>" || $arial_file_permission > 644){
            $arial_file_error = 'class="alert alert-danger"';
            $arial_file_fix = "<a class='btn btn-primary' target='_blank' href='addonmodules.php?module=bpay_rh&ttf_perm_fix=1#step1'>Resolve</a>";
        }

        if($bpay_image_status == "<font color='red'>Missing</font>" || $bpay_image_permission > 644){
            $bpay_image_error = 'class="alert alert-danger"';
            $bpay_image_file_fix = "<a class='btn btn-primary' target='_blank' href='addonmodules.php?module=bpay_rh&jpg_perm_fix=1#step1'>Resolve</a>";
        }

        if($customers_dir_status == "<font color='red'>Missing</font>" || $customers_dir_permission > 755){
            if($crnMethod != "Invoice Number")
                $customers_dir_error = 'class="alert alert-danger"';
            $create_cust_dir = "<a class='btn btn-primary' href='addonmodules.php?module=bpay_rh&create_cust_dir=1#step1'>Resolve</a>";
        }

        if($invoices_dir_status == "<font color='red'>Missing</font>" || $invoices_dir_permission > 755){
            if($crnMethod != "Customer ID")
                $invoices_dir_error = 'class="alert alert-danger"';
            $create_inv_dir = "<a class='btn btn-primary' href='addonmodules.php?module=bpay_rh&create_inv_dir=1#step1'>Resolve</a>";
        }

        if($bpay_file_error || $arial_file_error || $bpay_image_error || $customers_dir_error || $invoices_dir_error)
            $file_permission_error_icon = "<span class='glyphicon glyphicon-warning-sign'></span> ";

            // Environment STATS
        $system_manager_version = bpay_version();
        $current_version = get_bpay_lastest_version();
        if($system_manager_version < $current_version){
            $update_manager_needed = "<a style='color:red' href='https://github.com/beanonymous/whmcsBPAY/'>Download New Update!</a>";
            $environment_error_icon = "<span class='glyphicon glyphicon-warning-sign'></span> ";
            $bpay_manager_error = 'class="alert alert-danger"';
        }

        if(file_exists(ROOTDIR."/modules/addons/bpay_rh/bpay_rh_hooks.php")){
            include_once(ROOTDIR."/modules/addons/bpay_rh/bpay_rh_hooks.php");
            $system_hooks_version = bpay_hook_version();
            $current_hooks_version = get_hooks_lastest_version();
            if($system_hooks_version < $current_hooks_version){
                $update_hooks_needed = "<a style='color:red' href='https://github.com/beanonymous/whmcsBPAY/'>Download New Update!</a>";
                $environment_error_icon = "<span class='glyphicon glyphicon-warning-sign'></span> ";
                $bpay_hooks_error = 'class="alert alert-danger"';
            }
        }else{
            $environment_error_icon = "<span class='glyphicon glyphicon-warning-sign'></span> ";
            $bpay_hooks_error = 'class="alert alert-danger"';
            $system_hooks_version = "<a style='color:red'>bpay_rh_hooks.php - Missing!</a>";
        }

        if(file_exists(ROOTDIR."/modules/gateways/bpay.php")){
            include_once(ROOTDIR."/modules/gateways/bpay.php");
            $system_gateway_version = gate_bpay_version();
            $current_gateway_version = gateway_check_version();
            if($system_gateway_version < $current_gateway_version){
                $update_gateway_needed = "<a style='color:red' href='https://github.com/beanonymous/whmcsBPAY/'>Download New Update!</a>";
                $environment_error_icon = "<span class='glyphicon glyphicon-warning-sign'></span> ";
            }
        }else{
            $environment_error_icon = "<span class='glyphicon glyphicon-warning-sign'></span> ";
            $system_gateway_version = "<a style='color:red'>bpay.php - Missing!</a>";
            $bpay_gateway_error = 'class="alert alert-danger"';
        }

        if($file_permission_error_icon){
            $info_icon = $file_permission_error_icon;
            $permission_expand = " in";
        }elseif($environment_error_icon){
            $info_icon = $environment_error_icon;
            $environment_expand = " in";
        }else{
            $permission_expand = " in";
        }

    /////////////////////////////////////
    /// END System environment Stats ////
    /////////////////////////////////////
    

    /////////////////////////////////////
    /////////// START Settings //////////
    /////////////////////////////////////
    
        // START settings Lookup /////
        $data = db_access("settings");

        // $BillerCode = $CRNLength = $crnMethod = $mod10type = $key = $crnform = $crnMethodCust = $crnMethodInv = $MOD10v1 = $MOD10v5 = "";
        if(is_array($data)){

            if(isset($_POST['BillerCode']))
                $BillerCode = $_POST['BillerCode'];
            else
                $BillerCode = $settingsData['BillerCode'];

            $BillerName = getBillerName($BillerCode);

            if(isset($_POST['CRNLength'])){
                if(is_numeric($_POST['CRNLength']) && $_POST['CRNLength'] >= 3 || $_POST['CRNLength'] <= 19){
                    $CRNLength = $_POST['CRNLength'];
                }
            }else{
                $CRNLength = $data['CRNLength'];
            }

            if(isset($_POST['crnMethod']))
                $crnMethod = $_POST['crnMethod'];
            else
                $crnMethod = $data['crnMethod'];

            if(isset($_POST['mod10type']))
                $mod10type = $_POST['mod10type'];
            else
                $mod10type = $data['mod10type'];

            if(isset($_POST['key']))
                $key = $_POST['key'];
            else
                $key = $data['key'];

            if(isset($_POST['num_padding']))
                $num_padding = $_POST['num_padding'];
            else
                $num_padding = $settingsData['num_padding'];

            if(isset($_POST['Merchant_settings']))
                $Merchant_settings = $_POST['Merchant_settings'];
            else
                $Merchant_settings = $settingsData['Merchant_settings'];

            if(isset($_POST['prefix']))
                $prefix = $_POST['prefix'];
            else
                $prefix = $settingsData['prefix'];
        }

        if($crnMethod == "Customer ID"){
            $crnMethodCust = 'checked="checked"';
            $initialiseRowsToProcess = db_access("countActiveClients");
        }
        elseif($crnMethod == "Invoice Number"){
            $crnMethodInv = 'checked="checked"';
            $initialiseRowsToProcess = db_access("countUnpaidInvoices");
        }

        if($mod10type == "MOD10v1")
            $MOD10v1 = 'selected="selected"';
        elseif($mod10type == "MOD10v5")
            $MOD10v5 = 'selected="selected"';

        if($num_padding == "before")
            $num_padding_before = 'selected="selected"';
        else
            $num_padding_after = 'selected="selected"';

        for ($i=3; $i < 20; $i++) { 
            if($CRNLength == $i)
                $crnform .= "<option selected='selected' value='$i'>$i</option>";
            else
                $crnform .= "<option value='$i'>$i</option>";
        }

        if($Merchant_settings == "ezidebit"){
            $Merchant_settings_form = "<option value='manual'>Manual</option><option value='ezidebit' selected='selected'>EziDebit.com.au</option><option value='cba'>Commonwealth Bank of Australia</option><option value='nab'>National Australia Bank</option><option value='westpac'>Westpac</option>";
            $Merchant_settings_hide = $CRN_Generated_via = "style='display: none;'";
            $show_prefix = "";
        }
        elseif($Merchant_settings == "cba"){
            $Merchant_settings_form = "<option value='manual'>Manual</option><option value='ezidebit'>EziDebit.com.au</option><option value='cba' selected='selected'>Commonwealth Bank of Australia</option><option value='nab'>National Australia Bank</option><option value='westpac'>Westpac</option>";
            $Merchant_settings_hide = "style='display: none;'";
            $show_prefix = "style='display: none;'";
            $CRN_Generated_via = "";
        }
        elseif($Merchant_settings == "westpac"){
            $Merchant_settings_form = "<option value='manual'>Manual</option><option value='ezidebit'>EziDebit.com.au</option><option value='cba'>Commonwealth Bank of Australia</option><option value='nab'>National Australia Bank</option><option value='westpac' selected='selected'>Westpac</option>";
            $Merchant_settings_hide = "style='display: none;'";
            $show_prefix = "style='display: none;'";
            $CRN_Generated_via = "";
        }
        elseif($Merchant_settings == "nab"){
            $Merchant_settings_form = "<option value='manual'>Manual</option><option value='ezidebit'>EziDebit.com.au</option><option value='cba'>Commonwealth Bank of Australia</option><option value='nab' selected='selected'>National Australia Bank</option><option value='westpac'>Westpac</option>";
            $Merchant_settings_hide = "style='display: none;'";
            $show_prefix = "style='display: none;'";
            $CRN_Generated_via = "";
        }
        else{
            $Merchant_settings_form = "<option value='manual' selected='selected'>Manual</option><option value='ezidebit'>EziDebit.com.au</option><option value='cba'>Commonwealth Bank of Australia</option><option value='nab'>National Australia Bank</option><option value='westpac'>Westpac</option>";
            $show_prefix = "style='display: none;'";
            $CRN_Generated_via = "";
        }

        // Generate CRN if bpay gateway file exists.
        if(file_exists(ROOTDIR."/modules/gateways/bpay.php"))
            $CRN = generateBpayRef('12345');
        else
            $CRN = "12345";

        // Settings Tab
        $HTML_Settings = "<form method='post' action='?module=bpay_rh#settings'>
        <input type='hidden' name='settings' value='true' />";

        $HTML_Settings .= '<table width="100%" class="form" border="0" cellspacing="2" cellpadding="3">
        <tbody>
        <tr><td class="fieldlabel">BPay Merchant</td><td class="fieldarea"><select name="Merchant_settings" class="form-control select-inline" '.$Merchant_settings_Error.' id="Merchant_settings" onchange="merchantChange()">'.$Merchant_settings_form.'</select> Either use pre-config merchant settings or manually setup settings.</td></tr>
        <tr><td class="fieldlabel">Biller Code</td><td class="fieldarea"><input class="form-control select-inline" id="BillerCode"  name="BillerCode" type="number" size="20" value="'.$BillerCode.'" '.$billerCodeError.' id="billerCode"> Your biller code ID provided by your bank<br/>Biller name:  <span id="billerName">'.$BillerName.'</span></td></tr>
        <tr><td class="fieldlabel">Customer Reference Number (CRN) Length</td><td class="fieldarea"><select name="CRNLength" class="form-control select-inline '.$crnLengthError.'" id="CRNLength">'.$crnform.'</select> Enter length of CRN specified by your bank</td></tr>
        <tr '.$show_prefix.' id="show_prefix"><td class="fieldlabel">CRN Prefix</td><td class="fieldarea"><input class="form-control select-inline"  name="prefix" type="number" size="20" value="'.$prefix.'" '.$prefixCodeError.' id="prefixCode"> Enter your prefix to be at the start of your CRN, as required by EziDebit</td></tr>
        <tr '.$CRN_Generated_via.' id="crnGenBy"><td class="fieldlabel">CRN Generated via</td><td class="fieldarea '.$crnGenBy.'"><label class="radio-inline"><input name="crnMethod" type="radio" value="Customer ID" '.$crnMethodCust.' > Customer ID</label><br><label class="radio-inline"><input name="crnMethod" type="radio" '.$crnMethodInv.' value="Invoice Number"> Invoice Number</label><br></div></td></tr>
        <tr '.$Merchant_settings_hide.' id="MOD10"><td class="fieldlabel">Check Digit MOD10 Version</td><td class="fieldarea"><select id="mod10type" name="mod10type" class="form-control select-inline"'.$crnMethodError.'"><option '.$MOD10v5.' value="MOD10v5">MOD10v5</option><option '.$MOD10v1.' value="MOD10v1">MOD10v1</option></select> <p class="help-block">CRN encoding algorythm check digit. Most banks use MOD10v5, check with your bank before changing.</p></td></tr>
        <tr '.$Merchant_settings_hide.' id="num_padding"><td class="fieldlabel">Pad zero&#39s calculation process for CRN</td><td class="fieldarea"><select id="num_padding" name="num_padding" class="form-control select-inline'.$num_paddingError.'" ><option '.$num_padding_before.' value="before">Before generating Ref number</option><option '.$num_padding_after.' value="after">After generating Ref number</option></select><p class="help-block">If a BPAY CRN number length is smaller than the CRN length, &#39;0&#39; are added to the overall CRN number. Depending on your bank will determine the way you need the CRN generated.</p></td></tr>
        </div>
        <tr><td class="fieldlabel"></td><td class="fieldarea"><strong>Example BPAY image</strong><br><img src="../modules/gateways/bpay.php?cust_id='.$CRN.'" width="300px" /></td></tr>
        
        </tbody>
        </table>';
        $HTML_Settings .= "<div class='btn-container'>
        <input type='submit' value='Save' class='btn btn-success' />
        </div>
        </form>";


        $HTML_Settings .= "<script>
        function mySettings(){            
            if($('#CRNLength option:selected').text() != ".$CRNLength."){

                if (confirm(\"The CRN length has changed from existing settings. \\nIf you still wish to change the CRN length, all your existing CRN numbers will be removed and recreated. \\nThis could cause issues matching old CRN payments with WHMCS later.\\nDo you wish to continue?\")) {
                    // lets do it
                } else {
                    return false;
                }
            }else if($('#mod10type option:selected').text() != '".$mod10type."'){

                if (confirm(\"The CRN Check Digit MOD10 Version has changed from existing settings. \\nIf you still wish to change the Check Digit MOD10 Version, all your existing CRN numbers will be removed and recreated. \\nThis could cause issues matching old CRN payments with WHMCS later.\\nDo you wish to continue?\")) {
                    // lets do it
                } else {
                    return false;
                }
            }else if($('#num_padding option:selected').val() != '".$num_padding."'){

                if (confirm(\"The Pad zero's calculation process for CRN has changed from existing settings. \\nIf you still wish to change the Pad zero's calculation process for CRN, all your existing CRN numbers will be removed and recreated. \\nThis could cause issues matching old CRN payments with WHMCS later.\\nDo you wish to continue?\")) {
                    // lets do it
                } else {
                    return false;
                }
            }else if(document.getElementById('Merchant_settings').options[document.getElementById('Merchant_settings').selectedIndex].value != '".$Merchant_settings."'){
                if (confirm(\"The BPay Merchant settings has changed from existing settings. \\nIf you still wish to change the BPay Merchant Settings, all your existing CRN numbers will be removed and recreated. \\nThis could cause issues matching old CRN payments with WHMCS later.\\nDo you wish to continue?\")) {
                    // lets do it
                } else {
                    return false;
                }
            }

        }
        function merchantChange(){
            var Merchant_settings = document.getElementById('Merchant_settings').options[document.getElementById('Merchant_settings').selectedIndex].value;
            if($('#Merchant_settings option:selected').text() == 'Manual'){
                document.getElementById('num_padding').setAttribute('style', '');
                document.getElementById('MOD10').setAttribute('style', '');
                document.getElementById('crnGenBy').setAttribute('style', '');
                document.getElementById('show_prefix').setAttribute('style', 'display: none;');
            }
            else if($('#Merchant_settings option:selected').text() == 'EziDebit.com.au'){
                document.getElementById('num_padding').setAttribute('style', 'display: none;');
                document.getElementById('MOD10').setAttribute('style', 'display: none;');
                document.getElementById('crnGenBy').setAttribute('style', 'display: none;');
                document.getElementById('show_prefix').setAttribute('style', '');
            }
            else if($('#Merchant_settings option:selected').text() == 'Commonwealth Bank of Australia'){
                document.getElementById('num_padding').setAttribute('style', 'display: none;');
                document.getElementById('MOD10').setAttribute('style', 'display: none;');
                document.getElementById('crnGenBy').setAttribute('style', '');
                document.getElementById('show_prefix').setAttribute('style', 'display: none;');
            }
            else if($('#Merchant_settings option:selected').text() == 'Westpac'){
                document.getElementById('num_padding').setAttribute('style', 'display: none;');
                document.getElementById('MOD10').setAttribute('style', 'display: none;');
                document.getElementById('crnGenBy').setAttribute('style', '');
                document.getElementById('show_prefix').setAttribute('style', 'display: none;');
            }
            else if($('#Merchant_settings option:selected').text() == 'National Australia Bank'){
                document.getElementById('num_padding').setAttribute('style', 'display: none;');
                document.getElementById('MOD10').setAttribute('style', 'display: none;');
                document.getElementById('crnGenBy').setAttribute('style', '');
                document.getElementById('show_prefix').setAttribute('style', 'display: none;');
            }
        }
        //num_padding, MOD10, crnGenBy
        $('#BillerCode').on('focusout', function () {
        $.post('addonmodules.php?module=bpay_rh',
            {
                billerCode: $('#BillerCode').val(),
                get_biller_code: true
            },
            function(data, status){
                $('#billerName').html(data);
            });
        });
        </script>";
    /////////////////////////////////////
    //////////// END Settings ///////////
    /////////////////////////////////////
        
    $tableStrat = "<table class='table table-bordered' style='font-family:arial;'><tr><td>";
    $tableEnd = "</td></tr></table>";

    $change_log = change_log();

    echo "<div class='tab-content'>$HTML_Output
        <div role='tabpanel' class='tab-pane active' id='welcome'>".$tableStrat."
        <h1>Welcome - BPAY ".bpay_version()."</h1>
        <p>Welcome to BPAY Module, Developed by <a href='https://www.linkedin.com/in/clinton-nesbitt/'>Clinton Nesbitt</a> </p>
        <p>This module is a standalone application that generates BPAY CRN codes to your banks requirments and gives you the flexability to customise how it works within your WHMCS.</p>
        <p>Some cool feature to note with this BPAY module is that once a CRN is generated for an invoice or client, Depending on the settings selected, you can actually use the WHMCS global search bar to search for any CRN payments that appear within your bank account.</p>
        <p>You can customise all sorts of things with your BPAY setup like where the BPAY details appear on an invoice.</p>

        <p>Below is a list of changes made in the past to improve the BPAY module and we are always open to hear new features and ideas on how to impove this module.</p>

        <p>All updates are free to all paid licenses for life.</p>

        <p>Click the <strong>".'"'."Get Started".'"'."</strong> button at the bottom when you are ready to go ahead with installation.</p>
        <p><strong>ENJOY!</strong></p>
        <p>For more information on BPAY and how it works, please go to <a href='http://bpay.com' target='_blank'>www.bpay.com</a></p>
        <h3><strong>BPAY Update History</strong></h3>
        ".$change_log."
        <a class='btn btn-primary' href='#step1' aria-controls='step1' role='tab' data-toggle='tab'>Get Started</a> 
        ".$tableEnd."</div>
        <div role='tabpanel' class='tab-pane' id='step1'>".$tableStrat."<h1>Step 1</h1>";
        $health_check = health_check();
        if($health_check){
            echo $health_check; // display the error that needs to be fixed
        }

        echo '
        <div class="panel-body">
            <div class="panel-group" id="accordion" role="tablist" aria-multiselectable="true">
            <div class="panel panel-default">
            <div class="panel-heading" role="tab" id="headingAdmin">
            <h4 class="panel-title">
            <a role="button" data-toggle="collapse" data-parent="#accordion" href="#collapsePermission" aria-expanded="true" aria-controls="collapsePermission">
            '.$file_permission_error_icon.'File Permissions
            </a>
            </h4>
            </div>
            <div id="collapsePermission" class="panel-collapse collapse'.$permission_expand.'" role="tabpanel" aria-labelledby="headingAdmin">
            <div class="panel-body">
            <table class="table table-condensed" style="margin-bottom: 0">
            <tbody>
            <tr>
            <th><strong>File / Directory Name</strong></th>
            <th><strong>File Type</strong></th>
            <th><strong>Status</strong></th>
            <th><strong>Permission Level</strong></th>
            <th>Action</th>
            </tr>
            <tr '.$bpay_file_error.'>
            <td>bpay.php</td>
            <td>PHP (Personal Home Page)</td>
            <td>'.$bpay_file_status.'</td>
            <td>'.$bpay_file_permission.'</td>
            <td>'.$bpay_file_download.'</td>
            </tr>
            <tr '.$arial_file_error.'>
            <td>arial.ttf</td>
            <td>TTF (TrueType Font)</td>
            <td>'.$arial_file_status.'</td>
            <td>'.$arial_file_permission.'</td>
            <td>'.$arial_file_fix.'</td>
            </tr>
            <tr '.$bpay_image_error.'>
            <td>bpay.jpg</td>
            <td>JPG (Joint Photographic Group)</td>
            <td>'.$bpay_image_status.'</td>
            <td>'.$bpay_image_permission.'</td>
            <td>'.$bpay_image_file_fix.'</td>
            </tr>
            <tr '.$customers_dir_error.'>
            <td>Customers</td>
            <td>Directory</td>
            <td>'.$customers_dir_status.'</td>
            <td>'.$customers_dir_permission.'</td>
            <td>'.$create_cust_dir.'</td>
            </tr>
            <tr '.$invoices_dir_error.'>
            <td>Invoices</td>
            <td>Directory</td>
            <td>'.$invoices_dir_status.'</td>
            <td>'.$invoices_dir_permission.'</td>
            <td>'.$create_inv_dir.'</td>
            </tr>
            </tbody>
            </table>
            </div>
            </div>
            </div>
            <!--END File Permissions -->
            <!--Start Environment -->
            <div class="panel panel-default">
            <div class="panel-heading" role="tab" id="headingAdmin">
            <h4 class="panel-title">
            <a role="button" data-toggle="collapse" data-parent="#accordion" href="#collapseEnvironment" aria-expanded="true" aria-controls="collapseEnvironment">
            '.$environment_error_icon.'System Environment
            </a>
            </h4>
            </div>
            <div id="collapseEnvironment" class="panel-collapse collapse'.$environment_expand.'" role="tabpanel" aria-labelledby="headingAdmin">
            <div class="panel-body">
            <table class="table table-condensed" style="margin-bottom: 0">
            <tbody>
            <tr>
            <th><strong>Environment Type</strong></th>
            <th><strong>Result</strong></th>
            <th></th>
            <th></th>
            <th></th>
            </tr>
            <tr>
            <td>Server OS</td>
            <td>'.PHP_OS.'</td>
            <td></td>
            <td></td>
            <td></td>
            </tr>
            <tr>
            <td>Web Server Software</td>
            <td>'.$_SERVER['SERVER_SOFTWARE'].'</td>
            <td></td>
            <td></td>
            <td></td>
            </tr>
            <tr>
            <td>WHMCS Version</td>
            <td>'.db_access('getWHMCSVersion').'</td>
            <td></td>
            <td></td>
            <td></td>
            </tr>
            <tr>
            <td>IonCube Version</td>
            <td>'.ioncube_loader_version_information().'</td>
            <td></td>
            <td></td>
            <td></td>
            </tr>
            <tr '.$bpay_manager_error.'>
            <td>BPAY Manage Addon Version</td>
            <td>'.$system_manager_version.'</td>
            <td>'.$update_manager_needed.'</td>
            <td></td>
            <td></td>
            </tr>
            <tr '.$bpay_hooks_error.'>
            <td>BPAY Manage Hooks Version</td>
            <td>'.$system_hooks_version.'</td>
            <td>'.$update_hooks_needed.'</td>
            <td></td>
            <td></td>
            </tr>
            <tr '.$bpay_gateway_error.'>
            <td>BPAY Gateway Version</td>
            <td>'.$system_gateway_version.'</td>
            <td>'.$update_gateway_needed.'</td>
            <td></td>
            <td></td>
            </tr>';
            echo '</tbody>
            </table>
            </div>
            </div>
            </div>
            <!--End Environment -->
            </div>
        </div>';
        if(!$health_check)
            echo "<a class='btn btn-primary' href='#settings' aria-controls='settings' role='tab' data-toggle='tab'>Next Step</a>";
        echo $tableEnd."</div>
        <div role='tabpanel' class='tab-pane' id='settings'>".$tableStrat."<h1>Step 2</h1>";
        echo $HTML_Settings;
        echo "<a class='btn btn-primary hidden' href='#confirm' id='settings_next' aria-controls='confirm' role='tab' data-toggle='tab'>Next Step</a>";
        echo $tableEnd."</div>
        <div role='tabpanel' class='tab-pane' id='confirm'>".$tableStrat."<h1>Confirm Details</h1>";
        echo "Total number of BPAY references that need to be generated based off your existing BPAY settings: <strong>".$initialiseRowsToProcess."</strong><br>";
        echo "BPAY references will be generated based off: <strong>".$crnMethod."</strong><br>If you are happy with the above details, please click Finalised and we can get started.<p>";
        echo "<center><a class='btn btn-success' href='addonmodules.php?module=bpay_rh&initialise_record=1'>Finalise Install</a></center>";
        echo $tableEnd."</div>
        </div>";

        // settings next button
        echo "<script>var url = document.location.toString();
        if (url.match('#')) {
            $('.nav-tabs a[href=".'"'."#' + url.split('#')[1] + '".'"'."]').tab('show');
            console.log(url.split('#')[1]);
            $('#settings_next').removeClass('hidden');
        } </script>";


        echo "<script>
        $(function(){
            var hash = window.location.hash;
            hash && $('ul.nav a[href=".'"'."' + hash + '".'"'."]').tab('show');

            $('.nav-tabs a').click(function (e) {
                $(this).tab('show');
                var scrollmem = $('body').scrollTop() || $('html').scrollTop();
                window.location.hash = this.hash;
                $('html,body').scrollTop(scrollmem);
            });
        });
        </script>";
}

function echo_die($message = ""){
    die($message);
} // } = echo_die()

function change_log(){
    return "
        <p>Bug fixes 2.1.8 is:<ul>
        <li><strong>Added Feature</strong> - Artwork change from 7 different variations</li>
        <li><strong>Added Feature</strong> - PHP 7.2 support</li>
        <li><strong>Added Feature</strong> - Ioncude 10.2 support</li>
        <li><strong>Cron base directory</strong> - Added check for users who cron's run from rood directory rather than base web directory</li>
        </ul>

        <p>Bug fixes in 2.1.7 is:<ul>
        <li><strong>Added Feature</strong> - Added prefix support through EziDebit</li>
        <li><strong>Added Feature</strong> - Added option to use pre-configured bank requirements for BPAY (please let us know if your bank is not listed)</li>
        <li><strong>Added Feature</strong> - Modified Example BPAY image to demonstrate an actual BPAY image generated based off settings made in settings area.</li>
        <li><strong>Added Feature</strong> - Added preview button on appearances page to quickly view new placement of an invoice after change made.</li>
        <li><strong>Added Feature</strong> - Added name lookup feature based off biller code entered</li>
        </ul>
        <p>Bug fixes in 2.1.6 is:<ul>
        <li><strong>Invoice PDF bug</strong> - Fixed bug with loading BPAY Ref on order complete</li>
        <li><strong>Invoice PDF bug</strong> - Fixed MOD10v1 check digit issue</li>
        </ul>
        <p>Bug fixes in 2.1.5 is:<ul>
        <li><strong>Invoice PDF bug</strong> - Fixed minor bug with loading BPAY library assets</li>
        <li><strong>Invoice PDF bug</strong> - Fixed minor bug where the BPAY details would only load on one PDF if invoice overflowed to multiple pages</li>
        <li><strong>CSS for Hooks</strong> - Added the ability for developers to manipulate BPAY elements generated by WHMCS hooks</li>
        </ul>
        <p>Bug fixes in 2.1.4 is:
        <ul>
        <li><strong>License validation fix</strong> - Allow for BPAY license to stay active without constant polling license service (improves page load time)</li>
        <li><strong>Pagination fix</strong> - Fixed minor bug pagination on search for CRN's in the BPAY Manager</li>
        </ul>
        <p>Bug fixes in 2.1.3 is:
        <ul>
        <li><strong>License validation fix</strong> - Allow for BPAY license to stay active without constant polling license service (improves page load time)</li>
        <li><strong>Image Generator compatibility improvements</strong> - Image generation in this addon is now supported on more shard web hosting servers online</li>
        <li><strong>Global Search bug fix</strong> - Fixed minor bug with using the global search on WHMCS</li>
        <li><strong>Hook fix</strong> - Fixed minor bug with some invoices that were in a specific state</li>
        </ul>
        <p>Bug fixes in 2.1.2 is:
        <ul>
        <li><strong>Ioncube Later Version</strong> - Support for PHP 7.0 requires a later version of PHP</li>
        </ul>
        <p>Bug fixes in 2.1.1 is:
        <ul>
        <li><strong>Database fix</strong> - Support for MYSQL version 5.6</li>
        </ul>
        <p>Bug fixes in 2.1 is:
        <ul>
        <li><strong>Incorrect CRN in invocie</strong> - When set to client mode the CRN would always use invoice ID rather than client ID</li>
        </ul> 
        <p>New release in 2.0 is:
        <ul>
        <li><strong>WHMCS Global Search integration</strong> - you can now use the top search bar on WHMCS to search BPAY references numbers</li>
        <li><strong>Ability to search BPAY reference codes</strong> - You can actually search for clients with the complete BPAY reference code</li>
        <li><strong>Centrally control BPAY appearances in one place</strong> - Manage all locations BPAY can be seen on WHMCS is now easy to manage without needing to code anything.</li>
        <li><strong>BPAY system automated health check function</strong> - Never have issues with BPAY manager not be installed correctly again, with the new automated BPAY health checker</li>
        </ul>";
}

function getBillerName($billerCode){
    if(!is_array($billerCode))
        $billerCode = array('billerCode' => $billerCode);
    
    $billerCode = json_encode($billerCode);
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "https://www.bpay.com.au/CMSPages/WebService.asmx/GetBillerLongNameAndCheckDigitRule");
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $billerCode);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
    $data = curl_exec($ch);
    curl_close($ch);
    
    $data = json_decode($data);
    $data = explode(",", $data->d);
    if($data[0] == ""){
        return "No Biller Found";
    }else{
        return str_replace('"', "", $data[0]);
    }
}

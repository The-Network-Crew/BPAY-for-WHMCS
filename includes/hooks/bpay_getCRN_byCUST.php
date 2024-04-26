<?php

/**
 * Adds a BPAY CRN with a MOD10 version 5 check digit for client-related emails and returns it as a merge field.
 * https://stackoverflow.com/questions/11024309/luhncalc-and-bpay-mod10-version-5
 * 
 * Only configured to use Client ID - ie. never-changing reference (CRN).
 * For our usage, set to pad out to 7 digits - amend to suit you!
 * 
 * Then, in your Email Templates, simply use {$bpay_reference}
 * eg: Biller Code: 000 and CRN: {$bpay_reference}
 */

if (!defined('WHMCS')) {
    die('This file cannot be accessed directly');
}

use WHMCS\Database\Capsule;

/**
 * Adds a MOD10 version 5 check digit to the given number.
 * 
 * @param string $number The number to calculate the check digit for
 * @return string The number with its check digit
 */
function addMod10v5CheckDigit($number) {
    $number = preg_replace("/\D/", "", $number);

    // The seed number needs to be numeric
    if(!is_numeric($number)) return false;

    // Must be a positive number
    if($number <= 0) return false;

    // Get the length of the seed number
    $length = strlen($number);

    $total = 0;

    // For each character in seed number, sum the character multiplied by its one based array position (instead of normal PHP zero based numbering)
    for($i = 0; $i < $length; $i++) $total += $number[$i] * ($i + 1);

    // The check digit is the result of the sum total from above mod 10
    $checkdigit = fmod($total, 10);

    // Return the original seed plus the check digit
    return $number . $checkdigit;
}

add_hook('EmailPreSend', 1, function($vars) {
    // Initialise merge fields array
    $merge_fields = [];
    
    // Check if the email is related to a client and 'relid' is available
    if (isset($vars['relid'])) {
        // Assuming 'relid' might be an invoice ID, so fetch the user ID from the invoice
        $invoice = Capsule::table('tblinvoices')->where('id', $vars['relid'])->first();
        
        if ($invoice) {
            $clientID = $invoice->userid;  // Extract the client ID from the invoice
            
            // Pad the pre-check-digit CRN to 7 digits long
            $paddedClientID = str_pad($clientID, 7, "0", STR_PAD_LEFT);
            
            // Generate the check digit using the MOD10v5 algorithm
            $CRNwithCheckDigit = addMod10v5CheckDigit($paddedClientID);
            
            // Add the custom BPAY reference to the email template variables
            $merge_fields['bpay_reference'] = $CRNwithCheckDigit;
        }
    }
    
    return $merge_fields;
});

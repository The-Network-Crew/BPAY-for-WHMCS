<?php
// BPAY for WHMCS - v3.x - Reliable CRN Generation & Handling (this file: GENERATION BY CUSTOMER ID)
// 
// Adds a BPAY CRN with a MOD10 version 5 check digit for client-related emails and returns it as a merge field.
// You can then use the {$bpay_reference} merge field in WHMCS Admin > Email Templates, to render the CRN.
// 
// Config: Pick a file (Customer ID- or Invoice ID-based CRNs), then amend zero padding inline!
// 
// Ref: https://stackoverflow.com/questions/11024309/luhncalc-and-bpay-mod10-version-5
// Ref: https://developers.whmcs.com/hooks-reference/everything-else/#emailpresend

/*
    Copyright (C) The Network Crew Pty Ltd

    This program is free software: you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation, either version 3 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program.  If not, see <https://www.gnu.org/licenses/>.
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
    // Strip anything non-numeric from input
    $number = preg_replace("/\D/", "", $number);

    // The seed number needs to be numeric
    if(!is_numeric($number)) return false;

    // Must be a positive number
    if($number <= 0) return false;

    // Get the length of the seed
    $length = strlen($number);

    // For each character in seed, sum the character multiplied by its one based array position (instead of normal PHP zero based numbering)
    $total = 0;
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

    // Whether empty or filled, return to-spec
    return $merge_fields;
});

# BPAY for WHMCS (Module / Gateway) Australia

## WIP: This repo is being updated for PHPv7/8 & WHMCSv8.
For now, the 2019 release is available here, tagged v2.1.9.

## Requirements 
Below is a list of requirements to be met in order for BPAY Manager to work properly.
- PHP: v5.6 or greater
- WHMCS: v5.3 or greater 
- cURL: Must be enabled in php.ini
- Firewall: Need to have TCP/80+443 open

## Installation Instructions 
Below are the proper steps to Upload & Install the Module/Gateway.
1. Download the latest version of the BPAY Module for WHMCS from the GitHub repository.
2. Upload all the files into your WHMCS directory in the same file structure as set in the ZIP.
3. Once all files are uploaded, then go to your WHMCS Administrator area. Go to Setup tab => Addon Modules.
4. Look for “BPAY Manager” and click “Activate” Select "BPAY" from "Activate Module" and then click “Configure” and grant your user access to the module.
5. Finally, in the top blue menu in WHMCS click “Addons” and then select “BPAY Manager”.
6. An installation page will appear and once you have completed all steps of the installation.
7. You can start using BPAY right away for your existing invoices.

## Trouble-shooting
My BPAY image is not appearing on the invoice.
- Simply go to the BPAY Manager in your WHMCS and click the “info” tab and look for any errors that may be causing BPAY Manager to not work correctly.
- If all else fails, please raise an Issue on the GitHub Repo.

If you encounter any issue using the module with the version specifications listed above, please open an Issue.

# BPAY for WHMCS (Module / Gateway) Australia

## WIP: This repo is being updated for PHPv7/8 & WHMCSv8.
For now, the 2019 release is available here, tagged [v2.1.9](https://github.com/lsthompson/BPAY-for-WHMCS/releases/tag/v2.1.9)

## Module (System) Requirements 
Below is a list of requirements to be met in order for BPAY Manager to work properly.
- PHP: v5.6 or v7.2+
- WHMCS: v5.3 or greater 
- cURL: Must be enabled in php.ini
- Firewall: Need to have TCP/80+443 open

## Installation Instructions 
Below are the proper steps to Upload & Install the Module/Gateway.
1. Download the latest version of the BPAY Manager for WHMCS from the GitHub repository.
2. Upload all the files into your WHMCS directory in the same file structure as set in the ZIP.
3. Once all files are uploaded, then go to your WHMCS Administrator area. Go to Setup tab => Addon Modules.
4. Look for “BPAY Manager” and click “Activate” Select "BPAY" from "Activate Module" and then click “Configure” and grant your user access to the module.
5. Finally, in the top blue menu in WHMCS click “Addons” and then select “BPAY Manager” (per user group/s authorised).
6. The installer will appear, allowing you to configure the module as the bank specifies.
7. (You will be prompted to separately enable the Payment Gateway of BPAY as well)
7. You can start using BPAY right away for your existing invoices - done!

## Troubleshooting
My BPAY image is not appearing on the invoice.
- Simply go to the BPAY Manager in your WHMCS and click the “Health” tab and look for any errors that may be causing BPAY Manager to not work correctly.
- If all else fails, please raise an Issue on the GitHub Repo - making sure to detail the problem, including errors/logs, steps to reproduce, etc.

Something else not behaving? Check any Open Issues on GitHub.

## BPAY

BPAY and the BPAY logo are registered trade marks of BPAY Pty Ltd.

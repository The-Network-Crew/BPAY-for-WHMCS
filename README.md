# BPAY for WHMCS (Module / Gateway) BPAY Manager

## WIP: This repo is being updated to resolve long-standing bugs.
For now, the 2019 release is available here, tagged [v2.1.9](https://github.com/LEOPARD-host/BPAY-for-WHMCS/releases/tag/v2.1.9) (final version from RH).

We'll bump the version once some of the outstanding issues have been resolved.

## Downloading the latest version

NOTE: The master branch contains **improved code**, though also reports as v2.1.9!

So in the interim, please [download](https://github.com/LEOPARD-host/BPAY-for-WHMCS/archive/refs/heads/master.zip) the master ZIP and upload into your WHMCS site.

Rename: If upgrading, make sure you remove all legacy (_rh etc) files & directories.

_(the module name/directories/hook/etc were changed from bpay_rh to bpay_mgr)_

## Module (System) Requirements 
Below is a list of requirements to be met in order for BPAY Manager to work.
- PHP: v8.x (latest stable)
- WHMCS: v8.x (latest stable)
- ionCube: No requirement for it
- cURL: Must be enabled in php.ini
- Firewall: Need to have TCP/443 open
- SSL Certificate: WHMCS needs to use one

## Installation Instructions (WHMCS)
Below are the proper steps to Upload & Install the Module/Gateway.
1. Download the latest version of the BPAY Manager for WHMCS from the GitHub repository.
2. Upload all the files into your WHMCS directory in the same file structure as set in the ZIP.
3. Once all files are uploaded, then go to your WHMCS Administrator area. Go to Setup tab => Addon Modules.
4. Look for “BPAY Manager” and click “Activate” Select "BPAY" from "Activate Module" and then click “Configure” and grant your user access to the module.
5. Finally, in the top blue menu in WHMCS click “Addons” and then select “BPAY Manager” (per user group/s authorised).
6. The installer will appear, allowing you to configure the module as the bank specifies.
7. (You will be prompted to separately enable the Payment Gateway of BPAY as well)
7. You can start using BPAY right away for your existing invoices - done!

## Troubleshooting the Gateway Module
Problem: My biller code / reference number image is not appearing on the invoice.
- Go to the BPAY Manager in your WHMCS and click the “Health” tab and look for any errors that may be causing BPAY Manager to not work correctly.
- If all else fails, please raise an Issue on the GitHub Repo - making sure to detail the problem, including errors/logs, steps to reproduce, etc.

Something else not behaving? Check any [Open Issues](https://github.com/LEOPARD-host/BPAY-for-WHMCS/issues) on GitHub.

## BPAY

BPAY and the BPAY logo are registered trade marks of BPAY Pty Ltd.

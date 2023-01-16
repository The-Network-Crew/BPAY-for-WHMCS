# BPAY for WHMCS (Module / Gateway) BPAY Manager

**_Addon Module for WHMCS which adds BPAY Payment support, image generation, etc._**

### Module (System) Requirements 

Below is what the module needs in order to work properly.
- PHP: v8.x (latest stable)
- WHMCS: v8.x (latest stable)
- ionCube: No requirement for it
- cURL: Must be enabled in php.ini
- Firewall: Need to have TCP/443 open
- SSL Certificate: WHMCS needs to use one

### Installation Instructions (WHMCS)

Below are the proper steps to Upload & Install the Module/Gateway.
1. Download the latest version of the BPAY Manager for WHMCS from the GitHub repository.
2. Upload all the files into your WHMCS directory in the same file structure as set in the ZIP.
3. Once all files are uploaded, then go to your WHMCS Administrator area. Go to Setup tab => Addon Modules.
4. Look for “BPAY Manager” and click “Activate” Select "BPAY" from "Activate Module" and then click “Configure” and grant your user access to the module.
5. Finally, in the top blue menu in WHMCS click “Addons” and then select “BPAY Manager” (per user group/s authorised).
6. The installer will appear, allowing you to configure the module as the bank specifies.
7. (You will be prompted to separately enable the Payment Gateway of BPAY as well)
7. You can start using BPAY right away for your existing invoices - done!

### Upgrading from a legacy version ⚠️

Due to the module being renamed, it's important to make sure you upgrade properly.

1. Download the v2.1.9 **_and_** latest release and compare the file/folder structures
2. Based on that, delete & upload the old & new versions respectively, to WHMCS
3. Do a manual check to verify that all legacy (\_rh) files & folders are gone
4. Login to WHMCS Admin and check Settings > Addon Modules for its version #

Note: The SQL Tables weren't renamed, so the critical checking is file-only.

### Troubleshooting the Gateway Module

Problem: My biller code / reference number image is not appearing on the invoice.
- Go to the BPAY Manager in your WHMCS and click the “Health” tab and look for any errors that may be causing BPAY Manager to not work correctly.
- If all else fails, please raise an Issue on the GitHub Repo - making sure to detail the problem, including errors/logs, steps to reproduce, etc.

Something else not behaving? Check any [Open Issues](https://github.com/LEOPARD-host/BPAY-for-WHMCS/issues) on GitHub.

### BPAY

BPAY and the BPAY logo are registered trade marks of BPAY Pty Ltd.

#### [The Network Crew Pty Ltd](https://thenetworkcrew.com.au)

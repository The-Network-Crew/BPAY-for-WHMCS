# BPAY for WHMCS (Module / Gateway) BPAY Manager

**_Addon Module for WHMCS which adds BPAY Payment support, image generation, etc._**

### 🎯 Module (System) Requirements 🎯

Below is what the module needs in order to work properly.
- PHP: v8.x (latest stable)
- WHMCS: v8.x (latest stable)
- ionCube: No requirement for it
- cURL: Must be enabled in php.ini
- Firewall: Need to have TCP/443 open
- SSL Certificate: WHMCS needs to use one

### ✅ Installation Instructions (WHMCS) ✅

Below are the proper steps to Upload & Install the Module/Gateway.
1. Download the latest version of the BPAY Manager for WHMCS from the GitHub repo.
2. Upload all the files into your WHMCS directory in the same hierarchy as set in the ZIP.
3. Once all files are uploaded, then go to your WHMCS Admin area. Go to Setup -> Addon Modules.
4. Find “BPAY Manager”, click “Activate”, then click “Configure” & grant admin access to the module.
5. Finally, in the top blue menu in WHMCS click “Addons” and then select “BPAY Manager” (per the ACL).
6. The installer will appear, allowing you to configure the module as the bank specifies.
7. (You will be prompted to separately enable the Payment Gateway of BPAY as well)
8. You can start using BPAY right away for your existing invoices - done!

### 🐛 Troubleshooting the Gateway Module 🐛

Problem: My biller code / reference number image is not appearing in the PDF file.
- Go to the BPAY Manager in your WHMCS and click the “Health” tab and check for errors.
- Ensure BPAY code is in /templates/your_template/invoicepdf.tpl AND /templates/invoicepdf.tpl
- If all else fails, please raise an Issue on the repo - detailing steps, error, logs, etc - thanks!

Problem: Every time I update my WHMCS installation (core), the Invoice PDFs stop having the image.
- This occurs due to the invoicepdf.tpl file/s being over-written. Same when using Clean PDF, etc.
- You can use the reinject_template_code flag (see Advanced Operation below) to re-inject the code.

Something else not behaving? Check any [Open Issues](https://github.com/LEOPARD-host/BPAY-for-WHMCS/issues) on GitHub.

### ⚠️ Upgrading from a legacy version ⚠️

Due to the module being renamed, it's important to make sure you upgrade properly.

1. Download the v2.1.9 **_and_** latest release and compare the file/folder structures
2. From the Admin Area, Deactivate the Legacy Module Version (all vers =< v2.1.9)
3. Having checked the file structures, delete old files and upload the new ones
4. Do a manual check to verify that all legacy (\_rh) files & folders are gone
5. Also within the Admin Area, go to Addon Modules and activate the new ver!

### ⚙️ Advanced Operation (Flags) ⚙️

##### Bypass initializion without needing to install the BPAY Manager again.
URL: https://{whmcs_admin_url}/addonmodules.php?module=bpay_mgr&initialise_record_bypass=1

##### Reinject the Invoice PDF template code without needing to reinitialise.
URL: https://{whmcs_admin_url}/addonmodules.php?module=bpay_mgr&reinject_template_code=1

### ™️ BPAY Trade Marks ™️

BPAY and the BPAY logo are registered trade marks of BPAY Pty Ltd.

#### [The Network Crew Pty Ltd](https://thenetworkcrew.com.au)

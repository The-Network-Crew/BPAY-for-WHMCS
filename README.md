# BPAY for WHMCS (v3.x)

Revised and simplified implementation of the previous BPAY Manager for WHMCS.

## What's different to v2?

Everything.

**Any prior v2 installation should be uninstalled.** Then you can add the v3 hook easily.

This is just a hook file (pick one: Client ID based or Invoice ID based) for a merge field.

So you add the file you'd like, and then amend padding to suit your needs (pre-check-digit). Done!

## Which file do I use?

This depends on your BPAY Biller Code configuration.

It may be generated based on Client ID or Invoice ID.

Many prefer the static CID-based approach: less hassle.

## What needs configuring?

Potentially nothing, likely 1 thing - CRN padding.

Just the number of digits that should be made up, pre-check-digit. 

This is in the file, on the below line (pre-check-digit = 7 chars):

    $paddedClientID = str_pad($clientID, 7, "0", STR_PAD_LEFT);

## How do I use it in Email Templates?

Once you've added 1x file (only) based on your generation method, amend your Invoice-related templates.

Add the below merge field, which will give you just the BPAY CRN:

    {$bpay_reference}

So for instance, your section might go like this:

> How to pay us via BPAY:
>
> - Biller Code: 000
> - CRN: {$bpay_reference}

## Anything else to know?

Not really. It's just a MOD10v5 calc, with padding added to ensure it conforms to your Biller Code.

## (BPAY) Trade Mark clarity ™

BPAY and the BPAY logo are registered trade marks of BPAY Pty Ltd.

## (Corporate Sites) TNC & Merlot Digital

**The Network Crew Pty Ltd :: https://tnc.works

**Merlot Digital** :: https://merlot.digital

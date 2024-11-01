=== Plugin Name ===
Contributors: 2cpIT
Tags: woocommerce,payment,tpro3,tpro,intacct,payment gateway,level 2 data,level 3 data
Requires at least: 3.0.1
Tested up to: 4.9.8
Stable tag: 4.9.8
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Payment plugin for woocommerce using TPro3 by 2C Processor USA, LLC ( 2CP ). Seemlessly syncs data to Intacct.

== Description ==

This plugin is an addon for WooCommerce to implement a payment gateway method for accepting Credit Cards Payments 
by merchants using 2C Processor's TransactionPro3 (TPro3) payment gateway. Order data will be sent to TPro 3
allowing credit card transactions to be settled with full level 3 invoice data.

If you are using Intacct as your financial management and accounting software solution, TPro3 seemlessly syncs
Customers, Contacts, and Sales Documents into Intacct. Orders taken through WooCommerce will be availble in 
Intacct's order entry module within 15 minutes.


== Installation ==

1. upload the plugin using the Add New menu item in the menubar.

== Changelog ==

= 1.2.0 -
* Fixed bug in Sales Document (Invoice) amount. It was pulling the extended amount instead of the item amount.
= 1.1.5 =
* Bug fixes.

= 1.1.4 =
* Fixed bug with Canadian transactions.

= 1.1.3 =
* Fixed bug that removed inventory if no response was received from api.

= 1.1.2 =
* Fixed bug in shipping and billing contact records.

= 1.1.1 =
* Fixed bug in stored account creation.

= 1.1.0 =
* Added woosubscription support.

= 1.0.10 =
* Added the ability to not syncronize description and sku.

= 1.0.9 =
* Fixed bug in the account name drop down when only one account exists.

= 1.0.8 =
* Added the ability to push customer name into custom field
* Changed the way accounts are handled

= 1.0.5 =
* Fixed error message for failed to create sales document.

= 1.0.4 =
* Added the ability to set a default shipping class so products will be available for purchase without editing.
* Fixed bug in contact name for both billing and shipping

= 1.0.3 =
* Bug fixes
* Added support to select multiple categories to pull products
* Changed account selection to a drop down instead of text box entry
* Added sync schedule

= 1.0.2 =
* Added syncronizing of products and inventory

= 1.0.1 =
* Bug fixes
* Added checkbox for creation of registered and guest customers

= 1.0.0 =
* Created plugin to perform credit card transaction
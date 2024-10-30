Metrepay
Contributors: feragome
Tags: metrepay, payment
Requires at least: 5.3
Tested up to: 6.6.2
Stable tag: 1.3.0
Requires PHP: 7.3.5
License: GPLv3
License URI: https://www.gnu.org/licenses/gpl-3.0.txt

Metrepay's plugin to make online payments through credit and debit cards in Paraguay.

== Frequently Asked Questions ==

= How can I sign up my account to use Metrepay? =

Please send us an email to info@metrepay.com.

== Changelog ==

= 1.0.0 =
* First version with unique and recurrent payment support.

= 1.1.0 =
* Add specific logic to configuration form field related to activation of recurrent payments.
* In order to the value of this parameter, products must have specific field "cuotas" to use recurrent payments.

= 1.1.1 =
* Fix texts related to payment process.

= 1.1.2 =
* Improve texts into configuration form fields.

= 1.2.0 =
* Remove use of iframe to process payment.
* Update correctly the order status to "completed".

= 1.3.0 =
* Add configuration parameter to set preferred MetrePay instance.
* Add parameter to use (or not) configured site currency.
* Remove "staging" configuration parameter.
* Fix URL generation considering site permalink to pos-checkout page "pago-metrepay".
* Fix checkout box to view options: "Single payment" or "Recurrent payment".
* Optimize API request body generation.
# Port25 PowerMTA bounce handler for Interspire and MailWizz

___
:beer: **Please support me**: Although all my software is free, it is always appreciated if you can support my efforts on Github with a [contribution via Paypal](https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=ZRP5WBD8CT8EW) - this allows me to write cool projects like this in my personal time and hopefully help you or your business. 
___

## Overview
Transactional- and promotional mail to our customers is an important mechanism to stay in touch and hopefully improve revenue due to the value and information we provide in our communication with customers.

But let's be honest: Bounce handling and keeping your mail-lists is tough. Free solutions do not seem to exist and professional solutions/services are expensive. I have never really understood this, as bounce-handling is generally quite easy:
* You send emails to a number of customers
* Some emails will soft-bounce (full mailbox) and you will transition those eventually into hard-bounces
* Hard-bounces are mails we will never be able to deliver (mailbox does not exist, domain does not exist etc)
 
Once our MTA (mail transfer agent) such as PowerMTA records a hard-bounce we need to remove the recipient email from our system as it not just wastes resources attempting to to send mail to a non-existant email, it will eventually hurt sender reputation.

The big challenge comes in that campaign tools (especially when hosted in-house) either require "dedicated bounce email accounts" or require software development skills to write code to integrate with the campaign APIs to manage unsubscribes.

This Port25 PowerMTA bounce handler addresses this as it can run standalone (you provide a CSV) or it can integrate with PowerMTA `acct-file` pipe feature and unsubscribes via API calls to MailWizz, Interspire and any other provider in near-realtime.

:warning: I run OS X and Linux and I have not tested any of it on Windows. I suppose that Windows will play nice with PHP, but if you find any issues, please log it and I will fix with your help.

## Installation
The installation is quite simple:
* You need PHP and any version from 5.5 upwards should be fine. My code will work with just a base PHP installation
* Download the project and if you use PowerMTA create a directory `bouncehandler` under `/opt/pmta/` so that Port25 can execute it.
* Adjust settings in setup.php
 
### setup.php
All configuration is controlled within `setup.php` and the following options can be adjusted:

**DEFAULT OPTIONS**:
* __LOG_CONSOLE_MODE__: Set to `1` to log to the console. If you leave it at `0` it will log to `/var/log/pmta/pmta-bounce-handler.log` (or if the file can not be written, it will create `pmta-bounce-handler.log` in the directory where the script is run)
* __LOG_FILE__: The logfile you want to recourd the bounce-processing.
* __$bounceCategories__: An array of bounce-category strings. This is only used when processing PowerMTA accounting files. The default is ```$bounceCategories = array("bad-mailbox","bad-domain","routing-errors","inactive-mailbox");```. If the array is empty, all records are processed as bounces. ***CAREFUL****: PowerMTA also records transient/soft-bounces and you would inadvertently unsubscribe users due to temporary delivery errors (i.e. mailbox full).

**INTERSPIRE CONFIGURATION**:
If you do not use Interspire, leave constants as undefined:
* __INTERSPIRE_API_KEY__: The Interspire API key
* __INTERSPIRE_ENDPOINT_URL__: The URL to the `xml.php`, typically `http://www.example.com/xml.php`
* __INTERSPIRE_USER_ID__: The userid (default is `admin`)
* __$origInterspire__: An array of sender email-addresses from which you send campaigns via Interspire. This setting only applies when we process PowerMTA accounting records and need to determine which emails bounced via Interspire.
 
**MAILWIZZ CONFIGURATION**
If you do not use MailWizz, leave constants as undefined:
* __MAILWIZZ_API_PUBLIC_KEY__: The public API key - this can be found in the customer zone of MailWizz
* __MAILWIZZ_API_PRIVATE_KEY__: The private API key
* __MAILWIZZ_ENDPOINT_URL__: The URL to the API, typically `http://www.example.com/api`
* __$origMailWizzZA__:  An array of sender email-addresses from which you send campaigns via MailWizz. This setting only applies when we process PowerMTA accounting records and need to determine which emails bounced via MailWizz.

## About Bounce processing
For me a mail-bounce means that we will never, ever be able to deliver to the recipient again for various reasons (mailbox does not exist, domain does not exist etc). There are many cases where free-mail (such as Nokia Mail) cease to exist and in such cases the recipient should be unsubscribed from **ALL CONTACT LISTS**. Email marketers freak out about such "bold" move, but to be honest if an email bounces in one contact list, it is pointless to hold on to that subscription in other contact lists.

My approach is that if a delivery permanently fails, we unsubscribe the recipient from all systems. On our transactional enviroment (where users continue to log in with their non-functioning email-address/userid) we show the user a notice that we had delivery issues and ask the user to reconfirm his email. This is an elegant and efficient way to reconfirm email details.


## About Interspire bounce processing
Interspire allows the management of multiple contact lists which are used for various campaigns. Naturally an email-recipient can be subscribed to one or more contact lists.

When we bounce an email-recipient in Interspire, we subscribe the email-recipient from all contact-lists, irrespective from where the bounce originates from. Interspire does not allow a "bulk-unsubscribe via email-address" and we have to run several API calls to unsubscribe a single recipient.

:warning: We cache the Interspire contact lists upon startup. In a future release I will adjust this to periodically refresh the cache to avoid a scenario where newly created lists are not refreshed.

## About MailWizz bounce processing
MailWizz functions similar to Interspire and we also unsubscribe an email-recipient from all contact lists.




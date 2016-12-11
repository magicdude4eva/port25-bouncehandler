# Port25 PowerMTA bounce handler for Interspire and MailWizz

[![](https://img.shields.io/badge/PayPal--ffffff.svg?style=social&logo=data%3Aimage%2Fpng%3Bbase64%2CiVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAYAAAAf8%2F9hAAAABHNCSVQICAgIfAhkiAAAAZZJREFUOI3Fkb1PFFEUxX%2F3zcAMswFCw0KQr1BZSKUQYijMFibGkhj9D4zYYAuU0NtZSIiNzRZGamqD%2BhdoJR%2FGhBCTHZ11Pt%2B1GIiEnY0hFNzkFu%2FmnHPPPQ%2Buu%2BTiYGjy0ZPa5N1t0SI5m6mITeP4%2B%2FGP%2Fbccvto8j3cuCsQTSy%2FCzLkdxqkXpoUXJoUXJrkfFTLMwHiDYLrFz897Z3jT6ckdBwsiYDMo0tNOIGuBqS%2Beh7sdAkU2g%2BkBFGkd%2FrtSgD8Z%2BrBxj68MAGG1A9efRhVsXrKMU7Y4cNyGOwtDU28OtrqdUMetldvzFKxCYSHJ4NsJ%2BnRJGexHba7VJ%2FTff4BaQFBjVcbqIEZ1bESYn4PRUcHx2N952awUkOHZedUcWm14%2FtjqjREHawUEsgx6Ajg5%2Bsi7jWqBwA%2BmIrXlo9YHUVTmEP%2F6hOO1Ofiyy3pjo%2BsvBDX%2FZpSakhz4BqvQDvdYvrXQEXZViI5rPpBEOwR2l16vtN7bd9SN3L1WXj%2BjGSnN38rq%2B7VL8xXQOdDF%2F0KvXn8BlbuY%2FvUAHysAAAAASUVORK5CYII%3D)][paypal]
[paypal]: https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=ZRP5WBD8CT8EW
___
:beer: **Please support me**: Although all my software is free, it is always appreciated if you can support my efforts on Github with a [contribution via Paypal][paypal] - this allows me to write cool projects like this in my personal time and hopefully help you or your business. 
___

## TL;DR - Features
* Requires PHP5.6 and PHPMailer (optional is RRD for graphing)
* Integrates with MailWizz, Interspire and provides functionality to plug in your own handler
* With Port25 PowerMTA you get the following real-time functionality
 * Uses PowerMTA accounting pipes to unsubscribe when a bounce occurs
 * Uses PowerMTA feedback loop processing and unsubscribes recipients as FBL complaints arrive
 * Uses PowerMTA domain pipe handling to handle List-Unsubscribes

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

# Standalone processing
You can manage bulk-unsubscribes via standalone. The only pre-requisite is a CSV file which contains an email-address in the first column of the file.

:warning: With Standalone processing we will **always** unsubscribe from all configured providers, so make sure that your CSV file is correct. We do however log from which lists a recipient is unsubscribed, so in case something goes wrong, you can find out which addresses are affected.

**Command-line options**
* __--debug__: Turns on console mode and does not log to file - useful for debugging
* __--logfile=/var/log/pmta/bounce-handler.log__: Full path to the log-file. If run via Port25, the user `pmta` needs to have write-access to the file.


To run the standalone processing you simply pipe the CSV file into the bounce-handler:
```
cat bounce.csv | /usr/bin/php ./bouncehandler/bouncehandler.php
```

This will then output progress into the console or log-file:
```
[29/May/2016:09:46:44] Port25 PowerMTA bounce-handler
[29/May/2016:09:46:44] (C) 2016 Gerd Naschenweng  http://github.com/magicdude4eva
[29/May/2016:09:46:44] ------------------------------------------------------------------
[29/May/2016:09:46:44] Handling bounce categories=bad-mailbox,bad-domain,routing-errors,inactive-mailbox
[29/May/2016:09:46:44] Bounce-provider: Interspire, initialising
[29/May/2016:09:46:44]    Endpoint-URL=http://interspire.example.com/xml.php
[29/May/2016:09:46:45]    Interspire enabled with lists=112,3,32,95,81,108,109,115,116,117
[29/May/2016:09:46:45] Bounce-provider: Interspire, complete
[29/May/2016:09:46:45] Bounce-provider: MailWizz, initialising
[29/May/2016:09:46:45]    Endpoint-URL=http://mailwizz.example.com/api
[29/May/2016:09:46:45]    MailWizz enabled!
[29/May/2016:09:46:45] Bounce-provider: MailWizz, complete
[29/May/2016:09:46:45] Starting bounce processing
[29/May/2016:11:22:09] Starting bounce processing
[29/May/2016:11:22:10]   MailWizz: unsubscribe for XXX@domain.com:
[29/May/2016:11:22:10]    - skipped: A Mailwizz list #1
[29/May/2016:11:22:10]    - skipped: A Mailwizz list #2
[29/May/2016:11:22:11]   Interspire: Skipping recipient XXX@domain.com - no subscribed lists returned
....
[29/May/2016:11:22:12] Completed bounce processing! Total records=4, processed=4, skipped=0
```

The Standalone processing can also be used to process PowerMTA files without using the `acct-file`-pipe-processing. It is perhaps something you should look at before automating it to test that integration works and that your bounce file is correct.

Running a PowerMTA file in standalone processing is the same command as above:
```
cat pmta-bounce-file.csv | /usr/bin/php ./bouncehandler/bouncehandler.php --debug
```

Note that if your setup does not follow the recommendations for the `acct-file` and your columns are in different sequence, you will have to change the `PORT25_OFFSET_*`-constants in `bouncehandler.php`.

The processing of a PowerMTA file is quite similar, with the only addition that the record and bounce-category is also logged:

```
...
[29/May/2016:11:28:04] Bounce: bad-domain from=mailwizz@campaign.com via vmta-my-mta01/XXX@domain.com
[29/May/2016:11:28:06]   MailWizz: Skipping XXX@domain.com, already unsubscribed!
[29/May/2016:11:28:06] Bounce: bad-mailbox from=mailwizz@campaign.com via vmta-my-mta01/YYY@anotherdomain.com
[29/May/2016:11:28:06]   MailWizz: unsubscribe for YYY@anotherdomain.com:
[29/May/2016:11:28:06]    - skipped: MailWizz list name
[29/May/2016:11:28:06] Bounce: bad-domain from=interspire@campaign.com via vmta-my-mta01/WWW@domain.com
[29/May/2016:11:28:07]   Interspire: Skipping recipient WWW@domain.com - no subscribed lists returned
...
```

# Port25 processing
:warning: I can not help you with configuration/setup issues when it comes to PowerMTA. I have extensive experience, but each setup varies, although the basic principles remain the same.

If you do not follow the default setup below, I suggest you read the PowerMTA user-guide (specifically: `3.3.7 Accounting Directives` and `11. The Accounting and Statistics`).

## Process manually first
My suggestion is that you first start off with manual processing, by just adding the following section to your /etc/pmta/config file.
```
<acct-file /var/log/pmta/bounce.csv>
    delete-after 60d
    move-interval 5m
    max-size 500M
    records b
    record-fields b timeQueued,bounceCat,vmta,orig,rcpt,srcMta,dlvSourceIp,jobId,dsnStatus,dsnMta,dsnDiag
</acct-file>
```

This will generate an accounting file for just bounced records and will look something like this:
```
type,timeQueued,bounceCat,vmta,orig,rcpt,srcMta,dlvSourceIp,jobId,dsnStatus,dsnMta
b,2016-05-29 01:10:51+0200,bad-domain,vmta-mymta04,mailwizz@example.com,XXX@domain.com,sourcemta.domain.com (0.0.0.0),1.1.1.1,,5.1.2 (bad destination system: no such domain),
b,2016-05-29 01:10:23+0200,bad-mailbox,vmta-mymta03,mailwizz@example.com,YYY@anotherdomain.com,sourcemta.domain.com (0.0.0.0),1.1.1.1,,5.1.1 (bad destination mailbox address),mx1.destindomain.com (2.2.2.2)
```

Our bounce-processing relies on the sequence of record-fields in the bounce-file and if you change the order, you will need to change the index of those fields in the constants `PORT25_OFFSET_*` as defined in `bouncehandler.php`:

PowerMTA field | mapped record | Description
------------ | ------------- | -------------
type | bounceRecord[0] | type - always b
timeQueued | bounceRecord[1] | Time message was queued to disk
bounceCat | bounceRecord[2] | likely category of the bounce (see Section 1.5), following the recipient which it refers
vmta | bounceRecord[3] | VirtualMTA selected for this message, if any
orig | bounceRecord[4] | originator (from MAIL FROM:<x>)
rcpt | bounceRecord[5] | recipient (RCPT TO:<x>) being reported
srcMta | bounceRecord[6] | source from which the message was received. the MTA name (from the HELO/EHLO command) for messages received through SMTP
dlvSourceIp | bounceRecord[7] | local IP address PowerMTA used for delivery
jobId | bounceRecord[8] | job ID for the message, if any
dsnStatus | bounceRecord[9] | DSN status for the recipient to which it refers
dsnMta | bounceRecord[10] | DSN remote MTA for the recipient to which it refers
dsnDiag | bounceRecord[11] | DSN diagnostic string for the recpient to which it refers

You can then run the bounce-processing manually:
```
cat /var/log/pmta/bounce-2016-05-29-0000.csv | /usr/bin/php /opt/pmta/bouncehandler/bouncehandler.php --debug
```

Once you are comfortable with this you can then switch into automatic processing.

## Automatic processing
Switching to automatic processing is quite simple, you adjust your current record to the following:
```
<acct-file |/usr/bin/php /opt/pmta/bouncehandler/bouncehandler.php>
    records b
    record-fields b timeQueued,bounceCat,vmta,orig,rcpt,srcMta,dlvSourceIp,jobId,dsnStatus,dsnMta,dsnDiag
</acct-file>
```

Ensure that the bouncehandler logs the startup-messages in it's log-file. If this does not happen, then PowerMTA is not able to run the PHP due to possible permission errors (ownership of the /opt/pmta/bouncehandler folder or no executable permissions of the `bouncehandler.php`).

# Port25 feedback loop processing
Port25 is capable of processing feedback loop (FBL) reports. In our case we have automated the FBL processing, where Port25 receives the FBL report, then pipes it into our bouncehandler.php which then calls a feedback-loop processor. We automatically remove any reported email from all systems and notify our Postmaster team via email.

![Port25 FBL notification](https://raw.githubusercontent.com/magicdude4eva/port25-bouncehandler/master/port25-fbl-notice.png)

For the setup to work, the following is required:

- Create a FBL domain `fbl.example.com`

- Create a MX record for `fbl.example.com` which points to your Port25 server - i.e. `fbl.example.com MX 1 mailserver.example.com`

- Configure the `feedback-loop-processor` in Port25 and list any addresses you accept for FBL reports:
```
<feedback-loop-processor>
    deliver-unmatched-email no
    deliver-matched-email no # default: no 
    forward-errors-to postmaster@example.com
    forward-unmatched-to postmaster@example.com

    <address-list>
      address /abuse@fbl.example.com/
    </address-list>
</feedback-loop-processor>
```
- Configure for which domains / addresses you allow inbound mail:
```
relay-domain fbl.bidorbuy.co.za
```
- Configure Port25 to write FBL requests into a fbl-CCYY-MM-DD-####.csv as well as a pipe-handler which accepts FBL records (note that we use `--logfile` to write to a different log-file:
```
<acct-file /var/log/pmta/fbl.csv>
    records feedback-loop
    map-header-to-field f header_X-HmXmrOriginalRecipient rcpt  # hotmail recipient
    record-fields f *, header_subject, header_BatchId, header_Message-Id, header_List-Unsubscribe, header_List-Id, header_X-Mw-Subscriber-Uid, header_X-Mailer-LID, header_X-Mailer-RecptId
</acct-file>

<acct-file |/usr/bin/php /opt/pmta/bouncehandler/bouncehandler.php --logfile=/var/log/pmta/fbl-processor.log>
    records feedback-loop
    map-header-to-field f header_X-HmXmrOriginalRecipient rcpt  # hotmail recipient
    record-fields f *, header_subject, header_BatchId, header_Message-Id, header_List-Unsubscribe, header_List-Id, header_X-Mw-Subscriber-Uid, header_X-Mailer-LID, header_X-Mailer-RecptId
</acct-file>
```
- Adjust the `feedback-loop-processor.php` to according to your requirements
- Register your address `abuse@fbl.example.com` with the various FBL lists [Word To The Wise - ISP Summary Information](http://wiki.wordtothewise.com/ISP_Summary_Information)

## About the Port25 FBL record fields
In addition to the standard FBL fields, we write out the following fields:

* __header_X-HmXmrOriginalRecipient__: Outlook places the original recipient into a customer header, we map this back to `rcpt`
* __header_subject__: We log the subject as it allows to identify the nature of the emil
* __header_BatchId__: Typically the `x-job-id`
* __header_Message-Id__: The unique message-id
* __header_List-Unsubscribe__: The unsubscribe link - this might be useful to just post a HTTP-GET from Port25
* __header_List-Id__: The `List-Id` - most mailers such as MailWizz use the standard header
* __header_X-Mw-Subscriber-Uid__: The MailWizz Subscriber-UID. We use this to unsubscribe users from MailWizz
* __header_X-Mailer-LID__: The list-id provided by Interspire
* __header_X-Mailer-RecptId__: The Subscriber-UID provided by Interspire
 
Some FBL providers (such as OpenSRS/ReturnPath) will anonymise the recipient-email address and in those cases we need to use the Subscriber-ID/List-ID to unsubscribe the user.

# Port25 List-Unsubscribe handling
The unsubscribe handling currently only supports MailWizz, but can be extended to anything else. In order for MailWizz to work, you will need install and configure the `mailwizz-unsubscribe-extension`.

![Port25 List-Unsubscribe notification](https://raw.githubusercontent.com/magicdude4eva/port25-bouncehandler/master/port25-list-unsubscribe-notice.png)

Additionally, you need to make the following DNS and Port25 changes:

- Create a FBL domain `fbl-unsub.example.com`

- Create a MX record for `fbl-unsub.example.com` which points to your Port25 server - i.e. `fbl-unsub.example.com MX 1 mailserver.example.com`

- Configure the `fbl-unsub.example.com` domain in Port25 to process unsubscribes:
```
relay-domain fbl-unsub.example.com

<domain fbl-unsub.example.com>
  type pipe
  command "/usr/bin/php /opt/pmta/bouncehandler/unsubhandler.php --logfile=/var/log/pmta/unsubhandler.log --from=""$from"" --to=""$to"""
</domain>
```

## How it works
The MailWizz extension modifies the `List-Unsubscribe` to includes mailto and HTTP tags to allow providers such as Google, Microsoft, Yahoo to offer their users an automatic unsubscribe without leaving the email client:

```
List-Unsubscribe:
 <mailto:[SUBSCRIBER_UID].[LIST_UID].[CAMPAIGN_UID]@fbl-unsub.example.com?subject=unsubscribe>,
 <http://YOURMAILWIZZDOMAIN.COM/lists/[LIST_UID]/unsubscribe/[CAMPAIGN_UID]/[SUBSCRIBER_UID]/unsubscribe-direct?source=email-client-unsubscribe-button>
 ```
 
 When a user clicks on the "Unsubscribe link", the mail-client will send an email to the `mailto` address. Port25 (via the configuration of the MX record) will accept the email, extract the `from` and `to` details and then invokce the `unsubhandler.php` piping the content of the email received into the handler.
 
 The `unsubhandler.php` then extracts the subscriber-UID and list-UID from the `to` address and then calls the MailWizz API to unsubscribe the user. Once a unsubscribe was successful, an email is sent to you (you will need to adjust `unsubhandler.php` to change this).
 
 If you do not use MailWizz and generate your own List-Unsubscribe headers, I found the sequence of `mailto` (first) and `http` (second) to be very important. Google outright refuses to process unsubscribe requests if `mailto` is not first.
 
 ## IMPORTANT: DKIM signing of additional headers
 If you are not using DKIM to sign your emails, you should revisit your email sender practises. DKIM is *absolutely* necessary for Google Postmaster Tools to function and both `Feedback-ID` and `List-Unsubscribe` need to be signed.
 
 I have placed default options into a wild-card domain so that it applies to all recipients (also note the optimistic TLS for encrypted outbound mail):
 
 ```
 <domain *>
 ...

  dkim-sign                                        yes    # DKIM signing on messages
  dkim-algorithm                            rsa-sha256    # recommended by RFC4871, default rsa-sha1
  dkim-body-canon                               simple    # recommended by DKIMCore (default relaxed)
  dkim-headers            Feedback-ID,List-Unsubscribe    # additionally sign the Feedback-ID header

  use-starttls                                     yes    # Specifies whether PowerMTA should use the STARTTLS extension
  require-starttls                                  no    # We use optimistic TLS - i.e. only use it if the recipient supports it

...
</domain>
 ```

## Installation troubleshooting
The configuration described above is running on a vanilla Centos7 installation with the [Webtatic PHP repository](https://webtatic.com/projects/yum-repository/) and our current version is PHP 5.6.24.

From user feedback so far I noticed the following issues:

### Wrong permissions / ownership of /opt/pmta/bouncehandler
The bouncehandler receives piped data via Port25 and as such needs to run as the same user as Port25. Typically PowerMTA runs as user `pmta`, so make sure to set the following permissions:
```
chown -R pmta:pmta /opt/pmta/bouncehandler
```

### Wrong path to PHP
On CentOS7, PHP resides in `/usr/bin/php` and the above configuration is based on that. I have seen some customer configurations where PHP resides in `/usr/local/bin/php` and you would have to adjust the Port25 configuration to reflect that.


## RRD configuration
In a Linux environment, RRD is easy to configure and we use it to log unsubscribes and stats - this is pretty much experimental at the moment.

If you do not need it, just remove the following line from `setup.php`:
```
// RRD Graphs - requires installation of php-rrdtool - if not defined, it will not be enabled
define("RRD_FILE",        "/var/log/pmta/pmta.rrd");
```

The installation of RRD varies depending on your distribution. In our case of CentOS 7, it was as simple as:
```
yum install rrdtool-devel php56w-devel php56w-pear
```

In some environments you will need to either additionally install `php-pecl-rrd` or `php-rrdtool`

Once done, you have to make the following change in your `/etc/php.ini`
```
; Used for Port25 RRD reporting
extension=rrd.so
```

## Our mail- / campaign-environment
I have been asked a number of times what infrastructure we are running, and while I can not go into all the specifics, any decent Linux admin will be able to replicate it:
* Everything is FOSS with the exception of PowertMTA
* We run CentOS 7 in a XEN environment (our own "cloud solution")
* We have a dedciated AfriNIC IP range and dedicate IP blocks for system-, transactional- and promotional mails
* We send CAN-SPAM compliant and use TLS for mail
* All in-house servers use a local Postfix installation which relays to Port25. This is great, as Postfix will queue mail in case of maintenance on Port25
* Port25 runs on a dedicated 4-core/12GB virtual server
* We run the port25-bouncehandler in production with a few small customisations (mostly real-time reporting and daily/weekly MTA reports (`pmtastats` is just an awesome tool for this).
* We also use some custom reporting scripts with the RRD data we generate

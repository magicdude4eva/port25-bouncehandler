#!/usr/bin/php -q
<?php
 /*
 * 
 * bouncehandler.php | MailWizz / PowerMTA / Webhook bounce handler
 * Copyright (c) 2016 Gerd Naschenweng / bidorbuy.co.za
 * 
 * The MIT License (MIT)
 *
 * @author Gerd Naschenweng <gerd@naschenweng.info>
 * @link http://www.naschenweng.info/
 * @copyright 2016 Gerd Naschenweng  http://github.com/magicdude4eva
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 * SOFTWARE.
 *
 */

/**
 * Notes: 
 * - Configure your API keys in setup.php
 *
 * - For MailWizz 
 *   = download MailWizz SDK from https://github.com/twisted1919/mailwizz-php-sdk/tree/master/MailWizzApi
 *   = copy the MailWizzAPI directory into the same providers directory
 *
 * - In Port25 add the following accounting record:
   <acct-file | /usr/bin/php /opt/pmta/bouncehandler/bouncehandler.php>
      records b
      type,timeQueued,bounceCat,vmta,orig,rcpt,srcMta,dlvSourceIp,jobId,dsnStatus,dsnMta,dsnDiag
    </acct-file>
    
    Within code we will refer to the record as follows:
    type          = bounceRecord[0]   = type - always b
    timeQueued    = bounceRecord[1]   = Time message was queued to disk
    bounceCat     = bounceRecord[2]   = likely category of the bounce (see Section 1.5), following the recipient which it refers
    vmta          = bounceRecord[3]   = VirtualMTA selected for this message, if any
    orig          = bounceRecord[4]   = originator (from MAIL FROM:<x>)
    rcpt          = bounceRecord[5]   = recipient (RCPT TO:<x>) being reported
    srcMta        = bounceRecord[6]   = source from which the message was received. the MTA name (from the HELO/EHLO command) for messages received through SMTP
    dlvSourceIp   = bounceRecord[7]   = local IP address PowerMTA used for delivery
    jobId         = bounceRecord[8]   = job ID for the message, if any
    dsnStatus     = bounceRecord[9]   = DSN status for the recipient to which it refers
    dsnMta        = bounceRecord[10]  = DSN remote MTA for the recipient to which it refers
    dsnDiag       = bounceRecord[11]  = DSN diagnostic string for the recpient to which it refers
 * 
 *  USAGE - Port25 PowerMTA
 *  - Simply create the directory /opt/pmta/bouncehandler and make sure that PMTA has execute permissions
 *  - Configure the the acct-file as per instructions above
 *
 *  USAGE - Standalone processing
 *  - Provide a simple CSV with an email address per line and call via "cat bounce.csv | /usr/bin/php /opt/pmta/bouncehandler/bouncehandler.php"
 *  - NOTE: The email-address will be unsubscribed from all configured providers
 *
 *  LOGGING
 *  - If you set 'LOG_CONSOLE_MODE' to '1' all output goes to console.
 *  - If 'LOG_CONSOLE_MODE' is set to '0' logging goes to /var/log/pmta/pmta-bounce-handler.log (or the current directory if the file can not be written)
 */
 
// Bounce-Record offsets: Only adjust the offsets below if you create a different accounting file
define("PORT25_OFFSET_BOUNCE_BOUNCE_CAT",         2); // bounceCat
define("PORT25_OFFSET_BOUNCE_VMTA",               3); // vmta
define("PORT25_OFFSET_BOUNCE_SOURCE_EMAIL",       4); // orig
define("PORT25_OFFSET_BOUNCE_RECIPIENT",          5); // rcpt

// Feedback Loop Record offsets: Only adjust the offsets below if you create a different accounting file
define("PORT25_OFFSET_FEEDBACK_SOURCE_EMAIL",     7); // orig
define("PORT25_OFFSET_FEEDBACK_RECIPIENT",        8); // rcp


// Initialise Setup and configuration
// Adjust the setup.php accordingly
require_once dirname(__FILE__) . '/setup.php';


// ------------------------------------------------------------------------------------------------------
$totalRecords = 0;
$totalRecordsSkipped = 0;
$totalRecordsProcessed = 0;
// We read from standard in and expect a CSV
$log->lwrite('Starting bounce processing');

while(( $bounceRecord = fgetcsv(STDIN,4096)) !== FALSE ) {

  ++$totalRecords;

  // If the line is empty or it is the header line, we skip
  if (!$bounceRecord || $bounceRecord[0]=="type") {
    ++$totalRecordsSkipped;
    continue;
  }
	
  $STANDALONE_MODE = false;$BOUNCE_MODE = false;$FEEDBACK_LOOP_MODE = false;
  $recipient = null;
	
  // Let's check if this is standalone mode - i.e. we get a CSV and the first column is the email address to handle as the bounce
  // If the first record is not an email we assume it is a bounce record
  if (filter_var($bounceRecord[0], FILTER_VALIDATE_EMAIL)) {
    $STANDALONE_MODE = true;$BOUNCE_MODE = true;
    $recipient = $bounceRecord[0];
  }

  // This is a bounce record
  if ($STANDALONE_MODE == false && $bounceRecord[0]=="b" && filter_var($bounceRecord[PORT25_OFFSET_BOUNCE_RECIPIENT], FILTER_VALIDATE_EMAIL)) {
    $recipient = $bounceRecord[PORT25_OFFSET_BOUNCE_RECIPIENT];
    $BOUNCE_MODE = true;
  }

  // This is a feedback loop record
  if ($STANDALONE_MODE == false && $bounceRecord[0]=="f" && filter_var($bounceRecord[PORT25_OFFSET_FEEDBACK_RECIPIENT], FILTER_VALIDATE_EMAIL)) {
    $recipient = $bounceRecord[PORT25_OFFSET_FEEDBACK_RECIPIENT];
    $FEEDBACK_LOOP_MODE = true;
  }

  // Only handle valid bounce categories. We skip any bounce-category which does not match
  if ($STANDALONE_MODE == false && $BOUNCE_MODE == true && !in_array($bounceRecord[PORT25_OFFSET_BOUNCE_BOUNCE_CAT], $bounceCategories)) {
    ++$totalRecordsSkipped;
    continue;
  }
	
  // Invalid/skipped record
  if (is_null($recipient) || empty($recipient) || !filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
    ++$totalRecordsSkipped;
    continue;
  }
	
  // In Standalone mode, we unsubscribe bounces from all systems
  if ($STANDALONE_MODE == true) {
    MailWizz_unsubscribeRecipient($recipient);
    Interspire_unsubscribeRecipient($recipient);
    ++$totalRecordsProcessed;
    continue;
  }
  
  // In Feedback mode, handle feedback record
  if ($FEEDBACK_LOOP_MODE == true) {
    // feedbackLoopEvent($recipient,$bounceRecord); - your own FBL processor
    ++$totalRecordsProcessed;
    continue;
  }
	
  // The section below is purely for Port25 pipe-processing
  $log->lwrite('Bounce: ' . $bounceRecord[PORT25_OFFSET_BOUNCE_BOUNCE_CAT] . ' from=' . $bounceRecord[PORT25_OFFSET_BOUNCE_SOURCE_EMAIL] . ' via ' . $bounceRecord[PORT25_OFFSET_BOUNCE_VMTA] . '/' . $bounceRecord[PORT25_OFFSET_BOUNCE_RECIPIENT]);

  // If we have a transactional match, call the transactional webhook
  if (in_array($bounceRecord[PORT25_OFFSET_BOUNCE_SOURCE_EMAIL], $origTransactional)) {
    //Transactional_unsubscribeRecipient($recipient, $bounceRecord); - your own transactinal processor
    ++$totalRecordsProcessed;
    continue;
  }
	
  // Handle MailWizz bounces
  if (in_array($bounceRecord[PORT25_OFFSET_BOUNCE_SOURCE_EMAIL], $origMailWizzZA)) {
    MailWizz_unsubscribeRecipient($recipient);
    ++$totalRecordsProcessed;
    continue;
  }
	
  // Handle Interspire bounces
  if (in_array($bounceRecord[PORT25_OFFSET_BOUNCE_SOURCE_EMAIL], $origInterspire)) {
    Interspire_unsubscribeRecipient($recipient);
    ++$totalRecordsProcessed;
    continue;
  }

}

$log->lwrite('Completed bounce processing! Total records=' . $totalRecords . ', processed=' . $totalRecordsProcessed . ', skipped=' . $totalRecordsSkipped);

// close log file
$log->lclose();

die();

?>

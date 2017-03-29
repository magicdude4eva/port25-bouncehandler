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

 */
 
// Initialise Setup and configuration
// Adjust the setup.php accordingly
require_once dirname(__FILE__) . '/setup.php';


// ------------------------------------------------------------------------------------------------------
// Main programme
$log->lwrite('------------------------------------------------------------------');
$log->lwrite('Port25 PowerMTA unsubscribe-handler');
$log->lwrite('(C) 2016 Gerd Naschenweng  http://github.com/magicdude4eva');
$log->lwrite('------------------------------------------------------------------');

// ------------------------------------------------------------------------------------------------------
// Initialise bounce providers
require_once dirname(__FILE__) . '/providers/bounce-provider-mailwizz.php';

// ------------------------------------------------------------------------------------------------------
// **** Initialise Feedback Loop Provider
require_once('PHPMailer/PHPMailerAutoload.php');

// ------------------------------------------------------------------------------------------------------
// Initialise reporting class
if (defined('RRD_FILE') && RRD_FILE) {
  $reportingInterface = new BounceReporting(RRD_FILE);
}

// ------------------------------------------------------------------------------------------------------
// Process the arguments provided by Port25
$UNSUBSCRIBE_HANDLER_FROM = "";
$UNSUBSCRIBE_HANDLER_TO = "";

$options = getopt("ft::", array("from::", "to::"));
if (!empty($options)) {
  foreach (array_keys($options) as $option) {
    switch ($option) {
      case 'f':
      case 'from':
        $UNSUBSCRIBE_HANDLER_FROM=$options[$option];
        break;
      case 't':
      case 'to':
        $UNSUBSCRIBE_HANDLER_TO=$options[$option];
        break;
    }
  }
}

// We just read the buffer and do nothing with it as Port25 needs to have the pipe emptied
$UNSUBSCRIBE_DATA = "";
$DEBUG_DATA = "";
$SPLIT_HEADERS = true;

while($data = fgets(STDIN)) {

  $DEBUG_DATA .= $data;

  // We only split headers if we need to
  if ($SPLIT_HEADERS) {
    // look out for special headers
    if (preg_match("/^Return-Path: (.*)/", $data, $matches)) {
      $UNSUBSCRIBE_DATA .= $matches[0] . "\n";
      continue;
    }
    if (preg_match("/^To: (.*)/", $data, $matches)) {
      $UNSUBSCRIBE_DATA .= $matches[0] . "\n";
      continue;
    }
    if (preg_match("/^From: (.*)/", $data, $matches)) {
      $UNSUBSCRIBE_DATA .= $matches[0] . "\n";

      // In some cases we get Postmaster reporting the issue, we will then use the From-address from the mail as the reporter
      if (preg_match("/^(postmaster@outlook.com)/", $UNSUBSCRIBE_HANDLER_FROM, $match_from)) {
        preg_match('/[\\w\\.\\-+=*_]*@[\\w\\.\\-+=*_]*/', $matches[1], $regs);
        $UNSUBSCRIBE_HANDLER_FROM = $regs[0];
      }
      continue;
    }
    if (preg_match("/^Date: (.*)/", $data, $matches)) {
      $UNSUBSCRIBE_DATA .= $matches[0] . "\n";
      continue;
    }
    if (preg_match("/^Message-ID: (.*)/", $data, $matches)) {
      $UNSUBSCRIBE_DATA .= $matches[0] . "\n";
      continue;
    }
    if (preg_match("/^Subject: (.*)/", $data, $matches)) {
      $UNSUBSCRIBE_DATA .= $matches[0] . "\n";
      continue;
    }
    if (preg_match("/^X-OriginatorOrg: (.*)/", $data, $matches)) {
      $UNSUBSCRIBE_DATA .= $matches[0] . "\n";
      continue;
    }
  } else {
    // not a header, but message
    $UNSUBSCRIBE_DATA .= $data . "\n";
  }

  if (trim($data)=="") {
    // empty line, header section has ended
    if ($SPLIT_HEADERS) {
      $UNSUBSCRIBE_DATA .= "\nMessage body:\n-----------------------\n";
    }
    $SPLIT_HEADERS = false;
  }

}

  $log->lwrite(' Received: 
---------------------------------------------
' . $DEBUG_DATA . '
---------------------------------------------');


// Process the unsubscribe request
if (!is_null($UNSUBSCRIBE_HANDLER_TO) && !empty($UNSUBSCRIBE_HANDLER_TO) && filter_var($UNSUBSCRIBE_HANDLER_TO, FILTER_VALIDATE_EMAIL)) {
  $log->lwrite('* Unsubscribe request from ' . $UNSUBSCRIBE_HANDLER_FROM . ' for ' . $UNSUBSCRIBE_HANDLER_TO);
  
  // We get the to-address as:
  // [SUBSCRIBERID].[LIST_UID].[CAMPAIGN_UID]@fbl-unsub.bidorbuy.co.za
  preg_match('/(.*)\.(.*)\.(.*)@fbl-unsub\.bidorbuy\.co\.za/', $UNSUBSCRIBE_HANDLER_TO, $regs);
  
  if (!is_null($regs) && !empty($regs) && sizeof($regs) == 4) {
    $unsubscribe = MailWizz_unsubscribeSubscriberUIDFromListUID($regs[1], $regs[2]);
    
    if ($unsubscribe[0] == true) {
      $reportingInterface->logReportRecord("bounces", 1);
      sendUnsubscribeEmailNotification($UNSUBSCRIBE_HANDLER_FROM, $regs[1], $regs[2], $regs[3], htmlspecialchars($UNSUBSCRIBE_DATA, ENT_QUOTES));
    } 
  } else {
    $log->lwrite('   Unsubscribe address not in correct format!');
  }
} else {
  $log->lwrite('* Invalid Unsubscribe request from "' . $UNSUBSCRIBE_HANDLER_FROM . '" for "' . $UNSUBSCRIBE_HANDLER_TO. '"');
}

$log->lwrite('* Done processing');

// close log file
$log->lclose();

// close stats file
$statsfile->lclose();


die();


// ========================================================================================================
// Send Unsubscribe notification
function sendUnsubscribeEmailNotification($recipient, $subscriberUID, $listUID, $campaignUID, $unsubscribeEmailData) { 
  global $log, $statsfile, $LOG_STATS_FILE_ONLY;

  $campaignData = MailWizz_getCampaignListId($campaignUID);

  // Write stats file record
  if ($LOG_STATS_FILE_ONLY == true) {
    // DATE, Unsub, Recipient, FBL-Source, Campaign-UID, Subject, CampaignName
    if ($campaignData[0] == true) {
      $statsfile->lwrite(",\"Unsub\",\"" . $recipient . "\",\"direct\",\"" . $campaignUID . "\",\"" . $campaignData[5] . "\",\"" . $campaignData[4] . "\"");
    } else {
      $statsfile->lwrite(",\"Unsub\",\"" . $recipient . "\",\"direct\",\"" . $campaignUID . "\",,");
    }
    return;
  }
  
  // Send the email using PHPMailer
  $mail = new PHPMailer();
  $mail->IsSMTP();                                      // set mailer to use SMTP
  $mail->SMTPAuth = false;     // turn on SMTP authentication
  $mail->IsHTML(true);
  $mail->Host = "localhost";  // specify main and backup server
  $mail->From = "postmaster@YOURDOMAIN.COM";
  $mail->FromName = "Port25 List-Unsubscribe";
  $mail->AddAddress("postmaster@YOURDOMAIN.COM", "bidorbuy Postmaster");
  $mail->AddReplyTo("postmaster@YOURDOMAIN.COM", "bidorbuy Postmaster");
  $mail->Subject = "Port25: Unsubscribe request received";
  
  $mail->Body = '<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML+RDFa 1.1//EN" "http://www.w3.org/MarkUp/DTD/xhtml-rdfa-2.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:v="http://rdf.data-vocabulary.org/#" lang="en" xml:lang="en" dir="ltr" xmlns:og="http://ogp.me/ns#" >
<head><title>Port25 - List-Unsubscribe request</title></head>
    <body><h2>Port25 - List-Unsubscribe request reported via service-provider</h2>'
  . '<p>Port25 processed the following user complaint via List-Unsubscribe:</p>'
  . '<table rules="all" style="border-color: #666;border: 1px;width: 50%;" cellpadding="10">'
  . '<tr style="background: #eee"><td style="width:20%"><strong>Reporter:</strong> </td><td style="width:70%"><strong><a href="https://www.bidorbuy.co.za/adminjsp/useradmin/UserSearch.jsp?mode=query&OrderBy=Alias&orderDirection=asc&column2=alias-partial&column1=alias-userid-emailAddress&value1=' . $recipient . '">' . $recipient . '</a></strong></td></tr>';

  if ($campaignData[0] == true) {
    $mail->Body .= '<tr><td nowrap style="white-space:nowrap"><strong>Campaign Name:</strong></td><td nowrap style="white-space:nowrap"><strong><a href="https://hermes.bidorbuy.co.za/customer/campaigns/' . $campaignUID . '/overview">' . $campaignData[4] . '</a></strong></td></tr>'
      . '<tr><td nowrap style="white-space:nowrap"><strong>Campaign subject:</strong> </td><td nowrap style="white-space:nowrap">' .  $campaignData[5] . '</td></tr>'
      . '<tr><td nowrap style="white-space:nowrap"><strong>Send to list:</strong> </td><td nowrap style="white-space:nowrap"><a href="https://hermes.bidorbuy.co.za/customer/lists/' . $listUID . '/overview">' . $campaignData[2] . '</a></td></tr>'
      . '<tr><td nowrap style="white-space:nowrap"><strong>Send at:</strong> </td><td nowrap style="white-space:nowrap">' .  $campaignData[6] . '</td></tr>'
      . '<tr><td nowrap style="white-space:nowrap"><strong>List subscriber count:</strong> </td><td nowrap style="white-space:nowrap">' .  $campaignData[3] . '</td></tr>'
      ;
  }
  
  $mail->Body .= '</table>
<br/><br/>
<h2>Email received from service provider</h2>
<hr>
<pre>
' . $unsubscribeEmailData . '
</pre>
<br/>
<hr>
</body></html>
  ';
  
  if (!$mail->Send()) {
    $log->lwrite('FBL record processed! Email notification failed: ' . $mail->ErrorInfo);
  }
  
}


?>

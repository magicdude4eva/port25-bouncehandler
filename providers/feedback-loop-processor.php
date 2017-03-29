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
 
$log->lwrite('Feedback-provider: initialising');

// ------------------------------------------------------------------------------------------------------
// **** Initialise Feedback Loop Provider
require_once('PHPMailer/PHPMailerAutoload.php');

$log->lwrite('Feedback-provider: complete');


// ========================================================================================================
// Handle Feedback Loop Event
function feedbackLoopEvent($recipient, $feedbackLoopRecord) { 
  global $log, $statsfile, $LOG_STATS_FILE_ONLY;
  
  $reportAgent = $feedbackLoopRecord[5];
  $senderEmail = $feedbackLoopRecord[7];

  // For bounce records we sometimes get RFC emails (i.e. "<name@outlook.com>") and need to just strip out the email
  preg_match('/[\\w\\.\\-+=*_]*@[\\w\\.\\-+=*_]*/', $feedbackLoopRecord[12], $regs);

  if (!is_null($regs) && !empty($regs) && filter_var($regs[0], FILTER_VALIDATE_EMAIL)) {
    $senderEmail = $regs[0];
  }

  // In case the report agent is emtpy and this is a Microsoft JMRP report, we construct our own agent
  if ((is_null($reportAgent) || empty($reportAgent)) && $feedbackLoopRecord[4] == 'jmrp') {
    $reportAgent = "Microsoft JMRP/" . $feedbackLoopRecord[11];
  }
  
  $log->lwrite('FBL received from: ' . $reportAgent . ' for=' .  $feedbackLoopRecord[8] . ' via ' . $feedbackLoopRecord[8]);
  
  // We check if we have the MailWizz header "List-Id" and "X-Mw-Subscriber-Uid" in the FBL, then we change the recipient
  if (array_key_exists(20, $feedbackLoopRecord) && !is_null($feedbackLoopRecord[20]) && !empty($feedbackLoopRecord[20]) &&
      array_key_exists(21, $feedbackLoopRecord) && !is_null($feedbackLoopRecord[21]) && !empty($feedbackLoopRecord[21])) {
    $subscriberEmail = MailWizz_getSubscriber($feedbackLoopRecord[20], $feedbackLoopRecord[21]);
    
    if ($subscriberEmail[0] == true) {
      $log->lwrite('*** FBL record provided list-id=' . $feedbackLoopRecord[20] . ' and subscriberid=' . $feedbackLoopRecord[21] . ", using=" . $subscriberEmail[1]);
      $recipient = $subscriberEmail[1];
    }
  }
    
  $unsub_mailwizz   = MailWizz_unsubscribeRecipient($recipient);

  $unsub_interspire = Interspire_unsubscribeRecipient($recipient);

  // Write stats file record
  if ($LOG_STATS_FILE_ONLY == true) {
    // DATE, Abuse, Recipient, FBL-Source, Campaign-UID, Subject, CampaignName
    $statsfile->lwrite(",\"Abuse\",\"" . $recipient . "\",\"" . $reportAgent . "\",,\"" . $feedbackLoopRecord[15] . "\",");

    $log->lwrite('Written stats-file record="Abuse","' . $recipient . '","' . $reportAgent . '",,"' . $feedbackLoopRecord[15] . '"');

    return;
  }

  $unsubstatus = "<span title='" . $unsub_mailwizz[1] . "'>MailWizz=" . ($unsub_mailwizz[0] == true ? "OK":"Check") . "</span>"
    . ", <span title='" . $unsub_interspire[1] . "'>Interspire=" . ($unsub_interspire[0] == true ? "OK":"Check") . "</span>";

  // Send the email using PHPMailer
  $mail = new PHPMailer();
  $mail->IsSMTP();                                      // set mailer to use SMTP
  $mail->Host = "localhost";  // specify main and backup server
  $mail->SMTPAuth = false;     // turn off SMTP authentication

  $mail->From = "postmaster@example.com";
  $mail->FromName = "Port25 FBL";
  $mail->AddAddress("postmaster@example.com", "Example.com Postmaster");
  $mail->AddReplyTo("postmaster@example.com", "Example.com Postmaster");
  $mail->IsHTML(true);

  $mail->Subject = "Port25: Email abuse report processed";

  $mail->Body = '<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML+RDFa 1.1//EN" "http://www.w3.org/MarkUp/DTD/xhtml-rdfa-2.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:v="http://rdf.data-vocabulary.org/#" lang="en" xml:lang="en" dir="ltr" xmlns:og="http://ogp.me/ns#" >
<head><title>Port25 - Feedback Loop report</title></head>
    <body><h2>Port25 - Feedback Loop</h2>'
    . '<p>Port25 processed the following feedback loop complaint and the user was unsubscribed:</p>'
    . '<table rules="all" style="border-color: #666;border: 1px;width: 50%;" cellpadding="10">'
    . '<tr style="background: #eee"><td style="width:20%"><strong>Reporter:</strong> </td><td style="width:70%"><strong>' . $recipient . '</strong>'
    . (strcmp($recipient, $feedbackLoopRecord[8]) !== 0 ? " / " . $feedbackLoopRecord[8] : "") . '</td></tr>'    
    . '<tr><td><strong>Received:</strong> </td><td>' . $feedbackLoopRecord[1]  . '</td></tr>'
    . '<tr><td><strong>Reported via:</strong> </td><td>' . $reportAgent . '</td></tr>'
    . '<tr><td><strong>Reported IP:</strong> </td><td>' . $feedbackLoopRecord[2]  . '</td></tr>'
  . '<tr><td><strong>Sender:</strong> </td><td>' . $senderEmail . '</td></tr>'
    . '<tr><td><strong>Feedback ID:</strong> </td><td>' . $feedbackLoopRecord[14]  . '</td></tr>'
    . '<tr><td><strong>Subject:</strong> </td><td>' . $feedbackLoopRecord[15]  . '</td></tr>'
    . '<tr><td><strong>Message Id:</strong> </td><td><pre>' . rtrim(ltrim($feedbackLoopRecord[18], '<'), '>')  . '</pre></td></tr>'
    . '<tr><td><strong>Unsubscribe status:</strong> </td><td><pre>' . $unsubstatus . '</pre></td></tr>'
    . '<tr><td><strong>List unsubscribe:</strong> </td><td>' . rtrim(ltrim($feedbackLoopRecord[19], '<'), '>')  . '</td></tr>'
  . '</table>
<br/><br/>
</body></html>
  ';
  
  if (!$mail->Send()) {
    $log->lwrite('FBL record processed! Email notification failed: ' . $mail->ErrorInfo);
  } else {
    $log->lwrite('FBL record processed and email sent!');
  }
}

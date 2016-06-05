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
  global $log;

  $log->lwrite('FBL received from: ' . $feedbackLoopRecord[1] . ' for=' .  $feedbackLoopRecord[8] . ' via ' . $feedbackLoopRecord[8]);
  
  // Handle your unsubscribe processing here
  //Transactional_unsubscribeRecipient($recipient, $feedbackLoopRecord);    
  MailWizz_unsubscribeRecipient($recipient);
  Interspire_unsubscribeRecipient($recipient);

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
  $mail->Body    = "This is the HTML message body <b>in bold!</b>";

  $mail->Body = '<h2>Port25 - Feedback Loop</h2>'
    . '<p>Port25 processed the following feedback loop complaint and the user was unsubscribed:</p>'
    . '<table rules="all" style="border-color: #666;border: 1px;width: 50%;" cellpadding="10">'
    . '<tr style="background: #eee"><td style="width:20%"><strong>Reporter:</strong> </td><td style="width:70%"><strong>' . $feedbackLoopRecord[8] . '</strong></td></tr>'
    . '<tr><td><strong>Received:</strong> </td><td>' . $feedbackLoopRecord[1]  . '</td></tr>'
    . '<tr><td><strong>Reported via:</strong> </td><td>' . $feedbackLoopRecord[5]  . '</td></tr>'
    . '<tr><td><strong>Reported IP:</strong> </td><td>' . $feedbackLoopRecord[2]  . '</td></tr>'
    . '<tr><td><strong>Sender:</strong> </td><td>' . $feedbackLoopRecord[7]  . '</td></tr>'
    . '<tr><td><strong>Feedback ID:</strong> </td><td>' . $feedbackLoopRecord[14]  . '</td></tr>'
    . '<tr><td><strong>Subject:</strong> </td><td>' . $feedbackLoopRecord[15]  . '</td></tr>'
    . '<tr><td><strong>Message Id:</strong> </td><td><pre>' . rtrim(ltrim($feedbackLoopRecord[18], '<'), '>')  . '</pre></td></tr>'
    . '<tr><td><strong>List unsubscribe:</strong> </td><td>' . rtrim(ltrim($feedbackLoopRecord[19], '<'), '>')  . '</td></tr>'
    . '</table>';

  if (!$mail->Send()) {
    $log->lwrite('FBL record processed! Email notification failed: ' . $mail->ErrorInfo);
  } else {
    $log->lwrite('FBL record processed and email sent!');
  }


}

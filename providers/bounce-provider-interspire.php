<?php
 /*
 *
 * bouncehandler.php | MailWizz / PowerMTA / Webhook bounce handler
 * Copyright (c) 2016-2017 Gerd Naschenweng / bidorbuy.co.za
 *
 * The MIT License (MIT)
 *
 * @author Gerd Naschenweng <gerd@naschenweng.info>
 * @link https://www.naschenweng.info/
 * @copyright 2016-2017 Gerd Naschenweng  https://github.com/magicdude4eva
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

$log->lwrite('Bounce-provider: Interspire, initialising');

// ------------------------------------------------------------------------------------------------------
// **** Initialise Interspire
$apiInterspireListIDs = [];
$INTERSPIRE_HANDLER_ENABLED = false;
$INTERSPIRE_LIST_CHECK_ENABLED = false;
$INTERSPIRE_LIST_TTL = 600; // in seconds
$INTERSPIRE_LAST_LIST_REFRESH = microtime(true);

if (defined('INTERSPIRE_API_KEY') && INTERSPIRE_API_KEY &&
    defined('INTERSPIRE_ENDPOINT_URL') && INTERSPIRE_ENDPOINT_URL &&
    defined('INTERSPIRE_USER_ID') && INTERSPIRE_USER_ID) {
  $log->lwrite('   Endpoint-URL=' . INTERSPIRE_ENDPOINT_URL);
  if (!BounceUtility::testEndpointURL(INTERSPIRE_ENDPOINT_URL)) {
    return;
  }

  $apiInterspireListIDs = implode(',', Interspire_getLists());

  if (is_null($apiInterspireListIDs) || empty($apiInterspireListIDs)) {
    $log->lwrite('   Skipping Interspire, no contact-lists returned');
    $INTERSPIRE_LIST_CHECK_ENABLED = true;
  } else {
    $log->lwrite('   Interspire enabled with lists=' . $apiInterspireListIDs);
    $INTERSPIRE_HANDLER_ENABLED = true;
  }
} else {
  $log->lwrite('   Skipped - not configured!');
  return;
}

$log->lwrite('Bounce-provider: Interspire, complete');



// ========================================================================================================
// INTERSPIRE FUNCTIONS
// Handle the unsubscription of a recipient
function Interspire_unsubscribeRecipient($recipient) {
  global $log, $apiInterspireListIDs, $reportingInterface, $INTERSPIRE_HANDLER_ENABLED, $INTERSPIRE_LIST_CHECK_ENABLED, $INTERSPIRE_LIST_TTL, $INTERSPIRE_LAST_LIST_REFRESH;

  if ($INTERSPIRE_HANDLER_ENABLED == false) {
    return array(false, "Interspire not enabled! Check logs!");
  }

  // Let's check the last time we refreshed the list
  $timediff = microtime(true) - $INTERSPIRE_LAST_LIST_REFRESH;

  if ($timediff > $INTERSPIRE_LIST_TTL) {
    $INTERSPIRE_LIST_CHECK_ENABLED = true;
  }

  // We will refresh the Interspire lists
  if ($INTERSPIRE_LIST_CHECK_ENABLED == true) {
    $tempListIDs = implode(',', Interspire_getLists());
    $INTERSPIRE_LAST_LIST_REFRESH = microtime(true);

    if (!is_null($tempListIDs) && !empty($tempListIDs)) {
      $log->lwrite('   Interspire refreshed lists=' . $apiInterspireListIDs);
      $apiInterspireListIDs = $tempListIDs;
      $INTERSPIRE_LIST_CHECK_ENABLED = false;
      $INTERSPIRE_HANDLER_ENABLED = true;
    } else {
      $log->lwrite('   Interspire refreshing lists failed! Using old lists=' . $apiInterspireListIDs);
    }
  }

  // Get the interspire lists
  if (is_null($apiInterspireListIDs) || empty($apiInterspireListIDs)) {
    $apiInterspireListIDs = implode(',', Interspire_getLists());
  }

  if (is_null($apiInterspireListIDs) || empty($apiInterspireListIDs)) {
    $log->lwrite('   Interspire: Unable to unsubscribe user ' . $recipient . ', Interspire lists are empy!');
    return array(false, "Interspire lists are empty");
  }

  // Get all lists for email recipient
  $emailLists = Interspire_getAllListsForEmailAddress($recipient);

  if (is_null($emailLists) || empty($emailLists)) {
    $log->lwrite('   Interspire: Skipping recipient ' . $recipient . ' - no subscribed lists returned');
    return array(true, "Skipped - not subscribed");
  }
  $unsubMessage = "";

  // Iterate through users lists and unsubscribe
  $unsubCounter = 0;

  foreach ($emailLists as $listid) {
    $unsubStatus = Interspire_unsubscribeSubscriber($recipient, $listid);
    $log->lwrite('   Interspire: Unsubscribe user ' . $recipient . ' from list=' . $listid . ', status=' . $unsubStatus);

    if ($unsubStatus == "OK") {
      ++$unsubCounter;
    }
    $unsubMessage .= "List-" . $listid . "=" . $unsubStatus . ",";
  }

  // Log the unsubscribe count into RRD
  if ($unsubCounter > 0) {
    $reportingInterface->logReportRecord("bounce_interspire", $unsubCounter);
  }

  return array(true, $unsubMessage);
}

// Get Interspire lists - this is needed to individually unsubscribe users
function Interspire_getLists() {
  global $log;

  $xml = '<xmlrequest><username>' . INTERSPIRE_USER_ID . '</username>
    <usertoken>' . INTERSPIRE_API_KEY . '</usertoken>
    <requesttype>lists</requesttype>
    <requestmethod>GetLists</requestmethod>
    <details>
    </details>
    </xmlrequest>';

  $ch = curl_init(INTERSPIRE_ENDPOINT_URL);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
  curl_setopt($ch, CURLOPT_POST, 1);
  curl_setopt($ch, CURLOPT_TIMEOUT, ENDPOINT_TIMEOUT);
  curl_setopt($ch, CURLOPT_POSTFIELDS, $xml);
  $result = curl_exec($ch);	curl_close ($ch);

  if ($result === false || is_null($result) || empty($result)) {
    $log->lwrite('  Interspire: Can not access Interspire to get Interspire Lists!');
    return (array) null;
  }

  $response = (array) simplexml_load_string($result);
  if ($response['status'] == 'SUCCESS') {
    $listids = [];
    foreach($response['data'] as $list) {
      $listids[] = (string) $list->listid;
    }
    return (array) $listids;
  }

  $log->lwrite('  Interspire: Can not access Interspire to get Interspire Lists, error is: ' . $response['errormessage']);
  return (array) null;
}

// Post generic XML data to Interspire
function Interspire_postXMLData($xml) {
  global $log;

  $ch = curl_init(INTERSPIRE_ENDPOINT_URL);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
  curl_setopt($ch, CURLOPT_POST, 1);
  curl_setopt($ch, CURLOPT_TIMEOUT, ENDPOINT_TIMEOUT);
  curl_setopt($ch, CURLOPT_POSTFIELDS, $xml);
  $result = curl_exec($ch);
  curl_close ($ch);

  if ($result === false || is_null($result) || empty($result))
    return null;

  $xml_doc = simplexml_load_string($result);

  /** @noinspection PhpUndefinedFieldInspection */
  if ($xml_doc->status == 'SUCCESS')
    return 'OK';

  /** @noinspection PhpUndefinedFieldInspection */
  return $xml_doc->errormessage->__toString();
}

// Get all lists for a recipient
function Interspire_getAllListsForEmailAddress($email) {
  global $log, $apiInterspireListIDs;

  $xml = '<xmlrequest>
    <username>' . INTERSPIRE_USER_ID . '</username>
    <usertoken>' . INTERSPIRE_API_KEY . '</usertoken>
    <requesttype>subscribers</requesttype>
    <requestmethod>GetAllListsForEmailAddress</requestmethod>
    <details>
    <email>' . $email . '</email>
    <listids>' . $apiInterspireListIDs . '</listids>
    </details>
    </xmlrequest>';
  $ch = curl_init(INTERSPIRE_ENDPOINT_URL);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
  curl_setopt($ch, CURLOPT_POST, 1);
  curl_setopt($ch, CURLOPT_TIMEOUT, ENDPOINT_TIMEOUT);
  curl_setopt($ch, CURLOPT_POSTFIELDS, $xml);
  $result = curl_exec($ch);	curl_close ($ch);

  if ($result === false || is_null($result) || empty($result))
    return null;

  $xml_doc = simplexml_load_string($result);

  if ((string) $xml_doc->status == 'FAILED')
    return null;

  $list_ids = [];
  /** @noinspection PhpUndefinedFieldInspection */
  foreach ($xml_doc->data->item as $data) {
    $list_ids[] = (string) $data->listid;
  }
  return (array) $list_ids;
}

// Unsubscribe a recipient
function Interspire_unsubscribeSubscriber($email, $list_id = 1) {
  global $log;

  $xml = '<xmlrequest>
    <username>' . INTERSPIRE_USER_ID . '</username>
    <usertoken>' . INTERSPIRE_API_KEY . '</usertoken>
    <requesttype>subscribers</requesttype>
    <requestmethod>UnsubscribeSubscriber</requestmethod>
    <details>
    <emailaddress>' . $email . '</emailaddress>
    <listid>' . $list_id . '</listid>
    </details>
    </xmlrequest>';

    return Interspire_postXMLData($xml);
}

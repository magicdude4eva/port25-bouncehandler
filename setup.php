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

// Logging configuration. 1=log to console / 0=log to file
define("LOG_CONSOLE_MODE",          0);
define("LOG_FILE",                  "/var/log/pmta/pmta-bounce-handler.log");

// Handle the following bounce-categories only
// Leave empty to handle all bounce-categories
$bounceCategories = array("bad-mailbox","bad-domain","routing-errors","inactive-mailbox");


// ------------------------------------------------------------------------------------------------------
// INTERSPIRE BOUNCE CONFIGURATION - leave empty/undefined if not needed
define("INTERSPIRE_API_KEY",        "MY_API_KEY");
define("INTERSPIRE_ENDPOINT_URL",   "http://interspire.domain.com/xml.php");
define("INTERSPIRE_USER_ID",        "admin");

// Define which from-addresses should be handled for the bounces
$origInterspire = array("campaigns@interspire.domain.com");

// ------------------------------------------------------------------------------------------------------
// MAILWIZZ BOUNCE CONFIGURATION - leave empty/undefined if not needed
define("MAILWIZZ_API_PUBLIC_KEY",   "MY_PUBLIC_KEY");
define("MAILWIZZ_API_PRIVATE_KEY",  "MY_PRIVATE_KEY");
define("MAILWIZZ_ENDPOINT_URL",     "http://mailwizz.domain.com/api");

// Define which from-addresses should be handled for the bounces
$origMailWizzZA = array("campaign@mailwizz.com", "campaign2@mailwizz.com");

// ------------------------------------------------------------------------------------------------------
// TRANSACTIONAL BOUNCE CONFIGURATION - leave empty/undefined if not needed
// Define which from-addresses should be handled for the bounces
$origTransactional = array("");



// Timeout for webhook curl calls
define("ENDPOINT_TIMEOUT",          30);

// ------------------------------------------------------------------------------------------------------
// You should not have to touch anything below
// Use UTC as default
date_default_timezone_set('UTC');
  
// Logging class initialization
$log = new Logging();
$log->ldebug(LOG_CONSOLE_MODE);
$log->lfile(LOG_FILE);

$log->lwrite('------------------------------------------------------------------');
$log->lwrite('Port25 PowerMTA bounce-handler');
$log->lwrite('(C) 2016 Gerd Naschenweng  http://github.com/magicdude4eva');
$log->lwrite('------------------------------------------------------------------');
$log->lwrite('Handling bounce categories=' . (is_null($bounceCategories) || empty($bounceCategories) ? 'all records' : implode(',', $bounceCategories)));

// ------------------------------------------------------------------------------------------------------
// Initialise bounce providers
require_once dirname(__FILE__) . '/providers/bounce-provider-interspire.php';
require_once dirname(__FILE__) . '/providers/bounce-provider-mailwizz.php';


// ========================================================================================================
// LOGGING CLASS
class Logging {
    // declare log file and file pointer as private properties
    private $log_file, $fp, $debug = 0;
    // set log file (path and name)
    public function lfile($path) {
        $this->log_file = $path;
    }
    public function ldebug($debugOption) {
        $this->debug = $debugOption;
    }
    
    // write message to the log file
    public function lwrite($message) {
        // if file pointer doesn't exist, then open log file
        if ($this->debug == 0 && !is_resource($this->fp)) {
            $this->lopen();
        }
        // define current time and suppress E_WARNING if using the system TZ settings
        // (don't forget to set the INI setting date.timezone)
        $time = @date('[d/M/Y:H:i:s]');
        // write current time, script name and message to the log file
        if ($this->debug == 1) {
        	echo "$time $message" . PHP_EOL;
        } else {
        	fwrite($this->fp, "$time $message" . PHP_EOL);
        }
    }
    // close log file (it's always a good idea to close a file when you're done with it)
    public function lclose() {
        if ($this->debug == 0) {
	        fclose($this->fp);
	    }
    }
    // open log file (private method)
    private function lopen() {
        $log_file_default = '/var/log/pmta/pmta-bounce-handler.log';
        // define log file from lfile method or use previously set default
        $lfile = $this->log_file ? $this->log_file : $log_file_default;
        // open log file for writing only and place file pointer at the end of the file
        // (if the file does not exist, try to create it)
	// First try to write to configured log-file
        if ($this->debug == 0) {
	    $this->fp = fopen($lfile, 'a') or $lfile = 'pmta-bounce-handler.log';
        }
        
        if ($this->debug == 0 && is_null($this->fp)) {
	    $this->fp = fopen($lfile, 'a') or exit("Can't open $lfile!");
	}
    }
}
// LOGGING CLASS
// ========================================================================================================

class BounceUtility {

// Test if URL is available
public static function testEndpointURL($endpointURL) {
	global $log;
	
	$ch = curl_init($endpointURL);
	curl_setopt($ch,  CURLOPT_RETURNTRANSFER, TRUE);
	curl_setopt($ch, CURLOPT_TIMEOUT, 5);

	$result = curl_exec($ch);

	if ($result === false || is_null($result) || empty($result)) {
		$log->lwrite('   Failed connecting to ' . $endpointURL . ', check conncitivity!');
    	return false;
	}

	$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

	if ($httpCode != 200 && $httpCode != 301 && $httpCode != 302) {
		$log->lwrite('   Failed connecting to ' . $endpointURL . ', error=' . $httpCode);
		return false;
	}

	return true;
}


}

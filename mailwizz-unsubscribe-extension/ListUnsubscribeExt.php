<?php defined('MW_PATH') || exit('No direct script access allowed');

/**
 * List Unsubscribe Extension
 *
 * @package MailWizz EMA
 * @subpackage ListUnsubscribeExt
 * @author Serban George Cristian <cristian.serban@mailwizz.com>
 * @link http://www.mailwizz.com/
 * @copyright 2013-2015 MailWizz EMA (http://www.mailwizz.com)
 * @license http://www.mailwizz.com/license/
 */

class ListUnsubscribeExt extends ExtensionInit
{
    // name of the extension as shown in the backend panel
    public $name = 'List Unsubscribe';

    // description of the extension as shown in backend panel
    public $description = 'List unsubscribe custom header value';

    // current version of this extension
    public $version = '1.0';

    // minimum app version
    public $minAppVersion = '1.3.6.2';

    // the author name
    public $author = 'Cristian Serban';

    // author website
    public $website = 'http://www.mailwizz.com/';

    // contact email address
    public $email = 'cristian.serban@mailwizz.com';

    /**
     * in which apps this extension is allowed to run
     * '*' means all apps
     * available apps: customer, backend, frontend, api, console
     * so you can use any variation,
     * like: array('backend', 'customer'); or array('frontend');
     */
    public $allowedApps = array('backend', 'console');

    // cli enabled
    // since cli is a special case, we need to explicitly enable it
    // do it only if you need to hook inside console hooks
    public $cliEnabled = true;

    /**
     * The run method is the entry point of the extension.
     * This method is called by mailwizz at the right time to run the extension.
     */
    public function run()
    {
        /**
         * The path alias: ext-example
         * refers to the path of this folder on the server
         * so if you to echo Yii::getPathOfAlias('ext-example'); you get something like:
         * /var/www/html/apps/common/extensions/example
         * the ext- prefix is automatically added to the extension folder name in order to avoid
         * name clashes with other mailwizz internals
         */
        Yii::import($this->getPathAlias('common.models.*'));

        /**
         * We can detect in which application we currently are
         * By using $this->isAppName('appName') mailwizz tels us if we are in that app
         * or not.
         * We say we are in certain application, when it is loaded by a user in the url.
         * For example accessing http://mailwizzapp.com/customer means we are in the customer app
         *
         * Knowing the above, we will hook inside the backend app as follows:
         */
        if ($this->isAppName('backend')) {

            /**
             * Add the url rules.
             * Best is to follow the pattern below for your extension to avoid name clashes.
             * ext_example_settings is actually the controller file defined in controllers folder.
             */
            Yii::app()->urlManager->addRules(array(
                array('ext_list_unsubscribe_settings/index', 'pattern'    => 'extensions/list-unsubscribe/settings'),
                array('ext_list_unsubscribe_settings/<action>', 'pattern' => 'extensions/list-unsubscribe/settings/*'),
            ));

            /**
             * And now we register the controller for the above rules.
             *
             * Please note that you can register controllers and urls rules
             * in any of the apps.
             *
             * Remember how we said that ext_example_settings is actually the controller file:
             */
            Yii::app()->controllerMap['ext_list_unsubscribe_settings'] = array(
                // remember the ext-example path alias?
                'class'     => 'ext-list-unsubscribe.backend.controllers.Ext_list_unsubscribe_settingsController',

                // pass the extension instance as a variable to the controller
                'extension' => $this,
            );
        }

        // keep these globally for easier access from the callback.
        $data = new CAttributeCollection(array(
            'enabled'              => $this->getOption('enabled') == 'yes',
            'email_address_format' => $this->getOption('email_address_format'),
        ));
        Yii::app()->params['extensions.list-unsubscribe.data'] = $data;

        /**
         * Now we can continue only if the extension is enabled from its settings:
         */
        if (!$data->enabled || !$data->email_address_format) {
            return;
        }

        Yii::app()->hooks->addFilter('console_command_send_campaigns_before_send_to_subscriber', function($emailParams, $campaign, $subscriber, $customer, $server){
            foreach ($emailParams['headers'] as $index => $header) {
                if ($header['name'] == 'List-Unsubscribe') {
                    unset($emailParams['headers'][$index]);
                }
            }

            $data = Yii::app()->params['extensions.list-unsubscribe.data'];

            $listUnsubscribeHeaderValue  = Yii::app()->options->get('system.urls.frontend_absolute_url');
            $listUnsubscribeHeaderValue .= 'lists/'.$campaign->list->list_uid.'/unsubscribe/'.$subscriber->subscriber_uid . '/' . $campaign->campaign_uid . '/unsubscribe-direct?source=email-client-unsubscribe-button';
            $listUnsubscribeHeaderValue  = '<'.$listUnsubscribeHeaderValue.'>';

            //
            $searchReplace = array(
                '[CAMPAIGN_UID]'    => $campaign->campaign_uid,
                '[LIST_UID]'        => $campaign->list->list_uid,
                '[SUBSCRIBER_UID]'  => $subscriber->subscriber_uid,
                '[SUBSCRIBER_EMAIL]'=> $subscriber->email,
            );
            $_subject = sprintf('[%s:%s] Please unsubscribe me.', $campaign->campaign_uid, $subscriber->subscriber_uid);
            $_body    = sprintf('Please unsubscribe me from %s list.', $campaign->list->display_name);
            $_to      = str_replace(array_keys($searchReplace), array_values($searchReplace), $data->email_address_format);
            //$mailToUnsubscribeHeader    = sprintf(', <mailto:%s?subject=%s&body=%s>', $_to, $_subject, $_body);
            $mailToUnsubscribeHeader    = sprintf('<mailto:%s?subject=unsubscribe>', $_to);
            $listUnsubscribeHeaderValue = $mailToUnsubscribeHeader . ', ' . $listUnsubscribeHeaderValue;
            //

            $emailParams['headers'][] = array('name' => 'List-Unsubscribe', 'value' => $listUnsubscribeHeaderValue);
            return $emailParams;
        });
    }

    /**
     * This is an inherit method where we define the url to our settings page in backed.
     * Remember that we can click on an extension title to view the extension settings.
     * This method generates that link.
     */
    public function getPageUrl()
    {
        return Yii::app()->createUrl('ext_list_unsubscribe_settings/index');
    }
}

<?php defined('MW_PATH') || exit('No direct script access allowed');

/**
 * ListUnsubscribeExtCommon
 *
 */

class ListUnsubscribeExtCommon extends FormModel
{
    public $enabled = 'no';

    public $email_address_format = '';

    public function rules()
    {
        $rules = array(
            array('email_address_format', 'safe'),
            array('enabled', 'in', 'range' => array_keys($this->getYesNoOptions())),
        );
        return CMap::mergeArray($rules, parent::rules());
    }

    public function attributeLabels()
    {
        $labels = array(
            'enabled'              => Yii::t('app', 'Enabled'),
            'email_address_format' => $this->getExtensionInstance()->t('Email address format'),
        );
        return CMap::mergeArray($labels, parent::attributeLabels());
    }

    public function attributePlaceholders()
    {
        $placeholders = array(
            'email_address_format' => 'unsubscribe.[SUBSCRIBER_UID].[CAMPAIGN_UID]@domain.com',
        );
        return CMap::mergeArray($placeholders, parent::attributePlaceholders());
    }

    public function attributeHelpTexts()
    {
        $texts = array(
            'enabled'              => Yii::t('app', 'Whether the feature is enabled'),
            'email_address_format' => $this->getExtensionInstance()->t('The format of the email address'),
        );
        return CMap::mergeArray($texts, parent::attributeHelpTexts());
    }

    public function save()
    {
        $extension  = $this->getExtensionInstance();
        $attributes = array('enabled', 'email_address_format');
        foreach ($attributes as $name) {
            $extension->setOption($name, $this->$name);
        }
        return $this;
    }

    public function populate()
    {
        $extension  = $this->getExtensionInstance();
        $attributes = array('enabled', 'email_address_format');
        foreach ($attributes as $name) {
            $this->$name = $extension->getOption($name, $this->$name);
        }
        return $this;
    }

    public function getExtensionInstance()
    {
        return Yii::app()->extensionsManager->getExtensionInstance('list-unsubscribe');
    }
}

<?php defined('MW_PATH') || exit('No direct script access allowed');

/**
 * Controller file for settings.
 */

class Ext_list_unsubscribe_settingsController extends Controller
{
    // the extension instance
    public $extension;

    // move the view path
    public function getViewPath()
    {
        return Yii::getPathOfAlias('ext-list-unsubscribe.backend.views.settings');
    }

    /**
     * Common settings
     */
    public function actionIndex()
    {
        $request = Yii::app()->request;
        $notify  = Yii::app()->notify;

        $model = new ListUnsubscribeExtCommon();
        $model->populate();

        if ($request->isPostRequest) {
            $model->attributes = (array)$request->getPost($model->modelName, array());
            if ($model->validate()) {
                $notify->addSuccess(Yii::t('app', 'Your form has been successfully saved!'));
                $model->save();
            } else {
                $notify->addError(Yii::t('app', 'Your form has a few errors, please fix them and try again!'));
            }
        }

        $this->setData(array(
            'pageMetaTitle'    => $this->data->pageMetaTitle . ' | '. $this->extension->t('List unsubscribe'),
            'pageHeading'      => $this->extension->t('List unsubscribe'),
            'pageBreadcrumbs'  => array(
                Yii::t('app', 'Extensions') => $this->createUrl('extensions/index'),
                $this->extension->t('List unsubscribe') => $this->createUrl('ext_example_settings/index'),
            )
        ));

        $this->render('index', compact('model'));
    }
}

<?php

namespace Cstudios\CraftChat\controllers;

use craft\web\Controller;
use Cstudios\CraftChat\records\Faq;
use Craft;
use craft\helpers\UrlHelper;

class FaqController extends Controller
{
    // Make sure we check permission or at least restrict to CP admins
    protected array|int|bool $allowAnonymous = false;

    public function actionIndex()
    {
        $this->requireLogin();

        $faqs = Faq::find()
            ->orderBy(['relevancyCounter' => SORT_DESC, 'dateUpdated' => SORT_DESC])
            ->all();

        return $this->renderTemplate('craft-chat/cp/faq/index', [
            'faqs' => $faqs,
        ]);
    }

    public function actionEdit(int $faqId = null)
    {
        $this->requireLogin();

        if ($faqId) {
            $faq = Faq::findOne($faqId);
            if (!$faq) {
                return $this->asNotFound('FAQ not found.');
            }
            $title = 'Edit FAQ';
        } else {
            $faq = new Faq();
            $title = 'New FAQ';
        }

        return $this->renderTemplate('craft-chat/cp/faq/edit', [
            'faq' => $faq,
            'title' => $title,
        ]);
    }

    public function actionSave()
    {
        $this->requirePostRequest();
        $this->requireLogin();

        $request = Craft::$app->getRequest();
        $faqId = $request->getBodyParam('faqId');

        if ($faqId) {
            $faq = Faq::findOne($faqId);
            if (!$faq) {
                throw new \yii\web\NotFoundHttpException('FAQ not found.');
            }
        } else {
            $faq = new Faq();
            $faq->relevancyCounter = 1; // Default for manual creation
        }

        $faq->question = $request->getBodyParam('question');
        $faq->answer = $request->getBodyParam('answer');

        $linkedEntryIds = $request->getBodyParam('linkedEntryId');
        if (is_array($linkedEntryIds) && !empty($linkedEntryIds)) {
            $faq->linkedEntryId = (int) $linkedEntryIds[0];
        } else {
            $faq->linkedEntryId = null;
        }

        // Optional logic: allow manually setting relevancyCounter
        $counter = $request->getBodyParam('relevancyCounter');
        if ($counter !== null) {
            $faq->relevancyCounter = (int) $counter;
        }

        if ($faq->save()) {
            Craft::$app->getSession()->setNotice('FAQ saved.');
            return $this->redirectToPostedUrl($faq);
        }

        Craft::$app->getSession()->setError('Could not save FAQ.');
        return $this->renderTemplate('craft-chat/cp/faq/edit', [
            'faq' => $faq,
            'title' => $faqId ? 'Edit FAQ' : 'New FAQ'
        ]);
    }

    public function actionDelete()
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();

        $faqId = Craft::$app->getRequest()->getRequiredBodyParam('faqId');
        $faq = Faq::findOne($faqId);

        if ($faq && $faq->delete()) {
            return $this->asJson(['success' => true]);
        }

        return $this->asJson(['error' => 'Could not delete FAQ.']);
    }
}

<?php

namespace Cstudios\CraftChat\controllers;

use Craft;
use craft\web\Controller;
use Cstudios\CraftChat\records\Conversation;
use Cstudios\CraftChat\records\Message;

class CpController extends Controller
{
    public function actionIndex()
    {
        $conversations = Conversation::find()
            ->orderBy(['dateUpdated' => SORT_DESC])
            ->all();

        return $this->renderTemplate('craft-chat/cp/index', [
            'conversations' => $conversations,
        ]);
    }

    public function actionView(int $id)
    {
        $conversation = Conversation::findOne($id);

        if (!$conversation) {
            throw new \craft\web\NotFoundHttpException('Conversation not found');
        }

        $messages = Message::find()
            ->where(['conversationId' => $id])
            ->orderBy(['dateCreated' => SORT_ASC])
            ->all();

        return $this->renderTemplate('craft-chat/cp/view', [
            'conversation' => $conversation,
            'messages' => $messages,
        ]);
    }
}

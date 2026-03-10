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
        $request = Craft::$app->getRequest();
        $q = $request->getParam('q');
        $dateFrom = $request->getParam('dateFrom');
        $dateTo = $request->getParam('dateTo');

        $query = Conversation::find();

        if ($q) {
            $query->joinWith('messages');
            $query->andFilterWhere([
                'or',
                ['like', '{{%craft_chat_conversations}}.summary', $q],
                ['like', '{{%craft_chat_messages}}.content', $q]
            ]);
            $query->groupBy(['{{%craft_chat_conversations}}.id']);
        }

        if ($dateFrom) {
            $query->andFilterWhere(['>=', '{{%craft_chat_conversations}}.dateCreated', date('Y-m-d 00:00:00', strtotime($dateFrom))]);
        }

        if ($dateTo) {
            $query->andFilterWhere(['<=', '{{%craft_chat_conversations}}.dateCreated', date('Y-m-d 23:59:59', strtotime($dateTo))]);
        }

        $conversations = $query->orderBy(['{{%craft_chat_conversations}}.dateUpdated' => SORT_DESC])->all();

        return $this->renderTemplate('craft-chat/cp/index', [
            'conversations' => $conversations,
            'q' => $q,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
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

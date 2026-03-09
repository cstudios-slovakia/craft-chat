<?php

namespace Cstudios\CraftChat\controllers;

use Craft;
use craft\web\Controller;
use Cstudios\CraftChat\Plugin;
use Cstudios\CraftChat\records\Conversation;

class ChatController extends Controller
{
    // Important: false means anonymous users can hit these endpoints
    protected array|int|bool $allowAnonymous = true;

    // Remove CSRF validation for external AJAX if needed, though usually Craft handles this nicely.
    // public $enableCsrfValidation = false; 

    public function actionStart()
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();

        // Create new conversation
        $conversation = new Conversation();
        $conversation->status = 'active';
        $conversation->sessionId = Craft::$app->getSession()->getIsActive() ? Craft::$app->getSession()->getId() : bin2hex(random_bytes(16));
        $conversation->userId = Craft::$app->getUser()->getId(); // null if guest

        if ($conversation->save()) {
            return $this->asJson([
                'success' => true,
                'conversationId' => $conversation->id,
                'sessionId' => $conversation->sessionId
            ]);
        }

        return $this->asJson(['success' => false, 'error' => 'Could not create conversation.']);
    }

    public function actionMessage()
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();

        $conversationId = Craft::$app->getRequest()->getRequiredBodyParam('conversationId');
        $message = Craft::$app->getRequest()->getRequiredBodyParam('message');

        // Generate response via ChatService
        $botResponse = Plugin::getInstance()->chat->generateResponse($message, $conversationId);

        return $this->asJson([
            'success' => true,
            'response' => $botResponse
        ]);
    }
}

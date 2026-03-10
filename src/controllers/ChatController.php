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

    public function beforeAction($action): bool
    {
        // Disable CSRF for external API calls, since we manage it via JS body
        $this->enableCsrfValidation = false;
        return parent::beforeAction($action);
    }

    public function actionStart()
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();

        // Create new conversation
        $conversation = new Conversation();
        $conversation->status = 'active';
        $conversation->sessionId = Craft::$app->getSession()->getIsActive() ? Craft::$app->getSession()->getId() : bin2hex(random_bytes(16));
        $conversation->userId = Craft::$app->getUser()->getId(); // null if guest
        $conversation->ipAddress = Craft::$app->getRequest()->getUserIP();

        // Try getting location
        if ($conversation->ipAddress && !in_array($conversation->ipAddress, ['127.0.0.1', '::1'])) {
            try {
                // simple quick API, 45 requests per minute limit should be fine for basic start
                $geo = @file_get_contents("http://ip-api.com/json/{$conversation->ipAddress}?fields=country,city,status");
                if ($geo) {
                    $geoData = json_decode($geo, true);
                    if (isset($geoData['status']) && $geoData['status'] === 'success') {
                        $conversation->location = trim(($geoData['city'] ?? '') . ', ' . ($geoData['country'] ?? ''), ', ');
                    }
                }
            } catch (\Throwable $e) {
                // Ignore geo errors
            }
        }

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

        // Rate Limit Check
        $settings = Plugin::getInstance()->getSettings();
        $limit = $settings->maxMessagesPerMinute ?? 10;

        $oneMinuteAgo = (new \DateTime('-1 minute'))->format('Y-m-d H:i:s');
        $recentCount = \Cstudios\CraftChat\records\Message::find()
            ->where(['conversationId' => $conversationId, 'role' => 'user'])
            ->andWhere(['>=', 'dateCreated', $oneMinuteAgo])
            ->count();

        if ($recentCount >= $limit) {
            return $this->asJson([
                'success' => true,
                'response' => "You have reached the limit of {$limit} messages per minute. Please wait a moment before sending more messages."
            ]);
        }

        // Generate response via ChatService
        $botResponse = Plugin::getInstance()->chat->generateResponse($message, $conversationId);

        return $this->asJson([
            'success' => true,
            'response' => $botResponse
        ]);
    }
}

<?php

namespace Cstudios\CraftChat\services;

use Craft;
use craft\base\Component;
use Cstudios\CraftChat\Plugin;
use Cstudios\CraftChat\records\Conversation;
use Cstudios\CraftChat\records\Message;
use GuzzleHttp\Client;

class ChatService extends Component
{
    public function getSettings()
    {
        return Plugin::getInstance()->getSettings();
    }

    public function generateResponse(string $userMessage, int $conversationId): string
    {
        $settings = $this->getSettings();
        $apiKey = Craft::parseEnv($settings->openaiApiKey);
        $model = $settings->openaiModel;

        if (empty($apiKey)) {
            Craft::error('OpenAI API Key is not set in Craft Chat settings.', __METHOD__);
            return "Configuration error: Missing API Key.";
        }

        $conversation = Conversation::findOne($conversationId);
        if (!$conversation) {
            return "Error: Conversation not found.";
        }

        // Save User Message
        $userMsgRecord = new Message();
        $userMsgRecord->conversationId = $conversationId;
        $userMsgRecord->role = 'user';
        $userMsgRecord->content = $userMessage;
        $userMsgRecord->save();

        // Build Payload
        $messagesPayload = [
            ['role' => 'system', 'content' => $settings->initialInstructions]
        ];

        // Fetch last 10 messages for context
        $history = Message::find()
            ->where(['conversationId' => $conversationId])
            ->orderBy(['dateCreated' => SORT_ASC])
            ->limit(10)
            ->all();

        foreach ($history as $msg) {
            $messagesPayload[] = [
                'role' => $msg->role,
                'content' => $msg->content
            ];
        }

        $client = Craft::createGuzzleClient();

        try {
            $response = $client->post('https://api.openai.com/v1/chat/completions', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $apiKey,
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'model' => $model,
                    'messages' => $messagesPayload,
                ]
            ]);

            $responseData = json_decode($response->getBody()->getContents(), true);
            $aiResponseText = $responseData['choices'][0]['message']['content'] ?? "Sorry, I couldn't process that.";

            // Save AI Message
            $aiMsgRecord = new Message();
            $aiMsgRecord->conversationId = $conversationId;
            $aiMsgRecord->role = 'assistant';
            $aiMsgRecord->content = $aiResponseText;
            $aiMsgRecord->save();

            // Update conversation count
            $conversation->messageCount += 2; // User + AI
            $conversation->save();

            // Re-generate summary in background (queue)
            Craft::$app->getQueue()->push(new \Cstudios\CraftChat\jobs\GenerateSummaryJob([
                'conversationId' => $conversationId
            ]));

            return $aiResponseText;
        } catch (\Exception $e) {
            Craft::error("OpenAI API Error: " . $e->getMessage(), __METHOD__);
            return "Sorry, there was an error communicating with the AI. " . $e->getMessage();
        }
    }
}

<?php

namespace Cstudios\CraftChat\jobs;

use Craft;
use craft\queue\BaseJob;
use Cstudios\CraftChat\records\Conversation;
use Cstudios\CraftChat\records\Message;
use Cstudios\CraftChat\Plugin;

class GenerateSummaryJob extends BaseJob
{
    public int $conversationId;

    public function execute($queue): void
    {
        $conversation = Conversation::findOne($this->conversationId);
        if (!$conversation) {
            return;
        }

        $messages = Message::find()
            ->where(['conversationId' => $this->conversationId])
            ->orderBy(['dateCreated' => SORT_ASC])
            ->all();

        if (count($messages) < 2) {
            return; // Not enough context to summarize
        }

        $textToSummarize = "";
        foreach ($messages as $msg) {
            $roleLabel = $msg->role === 'user' ? 'User' : 'AI';
            $textToSummarize .= "{$roleLabel}: {$msg->content}\n";
        }

        $settings = Plugin::getInstance()->getSettings();
        $apiKey = Craft::parseEnv($settings->openaiApiKey);
        $model = $settings->openaiModel;

        if (empty($apiKey))
            return;

        $client = Craft::createGuzzleClient();

        try {
            $response = $client->post('https://api.openai.com/v1/chat/completions', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $apiKey,
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'model' => 'gpt-4o-mini', // usually cheaper model is fine for summary
                    'messages' => [
                        ['role' => 'system', 'content' => 'Generate a concise 2-sentence summary of the following conversation for administrative purposes. Highlight keywords. Do not use quotes or introductory phrases.'],
                        ['role' => 'user', 'content' => $textToSummarize],
                    ],
                ]
            ]);

            $responseData = json_decode($response->getBody()->getContents(), true);
            $summaryText = $responseData['choices'][0]['message']['content'] ?? "";

            if ($summaryText) {
                $conversation->summary = trim($summaryText);
                $conversation->save();
            }

        } catch (\Exception $e) {
            Craft::error("Summary Error: " . $e->getMessage(), __METHOD__);
        }
    }

    protected function defaultDescription(): ?string
    {
        return Craft::t('craft-chat', 'Generating conversation summary for #{id}', ['id' => $this->conversationId]);
    }
}

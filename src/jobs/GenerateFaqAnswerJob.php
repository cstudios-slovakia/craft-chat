<?php

namespace Cstudios\CraftChat\jobs;

use Craft;
use craft\queue\BaseJob;
use Cstudios\CraftChat\records\Faq;
use Cstudios\CraftChat\Plugin;

class GenerateFaqAnswerJob extends BaseJob
{
    public int $faqId;

    public function execute($queue): void
    {
        $faq = Faq::findOne($this->faqId);

        if (!$faq || $faq->answer) {
            return; // Doesn't exist or already answered
        }

        $settings = Plugin::getInstance()->getSettings();
        $apiKey = Craft::parseEnv($settings->openaiApiKey);
        $model = $settings->openaiModel;

        if (empty($apiKey)) {
            return;
        }

        $client = Craft::createGuzzleClient();

        // Use a background prompt to try and synthesize an answer, or at least structure the question for human review.
        $systemContent = "You are an expert internal knowledge base editor. A user asked a question that the tier-1 support bot could not answer. Your job is to draft a helpful answer if you can infer one based on general knowledge, or draft a placeholder template for the human admin to fill out. Keep it concise, helpful, and professional.";

        $messagesPayload = [
            ['role' => 'system', 'content' => $systemContent],
            ['role' => 'user', 'content' => "Draft an official FAQ answer for this user question: " . $faq->question]
        ];

        try {
            $response = $client->post('https://api.openai.com/v1/chat/completions', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $apiKey,
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'model' => $model,
                    'messages' => $messagesPayload,
                    // Slightly lower temperature for more factual internal drafting
                    'temperature' => 0.4
                ]
            ]);

            $responseData = json_decode($response->getBody()->getContents(), true);
            $aiDraftText = $responseData['choices'][0]['message']['content'] ?? null;

            if ($aiDraftText) {
                // Prepend a note so the admin knows it was AI generated
                $faq->answer = "🤖 [AI DRAFT - NEEDS REVIEW]\n\n" . $aiDraftText;
                $faq->save();
            }

        } catch (\Throwable $e) {
            Craft::error("GenerateFaqAnswerJob Error: " . $e->getMessage(), __METHOD__);
        }
    }

    protected function defaultDescription(): string
    {
        return 'Drafting FAQ Answer via AI';
    }
}

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

        // Search website for relevant content to feed the AI as context
        $chatService = Plugin::getInstance()->chat;
        $contextEntries = $chatService->searchWebsite($faq->question);

        $contextText = "";
        foreach ($contextEntries as $entry) {
            $contextText .= "--- Page: {$entry['title']} ({$entry['url']}) ---\n";
            $contextText .= $entry['content'] . "\n\n";
        }

        $systemContent = "You are an expert internal knowledge base editor. A user asked a question that the tier-1 support bot could not answer. Your job is to draft a helpful answer BASED ONLY ON THE PROVIDED CONTEXT from our website. If the context does not contain the answer, draft a placeholder template for the human admin to fill out and state that no clear answer was found on the site. Keep it concise, helpful, and professional.";

        $messagesPayload = [
            ['role' => 'system', 'content' => $systemContent],
            ['role' => 'user', 'content' => "CONTEXT FROM WEBSITE:\n\n" . ($contextText ?: "No relevant content found on the website.") . "\n\nUSER QUESTION: " . $faq->question . "\n\nDraft the FAQ answer:"]
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

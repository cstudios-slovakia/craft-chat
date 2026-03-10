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

        $systemContent = trim($settings->initialInstructions) . "\n\nIf you need specific information, use the search_website tool to find content and cite the source URLs provided in your answer.";

        // Build Payload
        $messagesPayload = [
            ['role' => 'system', 'content' => $systemContent]
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

        $payload = [
            'model' => $model,
            'messages' => $messagesPayload,
        ];

        $searchSections = $settings->searchSections;
        if (is_string($searchSections)) {
            $searchSections = json_decode($searchSections, true) ?: [];
        }

        if (!empty($searchSections)) {
            $payload['tools'] = [
                [
                    'type' => 'function',
                    'function' => [
                        'name' => 'search_website',
                        'description' => 'Search the website content for specific keywords if you need information to answer the user\'s query. If you don\'t find results, try searching with synonyms or simpler keywords.',
                        'parameters' => [
                            'type' => 'object',
                            'properties' => [
                                'query' => [
                                    'type' => 'string',
                                    'description' => 'The keyword or phrase to search.'
                                ]
                            ],
                            'required' => ['query']
                        ]
                    ]
                ]
            ];
        }

        $client = Craft::createGuzzleClient();

        try {
            $response = $client->post('https://api.openai.com/v1/chat/completions', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $apiKey,
                    'Content-Type' => 'application/json',
                ],
                'json' => $payload
            ]);

            $responseData = json_decode($response->getBody()->getContents(), true);

            $iterations = 0;
            $maxIterations = 3;

            // Execute Tool Calls if AI wants to search, support recursive calls
            while ($iterations < $maxIterations) {
                $messageBlock = $responseData['choices'][0]['message'] ?? [];

                if (empty($messageBlock['tool_calls'])) {
                    break;
                }

                $iterations++;
                $messagesPayload[] = $messageBlock; // append assistant tool call request

                foreach ($messageBlock['tool_calls'] as $toolCall) {
                    if ($toolCall['function']['name'] === 'search_website') {
                        $args = json_decode($toolCall['function']['arguments'], true);
                        $query = $args['query'] ?? '';

                        Craft::info("Craft Chat Tool: search_website query='{$query}'", __METHOD__);

                        // Use fuzzy searching by wrapping with asterisks
                        $searchQuery = '*' . trim($query) . '*';

                        $entries = \craft\elements\Entry::find()
                            ->section($searchSections)
                            ->search($searchQuery)
                            ->limit(3)
                            ->all();

                        $searchResults = [];
                        foreach ($entries as $entry) {
                            $searchResults[] = [
                                'title' => $entry->title,
                                'url' => $entry->getUrl(),
                                'content' => $this->extractTextFromElement($entry)
                            ];
                        }

                        $messagesPayload[] = [
                            'role' => 'tool',
                            'tool_call_id' => $toolCall['id'],
                            'name' => 'search_website',
                            'content' => empty($searchResults) ? "No results found for '{$query}'. Try different keywords." : json_encode($searchResults)
                        ];
                    }
                }

                // Call API again with tool results
                $payload['messages'] = $messagesPayload;
                $response = $client->post('https://api.openai.com/v1/chat/completions', [
                    'headers' => [
                        'Authorization' => 'Bearer ' . $apiKey,
                        'Content-Type' => 'application/json',
                    ],
                    'json' => $payload
                ]);

                $responseData = json_decode($response->getBody()->getContents(), true);
            }

            $aiResponseText = $responseData['choices'][0]['message']['content'] ?? null;
            if (!$aiResponseText) {
                $aiResponseText = "Sorry, I couldn't process that. Details: " . json_encode($responseData);
            }

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
        } catch (\GuzzleHttp\Exception\RequestException $e) {
            $errorBody = $e->hasResponse() ? $e->getResponse()->getBody()->getContents() : $e->getMessage();
            Craft::error("OpenAI API Request Error: " . $errorBody, __METHOD__);
            return "API Error: " . $errorBody;
        } catch (\Throwable $e) {
            Craft::error("OpenAI API Error: " . $e->getMessage(), __METHOD__);
            return "System Error: " . $e->getMessage() . " on line " . $e->getLine();
        }
    }

    protected function extractTextFromElement($element, int $depth = 0): string
    {
        if ($depth > 2 || !$element) {
            return '';
        }

        $text = '';
        try {
            // In Craft 4/5, elements behave like arrays/models for their custom fields.
            // We can iterate over the field layout to get all field handles.
            $fieldLayout = $element->getFieldLayout();
            if ($fieldLayout) {
                $customFields = $fieldLayout->getCustomFields();
                foreach ($customFields as $field) {
                    $handle = $field->handle;
                    $fieldValue = $element->$handle ?? null;

                    if (is_string($fieldValue) || (is_object($fieldValue) && method_exists($fieldValue, '__toString'))) {
                        $val = trim(strip_tags((string) $fieldValue));
                        if (!empty($val)) {
                            $text .= $val . " \n";
                        }
                    } elseif ($fieldValue instanceof \craft\elements\db\ElementQuery) {
                        $relatedElements = (clone $fieldValue)->limit(5)->all();
                        foreach ($relatedElements as $relatedElement) {
                            $text .= $this->extractTextFromElement($relatedElement, $depth + 1) . " \n";
                        }
                    }
                }
            }
        } catch (\Throwable $e) {
            // Ignore errors for un-renderable formats
        }

        return mb_substr(trim(preg_replace('/\s+/', ' ', $text)), 0, 5000);
    }
}

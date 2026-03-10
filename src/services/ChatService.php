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

    public function getAvailableCredits(): array
    {
        $settings = $this->getSettings();
        $apiKey = Craft::parseEnv($settings->openaiApiKey);

        if (empty($apiKey)) {
            return ['status' => 'info', 'message' => 'No API Key configured.'];
        }

        $client = Craft::createGuzzleClient();

        try {
            $response = $client->get('https://api.openai.com/dashboard/billing/credit_grants', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $apiKey,
                ],
                'timeout' => 5,
            ]);

            $responseData = json_decode($response->getBody()->getContents(), true);
            $totalAvailable = $responseData['total_available'] ?? 0;

            return [
                'status' => 'success',
                'available' => floatval($totalAvailable),
            ];

        } catch (\GuzzleHttp\Exception\RequestException $e) {
            $errorBody = $e->hasResponse() ? $e->getResponse()->getBody()->getContents() : $e->getMessage();
            return ['status' => 'error', 'message' => 'API Error: ' . $errorBody];
        } catch (\Throwable $e) {
            return ['status' => 'error', 'message' => 'System Error: ' . $e->getMessage()];
        }
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

        $systemContent = trim($settings->initialInstructions) . "\n\nCRITICAL INSTRUCTIONS:\n1. When asked a question, ALWAYS use the `search_faqs` tool first to check the Knowledge Base.\n2. If `search_faqs` returns a verified answer, you MUST reply with that exact answer directly to the user.\n3. If `search_faqs` returns no answer, use `search_website`.\n4. If both tools fail to find an answer, call the `log_unanswered_question` tool with the user's question, and then reply apologizing that you do not know the answer.";

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
                        'name' => 'search_faqs',
                        'description' => 'Search the official Knowledge Base/FAQ for verified answers.',
                        'parameters' => [
                            'type' => 'object',
                            'properties' => [
                                'query' => [
                                    'type' => 'string',
                                    'description' => 'The keyword or phrase to search in the FAQ database.'
                                ]
                            ],
                            'required' => ['query']
                        ]
                    ]
                ],
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
                ],
                [
                    'type' => 'function',
                    'function' => [
                        'name' => 'log_unanswered_question',
                        'description' => 'Log the user\'s question to the database if you absolutely cannot find an answer in the FAQ or Website. Use this before telling the user you do not know.',
                        'parameters' => [
                            'type' => 'object',
                            'properties' => [
                                'question' => [
                                    'type' => 'string',
                                    'description' => 'The specific question the user asked that you could not answer.'
                                ]
                            ],
                            'required' => ['question']
                        ]
                    ]
                ]
            ];
        } else {
            // Even if no sections selected, we can enable FAQ tools
            $payload['tools'] = [
                [
                    'type' => 'function',
                    'function' => [
                        'name' => 'search_faqs',
                        'description' => 'Search the official Knowledge Base/FAQ for verified answers.',
                        'parameters' => [
                            'type' => 'object',
                            'properties' => [
                                'query' => ['type' => 'string']
                            ],
                            'required' => ['query']
                        ]
                    ]
                ],
                [
                    'type' => 'function',
                    'function' => [
                        'name' => 'log_unanswered_question',
                        'description' => 'Log the user\'s question to the database if you absolutely cannot find an answer in the FAQ or Website.',
                        'parameters' => [
                            'type' => 'object',
                            'properties' => [
                                'question' => ['type' => 'string']
                            ],
                            'required' => ['question']
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

            $totalPromptTokens = $responseData['usage']['prompt_tokens'] ?? 0;
            $totalCompletionTokens = $responseData['usage']['completion_tokens'] ?? 0;

            $iterations = 0;
            $maxIterations = 5;

            // Execute Tool Calls if AI wants to search, support recursive calls
            while ($iterations < $maxIterations) {
                $messageBlock = $responseData['choices'][0]['message'] ?? [];

                if (empty($messageBlock['tool_calls'])) {
                    break;
                }

                $iterations++;
                $messagesPayload[] = $messageBlock; // append assistant tool call request

                foreach ($messageBlock['tool_calls'] as $toolCall) {
                    if ($toolCall['function']['name'] === 'search_faqs') {
                        $args = json_decode($toolCall['function']['arguments'], true);
                        $query = $args['query'] ?? '';

                        Craft::info("Craft Chat Tool: search_faqs query='{$query}'", __METHOD__);

                        $faqRecords = \Cstudios\CraftChat\records\Faq::find()
                            ->where(['not', ['answer' => null]])
                            ->andWhere([
                                'or',
                                ['like', 'question', $query],
                                ['like', 'answer', $query]
                            ])
                            ->limit(3)
                            ->all();

                        $searchResults = [];
                        foreach ($faqRecords as $faq) {
                            $searchResults[] = [
                                'question' => $faq->question,
                                'answer' => $faq->answer
                            ];
                        }

                        $messagesPayload[] = [
                            'role' => 'tool',
                            'tool_call_id' => $toolCall['id'],
                            'name' => 'search_faqs',
                            'content' => empty($searchResults) ? "No verified FAQ found for '{$query}'." : json_encode($searchResults)
                        ];

                    } elseif ($toolCall['function']['name'] === 'search_website') {
                        $args = json_decode($toolCall['function']['arguments'], true);
                        $query = $args['query'] ?? '';

                        $searchResults = $this->searchWebsite($query);

                        $messagesPayload[] = [
                            'role' => 'tool',
                            'tool_call_id' => $toolCall['id'],
                            'name' => 'search_website',
                            'content' => empty($searchResults) ? "No results found for '{$query}'. Try different keywords." : json_encode($searchResults)
                        ];

                    } elseif ($toolCall['function']['name'] === 'log_unanswered_question') {
                        $args = json_decode($toolCall['function']['arguments'], true);
                        $question = $args['question'] ?? '';

                        Craft::info("Craft Chat Tool: log_unanswered_question question='{$question}'", __METHOD__);

                        // Check if a similar unanswered question already exists
                        if (!empty($question)) {
                            $existingFaq = \Cstudios\CraftChat\records\Faq::find()
                                ->where(['answer' => null])
                                ->andWhere(['like', 'question', $question])
                                ->one();

                            if ($existingFaq) {
                                $existingFaq->relevancyCounter += 1;
                                $existingFaq->save();
                            } else {
                                $newFaq = new \Cstudios\CraftChat\records\Faq();
                                $newFaq->question = $question;
                                $newFaq->relevancyCounter = 1;
                                $newFaq->save();

                                // Dispatch background worker to try to draft an answer
                                Craft::$app->getQueue()->push(new \Cstudios\CraftChat\jobs\GenerateFaqAnswerJob([
                                    'faqId' => $newFaq->id
                                ]));
                            }
                        }

                        $messagesPayload[] = [
                            'role' => 'tool',
                            'tool_call_id' => $toolCall['id'],
                            'name' => 'log_unanswered_question',
                            'content' => "Question logged successfully. You may now apologize to the user."
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
                if (isset($responseData['usage'])) {
                    $totalPromptTokens += $responseData['usage']['prompt_tokens'] ?? 0;
                    $totalCompletionTokens += $responseData['usage']['completion_tokens'] ?? 0;
                }
            }

            $aiResponseText = $responseData['choices'][0]['message']['content'] ?? null;
            if (!$aiResponseText) {
                if (!empty($responseData['choices'][0]['message']['tool_calls'])) {
                    $aiResponseText = "Sorry, I am still processing the information but ran out of time. Please try asking again.";
                } else {
                    $aiResponseText = "Sorry, I couldn't process that. Server response was empty.";
                }
            }

            // Save AI Message
            $aiMsgRecord = new Message();
            $aiMsgRecord->conversationId = $conversationId;
            $aiMsgRecord->role = 'assistant';
            $aiMsgRecord->content = $aiResponseText;
            $aiMsgRecord->save();

            // Update conversation count and tokens
            $conversation->messageCount += 2; // User + AI
            $conversation->promptTokens = (int) $conversation->promptTokens + $totalPromptTokens;
            $conversation->completionTokens = (int) $conversation->completionTokens + $totalCompletionTokens;
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

    public function searchWebsite(string $query): array
    {
        $settings = $this->getSettings();
        $searchSections = $settings->searchSections ?? [];
        if (!is_array($searchSections)) {
            $searchSections = !empty($searchSections) ? explode(',', $searchSections) : [];
        }

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

        return $searchResults;
    }

    public function extractTextFromElement($element, int $depth = 0): string
    {
        if ($depth > 2 || !$element) {
            return '';
        }

        $text = '';
        try {
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

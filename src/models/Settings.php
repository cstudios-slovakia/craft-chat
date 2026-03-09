<?php

namespace Cstudios\CraftChat\models;

use craft\base\Model;

class Settings extends Model
{
    public string $openaiApiKey = '';
    public string $openaiModel = 'gpt-4o-mini';
    public string $initialInstructions = 'You are a helpful assistant on our website.';
    public array $searchSections = []; // sections to search for info
    public string $chatSide = 'right'; // 'left' or 'right'
    public string $defaultLanguage = 'en';
    public string $botName = 'CraftBot';

    // Styling
    public string $colorChatBubbleAI = '#E5E7EB';
    public string $colorChatBubbleUser = '#3B82F6';
    public string $colorBackground = '#FFFFFF';

    public string $welcomeMessage = 'Hello! How can I help you today?';

    // Which sections & categories to feed
    public array $feedSections = [];
    public array $feedCategories = [];

    protected function defineRules(): array
    {
        return [
            [['openaiApiKey', 'openaiModel', 'botName', 'welcomeMessage'], 'string'],
            // Add more specific validations if necessary
        ];
    }
}

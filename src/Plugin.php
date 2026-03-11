<?php

namespace Cstudios\CraftChat;

use Craft;
use craft\base\Plugin as BasePlugin;
use craft\web\twig\variables\CraftVariable;
use yii\base\Event;

use craft\events\RegisterUrlRulesEvent;
use craft\events\RegisterTemplateRootsEvent;
use craft\web\UrlManager;
use craft\web\View;

use Cstudios\CraftChat\models\Settings;
use Cstudios\CraftChat\services\ChatService;
use Cstudios\CraftChat\variables\CraftChatVariable;

/**
 * @property ChatService $chat
 */
class Plugin extends BasePlugin
{
    public string $schemaVersion = '1.0.1';
    public string $version = '1.0.0';
    public bool $hasCpSettings = true;
    public bool $hasCpSection = true;

    public function init()
    {
        parent::init();

        // Register components / services
        $this->setComponents([
            'chat' => ChatService::class,
        ]);

        // Register variables
        Event::on(
            CraftVariable::class,
            CraftVariable::EVENT_INIT,
            function (Event $event) {
                /** @var CraftVariable $variable */
                $variable = $event->sender;
                $variable->set('craftChat', CraftChatVariable::class);
            }
        );

        Event::on(
            View::class,
            View::EVENT_REGISTER_SITE_TEMPLATE_ROOTS,
            function (RegisterTemplateRootsEvent $event) {
                $event->roots['craft-chat'] = __DIR__ . '/templates';
            }
        );

        Craft::$app->getView()->hook('chat', function (array &$context) {
            return Craft::$app->getView()->renderTemplate('craft-chat/_chat-hook');
        });

        Event::on(
            UrlManager::class,
            UrlManager::EVENT_REGISTER_SITE_URL_RULES,
            function (RegisterUrlRulesEvent $event) {
                $event->rules['craft-chat/api/start'] = 'craft-chat/chat/start';
                $event->rules['craft-chat/api/message'] = 'craft-chat/chat/message';
            }
        );

        Event::on(
            UrlManager::class,
            UrlManager::EVENT_REGISTER_CP_URL_RULES,
            function (RegisterUrlRulesEvent $event) {
                // Conversations
                $event->rules['craft-chat'] = 'craft-chat/cp/index';
                $event->rules['craft-chat/conversations/<id:\d+>'] = 'craft-chat/cp/view';

                // Knowledge Base
                $event->rules['craft-chat/faq'] = 'craft-chat/faq/index';
                $event->rules['craft-chat/faq/new'] = 'craft-chat/faq/edit';
                $event->rules['craft-chat/faq/<faqId:\d+>'] = 'craft-chat/faq/edit';
            }
        );

        Craft::info('Craft Chat plugin loaded', __METHOD__);
    }

    public function getCpNavItem(): ?array
    {
        $item = parent::getCpNavItem();
        $item['subnav'] = [
            'conversations' => ['label' => 'Conversations', 'url' => 'craft-chat'],
            'faq' => ['label' => 'Knowledge Base', 'url' => 'craft-chat/faq'],
            'settings' => ['label' => 'Settings', 'url' => 'settings/plugins/craft-chat']
        ];
        return $item;
    }

    protected function createSettingsModel(): ?\craft\base\Model
    {
        return new Settings();
    }

    protected function settingsHtml(): ?string
    {
        $sections = Craft::$app->getEntries()->getAllSections();
        $sectionOptions = [];
        foreach ($sections as $section) {
            $sectionOptions[] = ['label' => $section->name, 'value' => $section->handle];
        }

        $settings = $this->getSettings();
        $searchEntriesElements = [];
        if (!empty($settings->searchEntries)) {
            $searchEntriesElements = \craft\elements\Entry::find()
                ->id($settings->searchEntries)
                ->status(null)
                ->all();
        }

        return Craft::$app->getView()->renderTemplate(
            'craft-chat/settings',
            [
                'settings' => $settings,
                'sectionOptions' => $sectionOptions,
                'searchEntriesElements' => $searchEntriesElements
            ]
        );
    }
}

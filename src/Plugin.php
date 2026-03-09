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
                $event->rules['craft-chat'] = 'craft-chat/cp/index';
                $event->rules['craft-chat/conversations/<id:\d+>'] = 'craft-chat/cp/view';
            }
        );

        Craft::info('Craft Chat plugin loaded', __METHOD__);
    }

    protected function createSettingsModel(): ?\craft\base\Model
    {
        return new Settings();
    }

    protected function settingsHtml(): string
    {
        $sections = Craft::$app->getEntries()->getAllSections();
        $sectionOptions = [];
        foreach ($sections as $section) {
            $sectionOptions[] = ['label' => $section->name, 'value' => $section->handle];
        }

        return Craft::$app->view->renderTemplate(
            'craft-chat/settings',
            [
                'settings' => $this->getSettings(),
                'sectionOptions' => $sectionOptions
            ]
        );
    }
}

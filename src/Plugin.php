<?php

namespace Cstudios\CraftChat;

use Craft;
use craft\base\Plugin as BasePlugin;
use craft\web\twig\variables\CraftVariable;
use yii\base\Event;

use craft\events\RegisterUrlRulesEvent;
use craft\web\UrlManager;

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
            UrlManager::class,
            UrlManager::EVENT_REGISTER_SITE_URL_RULES,
            function (RegisterUrlRulesEvent $event) {
                $event->rules['craft-chat/api/start'] = 'craft-chat/chat/start';
                $event->rules['craft-chat/api/message'] = 'craft-chat/chat/message';
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
        return Craft::$app->view->renderTemplate(
            'craft-chat/settings',
            ['settings' => $this->getSettings()]
        );
    }
}

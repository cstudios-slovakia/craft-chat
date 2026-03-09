<?php

namespace Cstudios\CraftChat\records;

use craft\db\ActiveRecord;

/**
 * @property int $id
 * @property int $conversationId
 * @property string $role
 * @property string $content
 */
class Message extends ActiveRecord
{
    public static function tableName()
    {
        return '{{%craft_chat_messages}}';
    }

    public function getConversation()
    {
        return $this->hasOne(Conversation::class, ['id' => 'conversationId']);
    }
}

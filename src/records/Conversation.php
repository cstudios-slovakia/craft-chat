<?php

namespace Cstudios\CraftChat\records;

use craft\db\ActiveRecord;

/**
 * @property int $id
 * @property int|null $userId
 * @property string|null $sessionId
 * @property string|null $summary
 * @property int $messageCount
 * @property int $promptTokens
 * @property int $completionTokens
 * @property string $status
 * @property string|null $ipAddress
 * @property string|null $location
 */
class Conversation extends ActiveRecord
{
    public static function tableName()
    {
        return '{{%craft_chat_conversations}}';
    }

    public function getMessages()
    {
        return $this->hasMany(Message::class, ['conversationId' => 'id']);
    }
}

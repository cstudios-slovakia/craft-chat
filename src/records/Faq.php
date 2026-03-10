<?php

namespace Cstudios\CraftChat\records;

use craft\db\ActiveRecord;

/**
 * @property int $id
 * @property string $question
 * @property string|null $answer
 * @property int $relevancyCounter
 * @property string $dateCreated
 * @property string $dateUpdated
 * @property string $uid
 */
class Faq extends ActiveRecord
{
    public static function tableName()
    {
        return '{{%craft_chat_faqs}}';
    }
}

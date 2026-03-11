<?php

namespace Cstudios\CraftChat\records;

use craft\db\ActiveRecord;

/**
 * @property int $id
 * @property string $question
 * @property string|null $answer
 * @property int $relevancyCounter
 * @property int|null $linkedEntryId
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

    /**
     * @return \yii\db\ActiveQueryInterface
     */
    public function getLinkedEntry()
    {
        return $this->hasOne(\craft\records\Element::class, ['id' => 'linkedEntryId']);
    }

    /**
     * Helpers to get the actual Element
     */
    public function getLinkedEntryElement(): ?\craft\elements\Entry
    {
        if (!$this->linkedEntryId) {
            return null;
        }
        return \craft\elements\Entry::find()->id($this->linkedEntryId)->one();
    }
}

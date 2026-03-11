<?php

namespace Cstudios\CraftChat\migrations;

use Craft;
use craft\db\Migration;

class m260311_210000_add_linked_entry_to_faqs extends Migration
{
    public function safeUp()
    {
        $table = '{{%craft_chat_faqs}}';

        if (Craft::$app->db->tableExists($table)) {
            $this->addColumn($table, 'linkedEntryId', $this->integer()->null()->after('answer'));

            $this->addForeignKey(
                $this->db->getForeignKeyName($table, 'linkedEntryId'),
                $table,
                'linkedEntryId',
                '{{%elements}}',
                'id',
                'SET NULL',
                'CASCADE'
            );
        }

        return true;
    }

    public function safeDown()
    {
        $table = '{{%craft_chat_faqs}}';

        if (Craft::$app->db->tableExists($table)) {
            $this->dropForeignKey($this->db->getForeignKeyName($table, 'linkedEntryId'), $table);
            $this->dropColumn($table, 'linkedEntryId');
        }

        return true;
    }
}

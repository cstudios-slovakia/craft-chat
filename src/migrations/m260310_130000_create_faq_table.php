<?php

namespace Cstudios\CraftChat\migrations;

use Craft;
use craft\db\Migration;

class m260310_130000_create_faq_table extends Migration
{
    public function safeUp()
    {
        $table = '{{%craft_chat_faqs}}';

        if (!Craft::$app->db->tableExists($table)) {
            $this->createTable($table, [
                'id' => $this->primaryKey(),
                'question' => $this->text()->notNull(),
                'answer' => $this->text()->null(),
                'relevancyCounter' => $this->integer()->defaultValue(1)->notNull(),
                'dateCreated' => $this->dateTime()->notNull(),
                'dateUpdated' => $this->dateTime()->notNull(),
                'uid' => $this->uid(),
            ]);
        }

        return true;
    }

    public function safeDown()
    {
        $table = '{{%craft_chat_faqs}}';

        if (Craft::$app->db->tableExists($table)) {
            $this->dropTable($table);
        }

        return true;
    }
}

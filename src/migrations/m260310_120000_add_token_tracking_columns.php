<?php

namespace Cstudios\CraftChat\migrations;

use Craft;
use craft\db\Migration;

class m260310_120000_add_token_tracking_columns extends Migration
{
    public function safeUp()
    {
        $table = '{{%craft_chat_conversations}}';

        if (!Craft::$app->db->columnExists($table, 'promptTokens')) {
            $this->addColumn($table, 'promptTokens', $this->integer()->defaultValue(0)->after('messageCount'));
        }

        if (!Craft::$app->db->columnExists($table, 'completionTokens')) {
            $this->addColumn($table, 'completionTokens', $this->integer()->defaultValue(0)->after('promptTokens'));
        }

        return true;
    }

    public function safeDown()
    {
        $table = '{{%craft_chat_conversations}}';

        if (Craft::$app->db->columnExists($table, 'promptTokens')) {
            $this->dropColumn($table, 'promptTokens');
        }

        if (Craft::$app->db->columnExists($table, 'completionTokens')) {
            $this->dropColumn($table, 'completionTokens');
        }

        return true;
    }
}

<?php

namespace Cstudios\CraftChat\migrations;

use Craft;
use craft\db\Migration;

class m260310_070000_add_location_ip_to_conversations extends Migration
{
    public function safeUp()
    {
        $this->addColumn('{{%craft_chat_conversations}}', 'ipAddress', $this->string()->null());
        $this->addColumn('{{%craft_chat_conversations}}', 'location', $this->string()->null());

        Craft::$app->db->schema->refresh();
        return true;
    }

    public function safeDown()
    {
        $this->dropColumn('{{%craft_chat_conversations}}', 'ipAddress');
        $this->dropColumn('{{%craft_chat_conversations}}', 'location');
        return true;
    }
}

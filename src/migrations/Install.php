<?php

namespace Cstudios\CraftChat\migrations;

use Craft;
use craft\db\Migration;

class Install extends Migration
{
    public function safeUp()
    {
        $this->createTables();
        $this->createIndexes();
        $this->addForeignKeys();

        Craft::$app->db->schema->refresh();

        return true;
    }

    public function safeDown()
    {
        $this->dropForeignKeys();
        $this->dropTables();

        return true;
    }

    protected function createTables()
    {
        $tableSchema = Craft::$app->db->schema->getTableSchema('{{%craft_chat_conversations}}');
        if ($tableSchema === null) {
            $this->createTable('{{%craft_chat_conversations}}', [
                'id' => $this->primaryKey(),
                'dateCreated' => $this->dateTime()->notNull(),
                'dateUpdated' => $this->dateTime()->notNull(),
                'uid' => $this->uid(),
                // Our columns
                'userId' => $this->integer()->null(),
                'sessionId' => $this->string()->null(),
                'summary' => $this->text()->null(),
                'messageCount' => $this->integer()->defaultValue(0),
                'status' => $this->string()->defaultValue('active') // active, closed
            ]);
        }

        $tableSchema = Craft::$app->db->schema->getTableSchema('{{%craft_chat_messages}}');
        if ($tableSchema === null) {
            $this->createTable('{{%craft_chat_messages}}', [
                'id' => $this->primaryKey(),
                'dateCreated' => $this->dateTime()->notNull(),
                'dateUpdated' => $this->dateTime()->notNull(),
                'uid' => $this->uid(),
                // Our columns
                'conversationId' => $this->integer()->notNull(),
                'role' => $this->string()->notNull(), // user or assistant
                'content' => $this->text()->notNull(),
            ]);
        }
    }

    protected function createIndexes()
    {
        $this->createIndex(
            $this->db->getIndexName('{{%craft_chat_messages}}', 'conversationId', false),
            '{{%craft_chat_messages}}',
            'conversationId',
            false
        );

        $this->createIndex(
            $this->db->getIndexName('{{%craft_chat_conversations}}', 'userId', false),
            '{{%craft_chat_conversations}}',
            'userId',
            false
        );
        $this->createIndex(
            $this->db->getIndexName('{{%craft_chat_conversations}}', 'sessionId', false),
            '{{%craft_chat_conversations}}',
            'sessionId',
            false
        );
    }

    protected function addForeignKeys()
    {
        $this->addForeignKey(
            $this->db->getForeignKeyName('{{%craft_chat_messages}}', 'conversationId'),
            '{{%craft_chat_messages}}',
            'conversationId',
            '{{%craft_chat_conversations}}',
            'id',
            'CASCADE',
            'CASCADE'
        );

        $this->addForeignKey(
            $this->db->getForeignKeyName('{{%craft_chat_conversations}}', 'userId'),
            '{{%craft_chat_conversations}}',
            'userId',
            '{{%users}}',
            'id',
            'SET NULL',
            'CASCADE'
        );
    }

    protected function dropTables()
    {
        $this->dropTableIfExists('{{%craft_chat_messages}}');
        $this->dropTableIfExists('{{%craft_chat_conversations}}');
    }

    protected function dropForeignKeys()
    {
        if (Craft::$app->db->schema->getTableSchema('{{%craft_chat_messages}}')) {
            $this->dropForeignKey(
                $this->db->getForeignKeyName('{{%craft_chat_messages}}', 'conversationId'),
                '{{%craft_chat_messages}}'
            );
        }

        if (Craft::$app->db->schema->getTableSchema('{{%craft_chat_conversations}}')) {
            $this->dropForeignKey(
                $this->db->getForeignKeyName('{{%craft_chat_conversations}}', 'userId'),
                '{{%craft_chat_conversations}}'
            );
        }
    }
}

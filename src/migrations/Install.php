<?php

namespace webhubworks\verifiedelements\migrations;

use craft\db\Migration;
use craft\db\Table as CraftTable;
use webhubworks\verifiedelements\db\PluginTable;

/**
 * Install migration.
 */
class Install extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        $this->createTable(PluginTable::ATTRIBUTES, [
            'id' => $this->primaryKey(),
            'elementId' => $this->integer()->notNull(),
            'siteId' => $this->integer()->notNull(),
            'reviewerId' => $this->integer()->null(),
            'verifiedUntilDate' => $this->dateTime()->null(),
        ]);

        $this->createIndex(null, PluginTable::ATTRIBUTES, ['elementId', 'siteId'], true);

        $this->addForeignKey(null, PluginTable::ATTRIBUTES, ['elementId'], CraftTable::ELEMENTS, ['id'], 'CASCADE');
        $this->addForeignKey(null, PluginTable::ATTRIBUTES, ['siteId'], CraftTable::SITES, ['id'], 'CASCADE');
        $this->addForeignKey(null, PluginTable::ATTRIBUTES, ['reviewerId'], CraftTable::USERS, ['id'], 'SET NULL');

        $this->createTable(PluginTable::CONTAINERS, [
            'id' => $this->primaryKey(),
            'containerId' => $this->integer()->notNull(),
            'elementType' => $this->string()->notNull(),
            'siteId' => $this->integer()->notNull(),
            'reviewerId' => $this->integer()->null(),
            'enabled' => $this->boolean()->defaultValue(false),
            'defaultPeriod' => $this->string()->null(),
        ]);

        $this->createIndex(null, PluginTable::CONTAINERS, ['containerId', 'siteId', 'elementType'], true);

        $this->addForeignKey(null, PluginTable::CONTAINERS, ['siteId'], CraftTable::SITES, ['id'], 'CASCADE');
        $this->addForeignKey(null, PluginTable::CONTAINERS, ['reviewerId'], CraftTable::USERS, ['id'], 'SET NULL');

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        $this->dropTableIfExists(PluginTable::ATTRIBUTES);
        $this->dropTableIfExists(PluginTable::CONTAINERS);

        return true;
    }
}

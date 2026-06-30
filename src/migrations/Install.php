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
        $this->createTable(PluginTable::ENTRIES, [
            'id' => $this->primaryKey(),
            'entryId' => $this->integer()->notNull(),
            'siteId' => $this->integer()->notNull(),
            'reviewerId' => $this->integer()->null(),
            'verifiedUntilDate' => $this->dateTime()->null(),
        ]);

        $this->createIndex(null, PluginTable::ENTRIES, ['entryId', 'siteId'], true);

        $this->addForeignKey(null, PluginTable::ENTRIES, ['entryId'], CraftTable::ENTRIES, ['id'], 'CASCADE');
        $this->addForeignKey(null, PluginTable::ENTRIES, ['siteId'], CraftTable::SITES, ['id'], 'CASCADE');
        $this->addForeignKey(null, PluginTable::ENTRIES, ['reviewerId'], CraftTable::USERS, ['id'], 'SET NULL');

        $this->createTable(PluginTable::SECTIONS, [
            'id' => $this->primaryKey(),
            'sectionId' => $this->integer()->notNull(),
            'siteId' => $this->integer()->notNull(),
            'reviewerId' => $this->integer()->null(),
            'enabled' => $this->boolean()->defaultValue(false),
            'defaultPeriod' => $this->string()->null(),
        ]);

        $this->createIndex(null, PluginTable::SECTIONS, ['sectionId', 'siteId'], true);

        $this->addForeignKey(null, PluginTable::SECTIONS, ['sectionId'], CraftTable::SECTIONS, ['id'], 'CASCADE');
        $this->addForeignKey(null, PluginTable::SECTIONS, ['siteId'], CraftTable::SITES, ['id'], 'CASCADE');
        $this->addForeignKey(null, PluginTable::SECTIONS, ['reviewerId'], CraftTable::USERS, ['id'], 'SET NULL');

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        $this->dropTableIfExists(PluginTable::ENTRIES);
        $this->dropTableIfExists(PluginTable::SECTIONS);

        return true;
    }
}

<?php

namespace webhubworks\verifiedentries\migrations;

use craft\db\Migration;
use craft\db\Table as CraftTable;
use webhubworks\verifiedentries\db\Table as PluginTables;

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
        $this->createTable(PluginTables::ENTRIES, [
            'id' => $this->primaryKey(),
            'entryId' => $this->integer()->notNull(),
            'siteId' => $this->integer()->notNull(),
            'reviewerId' => $this->integer()->null(),
            'verifiedUntilDate' => $this->dateTime()->null(),
        ]);

        $this->createIndex(null, PluginTables::ENTRIES, ['entryId', 'siteId'], true);

        $this->addForeignKey(null, PluginTables::ENTRIES, ['entryId'], CraftTable::ENTRIES, ['id'], 'CASCADE', null);
        $this->addForeignKey(null, PluginTables::ENTRIES, ['siteId'], CraftTable::SITES, ['id'], 'CASCADE', null);
        $this->addForeignKey(null, PluginTables::ENTRIES, ['reviewerId'], CraftTable::USERS, ['id'], 'SET NULL');

        $this->createTable(PluginTables::SECTIONS, [
            'id' => $this->primaryKey(),
            'sectionId' => $this->integer()->notNull(),
            'siteId' => $this->integer()->notNull(),
            'reviewerId' => $this->integer()->null(),
            'enabled' => $this->boolean()->defaultValue(false),
            'defaultPeriod' => $this->string()->null(),
        ]);

        $this->createIndex(null, PluginTables::SECTIONS, ['sectionId', 'siteId'], true);

        $this->addForeignKey(null, PluginTables::SECTIONS, ['sectionId'], CraftTable::SECTIONS, ['id'], 'CASCADE');
        $this->addForeignKey(null, PluginTables::SECTIONS, ['siteId'], CraftTable::SITES, ['id'], 'CASCADE');
        $this->addForeignKey(null, PluginTables::SECTIONS, ['reviewerId'], CraftTable::USERS, ['id'], 'SET NULL');

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        $this->dropTableIfExists(PluginTables::ENTRIES);
        $this->dropTableIfExists(PluginTables::SECTIONS);

        return true;
    }
}

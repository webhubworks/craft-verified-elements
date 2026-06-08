<?php

namespace webhubworks\verifiedentries\migrations;

use craft\db\Migration;
use craft\db\Query;
use craft\db\Table as CraftTable;
use webhubworks\verifiedentries\db\PluginTable;

class m260608_000000_add_multisite_support extends Migration
{
    public function safeUp(): bool
    {
        $primarySiteId = (int)(new Query())
            ->select(['id'])
            ->from(CraftTable::SITES)
            ->where(['primary' => true])
            ->scalar();

        // verifiedentries_entryattributes

        $entriesSchema = $this->db->getSchema()->getTableSchema(PluginTable::ENTRIES);
        foreach ($entriesSchema->foreignKeys as $fkName => $fk) {
            if (isset($fk['entryId'])) {
                $this->dropForeignKey($fkName, PluginTable::ENTRIES);
                break;
            }
        }

        $this->addColumn(PluginTable::ENTRIES, 'siteId', $this->integer()->null()->after('entryId'));
        $this->update(PluginTable::ENTRIES, ['siteId' => $primarySiteId]);
        $this->alterColumn(PluginTable::ENTRIES, 'siteId', $this->integer()->notNull());
        $this->dropIndex('entryId', PluginTable::ENTRIES);
        $this->createIndex(null, PluginTable::ENTRIES, ['entryId', 'siteId'], true);
        $this->addForeignKey(null, PluginTable::ENTRIES, ['entryId'], CraftTable::ENTRIES, ['id'], 'CASCADE');
        $this->addForeignKey(null, PluginTable::ENTRIES, ['siteId'], CraftTable::SITES, ['id'], 'CASCADE');

        // verifiedentries_sections

        $sectionsSchema = $this->db->getSchema()->getTableSchema(PluginTable::SECTIONS);
        foreach ($sectionsSchema->foreignKeys as $fkName => $fk) {
            if (isset($fk['sectionId'])) {
                $this->dropForeignKey($fkName, PluginTable::SECTIONS);
                break;
            }
        }

        $this->addColumn(PluginTable::SECTIONS, 'siteId', $this->integer()->null()->after('sectionId'));
        $this->update(PluginTable::SECTIONS, ['siteId' => $primarySiteId]);
        $this->alterColumn(PluginTable::SECTIONS, 'siteId', $this->integer()->notNull());
        $this->dropIndex('sectionId', PluginTable::SECTIONS);
        $this->createIndex(null, PluginTable::SECTIONS, ['sectionId', 'siteId'], true);
        $this->addForeignKey(null, PluginTable::SECTIONS, ['sectionId'], CraftTable::SECTIONS, ['id'], 'CASCADE');
        $this->addForeignKey(null, PluginTable::SECTIONS, ['siteId'], CraftTable::SITES, ['id'], 'CASCADE');

        return true;
    }

    public function safeDown(): bool
    {
        return false;
    }
}

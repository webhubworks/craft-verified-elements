<?php

namespace webhubworks\verifiedelements\migrations;

use craft\db\Migration;
use craft\db\Query;
use craft\db\Table as CraftTable;
use craft\elements\Entry;

/**
 * m260701_100838_generalize_to_verified_elements migration.
 */
class m260701_100838_generalize_to_verified_elements extends Migration
{
    private const OLD_STATE = '{{%verifiedentries_entryattributes}}';
    private const NEW_STATE = '{{%verifiedelements_attributes}}';
    private const OLD_SETTINGS = '{{%verifiedentries_sections}}';
    private const NEW_SETTINGS = '{{%verifiedelements_containers}}';

    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        $primarySiteId = (int)(new Query())
            ->select(['id'])
            ->from(CraftTable::SITES)
            ->where(['primary' => true])
            ->scalar();

        // ---- State table: verifiedentries_entryattributes -> verifiedelements_attributes ----

        // Drop entryId -> {{%entries}} FK (random name; discover). reviewerId -> {{%users}} FK is
        // left untouched and carries over.
        $stateSchema = $this->db->getSchema()->getTableSchema(self::OLD_STATE);
        foreach ($stateSchema->foreignKeys as $fkName => $fk) {
            if (isset($fk['entryId'])) {
                $this->dropForeignKey($fkName, self::OLD_STATE);
                break;
            }
        }

        // Multisite half (was m260608): add siteId, backfill primary site, lock notNull, add FK.
        $this->addColumn(self::OLD_STATE, 'siteId', $this->integer()->null()->after('entryId'));
        $this->update(self::OLD_STATE, ['siteId' => $primarySiteId]);
        $this->alterColumn(self::OLD_STATE, 'siteId', $this->integer()->notNull());
        $this->addForeignKey(null, self::OLD_STATE, ['siteId'], CraftTable::SITES, ['id'], 'CASCADE');

        // Replace the ORIGINAL single-column unique (`entryId`) with the composite, and generalise
        // the column + FK to {{%elements}}.
        $this->dropIndex('entryId', self::OLD_STATE);
        $this->renameColumn(self::OLD_STATE, 'entryId', 'elementId');
        $this->createIndex(null, self::OLD_STATE, ['elementId', 'siteId'], true);
        $this->addForeignKey(null, self::OLD_STATE, ['elementId'], CraftTable::ELEMENTS, ['id'], 'CASCADE');

        $this->renameTable(self::OLD_STATE, self::NEW_STATE);

        // ---- Settings table: verifiedentries_sections -> verifiedelements_containers ----

        $settingsSchema = $this->db->getSchema()->getTableSchema(self::OLD_SETTINGS);
        foreach ($settingsSchema->foreignKeys as $fkName => $fk) {
            if (isset($fk['sectionId'])) {
                $this->dropForeignKey($fkName, self::OLD_SETTINGS);
                break;
            }
        }

        $this->addColumn(self::OLD_SETTINGS, 'siteId', $this->integer()->null()->after('sectionId'));
        $this->update(self::OLD_SETTINGS, ['siteId' => $primarySiteId]);
        $this->alterColumn(self::OLD_SETTINGS, 'siteId', $this->integer()->notNull());
        $this->addForeignKey(null, self::OLD_SETTINGS, ['siteId'], CraftTable::SITES, ['id'], 'CASCADE');

        $this->dropIndex('sectionId', self::OLD_SETTINGS);
        $this->renameColumn(self::OLD_SETTINGS, 'sectionId', 'containerId');

        // Discriminator: add nullable, backfill existing rows to Entry, then lock notNull.
        $this->addColumn(self::OLD_SETTINGS, 'elementType', $this->string()->null()->after('containerId'));
        $this->update(self::OLD_SETTINGS, ['elementType' => Entry::class]);
        $this->alterColumn(self::OLD_SETTINGS, 'elementType', $this->string()->notNull());

        // Composite unique; NO FK on containerId (multi-target: section OR volume id).
        $this->createIndex(null, self::OLD_SETTINGS, ['containerId', 'siteId', 'elementType'], true);

        $this->renameTable(self::OLD_SETTINGS, self::NEW_SETTINGS);

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        // Destructive generalise + backfill isn't safely reversible. Matches m260608's precedent.
        return false;
    }
}

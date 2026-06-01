<?php

namespace webhubworks\verifiedentries\db;

/**
 * Class for managing static aspects of the database related to this plugin.
 */
abstract class PluginTable
{
    public const ENTRIES = '{{%verifiedentries_entryattributes}}';
    public const SECTIONS = '{{%verifiedentries_sections}}';
}
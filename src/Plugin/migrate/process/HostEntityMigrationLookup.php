<?php

namespace Drupal\registration\Plugin\migrate\process;

use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\Plugin\migrate\process\MigrationLookup;
use Drupal\migrate\Row;

/**
 * Migrates a host entity ID.
 *
 * Uses the host entity type to look up the ID in the appropriate migration.
 * This process plugin is necessary since the target type is unknown until
 * run time - the standard migration lookup requires the type to be known
 * in advance so it can be specified in the migration configuration file.
 *
 * @MigrateProcessPlugin(
 *   id = "host_entity_migration_lookup"
 * )
 */
class HostEntityMigrationLookup extends MigrationLookup {

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property): array|string|NULL {
    $entity_type_id = $row->getSourceProperty('entity_type');
    // Find migrations for the host entity type.
    switch ($entity_type_id) {
      case 'commerce_product':
        // Commerce 1 products became Commerce 2 product variations.
        $migrations = $this->getSourcePlugins('commerce1_product_variation:');
        $this->configuration['migration'] = $migrations;
        break;

      default:
        $migrations = $this->getSourcePlugins($entity_type_id . ':');
        $this->configuration['migration'] = $migrations;
    }

    return parent::transform($value, $migrate_executable, $row, $destination_property);
  }

  /**
   * Retrieve plugin IDs matching a scan mask.
   *
   * @param string $scan_mask
   *   The scan mask.
   *
   * @return array
   *   An array of plugin IDs.
   */
  protected function getSourcePlugins(string $scan_mask): array {
    $all_definitions = \Drupal::service('plugin.manager.migration')
      ->getDefinitions();
    $definitions = [];
    foreach ($all_definitions as $key => $value) {
      if (strpos($key, $scan_mask) !== FALSE) {
        $definitions[] = str_replace(':', '_', $key);
      }
    }
    return $definitions;
  }

}

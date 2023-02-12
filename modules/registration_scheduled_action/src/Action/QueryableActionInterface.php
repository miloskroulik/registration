<?php

namespace Drupal\registration_scheduled_action\Action;

use Drupal\Core\Database\Query\SelectInterface;
use Drupal\registration_scheduled_action\Entity\ScheduledActionInterface;

/**
 * Defines the interface for a queryable action plugin.
 */
interface QueryableActionInterface {

  /**
   * Gets the date field label.
   *
   * This is the display name of the date field that is used to drive the query
   * selection. This is shown in the scheduled action administrative listing in
   * the 'Date' column.
   *
   * @return string
   *   The date field label.
   */
  public function getDateFieldLabel(): string;

  /**
   * Gets the name of the collection used for key value store lookups.
   *
   * The key value store is used to determine whether a given action has
   * already processed a given object.
   *
   * Typically, this name should start with 'registration_scheduled_action' or
   * the name of a custom module implementing the plugin, followed by a period
   * and either an entity type ID or plugin ID. It should be unique across all
   * queryable action plugins in a given system.
   *
   * @return string
   *   The name of the collection.
   *
   * @see https://api.drupal.org/api/drupal/core%21lib%21Drupal.php/function/Drupal%3A%3AkeyValueExpirable/10
   */
  public function getKeyValueStoreCollectionName(): string;

  /**
   * Gets the query for the action.
   *
   * @param \Drupal\registration_scheduled_action\Entity\ScheduledActionInterface $scheduled_action
   *   The scheduled action.
   *
   * @return \Drupal\Core\Database\Query\SelectInterface
   *   The query used to select objects to which the action will be applied.
   */
  public function getQuery(ScheduledActionInterface $scheduled_action): SelectInterface;

  /**
   * Gets the name of the column holding the unique key for a given result row.
   *
   * This must match the name of a column retrieved by the query.
   *
   * This is often the name of the ID field for an entity type, although it can
   * be any column that is unique across all rows the query could retrieve.
   *
   * @return string
   *   The column name.
   */
  public function getQueryUniqueKeyColumnName(): string;

}

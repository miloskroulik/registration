<?php

namespace Drupal\registration;

use Drupal;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Link;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines the list builder for registrations.
 */
class RegistrationListBuilder extends EntityListBuilder {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type):RegistrationListBuilder {
    $instance = parent::createInstance($container, $entity_type);
    $instance->entityTypeManager = $container->get('entity_type.manager');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function buildHeader(): array {
    $header['id'] = $this->t('ID');
    $header['type'] = $this->t('Type');
    $header['user'] = $this->t('User');
    $header['spaces'] = $this->t('Spaces');
    $header['host'] = $this->t('Host');
    $header['status'] = $this->t('Status');
    $header['updated'] = $this->t('Updated');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity): array {
    /** @var \Drupal\registration\Entity\RegistrationInterface $entity */
    if ($user = $entity->getUser()) {
      $user = Link::fromTextAndUrl($user->getDisplayName(), $user->toUrl());
    }
    else {
      $user = $entity->getAnonymousEmail();
    }

    // Get the attached column value.
    $host = '';
    if ($entity->getHostEntityId() && $entity->getHostEntityTypeId()) {
      $storage = $this->entityTypeManager->getStorage($entity->getHostEntityTypeId());
      $host_entity = $storage->load($entity->getHostEntityId());
      if ($host_entity) {
        $host = Link::fromTextAndUrl($host_entity->label(), $host_entity->toUrl());
      }
    }

    $row['id'] = Link::fromTextAndUrl($entity->id(), $entity->toUrl());
    $row['type'] = $entity->getType()->label();
    $row['user'] = $user;
    $row['spaces'] = $entity->getSpacesReserved();
    $row['host'] = $host;
    $row['status'] = $entity->getState()->label();
    $row['updated'] = Drupal::service('date.formatter')->format($entity->getChangedTime(), 'short');

    return $row + parent::buildRow($entity);
  }

}

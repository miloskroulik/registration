<?php

namespace Drupal\registration;

use Drupal;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Link;
use Drupal\registration\Entity\RegistrationType;
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
    $header['attached'] = $this->t('Attached to');
    $header['status'] = $this->t('Status');
    $header['updated'] = $this->t('Updated');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity): array {
    // Get the user column value.
    $user = '';
    if (!$entity->get('anon_mail')->isEmpty()) {
      $user = $entity->get('anon_mail')->first()->value;
    }
    elseif (!$entity->get('user_uid')->isEmpty()) {
      $user_entity = $entity->get('user_uid')->first()->entity;
      $user = Link::fromTextAndUrl($user_entity->getDisplayName(), $user_entity->toUrl());
    }

    // Get the attached column value.
    $attached = '';
    if (!$entity->get('entity_type_id')->isEmpty() && !$entity->get('entity_id')->isEmpty()) {
      $storage = $this->entityTypeManager->getStorage($entity->get('entity_type_id')->first()->value);
      $attached_to_entity = $storage->load($entity->get('entity_id')->first()->value);
      if ($attached_to_entity) {
        $attached = Link::fromTextAndUrl($attached_to_entity->label(), $attached_to_entity->toUrl());
      }
    }

    /** @var \Drupal\registration\Entity\RegistrationInterface $entity */
    $registration_type = RegistrationType::load($entity->bundle());

    $row['id'] = Link::fromTextAndUrl($entity->id(), $entity->toUrl());
    $row['type'] = $registration_type->label();
    $row['user'] = $user;
    $row['attached'] = $attached;
    $row['status'] = $entity->getState()->label();
    $row['updated'] = Drupal::service('date.formatter')->format($entity->getChangedTime(), 'short');

    return $row + parent::buildRow($entity);
  }

}

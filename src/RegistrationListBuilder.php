<?php

namespace Drupal\registration;

use Drupal;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;
use Drupal\Core\Link;
use Drupal\registration\Entity\RegistrationType;

/**
 * Defines the list builder for registrations.
 */
class RegistrationListBuilder extends EntityListBuilder {

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

    /** @var \Drupal\registration\Entity\RegistrationInterface $entity */
    $registration_type = RegistrationType::load($entity->bundle());

    $row['id'] = Link::fromTextAndUrl($entity->id(), $entity->toUrl());
    $row['type'] = $registration_type->label();
    $row['user'] = $user;
    $row['attached'] = '';
    $row['status'] = $entity->getState()->label();
    $row['updated'] = Drupal::service('date.formatter')->format($entity->getChangedTime(), 'short');

    return $row + parent::buildRow($entity);
  }

}

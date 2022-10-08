<?php

namespace Drupal\registration\Event;

use Drupal\Component\EventDispatcher\Event;

/**
 * Defines the registration data alter event.
 *
 * This event is used to alter registration related data
 * such as status flags, usage counts and mail recipients.
 * In this way a site builder can make the registration
 * module more dynamic by integrating with third party
 * data sources or incorporating custom logic.
 *
 * @see \Drupal\registration\Event\RegistrationEvents
 */
class RegistrationDataAlterEvent extends Event {

  /**
   * The data value.
   *
   * @var bool
   */
  protected mixed $data;

  /**
   * The context in which the data was derived.
   *
   * @var array
   */
  protected array $context;

  /**
   * Constructs a new RegistrationDataAlterEvent.
   *
   * @param mixed $data
   *   The data value.
   * @param array $context
   *   The context in which the data was derived:
   *   [
   *     'host_entity' => \Drupal\registration\HostEntityInterface,
   *     'settings' => \Drupal\registration\Entity\RegistrationSettings,
   *     'registration' => \Drupal\registration\Entity\RegistrationInterface,
   *     'user' => \Drupal\user\UserInterface
   *   ]
   *   The host_entity and settings elements are usually present.
   *   The registration and user elements are only present for a few events.
   */
  public function __construct(mixed $data, array $context) {
    $this->data = $data;
    $this->context = $context;
  }

  /**
   * Gets the context.
   *
   * @return array
   *   The context.
   */
  public function getContext(): array {
    return $this->context;
  }

  /**
   * Gets the data.
   *
   * @return mixed
   *   The data.
   */
  public function getData(): mixed {
    return $this->data;
  }

  /**
   * Sets the data.
   *
   * @param mixed $data
   *   The new data value.
   */
  public function setData(mixed $data) {
    $this->data = $data;
  }

}

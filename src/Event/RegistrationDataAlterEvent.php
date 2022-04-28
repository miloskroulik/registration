<?php

namespace Drupal\registration\Event;

use Drupal\Component\EventDispatcher\Event;

/**
 * Defines the registration data alter event.
 *
 * @see \Drupal\registration\Event\RegistrationAlterEvents
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
   *     'host_entity' => \Drupal\Core\Entity\EntityInterface
   *     'settings' => \Drupal\registration\Entity\RegistrationSettings
   *   ]
   *
   *   For the REGISTRATION_ALTER_USAGE event, there is an additional context
   *   element: ['registration' => \Drupal\registration\Entity\Registration|null ]
   *   When set, the registration has been excluded from the calculation of
   *   spaces reserved while an existing registration is being edited.
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

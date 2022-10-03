<?php

namespace Drupal\registration\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;

/**
 * Validates a registration before it is saved.
 *
 * @Constraint(
 *   id = "RegistrationConstraint",
 *   label = @Translation("Validates a registration before it is saved", context = "Validation")
 * )
 */
class RegistrationConstraint extends Constraint {

  /**
   * Missing host entity.
   *
   * This should only occur if the host entity was deleted and a previously
   * created registration is being resaved.
   *
   * @var string
   */
  public string $noHostEntityMessage = "Missing host entity.";

  /**
   * Registrations are disabled.
   *
   * @var string
   */
  public string $disabledMessage = "Registration for %label is disabled.";

  /**
   * Exceeds maximum spaces per registration.
   *
   * @var string
   */
  public string $tooManySpacesMessage = "You may not register for more than 1 space.|You may not register for more than @count spaces.";

  /**
   * Would exceed event capacity.
   *
   * @var string
   */
  public string $noRoomMessage = "Sorry, unable to register for %label due to: insufficient spaces remaining.";

  /**
   * Registration is not open yet.
   *
   * Only thrown for a new registration.
   *
   * @var string
   */
  public string $notOpenYetMessage = "Registration for %label is not open yet.";

  /**
   * Registration is already closed.
   *
   * Only thrown for a new registration.
   *
   * @var string
   */
  public string $closedMessage = "Registration for %label is closed.";

  /**
   * Email address is already registered.
   *
   * Only thrown if the registration settings do not allow multiple
   * registrations per user.
   *
   * @var string
   */
  public string $emailAlreadyRegisteredMessage = "%mail is already registered for this event.";

  /**
   * You are already registered.
   *
   * Only thrown if the registration settings do not allow multiple
   * registrations per user.
   *
   * @var string
   */
  public string $youAreAlreadyRegisteredMessage = "You are already registered for this event.";

  /**
   * User is already registered.
   *
   * Only thrown if the registration settings do not allow multiple
   * registrations per user.
   *
   * @var string
   */
  public string $userAlreadyRegisteredMessage = "%user is already registered for this event.";

}

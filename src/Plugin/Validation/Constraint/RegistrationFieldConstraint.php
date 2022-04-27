<?php

namespace Drupal\registration\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;

/**
 * Checks if a value represents a valid oEmbed resource URL.
 *
 * @Constraint(
 *   id = "registration_field",
 *   label = @Translation("Registration field", context = "Validation")
 * )
 */
class RegistrationFieldConstraint extends Constraint {

  /**
   * If the user tries to add two registration fields to the same bundle.
   *
   * @var string
   */
  public string $disallowedCardinalityMessage = 'An entity can only have one registration field.';

  /**
   * If the user tries to add a regisration field to the registration entity type.
   *
   * @var string
   */
  public string $disallowedTargetMessage = 'A registration field cannot be added to a registration type.';

  /**
   * If the target entity type does not have an "id" key in its annotation.
   *
   * @var string
   */
  public string $missingIdKeyMessage = 'A registration field can only be added to an entity with an "id" key.';

}

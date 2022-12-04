<?php

namespace Drupal\registration_workflow\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Routing\Access\AccessInterface;
use Drupal\Core\Routing\RouteMatch;
use Drupal\Core\Session\AccountInterface;
use Drupal\registration_workflow\StateTransitionValidationInterface;

/**
 * Checks access for the state transition route.
 */
class StateTransitionAccessCheck implements AccessInterface {

  /**
   * The state transition validator.
   *
   * @var \Drupal\registration_workflow\StateTransitionValidationInterface
   */
  protected StateTransitionValidationInterface $transitionValidator;

  /**
   * StateTransitionAccessCheck constructor.
   *
   * @param \Drupal\registration_workflow\StateTransitionValidationInterface $transition_validator
   *   The state transition validator.
   */
  public function __construct(StateTransitionValidationInterface $transition_validator) {
    $this->transitionValidator = $transition_validator;
  }

  /**
   * A custom access check.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   Run access checks for this account.
   * @param \Drupal\Core\Routing\RouteMatch $route_match
   *   Run access checks for this route.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function access(AccountInterface $account, RouteMatch $route_match): AccessResultInterface {
    $parameters = $route_match->getParameters();
    if ($parameters->has('registration') && $parameters->has('transition')) {
      /** @var \Drupal\registration\Entity\RegistrationInterface $registration */
      $entity = $parameters->get('registration');
      $transition = $parameters->get('transition');
      $workflow = $entity->getWorkflow();

      // Retrieving a transition could throw an exception, so must use a try
      // catch block here.
      try {
        $transition = $workflow
          ->getTypePlugin()
          ->getTransition($transition);

        // Ensure there is a valid transition from the registration current
        // state to the requested new state. The transition validator also
        // checks that the account has permission to perform the transition.
        $valid = $this->transitionValidator
          ->isTransitionValid($workflow, $entity->getState(), $transition->to(), $entity, $account);
        return AccessResult::allowedIf($valid)
          // Recalculate this result if the relevant entities are updated.
          ->cachePerPermissions()
          ->addCacheableDependency($workflow)
          ->addCacheableDependency($entity);
      }

      // Handle an invalid transition name.
      catch (\Exception $e) {
        return AccessResult::forbidden("The transition does not exist in the registration workflow.")
          // Recalculate this result if the relevant entities are updated.
          ->cachePerPermissions()
          ->addCacheableDependency($workflow)
          ->addCacheableDependency($entity);
      }
    }

    return AccessResult::neutral();
  }

}

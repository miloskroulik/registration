<?php

namespace Drupal\registration\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\registration\RegistrationManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Returns responses for Registration routes.
 */
class RegistrationController extends ControllerBase {

  /**
   * The registration manager.
   *
   * @var \Drupal\registration\RegistrationManagerInterface
   */
  protected RegistrationManagerInterface $registrationManager;

  /**
   * Creates a new RegistrationController instance.
   *
   * @param \Drupal\registration\RegistrationManagerInterface $registration_manager
   *   The registration manager.
   */
  public function __construct(RegistrationManagerInterface $registration_manager) {
    $this->registrationManager = $registration_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): RegistrationController {
    return new static($container->get('registration.manager'));
  }

  /**
   * Displays the Manage Registrations task.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request.
   *
   * @return array
   *   A render array as expected by drupal_render().
   */
  public function manageRegistrations(Request $request): array {
    $build = [];
    if ($entity = $this->registrationManager->getEntityFromParameters($request->attributes)) {
      $build = [
        '#type' => 'markup',
        '#markup' => $this->t('This is some content for the manage registrations task for @label.', [
          '@label' => $entity->label(),
        ]),
      ];
    }

    return $build;
  }

}

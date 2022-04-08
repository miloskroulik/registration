<?php

namespace Drupal\registration\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\registration\RegistrationServiceInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Returns responses for Registration routes.
 */
class RegistrationController extends ControllerBase {

  /**
   * The registration service.
   *
   * @var \Drupal\registration\RegistrationServiceInterface
   */
  protected RegistrationServiceInterface $registration;

  /**
   * Creates a new RegistrationController instance.
   *
   * @param \Drupal\registration\RegistrationServiceInterface $registration_service
   *   The registration service.
   */
  public function __construct(RegistrationServiceInterface $registration_service) {
    $this->registration = $registration_service;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): RegistrationController {
    return new static($container->get('registration.service'));
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
    if ($entity = $this->registration->getEntityFromParameters($request->attributes)) {
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

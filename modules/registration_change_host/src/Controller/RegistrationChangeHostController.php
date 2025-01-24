<?php

namespace Drupal\registration_change_host\Controller;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Controller\ControllerBase;
use Drupal\registration\Entity\RegistrationInterface;
use Drupal\registration_change_host\RegistrationChangeHostManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Returns responses for Registration Change Host routes.
 */
class RegistrationChangeHostController extends ControllerBase {

  /**
   * The registration change host manager.
   *
   * @var \Drupal\registration_change_host\RegistrationChangeHostManagerInterface
   */
  protected RegistrationChangeHostManagerInterface $registrationChangeHostManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): RegistrationChangeHostController {
    $instance = parent::create($container);
    $instance->registrationChangeHostManager = $container->get('registration_change_host.manager');
    return $instance;
  }

  /**
   * Displays the available hosts a registration can change to.
   *
   * Redirects if there are no candidates other than the current host.
   *
   * @param \Drupal\registration\Entity\RegistrationInterface $registration
   *   The registration.
   *
   * @return array|\Symfony\Component\HttpFoundation\RedirectResponse
   *   Return a render array showing each possible host.
   *   Classes that extend this method may return a redirect response.
   */
  public function changeHostPage(RegistrationInterface $registration): array|RedirectResponse {
    $set = $this->registrationChangeHostManager->getPossibleHosts($registration);

    if (!$set->hasAvailableHosts()) {
      \Drupal::messenger()->addMessage($this->t('There is nothing available to change to.'));
    }

    $build = [
      '#theme' => 'registration_change_host_list',
      '#set' => $set,
    ];

    // Merge metadata from the possible host set.
    $build_metadata = CacheableMetadata::createFromObject($set);
    // Merge metadata from each individual possible host.
    /** @var \Drupal\registration_change_host\PossibleHostEntityInterface $host */
    foreach ($set->getHosts() as $host) {
      $host_metadata = CacheableMetadata::createFromObject($host);
      $build_metadata = $build_metadata->merge($host_metadata);
    }
    $build_metadata->applyTo($build);

    return $build;
  }

  /**
   * Provides the title for the change host page.
   *
   * @param \Drupal\registration\Entity\RegistrationInterface $registration
   *   The registration.
   *
   * @return string
   *   The title.
   */
  public function title(RegistrationInterface $registration) {
    $host_type_label = $registration->getHostEntityTypeLabel();
    return (string) $this->t('Select @host_type_label', ['@host_type_label' => $host_type_label]);
  }

}

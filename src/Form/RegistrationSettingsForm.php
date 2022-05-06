<?php

namespace Drupal\registration\Form;

use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItemInterface;
use Drupal\registration\HostEntityInterface;
use Drupal\registration\RegistrationManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines the registration settings form.
 */
class RegistrationSettingsForm extends ContentEntityForm {

  /**
   * The registration manager.
   *
   * @var \Drupal\registration\RegistrationManagerInterface
   */
  protected RegistrationManagerInterface $registrationManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): RegistrationSettingsForm {
    $instance = parent::create($container);
    $instance->registrationManager = $container->get('registration.manager');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state): array {
    $form = parent::form($form, $form_state);

    if ($this->moduleHandler->moduleExists('token')) {
      if (!empty($form['reminder_template'])) {
        $form['reminder_template']['token_tree'] = [
          '#theme' => 'token_tree_link',
          '#token_types' => [
            'registration',
            'registration_settings',
          ],
          '#global_types' => FALSE,
          '#weight' => 10,
        ];
        if ($host_entity = $this->getHostEntity($form_state)) {
          $form['reminder_template']['token_tree']['#token_types'][] = $host_entity->getEntityTypeId();
        }
      }
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);

    $send_reminder = $form_state->getValue(['send_reminder', 'value']);
    $reminder_date = $form_state->getValue(['reminder_date', 0, 'value']);
    $reminder_template = $form_state->getValue(['reminder_template', 0, 'value']);
    $redirect = $form_state->getValue(['confirmation_redirect', 0, 'value']);

    // If sending a reminder, ensure date and template are set.
    if ($send_reminder && empty($reminder_date)) {
      $form_state->setError(
        $form['reminder_date'], $this->t('If sending a reminder, provide a date and template.'));
    }
    if ($send_reminder && empty($reminder_template)) {
      $form_state->setError(
        $form['reminder_template'], $this->t('If sending a reminder, provide a date and template.'));
    }

    // Ensure reminder date is not in the past when "send_reminder" is TRUE:
    if ($send_reminder && !empty($reminder_date)) {
      if ($reminder_date instanceof DrupalDateTime) {
        // Ensure dates are compared using the storage timezone for both.
        $storage_timezone = new \DateTimeZone(DateTimeItemInterface::STORAGE_TIMEZONE);
        $reminder_date->setTimezone($storage_timezone);
        $now = new DrupalDateTime('now', $storage_timezone);
        if ($reminder_date <= $now) {
          $form_state->setError($form['reminder_date'], $this->t('Reminder must be in the future.'));
        }
      }
    }

    // If a redirect is set must either be external or start with a slash.
    if (!empty($redirect)) {
      if (!UrlHelper::isExternal($redirect) && ($redirect[0] != '/')) {
        $form_state->setError($form['confirmation_redirect'], $this->t('Confirmation redirect path must be a valid URL. Internal paths must start with a forward slash.'));
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state): int {
    $return = parent::save($form, $form_state);
    $this->messenger()->addStatus($this->t('The settings have been saved.'));
    return $return;
  }

  /**
   * Gets the unwrapped host entity.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return \Drupal\registration\HostEntityInterface|null
   *   The host entity.
   */
  protected function getHostEntity(FormStateInterface $form_state): ?HostEntityInterface {
    $host_entity = $form_state->get('host_entity');
    if (!$host_entity) {
      $route_match = $this->getRouteMatch();
      $host_entity = $this->registrationManager->getEntityFromParameters($route_match->getParameters(), TRUE);
      $form_state->set('host_entity', $host_entity);
    }
    return $host_entity;
  }

  /**
   * {@inheritdoc}
   */
  public function getEntityFromRouteMatch(RouteMatchInterface $route_match, $entity_type_id) {
    if ($route_match->getRawParameter($entity_type_id) !== NULL) {
      $settings_entity = $route_match->getParameter($entity_type_id);
    }
    else {
      // Fetch settings entity from the host entity.
      $host_entity = $this->registrationManager->getEntityFromParameters($route_match->getParameters(), TRUE);
      /** @var \Drupal\registration\RegistrationSettingsStorage $storage */
      $storage = $this->entityTypeManager->getStorage($entity_type_id);
      $settings_entity = $storage->loadSettingsForHostEntity($host_entity);
    }

    return $settings_entity;
  }

}

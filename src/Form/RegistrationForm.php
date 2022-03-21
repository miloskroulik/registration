<?php

namespace Drupal\registration\Form;

use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines the registration add/edit form.
 */
class RegistrationForm extends ContentEntityForm {

  /**
   * The date formatter.
   *
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  protected DateFormatterInterface $dateFormatter;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): RegistrationForm {
    $instance = parent::create($container);
    $instance->dateFormatter = $container->get('date.formatter');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    /** @var \Drupal\registration\Entity\RegistrationInterface $registration */
    $registration = $this->entity;
    $form = parent::form($form, $form_state);

    $form['#tree'] = TRUE;
    $form['#theme'] = ['registration_form'];
    $form['#attached']['library'][] = 'registration/form';
    // Changed must be sent to the client, for later overwrite error checking.
    $form['changed'] = [
      '#type' => 'hidden',
      '#default_value' => $registration->getChangedTime(),
    ];
    $form['state']['#group'] = 'footer';

    $last_saved = $this->t('Not saved yet');
    if (!$registration->isNew()) {
      $last_saved = $this->dateFormatter->format($registration->getChangedTime(), 'short');
    }
    $form['meta'] = [
      '#attributes' => ['class' => ['entity-meta__header']],
      '#type' => 'container',
      '#group' => 'advanced',
      '#weight' => -100,
      'state' => [
        '#type' => 'html_tag',
        '#tag' => 'h3',
        '#value' => $registration->getState()->label(),
        '#access' => !$registration->isNew(),
        '#attributes' => [
          'class' => ['entity-meta__title'],
        ],
      ],
      'changed' => [
        '#type' => 'item',
        '#wrapper_attributes' => [
          'class' => ['entity-meta__last-saved', 'container-inline'],
        ],
        '#markup' => '<h4 class="label inline">' . $this->t('Last saved') . '</h4> ' . $last_saved,
      ],
      /*'author' => [
        '#type' => 'item',
        '#wrapper_attributes' => [
          'class' => ['author', 'container-inline'],
        ],
        '#markup' => '<h4 class="label inline">' . $this->t('Author') . '</h4> ' . $product->getOwner()->getDisplayName(),
      ],*/
    ];
    $form['advanced'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['entity-meta']],
      '#weight' => 99,
    ];
    $form['author'] = [
      '#type' => 'details',
      '#title' => $this->t('Authoring information'),
      '#group' => 'advanced',
      '#attributes' => [
        'class' => ['registration-form-author'],
      ],
      '#weight' => 90,
      '#optional' => TRUE,
    ];
    if (isset($form['author_uid'])) {
      $form['author_uid']['#group'] = 'author';
    }
    if (isset($form['created'])) {
      $form['created']['#group'] = 'author';
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function save(array $form, FormStateInterface $form_state) {
    /** @var \Drupal\registration\Entity\RegistrationInterface $registration */
    $registration = $this->getEntity();
    $registration->save();
    $this->messenger()->addMessage($this->t('The registration has been successfully saved.', ['%label' => $registration->label()]));
    $form_state->setRedirect('entity.registration.canonical', ['registration' => $registration->id()]);
  }

}

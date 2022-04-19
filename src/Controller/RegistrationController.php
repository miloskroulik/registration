<?php

namespace Drupal\registration\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Query\PagerSelectExtender;
use Drupal\Core\Database\Query\TableSortExtender;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Link;
use Drupal\Core\Routing\RedirectDestinationTrait;
use Drupal\Core\Url;
use Drupal\registration\Entity\RegistrationSettings;
use Drupal\registration\RegistrationManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Returns responses for Registration routes.
 */
class RegistrationController extends ControllerBase {

  use RedirectDestinationTrait;

  /**
   * The database service.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected Connection $database;

  /**
   * The date formatter service.
   *
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  protected DateFormatterInterface $dateFormatter;

  /**
   * The registration manager.
   *
   * @var \Drupal\registration\RegistrationManagerInterface
   */
  protected RegistrationManagerInterface $registrationManager;

  /**
   * Constructs a RegistrationController object.
   *
   * @param \Drupal\Core\Database\Connection $database
   *   A database connection.
   * @param \Drupal\Core\Datetime\DateFormatterInterface $date_formatter
   *   The date formatter service.
   * @param \Drupal\registration\RegistrationManagerInterface $registration_manager
   *   The registration manager.
   */
  public function __construct(Connection $database, DateFormatterInterface $date_formatter, RegistrationManagerInterface $registration_manager) {
    $this->database = $database;
    $this->dateFormatter = $date_formatter;
    $this->registrationManager = $registration_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): RegistrationController {
    return new static(
      $container->get('database'),
      $container->get('date.formatter'),
      $container->get('registration.manager')
    );
  }

  /**
   * Displays the Manage Registrations task.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request.
   *
   * @return array
   *   A render array as expected by drupal_render().
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityMalformedException
   */
  public function manageRegistrations(Request $request): array {
    $build = [];
    if ($entity = $this->registrationManager->getEntityFromParameters($request->attributes)) {
      $settings = $this->registrationManager->getSettingsForHost($entity);
      $build = $this->buildRegistrationsList($entity, $settings);
    }
    return $build;
  }

  /**
   * Displays the Manage Registrations task.
   *
   * @param \Drupal\Core\Entity\EntityInterface $host_entity
   *   The host entity.
   * @param \Drupal\registration\Entity\RegistrationSettings $settings
   *   The registration settings entity.
   *
   * @return array
   *   A render array as expected by drupal_render().
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityMalformedException
   */
  protected function buildRegistrationsList(EntityInterface $host_entity, RegistrationSettings $settings): array {
    $capacity = $this->registrationManager->getRegistrationSetting($host_entity, $settings, 'capacity');
    $count =  $this->registrationManager->getActiveRegistrationCount($host_entity, $settings);
    if ($capacity) {
      $caption = $this->t('List of registrations for %title. @count of @capacity spaces are filled.', [
        '%title' => $host_entity->label(),
        '@capacity' => $capacity,
        '@count' => $count,
      ]);
    }
    else {
      $caption = $this->t('List of registrations for %title. @count spaces are filled.', [
        '%title' => $host_entity->label(),
        '@count' => $count,
      ]);
    }

    $header = [
      [
        'data' => $this->t('Id'),
        'field' => 'r.registration_id',
        'class' => [RESPONSIVE_PRIORITY_LOW],
      ],
      [
        'data' => $this->t('Email'),
        'class' => [RESPONSIVE_PRIORITY_LOW],
      ],
      [
        'data' => $this->t('User'),
        'field' => 'r.user_uid',
        'class' => [RESPONSIVE_PRIORITY_MEDIUM],
      ],
      [
        'data' => $this->t('Created By'),
        'field' => 'r.author_uid',
        'class' => [RESPONSIVE_PRIORITY_LOW],
      ],
      [
        'data' => $this->t('Spaces'),
        'field' => 'r.count',
        'class' => [RESPONSIVE_PRIORITY_MEDIUM],
        'initial_click_sort' => 'desc',
      ],
      [
        'data' => $this->t('Created'),
        'field' => 'r.created',
        'sort' => 'desc',
        'class' => [RESPONSIVE_PRIORITY_MEDIUM],
      ],
      [
        'data' => $this->t('Status'),
        'field' => 'r.state',
        'class' => [RESPONSIVE_PRIORITY_MEDIUM],
      ],
      [
        'data' => $this->t('Operations'),
        'class' => [RESPONSIVE_PRIORITY_LOW],
      ],
    ];

    $rows = [];

    $registration_storage = $this->entityTypeManager()->getStorage('registration');
    $user_storage = $this->entityTypeManager()->getStorage('user');

    /** @var \Drupal\Core\Database\Query\TableSortExtender $query */
    $query = $this->database->select('registration', 'r')
      ->extend(PagerSelectExtender::class)
      ->extend(TableSortExtender::class);
    $query->fields('r', [
      'registration_id',
      'anon_mail',
      'count',
      'user_uid',
      'author_uid',
      'state',
      'created',
    ]);
    $query->condition('r.entity_type_id', $host_entity->getEntityTypeId());
    $query->condition('r.entity_id', $host_entity->id());
    $result = $query
      ->limit(20)
      ->orderByHeader($header)
      ->execute();

    foreach ($result as $registration) {
      /** @var \Drupal\registration\Entity\RegistrationInterface $registration_entity */
      $registration_entity = $registration_storage->load($registration->registration_id);

      // Registration ID.
      $id = Link::fromTextAndUrl($registration->registration_id, $registration_entity->toUrl());

      // User and Email.
      $email = NULL;
      $username = NULL;
      $authorname = NULL;
      if ($registration->user_uid) {
        /** @var \Drupal\user\UserInterface $user */
        $user = $user_storage->load($registration->user_uid);
        $username = [
          '#theme' => 'username',
          '#account' => $user,
        ];
        $email = $user->getEmail();
      }
      elseif ($registration->anon_mail) {
        $email = $registration->anon_mail;
      }

      // Author.
      if ($registration->author_uid) {
        $authorname = [
          '#theme' => 'username',
          '#account' => $user_storage->load($registration->author_uid),
        ];
      }

      // Created.
      $date = $this->dateFormatter->format($registration->created, 'short');

      $rows[] = [
        'data' => [
          // Cells.
          ['data' => $id],
          ['data' => $email],
          ['data' => $username],
          ['data' => $authorname],
          ['data' => $registration->count],
          ['data' => $date],
          ['data' => $registration_entity->getState()->label()],
          ['data' => $this->getOperations($registration_entity)],
        ],
      ];
    }

    $build['registration_table'] = [
      '#type' => 'table',
      '#caption' => $caption,
      '#header' => $header,
      '#rows' => $rows,
      '#empty' => $this->t('There are no registrants for %name', [
        '%name' => $host_entity->label(),
      ]),
    ];
    $build['registration_pager'] = ['#type' => 'pager'];

    return $build;
  }

  /**
   * Get the entity operations as a render array.
   *
   * @param \Drupal\Core\Entity\EntityInterface $host_entity
   *   The host entity.
   *
   * @return array
   *   A render array as expected by drupal_render().
   * @throws \Drupal\Core\Entity\EntityMalformedException
   */
  protected function getOperations(EntityInterface $host_entity): array {
    $prefix = FALSE;
    $operations = [];
    if ($host_entity->access('view') && $host_entity->hasLinkTemplate('canonical')) {
      $operations['view'] = [
        '#type' => 'link',
        '#title' => $this->t('View'),
        '#url' => $this->ensureDestination($host_entity->toUrl()),
      ];
      $prefix = TRUE;
    }
    if ($host_entity->access('update') && $host_entity->hasLinkTemplate('edit-form')) {
      $operations['edit'] = [
        '#type' => 'link',
        '#title' => $this->t('Edit'),
        '#url' => $this->ensureDestination($host_entity->toUrl('edit-form')),
        '#prefix' => $prefix ? ' | ' : '',
      ];
      $prefix = TRUE;
    }
    if ($host_entity->access('delete') && $host_entity->hasLinkTemplate('delete-form')) {
      $operations['delete'] = [
        '#type' => 'link',
        '#title' => $this->t('Delete'),
        '#url' => $this->ensureDestination($host_entity->toUrl('delete-form')),
        '#prefix' => $prefix ? ' | ' : '',
      ];
    }
    return $operations;
  }

  /**
   * Update a URL with a destination.
   *
   * @param \Drupal\Core\Url $url
   *   The url.
   *
   * @return \Drupal\Core\Url
   *   The updated URL object.
   */
  protected function ensureDestination(Url $url): Url {
    return $url
      ->mergeOptions([
      'query' => $this
        ->getRedirectDestination()
        ->getAsArray(),
    ]);
  }

}

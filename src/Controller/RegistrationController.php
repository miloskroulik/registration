<?php

namespace Drupal\registration\Controller;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Query\PagerSelectExtender;
use Drupal\Core\Database\Query\TableSortExtender;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Link;
use Drupal\Core\Render\Renderer;
use Drupal\Core\Routing\RedirectDestinationTrait;
use Drupal\Core\Url;
use Drupal\registration\Entity\RegistrationInterface;
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
   * The renderer.
   *
   * @var \Drupal\Core\Render\Renderer
   */
  protected Renderer $renderer;

  /**
   * Constructs a RegistrationController object.
   *
   * @param \Drupal\Core\Database\Connection $database
   *   A database connection.
   * @param \Drupal\Core\Datetime\DateFormatterInterface $date_formatter
   *   The date formatter service.
   * @param \Drupal\registration\RegistrationManagerInterface $registration_manager
   *   The registration manager.
   * @param \Drupal\Core\Render\Renderer $renderer
   *   The renderer.
   */
  public function __construct(Connection $database, DateFormatterInterface $date_formatter, RegistrationManagerInterface $registration_manager, Renderer $renderer) {
    $this->database = $database;
    $this->dateFormatter = $date_formatter;
    $this->registrationManager = $registration_manager;
    $this->renderer = $renderer;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): RegistrationController {
    return new static(
      $container->get('database'),
      $container->get('date.formatter'),
      $container->get('registration.manager'),
      $container->get('renderer')
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
    if ($host_entity = $this->registrationManager->getEntityFromParameters($request->attributes)) {
      $settings = $this->registrationManager->getSettingsForHost($host_entity);

      // Use the built-in manage registrations view if available.
      $view = NULL;
      if ($this->moduleHandler()->moduleExists('views')) {
        if ($view = $this->entityTypeManager()->getStorage('view')->load('manage_registrations')) {
          $display = 'block_1';
          if ($view->getExecutable()->access($display)) {
            $build = [
              '#type' => 'view',
              '#name' => 'manage_registrations',
              '#display_id' => $display,
              '#arguments' => [
                $host_entity->getEntityTypeId(),
                $host_entity->id(),
              ],
            ];
            $build['#attached']['library'][] = 'registration/manage_registrations';
          }
        }
      }

      // Fallback to data table.
      if (empty($build)) {
        $type = $this->registrationManager->getRegistrationTypeBundle($host_entity);
        $access_result = AccessResult::allowedIfHasPermissions($this->currentUser(), [
          "administer registration",
          "view any registration",
          "view any $type registration",
        ], 'OR');

        if ($access_result->isAllowed()) {
          $build = $this->buildDataTable($host_entity, $settings);
        }
        else {
          // The user cannot view registrations, so show a summary instead.
          $build = $this->buildSummary($host_entity, $settings);
        }
      }

      // Set cache directives so the form rebuilds when needed.
      $this->addCacheableDependencies($build, $host_entity, $settings);

      // If the view was retrieved, rebuild when it is updated.
      if ($view) {
        $this->renderer->addCacheableDependency($build, $view);
      }
    }

    return $build;
  }

  /**
   * Builds the Manage Registrations data table.
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
  protected function buildDataTable(EntityInterface $host_entity, RegistrationSettings $settings): array {
    $capacity = $this->registrationManager->getRegistrationSetting($host_entity, $settings, 'capacity');
    $spaces =  $this->registrationManager->getActiveRegistrationCount($host_entity, $settings);
    if ($capacity) {
      $caption = $this->formatPlural($capacity,
       'List of registrations for %title. @spaces of 1 space is filled.',
       'List of registrations for %title. @spaces of @count spaces are filled.', [
        '%title' => $host_entity->label(),
        '@capacity' => $capacity,
        '@spaces' => $spaces,
      ]);
    }
    else {
      $caption = $this->formatPlural($spaces,
       'List of registrations for %title. 1 space is filled.',
       'List of registrations for %title. @count spaces are filled.', [
        '%title' => $host_entity->label(),
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
    $query->addTag('registration_access');
    $result = $query
      ->limit(20)
      ->orderByHeader($header)
      ->execute();

    foreach ($result as $record) {
      /** @var \Drupal\registration\Entity\RegistrationInterface $registration */
      $registration = $registration_storage->load($record->registration_id);

      // User.
      if ($user = $registration->getUser()) {
        $user = [
          '#theme' => 'username',
          '#account' => $user,
        ];
      }
      // Author.
      if ($author = $registration->getAuthor()) {
        $author = [
          '#theme' => 'username',
          '#account' => $author,
        ];
      }
      else {
        // No author entity, this returns Anonymous.
        // This case occurs for an anonymous self registration.
        $author = $registration->getAuthorDisplayName();
      }

      $rows[] = [
        'data' => [
          ['data' => Link::fromTextAndUrl($registration->id(), $registration->toUrl())],
          ['data' => $registration->getEmail()],
          ['data' => $user],
          ['data' => $author],
          ['data' => $registration->getSpacesReserved()],
          ['data' => $this->dateFormatter->format($registration->getCreatedTime(), 'short')],
          ['data' => $registration->getState()->label()],
          ['data' => $this->getOperations($registration)],
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
   * Builds the Manage Registrations data table as a simple summary.
   *
   * @param \Drupal\Core\Entity\EntityInterface $host_entity
   *   The host entity.
   * @param \Drupal\registration\Entity\RegistrationSettings $settings
   *   The registration settings entity.
   *
   * @return array
   *   A render array as expected by drupal_render().
   */
  protected function buildSummary(EntityInterface $host_entity, RegistrationSettings $settings): array {
    $capacity = $this->registrationManager->getRegistrationSetting($host_entity, $settings, 'capacity');
    $spaces =  $this->registrationManager->getActiveRegistrationCount($host_entity, $settings);
    if ($capacity) {
      $caption = $this->formatPlural($capacity,
       'Registration summary for %title: @spaces of 1 space is filled.',
       'Registration summary for %title: @spaces of @count spaces are filled.', [
        '%title' => $host_entity->label(),
        '@capacity' => $capacity,
        '@spaces' => $spaces,
      ]);
    }
    else {
      $caption = $this->formatPlural($spaces,
       'Registration summary for %title: 1 space is filled.',
       'Registration summary for %title: @count spaces are filled.', [
        '%title' => $host_entity->label(),
      ]);
    }
    $build['registration_table'] = [
      '#markup' => $caption,
    ];
    return $build;
  }

  /**
   * Adds cache directives to the form.
   *
   * @param array $build
   *   The render array for the table.
   * @param \Drupal\registration\Entity\RegistrationSettings $settings
   *   The registration settings.
   */
  protected function addCacheableDependencies(array &$build, EntityInterface $host_entity, RegistrationSettings $settings) {
    // Rebuild if the relevant entities are updated.
    $this->renderer->addCacheableDependency($build, $host_entity);
    $this->renderer->addCacheableDependency($build, $settings);

    // Rebuild when permissions change.
    $build['#cache']['contexts'][] = 'user.permissions';

    // Rebuild when registrations are added and deleted.
    // @todo Implement a custom tag specific to the list for one host entity.
    $build['#cache']['tags'][] = 'registration_list';
  }

  /**
   * Get the entity operations for a given registration.
   *
   * @param \Drupal\registration\Entity\RegistrationInterface $registration
   *   The registration.
   *
   * @return array
   *   A render array as expected by drupal_render().
   * @throws \Drupal\Core\Entity\EntityMalformedException
   */
  protected function getOperations(RegistrationInterface $registration): array {
    $prefix = FALSE;
    $operations = [];
    if ($registration->access('view') && $registration->hasLinkTemplate('canonical')) {
      $operations['view'] = [
        '#type' => 'link',
        '#title' => $this->t('View'),
        '#url' => $this->ensureDestination($registration->toUrl()),
      ];
      $prefix = TRUE;
    }
    if ($registration->access('update') && $registration->hasLinkTemplate('edit-form')) {
      $operations['edit'] = [
        '#type' => 'link',
        '#title' => $this->t('Edit'),
        '#url' => $this->ensureDestination($registration->toUrl('edit-form')),
        '#prefix' => $prefix ? ' | ' : '',
      ];
      $prefix = TRUE;
    }
    if ($registration->access('delete') && $registration->hasLinkTemplate('delete-form')) {
      $operations['delete'] = [
        '#type' => 'link',
        '#title' => $this->t('Delete'),
        '#url' => $this->ensureDestination($registration->toUrl('delete-form')),
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

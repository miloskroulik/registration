<?php

namespace Drupal\registration;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides registration entity permissions.
 */
class RegistrationPermissionProvider implements ContainerInjectionInterface {

  use StringTranslationTrait;

  /**
   * The entity type bundle info.
   *
   * @var \Drupal\Core\Entity\EntityTypeBundleInfoInterface
   */
  protected EntityTypeBundleInfoInterface $entityTypeBundleInfo;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * Constructs a new RegistrationPermissionProvider object.
   *
   * @param \Drupal\Core\Entity\EntityTypeBundleInfoInterface $entity_type_bundle_info
   *   The entity type bundle info.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(EntityTypeBundleInfoInterface $entity_type_bundle_info, EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeBundleInfo = $entity_type_bundle_info;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('entity_type.bundle.info'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * Build permissions.
   *
   * @return array
   *   The list of permissions.
   */
  public function buildPermissions(): array {
    $entity_type = $this->entityTypeManager->getDefinition('registration');
    $entity_type_id = $entity_type->id();
    $plural_label = $entity_type->getPluralLabel();

    $permissions = [];

    $admin_permission = $entity_type->getAdminPermission() ?: "administer $entity_type_id";
    $permissions[$admin_permission] = [
      'title' => $this->t('Administer @type', ['@type' => $plural_label]),
      'restrict access' => TRUE,
    ];
    if ($entity_type->hasLinkTemplate('collection')) {
      $permissions["access $entity_type_id overview"] = [
        'title' => $this->t('Access the @type overview page', ['@type' => $plural_label]),
      ];
    }

    // Generate the other permissions.
    $permissions += $this->buildBundlePermissions($entity_type);
    return $this->processPermissions($permissions, $entity_type);
  }

  /**
   * Adds the provider and converts the titles to strings to allow sorting.
   *
   * @param array $permissions
   *   The array of permissions.
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type.
   *
   * @return array
   *   An array of processed permissions.
   */
  protected function processPermissions(array $permissions, EntityTypeInterface $entity_type): array {
    foreach ($permissions as $name => $permission) {
      // Permissions are grouped by provider on admin/people/permissions.
      $permissions[$name]['provider'] = $entity_type->getProvider();
      // TranslatableMarkup objects don't sort properly.
      $permissions[$name]['title'] = (string) $permission['title'];
    }
    return $permissions;
  }

  /**
   * Builds permissions for the bundle granularity.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type.
   *
   * @return array
   *   The permissions.
   */
  protected function buildBundlePermissions(EntityTypeInterface $entity_type): array {
    $entity_type_id = $entity_type->id();
    $bundles = $this->entityTypeBundleInfo->getBundleInfo($entity_type_id);
    $has_duplicate_form = $entity_type->hasLinkTemplate('duplicate-form');
    $singular_label = $entity_type->getSingularLabel();
    $plural_label = $entity_type->getPluralLabel();

    $permissions = [];
    $permissions["view any $entity_type_id"] = [
      'title' => $this->t('View @type', [
        '@type' => $plural_label,
      ]),
      'description' => $this->t('View all @type, regardless of type.', [
        '@type' => $plural_label,
      ]),
    ];
    foreach ($bundles as $bundle_name => $bundle_info) {
      $permissions["administer $bundle_name $entity_type_id"] = [
        'title' => $this->t('@bundle: Administer settings', [
          '@bundle' => $bundle_info['label'],
        ]),
        'description' => $this->t('Allow changing registration settings for all entities of this type.'),
      ];
      $permissions["administer own $bundle_name $entity_type_id"] = [
        'title' => $this->t('@bundle: Administer own settings', [
          '@bundle' => $bundle_info['label'],
        ]),
        'description' => $this->t('Allow changing registration settings for entities which a user has edit access.'),
      ];
      $permissions["edit $bundle_name $entity_type_id state"] = [
        'title' => $this->t('@bundle: Edit @type state', [
          '@bundle' => $bundle_info['label'],
          '@type' => $singular_label,
        ]),
      ];
      $permissions["create $bundle_name $entity_type_id self"] = [
        'title' => $this->t('@bundle: Register self', [
          '@bundle' => $bundle_info['label'],
        ]),
      ];
      $permissions["create $bundle_name $entity_type_id other users"] = [
        'title' => $this->t('@bundle: Register other accounts', [
          '@bundle' => $bundle_info['label'],
        ]),
        'description' => $this->t("Register other users by username. Note that giving this permission to Anonymous is not recommended, as it can expose a user's registration status to a site visitor who knows their username."),
      ];
      $permissions["create $bundle_name $entity_type_id other anonymous"] = [
        'title' => $this->t('@bundle: Register other people', [
          '@bundle' => $bundle_info['label'],
        ]),
      ];
      $permissions["view any $bundle_name $entity_type_id"] = [
        'title' => $this->t('@bundle: View any @type', [
          '@bundle' => $bundle_info['label'],
          '@type' => $singular_label,
        ]),
      ];
      $permissions["view own $bundle_name $entity_type_id"] = [
        'title' => $this->t('@bundle: View own @type', [
          '@bundle' => $bundle_info['label'],
          '@type' => $plural_label,
        ]),
      ];
      $permissions["update any $bundle_name $entity_type_id"] = [
        'title' => $this->t('@bundle: Update any @type', [
          '@bundle' => $bundle_info['label'],
          '@type' => $singular_label,
        ]),
      ];
      $permissions["update own $bundle_name $entity_type_id"] = [
        'title' => $this->t('@bundle: Update own @type', [
          '@bundle' => $bundle_info['label'],
          '@type' => $plural_label,
        ]),
      ];
      if ($has_duplicate_form) {
        $permissions["duplicate any $bundle_name $entity_type_id"] = [
          'title' => $this->t('@bundle: Duplicate any @type', [
            '@bundle' => $bundle_info['label'],
            '@type' => $singular_label,
          ]),
        ];
        $permissions["duplicate own $bundle_name $entity_type_id"] = [
          'title' => $this->t('@bundle: Duplicate own @type', [
            '@bundle' => $bundle_info['label'],
            '@type' => $plural_label,
          ]),
        ];
      }
      $permissions["delete any $bundle_name $entity_type_id"] = [
        'title' => $this->t('@bundle: Delete any @type', [
          '@bundle' => $bundle_info['label'],
          '@type' => $singular_label,
        ]),
      ];
      $permissions["delete own $bundle_name $entity_type_id"] = [
        'title' => $this->t('@bundle: Delete own @type', [
          '@bundle' => $bundle_info['label'],
          '@type' => $plural_label,
        ]),
      ];
    }

    return $permissions;
  }

}

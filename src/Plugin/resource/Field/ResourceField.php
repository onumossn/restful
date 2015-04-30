<?php

/**
 * @file
 * Contains \Drupal\restful\Plugin\resource\ResourceField.
 */

namespace Drupal\restful\Plugin\resource\Field;

use Drupal\restful\Exception\ServerConfigurationException;
use Drupal\restful\Plugin\resource\DataInterpreter\DataInterpreterInterface;
use Drupal\restful\Resource\ResourceManager;

class ResourceField extends ResourceFieldBase implements ResourceFieldInterface {

  /**
   * Constructor.
   *
   * @param array $field
   *   Contains the field values.
   *
   * @throws ServerConfigurationException
   */
  public function __construct(array $field) {
    if (empty($field['public_name'])) {
      throw new ServerConfigurationException('No public name provided in the field mappings.');
    }
    $this->publicName = $field['public_name'];
    $this->accessCallbacks = isset($field['access_callbacks']) ? $field['access_callbacks'] : $this->accessCallbacks;
    $this->property = isset($field['property']) ? $field['property'] : $this->property;
    // $this->column = isset($field['column']) ? $field['column'] : $this->column;
    $this->callback = isset($field['callback']) ? $field['callback'] : $this->callback;
    $this->processCallbacks = isset($field['process_callbacks']) ? $field['process_callbacks'] : $this->processCallbacks;
    $this->resource = isset($field['resource']) ? $field['resource'] : $this->resource;
    $this->methods = isset($field['methods']) ? $field['methods'] : $this->methods;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(array $field) {
    if ($class_name = static::fieldClassName($field)) {
      // Call the create factory in the derived class.
      return call_user_func_array(array($class_name, 'create'), array($field, new static($field)));
    }
    // If no other class was found, then use the current one.
    $resource_field = new static($field);
    $resource_field->addDefaults();
    return $resource_field;
  }

  /**
   * {@inheritdoc}
   */
  public function value(DataInterpreterInterface $interpreter) {
    if ($callback = $this->getCallback()) {
      return ResourceManager::executeCallback($callback, array($interpreter));
    }
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function access($op, DataInterpreterInterface $interpreter) {
    foreach ($this->getAccessCallbacks() as $callback) {
      $result = ResourceManager::executeCallback($callback, array(
        $op,
        $this,
        $interpreter,
      ));

      if ($result == ResourceFieldBase::ACCESS_DENY) {
        return FALSE;
      }
    }

    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function addDefaults() {
    // Almost all the defaults come are applied by the object's property
    // defaults.

    $resources = $this->getResource();
    foreach ($resources as &$resource) {
      // Expand array to be verbose.
      if (!is_array($resource)) {
        $resource = array('name' => $resource);
      }

      // Set default value.
      $resource += array(
        'full_view' => TRUE,
      );

      // Set the default value for the version of the referenced resource.
      if (empty($resource['majorVersion']) || empty($resource['minorVersion'])) {
        list($major_version, $minor_version) = restful()
          ->getResourceManager()
          ->getResourceLastVersion($resource['name']);
        $resource['majorVersion'] = $major_version;
        $resource['minorVersion'] = $minor_version;
      }
    }
  }

  /**
   * Get the class name to use based on the field definition.
   *
   * @param array $field_definition
   *   The processed field definition with the user values.
   *
   * @return string
   *   The class name to use. If the class name is empty or does not implement
   *   ResourceFieldInterface then ResourceField will be used. NULL if nothing
   *   was found.
   */
  protected static function fieldClassName(array $field_definition) {
    if (!empty($field_definition['class'])) {
      $class_name = $field_definition['class'];
    }
    // Search for indicators that this is a ResourceFieldEntityInterface.
    elseif (
      !empty($field_definition['sub_property']) ||
      !empty($field_definition['formatter']) ||
      !empty($field_definition['wrapper_method']) ||
      !empty($field_definition['wrapper_method_on_entity']) ||
      !empty($field_definition['column']) ||
      !empty($field_definition['image_styles']) ||
      (!empty($field_definition['property']) ? field_info_field($field_definition['property']) : NULL)
    ) {
      $class_name = '\Drupal\restful\Plugin\resource\Field\ResourceFieldEntity';
    }

    if (
      !empty($class_name) &&
      class_exists($class_name) &&
      in_array(
        'Drupal\restful\Plugin\resource\Field\ResourceFieldInterface',
        class_implements($class_name)
      )
    ) {
      return $class_name;
    }

    return NULL;
  }

}

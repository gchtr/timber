<?php

namespace Timber;

abstract class Core {

	/**
	 * @internal
	 * @var
	 */
	public $id;

	/**
	 * @internal
	 * @var
	 */
	public $ID;

	/**
	 * @internal
	 * @var
	 */
	public $object_type;

	/**
	 * @internal
	 * @return boolean
	 */
	public function __isset( $field ) {
		if ( isset($this->$field) ) {
			return $this->$field;
		}
		return false;
	}

	/**
	 * This is helpful for twig to return properties and methods
	 *
	 * @link https://github.com/twigphp/Twig/issues/2.
	 *
	 * @internal
	 * @return mixed
	 */
	public function __call( $field, $args ) {
		return $this->__get($field);
	}

	/**
	 * Get a custom field or property.
	 *
	 * This method is inherited from `Timber\Core` and will be called if you try to access a
	 * property that is not defined or not accessible on the object. You don’t call this method directly. This is a
	 * technique called [Property Overloading](http://de.php.net/manual/en/language.oop5.overloading.php#language.oop5.overloading.members).
	 *
	 * Here’s how it behaves:
	 *
	 * - When the property exists, it will return the property.
	 * - If a custom field with this name exists, it will return the value of this field.
	 * - If a method with this name exists on the object, it will call that method.
	 *
	 * @api
	 * @example
	 * ```twig
	 * {{ post.field }}
	 * {{ term.field }}
	 * {{ user.field }}
	 * ```
	 *
	 * ```php
	 * $value = $post->field;
	 * $value = $term->field;
	 * $value = $user->field;
	 * ```
	 *
	 * @param string $field The property/field name.
	 * @return mixed Property value, custom field value or method call result.
	 */
	public function __get( $field ) {
		if ( property_exists($this, $field) ) {
			return $this->$field;
		}
		if ( method_exists($this, 'meta') && $meta_value = $this->meta($field) ) {
			return $this->$field = $meta_value;
		}
		if ( method_exists($this, $field) ) {
			return $this->$field = $this->$field();
		}
		return $this->$field = false;
	}

	/**
	 * Takes an array or object and adds the properties to the parent object.
	 *
	 * @example
	 * ```php
	 * $data = array( 'airplane' => '757-200', 'flight' => '5316' );
	 * $post = new Timber\Post();
	 * $post->import(data);
	 *
	 * echo $post->airplane; // 757-200
	 * ```
	 * @param array|object $info an object or array you want to grab data from to attach to the Timber object
	 */
	public function import( $info, $force = false, $only_declared_properties = false ) {
		if ( is_object($info) ) {
			$info = get_object_vars($info);
		}
		if ( is_array($info) ) {
			foreach ( $info as $key => $value ) {
				if ( $key === '' || ord($key[0]) === 0 ) {
					continue;
				}
				if ( !empty($key) && $force ) {
					$this->$key = $value;
				} else if ( !empty($key) && !method_exists($this, $key) ) {
					if ( $only_declared_properties ) {
						if ( property_exists($this, $key) ) {
							$this->$key = $value;
						}
					} else {
						$this->$key = $value;
					}

				}
			}
		}
	}

	/**
	 * @deprecated since 2.0.0
	 * @param string  $key
	 * @param mixed   $value
	 */
	public function update( $key, $value ) {
		update_metadata($this->object_type, $this->ID, $key, $value);
	}

	/**
	 * Can you edit this post/term/user? Well good for you. You're no better than me.
	 * @example
	 * ```twig
	 * {% if post.can_edit %}
	 * <a href="{{ post.edit_link }}">Edit</a>
	 * {% endif %}
	 * ```
	 * ```html
	 * <a href="http://example.org/wp-admin/edit.php?p=242">Edit</a>
	 * ```
	 * @return bool
	 */
	public function can_edit() {
		if ( !function_exists('current_user_can') ) {
			return false;
		}
		if ( current_user_can('edit_post', $this->ID) ) {
			return true;
		}
		return false;
	}

	/**
	 *
	 *
	 * @return array
	 */
	public function get_method_values() {
		$ret = array();
		$ret['can_edit'] = $this->can_edit();
		return $ret;
	}

	/**
	 * @param string $field_name
	 * @return mixed
	 */
	public function get_field( $field_name ) {
		return $this->get_meta_field($field_name);
	}
}

<?php

namespace Timber;

use Timber\CoreInterface;
use Timber\Helper;
use Timber\Post;
use Timber\URLHelper;

/**
 * Class Image
 *
 * If Timber\Post is the class you're going to spend the most time, Timber\Image is the class you're
 * going to have the most fun with.
 *
 * @api
 * @example
 * ```php
 * $context = Timber::get_context();
 * $post = new Timber\Post();
 * $context['post'] = $post;
 *
 * // lets say you have an alternate large 'cover image' for your post stored in a custom field which returns an image ID
 * $cover_image_id = $post->cover_image;
 * $context['cover_image'] = new Timber\Image($cover_image_id);
 * Timber::render('single.twig', $context);
 * ```
 *
 * ```twig
 * <article>
 *   <img src="{{cover_image.src}}" class="cover-image" />
 *   <h1 class="headline">{{post.title}}</h1>
 *   <div class="body">
 *     {{post.content}}
 *   </div>
 *
 *  <img src="{{ Image(post.custom_field_with_image_id).src }}" alt="Another way to initialize images as Timber\Image objects, but within Twig" />
 * </article>
 * ```
 *
 * ```html
 * <article>
 *   <img src="http://example.org/wp-content/uploads/2015/06/nevermind.jpg" class="cover-image" />
 *   <h1 class="headline">Now you've done it!</h1>
 *   <div class="body">
 *     Whatever whatever
 *   </div>
 *   <img src="http://example.org/wp-content/uploads/2015/06/kurt.jpg" alt="Another way to initialize images as Timber\Image objects, but within Twig" />
 * </article>
 * ```
 */
class Image extends Post implements CoreInterface {

	protected $_can_edit;
	protected $_dimensions;

	/**
	 * @api
	 * @var string
	 */
	public $abs_url;

	/**
	 * @api
	 * @var string What does this class represent in WordPress terms?
	 */
	public $object_type = 'image';

	/**
	 * @api
	 * @var string What does this class represent in WordPress terms?
	 */
	public static $representation = 'image';

	/**
	 * @var array Array of supported relative file types.
	 */
	private $file_types = array('jpg', 'jpeg', 'png', 'svg', 'bmp', 'ico', 'gif', 'tiff', 'pdf');

	/**
	 * @api
	 * @var string The location of the image file in the filesystem (ex: `/var/www/htdocs/wp-content/uploads/2015/08/my-pic.jpg`)
	 */
	public $file_loc;

	/**
	 * @api
	 * @var mixed
	 */
	public $file;

	/**
	 * @api
	 * @var integer the ID of the image (which is a WP_Post)
	 */
	public $id;

	/**
	 * @api
	 * @var array
	 */
	public $sizes = array();

	/**
	 * @api
	 * @var string The string stored in the WordPress database
	 */
	public $caption;

	/**
	 * @var array The file as stored in the WordPress database
	 */
	protected $_wp_attached_file;

	/**
	 * Creates a new Timber\Image object
	 *
	 * @api
	 * @example
	 * ```php
	 * // You can pass it an ID number
	 * $myImage = new Timber\Image(552);
	 *
	 * //Or send it a URL to an image
	 * $myImage = new Timber\Image('http://google.com/logo.jpg');
	 * ```
	 * @param int|string $iid
	 */
	public function __construct( $iid ) {
		$this->init($iid);
	}

	/**
	 * The src of the image
	 *
	 * @api
	 *
	 * @return string the src of the file
	 */
	public function __toString() {
		return $this->src();
	}

	/**
	 * Get a PHP array with pathinfo() info from the file
	 *
	 * @api
	 *
	 * @return array
	 */
	public function get_pathinfo() {
		return pathinfo($this->file);
	}

	/**
	 * @internal
	 * @param string $dim
	 * @return array|int
	 */
	protected function get_dimensions( $dim ) {
		if ( isset($this->_dimensions) ) {
			return $this->get_dimensions_loaded($dim);
		}
		if ( file_exists($this->file_loc) && filesize($this->file_loc) ) {
			list($width, $height) = getimagesize($this->file_loc);
			$this->_dimensions = array();
			$this->_dimensions[0] = $width;
			$this->_dimensions[1] = $height;
			return $this->get_dimensions_loaded($dim);
		}
	}

	/**
	 * If the dimensions have already been loaded, return those saved values
	 *
	 * @internal
	 * @param string|null $dim what dimensions we seek (width or height).
	 * @return int
	 */
	protected function get_dimensions_loaded( $dim ) {
		$dim = strtolower($dim);
		if ( 'h' === $dim || 'height' === $dim ) {
			return $this->_dimensions[1];
		}
		return $this->_dimensions[0];
	}

	/**
	 * @return array
	 */
	protected function get_meta_values( $iid ) {
		$pc = get_post_custom($iid);
		if ( is_bool($pc) ) {
			return array();
		}
		return $pc;
	}

	/**
	 * @internal
	 * @param  int $iid the id number of the image in the WP database
	 */
	protected function get_image_info( $iid ) {
		$image_info = $iid;
		if ( is_numeric($iid) ) {
			$image_info = wp_get_attachment_metadata($iid);
			if ( ! is_array($image_info) ) {
				$image_info = array();
			}
			$image_custom = self::get_meta_values($iid);
			$basic        = get_post($iid);
			if ( $basic ) {
				if ( isset($basic->post_excerpt) ) {
					$this->caption = $basic->post_excerpt;
				}
				$image_custom = array_merge($image_custom, get_object_vars($basic));
			}
			return array_merge($image_info, $image_custom);
		}
		if ( is_array($image_info) && isset($image_info['image']) ) {
			return $image_info['image'];
		}
		if ( is_object($image_info) ) {
		   return get_object_vars($image_info);
		}
		return $iid;
	}

	/**
	 * @internal
	 * @param  string $url for evaluation
	 * @return string with http/https corrected depending on what's appropriate for server
	 */
	protected static function _maybe_secure_url( $url ) {
		if ( is_ssl() && strpos($url, 'https') !== 0 && strpos($url, 'http') === 0 ) {
			$url = 'https' . substr($url, strlen('http'));
		}
		return $url;
	}

	/**
	 * Returns the `wp_upload_dir` and saves result to static var
	 *
	 * @api
	 *
	 * @return array of data https://developer.wordpress.org/reference/functions/wp_upload_dir/
	 */
	public static function wp_upload_dir() {
		static $wp_upload_dir = false;

		if ( ! $wp_upload_dir ) {
			$wp_upload_dir = wp_upload_dir();
		}

		return $wp_upload_dir;
	}

	/**
	 * @internal
	 * @param int $iid
	 */
	public function init( $iid = false ) {
		// Make sure we actually have something to work with.
		if ( !$iid ) {
			Helper::error_log('Initalized Timber\Image without providing first parameter.'); return;
		}

		// If passed Timber\Image, grab the ID and continue.
		if ( $iid instanceof self ) {
			$iid = (int) $iid->ID;
		}

		// If passed ACF image array.
		if ( is_array($iid) && isset($iid['ID']) ) {
			$iid = $iid['ID'];
		}

		if ( !is_numeric($iid) && is_string($iid) ) {
			if ( strpos($iid, '//') === 0 || strstr($iid, '://') ) {
				$this->init_with_url($iid);
				return;
			}
			if ( strstr($iid, ABSPATH) ) {
				$this->init_with_file_path($iid);
				return;
			}

			$relative  = false;
			$iid_lower = strtolower($iid);
			foreach ( $this->file_types as $type ) { if ( strstr($iid_lower, $type) ) { $relative = true; break; } };
			if ( $relative ) {
				$this->init_with_relative_path($iid);
				return;
			}
		} else if ( $iid instanceof \WP_Post ) {
			$ref = new \ReflectionClass($this);
			$post = $ref->getParentClass()->newInstance($iid->ID);
			if ( $post->_thumbnail_id ) {
				return $this->init((int) $post->_thumbnail_id);
			}
			return $this->init($iid->ID);
		} else if ( $iid instanceof Post ) {
			/**
			 * This will catch Timber\Post and any post classes that extend Timber\Post,
			 * see http://php.net/manual/en/internals2.opcodes.instanceof.php#109108
			 * and https://timber.github.io/docs/guides/extending-timber/
			 */
			$iid = (int) $iid->_thumbnail_id;
		}

		$image_info = $this->get_image_info($iid);

		$this->import($image_info);
		$basedir = self::wp_upload_dir();
		$basedir = $basedir['basedir'];
		if ( isset($this->file) ) {
			$this->file_loc = $basedir.DIRECTORY_SEPARATOR.$this->file;
		} else if ( isset($this->_wp_attached_file) ) {
			$this->file = reset($this->_wp_attached_file);
			$this->file_loc = $basedir.DIRECTORY_SEPARATOR.$this->file;
		}
		if ( isset($image_info['id']) ) {
			$this->ID = $image_info['id'];
		} else if ( is_numeric($iid) ) {
			$this->ID = $iid;
		}
		if ( isset($this->ID) ) {
			$custom = self::get_meta_values($this->ID);
			foreach ( $custom as $key => $value ) {
				$this->$key = $value[0];
			}
			$this->id = $this->ID;
		}
	}

	/**
	 * @internal
	 * @param string $relative_path
	 */
	protected function init_with_relative_path( $relative_path ) {
		$this->abs_url = home_url($relative_path);
		$file_path = URLHelper::get_full_path($relative_path);
		$this->file_loc = $file_path;
		$this->file = $file_path;
	}

	/**
	 * @internal
	 * @param string $file_path
	 */
	protected function init_with_file_path( $file_path ) {
		$url = URLHelper::file_system_to_url($file_path);
		$this->abs_url = $url;
		$this->file_loc = $file_path;
		$this->file = $file_path;
	}

	/**
	 * @internal
	 * @param string $url
	 */
	protected function init_with_url( $url ) {
		$this->abs_url = $url;
		if ( URLHelper::is_local($url) ) {
			$this->file = URLHelper::remove_double_slashes(ABSPATH.URLHelper::get_rel_url($url));
			$this->file_loc = URLHelper::remove_double_slashes(ABSPATH.URLHelper::get_rel_url($url));
		}
	}

	/**
	 * @api
	 * @example
	 * ```twig
	 * <img src="{{ image.src }}" alt="{{ image.alt }}" />
	 * ```
	 * ```html
	 * <img src="http://example.org/wp-content/uploads/2015/08/pic.jpg" alt="W3 Checker told me to add alt text, so I am" />
	 * ```
	 * @return string alt text stored in WordPress
	 */
	public function alt() {
		$alt = trim(strip_tags(get_post_meta($this->ID, '_wp_attachment_image_alt', true)));
		return $alt;
	}

	/**
	 * Get the aspect ratio of the image
	 *
	 * @api
	 * @example
	 * ```twig
	 * {% if post.thumbnail.aspect < 1 %}
	 *   {# handle vertical image #}
	 *   <img src="{{ post.thumbnail.src|resize(300, 500) }}" alt="A basketball player" />
	 * {% else %}
	 *   <img src="{{ post.thumbnail.src|resize(500) }}" alt="A sumo wrestler" />
	 * {% endif %}
	 * ```
	 * @return float
	 */
	public function aspect() {
		$w = intval($this->width());
		$h = intval($this->height());
		return $w / $h;
	}

	/**
	 * @api
	 * @example
	 * ```twig
	 * <img src="{{ image.src }}" height="{{ image.height }}" />
	 * ```
	 * ```html
	 * <img src="http://example.org/wp-content/uploads/2015/08/pic.jpg" height="900" />
	 * ```
	 * @return int
	 */
	public function height() {
		return $this->get_dimensions('height');
	}

	/**
	 * Returns the link to an image attachment's Permalink page (NOT the link for the image itself!!)
	 *
	 * @api
	 * @example
	 * ```twig
	 * <a href="{{ image.link }}"><img src="{{ image.src }} "/></a>
	 * ```
	 * ```html
	 * <a href="http://example.org/my-cool-picture"><img src="http://example.org/wp-content/uploads/2015/whatever.jpg"/></a>
	 * ```
	 */
	public function link() {
		if ( strlen($this->abs_url) ) {
			return $this->abs_url;
		}
		return get_permalink($this->ID);
	}

	/**
	 * @api
	 * @return bool|\Timber\Post
	 */
	public function parent() {
		if ( !$this->post_parent ) {
			return false;
		}
		return new $this->PostClass($this->post_parent);
	}

	/**
	 * @api
	 * @example
	 * ```twig
	 * <img src="{{ image.path }}" />
	 * ```
	 * ```html
	 * <img src="/wp-content/uploads/2015/08/pic.jpg" />
	 * ```
	 * @return  string the /relative/path/to/the/file
	 */
	public function path() {
		return URLHelper::get_rel_path($this->src());
	}

	/**
	 * @api
	 * @example
	 * ```twig
	 * <h1>{{ post.title }}</h1>
	 * <img src="{{ post.thumbnail.src }}" />
	 * ```
	 * ```html
	 * <img src="http://example.org/wp-content/uploads/2015/08/pic.jpg" />
	 * ```
	 *
	 * @param string $size a size known to WordPress (like "medium")
	 * @return bool|string
	 */
	public function src( $size = 'full' ) {
		if ( isset($this->abs_url) ) {
			return $this->_maybe_secure_url($this->abs_url);
		}

		if ( ! $this->is_image() ) {
			return wp_get_attachment_url($this->ID);
		}

		$src = wp_get_attachment_image_src($this->ID, $size);
		$src = $src[0];

		/**
		 * Filters the src for a `Timber\Image`.
		 *
		 * @see \Timber\Image::src()
		 * @since 0.21.7
		 *
		 * @param string $src The image src.
		 * @param int    $id  The image ID.
		 */
		$src = apply_filters('timber/image/src', $src, $this->ID);

		/**
		 * Filters the src for a `Timber\Image`.
		 *
		 * @deprecated 2.0.0, use `timber/image/src`
		 */
		$src = apply_filters_deprecated( 'timber_image_src', array( $src, $this->ID ), '2.0.0', 'timber/image/src' );

		return $src;
	}

	/**
	 * @internal
	 * @return bool true if media is an image
	 */
	protected function is_image() {
		$src = wp_get_attachment_url($this->ID);
		$image_exts = array( 'jpg', 'jpeg', 'jpe', 'gif', 'png' );
		$check = wp_check_filetype(basename($src), null);
		return in_array($check['ext'], $image_exts);
	}

	/**
	 * @api
	 * @example
	 * ```twig
	 * <img src="{{ image.src }}" width="{{ image.width }}" />
	 * ```
	 * ```html
	 * <img src="http://example.org/wp-content/uploads/2015/08/pic.jpg" width="1600" />
	 * ```
	 * @return int
	 */
	public function width() {
		return $this->get_dimensions('width');
	}

	/**
	 * Gets the orientation of an image.
	 *
	 * This is useful if you need a string that describes the orientation of the image, e.g. if you
	 * need to make layout adjustments based on different image formats.
	 *
	 * @example
	 * ```twig
	 * <img src="{{ post.thumbnail.src }}" class="image image-is-{{ post.thubmnail.orientation }}">
	 * ```
	 *
	 * @api
	 * @return string The orientation of the image. Can be one of `landscape`, `portrait` or
	 *                `square`.
	 */
	public function orientation() {
		if ( $this->is_landscape() ) {
			return 'landscape';
		} elseif( $this->is_portrait() ) {
			return 'portrait';
		}

		return 'square';
	}

	/**
	 * Checks if an image has a landscape format.
	 *
	 * Landscape means when the width of the image is bigger than the height.
	 *
	 * @api
	 * @return bool Whether the image has a landscape format.
	 */
	public function is_landscape() {
		return $this->aspect() > 1;
	}

	/**
	 * Checks if an image has a portrait format.
	 *
	 * Portrait means when the height of the image is bigger than the width.
	 *
	 * @api
	 * @return bool Whether the image has a portrait format.
	 */
	public function is_portrait() {
		return $this->aspect() < 1;
	}

	/**
	 * Checks if an image has a square format.
	 *
	 * Square means when the width of the image is the same as the height.
	 *
	 * @api
	 * @return bool Whether the image has a square format.
	 */
	public function is_square() {
		return $this->aspect() === 0.5;
	}
}

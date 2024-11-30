<?php
/**
 * Image manipulation support.
 * Allows images to be resized, cropped, etc.
 *
 * @package        KO7/Image
 *
 * @copyright  (c) 2007-2016  Kohana Team
 * @copyright  (c) since 2016 Koseven Team
 * @license        https://koseven.dev/LICENSE
 */
abstract class KO7_Image {

	/**
	 * Resize image by given values, regardless of original dimensions
	 * e.g Image is 100x200, you resize to 50x80, image will be 50x80
	 */
	public const NONE = 0x01;

	/**
	 * Automatically choose resize direction with the highest reduction ratio *keeping* original dimensions
	 * e.g Image is 100x200, you resize to 70x50, highest ratio = 4 - image will be 25x50
	 */
	public const AUTO = 0x04;

	/**
	 * Automatically choose resize direction with the lowest reduction ratio *keeping* original dimensions
	 * e.g Image is 100x200, you resize to 70x50, lowest ratio = 1,4285 image will be 70x140
	 */
	public const INVERSE = 0x05;

	/**
	 * FLIP horizontal
	 */
	public const HORIZONTAL = 0x11;

	/**
	 * Flip vertical
	 */
	public const VERTICAL = 0x12;

	/**
	 * True if driver dependencies are satisfied, false if not
	 * @var bool
	 */
	protected static $_checked = FALSE;

	/**
	 * Path to Image File
	 * @var  string
	 */
	public $file;

	/**
	 * Image Width
	 * @var  integer
	 */
	public $width;

	/**
	 * Image Height
	 * @var  integer
	 */
	public $height;

	/**
	 * Image Type (IMAGETYPE_* constants)
	 * @var  integer
	 */
	public $type;

	/**
	 * Image mime type
	 * @var  string
	 */
	public $mime;

	/**
	 * Loads an image and prepares it for manipulation.
	 *
	 * @param string $file	  Path to the Image file
	 * @param string $driver  Driver to use (overrides driver which is set in config)
	 *
	 * @throws Image_Exception
	 * @throws KO7_Exception
	 *
	 * @return mixed
	 */
	public static function factory(string $file, $driver = NULL)
	{
		if ($driver === NULL)
		{
			// Use the driver from configuration file or default one
			$configured_driver = KO7::$config->load('image.default_driver');

			// If no driver is specified throw an error, there is no default driver since 4.0
			if ($configured_driver === NULL)
			{
				throw new Image_Exception('Please specify a driver in your image configuration.');
			}
		}

		// Set the class name
		$class = 'Image_' . $driver;

		// Check if class exists and extends this one
		if ( is_string($class) && (! class_exists($class) || ! ($class = new $class($file)) instanceof Image))
		{
			throw new Image_Exception('Driver: ":driver" is not a valid Image Driver.', [
				':driver' => $driver
			]);
		}

		// Perform dependency check
		// @codeCoverageIgnoreStart
		if ( ! ($class::$_checked = $class::check()))
		{
			throw new Request_Exception('Dependencies for driver :driver not satisfied. Please check docs.', [
				':driver' => $driver
			]);
		}
		// @codeCoverageIgnoreEnd

		return $class;
	}

	/**
	 * Loads information about the image. Will throw an exception if the image
	 * does not exist or is not an image.
	 *
	 * @param string $file Image file path
	 *
	 * @return  void
	 *@throws  Image_Exception
	 *
	 */
	public function __construct(string $file)
	{
        $info = false;
        if (empty($file)) {
			throw new Image_Exception('Not an image: :file', [
				':file' => Debug::path($file)
			]);
        }
		try
		{
			// Get the real path to the file
			$file = realpath($file);
            // Check if valid image file
            //Suppress warning:  getimagesize(): Filename cannot be empty
            if (is_file($file) && is_readable($file)) {
                // Get the image information
                $info = getimagesize($file);
            } else {
                throw new Image_Exception('Not an image or invalid image: :file', [
                    ':file' => Debug::path($file)
                ]);
            }
		}
		catch (Exception $e)
		{
			// Catch exceptions
            throw new Image_Exception('Unknow error: :error  an image :file', [
                ':file' => Debug::path($file),
                ':error' => $e->getMessage()
            ]);
        }
        if ($info === FALSE) {
            throw new Image_Exception('The file is not an image :file', [
                ':file' => Debug::path($file)
            ]);
        }
		// Store the image information
        $this->file = $file;
		$this->width = $info[0];
		$this->height = $info[1];
        // Type https://www.php.net/manual/en/function.image-type-to-mime-type.php
		$this->type = $info[2];
		$this->mime = image_type_to_mime_type($this->type);
		// Check if image type is supported by our driver
		// @codeCoverageIgnoreStart
		if ( ! $this->_is_supported_type($this->type)) {
			throw new Image_Exception('Image extension ":ext", is unknown by driver ":driver".', [
				':ext' => image_type_to_extension($this->type),
				':driver' => get_class($this)
			]);
		}
		// @codeCoverageIgnoreEnd
	}

	/**
	 * Resize the image to the given size. Either the width or the height can
	 * be omitted and the image will be resized proportionally.
	 *
	 * @param integer|null $width  New width
	 * @param integer|null $height New height
	 * @param integer|null $master Master dimension
	 *
	 * @throws Image_Exception
	 *
	 * @return  self
	 */
	public function resize(?int $width = NULL, ?int $height = NULL, ?int $master = NULL)
	{
		// Check if at least width or height was set
		if ($width === NULL && $height === NULL)
		{
			throw new Image_Exception('Please specify at least a width or a height.');
		}

		// Choose the master dimension automatically
		if ($master === NULL)
		{
			$master = Image::AUTO;
		}
		elseif ( ! in_array($master, [Image::AUTO, Image::INVERSE, Image::NONE], TRUE))
		{
			throw new Image_Exception('Invalid Master dimension, please specify a correct one');
		}

		// Set width if not set
		if ($width === NULL && $master !== Image::NONE)
		{
			$width = $this->width * $height / $this->height;
		}

		// Set height if not set
		if ($height === NULL && $master !== Image::NONE)
		{
			$height = $this->height * $width / $this->width;
		}

		// Resize to exact given width/height
		if ($master === Image::NONE)
		{
			if ($width === NULL)
			{
				$width = $this->width;
			}
			elseif ($height === NULL)
			{
				$height = $this->height;
			}
		}

		// Resize with the greatest / lowest reduction ratio
		elseif (($this->width / $width) > ($this->height / $height) === ($master === Image::AUTO))
		{
			$height = $this->height * $width / $this->width;
		}
		else
		{
			$width = $this->width * $height / $this->height;
		}

		// Convert the width and height to integers, minimum value is 1px
		$width = max(round($width), 1);
		$height = max(round($height), 1);

		// Call driver resize function
		$this->_do_resize($width, $height);

		return $this;
	}

	/**
	 * Crop an image to the given size. Either the width or the height can be
	 * omitted and the current width or height will be used.
	 *
	 * If no offset is specified, the center of the axis will be used.
	 * If an offset of TRUE is specified, the bottom of the axis will be used.
	 *
	 * @param integer $width    New width
	 * @param integer $height   New height
	 * @param mixed   $offset_x Offset from the left - Set to true to start from right
	 * @param mixed   $offset_y Offset from the top - Set to true to start from bottom
	 *
	 * @return  self
	 */
	public function crop($width, $height, $offset_x = NULL, $offset_y = NULL)
	{
		if ($width > $this->width)
		{
			// Use the current width
			$width = $this->width;
		}

		if ($height > $this->height)
		{
			// Use the current height
			$height = $this->height;
		}

		if ($offset_x === NULL)
		{
			// Center the X offset
			$offset_x = round(($this->width - $width) / 2);
		}
		elseif ($offset_x === TRUE)
		{
			// Right the X offset
			$offset_x = $this->width - $width;
		}
		elseif ($offset_x < 0)
		{
			// Set the X offset from the right
			$offset_x = $this->width - $width + $offset_x;
		}

		if ($offset_y === NULL)
		{
			// Center the Y offset
			$offset_y = round(($this->height - $height) / 2);
		}
		elseif ($offset_y === TRUE)
		{
			// Bottom the Y offset
			$offset_y = $this->height - $height;
		}
		elseif ($offset_y < 0)
		{
			// Set the Y offset from the bottom
			$offset_y = $this->height - $height + $offset_y;
		}

		// Determine the maximum possible width and height
		$max_width = $this->width - $offset_x;
		$max_height = $this->height - $offset_y;

		if ($width > $max_width)
		{
			// Use the maximum available width
			$width = $max_width;
		}

		if ($height > $max_height)
		{
			// Use the maximum available height
			$height = $max_height;
		}

		// Call image driver crop
		$this->_do_crop($width, $height, $offset_x, $offset_y);

		return $this;
	}

	/**
	 * Rotate the image by a given amount.
	 *
	 * @param integer $degrees degrees to rotate
	 *
	 * @return  self
	 */
	public function rotate($degrees)
	{
		// Make the degrees an integer
		$degrees = (int)$degrees;

		// Don't rotate if 360 degree
		if ($degrees % 360 === 0)
		{
			return $this;
		}

		if ($degrees > 180)
		{
			do
			{
				// Keep subtracting full circles until the degrees have normalized
				$degrees -= 360;
			} while ($degrees > 180);
		}

		if ($degrees < -180)
		{
			do
			{
				// Keep adding full circles until the degrees have normalized
				$degrees += 360;
			} while ($degrees < -180);
		}

		// Call Image driver rotation function
		$this->_do_rotate($degrees);

		return $this;
	}

	/**
	 * Flip the image along the horizontal or vertical axis.
	 *
	 * @param integer $direction Flip direction (Image::HORIZONTAL, Image::VERTICAL)
	 *
	 * @return self
	 */
	public function flip($direction)
	{
		if ($direction !== Image::HORIZONTAL)
		{
			// Flip vertically
			$direction = Image::VERTICAL;
		}

		// Call image driver flip function
		$this->_do_flip($direction);

		return $this;
	}

	/**
	 * Sharpen the image by a given amount.
	 *
	 * @param integer $amount amount to sharpen (1-100)
	 *
	 * @return  self
	 */
	public function sharpen($amount)
	{
		// The amount must be in the range of 1 to 100
		$amount = min(max($amount, 1), 100);

		// Call image driver sharpen function
		$this->_do_sharpen($amount);

		return $this;
	}

	/**
	 * Add a reflection to an image. The most opaque part of the reflection
	 * will be equal to the opacity setting and fade out to full transparent.
	 * Alpha transparency is preserved.
	 *
	 * @param integer $height  Reflection height
	 * @param integer $opacity Reflection opacity: 0-100
	 * @param boolean $fade_in TRUE to fade in, FALSE to fade out
	 *
	 * @return  self
	 */
	public function reflection($height = NULL, $opacity = 100, $fade_in = FALSE)
	{
		if ($height === NULL || $height > $this->height)
		{
			// Use the current height
			$height = $this->height;
		}

		// The opacity must be in the range of 0 to 100
		$opacity = min(max($opacity, 0), 100);

		// Call image driver reflection function
		$this->_do_reflection($height, $opacity, $fade_in);

		return $this;
	}

	/**
	 * Add a watermark to an image with a specified opacity. Alpha transparency
	 * will be preserved.
	 *
	 * @param Image   $watermark watermark Image instance
	 * @param integer $offset_x  Offset from the left
	 * @param integer $offset_y  Offset from the top
	 * @param integer $opacity   Opacity of watermark: 1-100
	 *
	 * @throws  Image_Exception
	 *
	 * @return  self
	 */
	public function watermark(Image $watermark, $offset_x = NULL, $offset_y = NULL, $opacity = 100)
	{
		// If watermark is to big, resize it to fit image
		if ($watermark->height > $this->height || $watermark->width > $this->width)
		{
			$watermark->resize($this->width, $this->height, self::AUTO);
		}

		if ($offset_x === NULL)
		{
			// Center the X offset
			$offset_x = round(($this->width - $watermark->width) / 2);
		}
		elseif ($offset_x === TRUE)
		{
			// Right the X offset
			$offset_x = $this->width - $watermark->width;
		}
		elseif ($offset_x < 0)
		{
			// Set the X offset from the right
			$offset_x = $this->width - $watermark->width + $offset_x;
		}

		if ($offset_y === NULL)
		{
			// Center the Y offset
			$offset_y = round(($this->height - $watermark->height) / 2);
		}
		elseif ($offset_y === TRUE)
		{
			// Bottom the Y offset
			$offset_y = $this->height - $watermark->height;
		}
		elseif ($offset_y < 0)
		{
			// Set the Y offset from the bottom
			$offset_y = $this->height - $watermark->height + $offset_y;
		}

		// The opacity must be in the range of 1 to 100
		$opacity = min(max($opacity, 1), 100);

		// Call image driver watermark function
		$this->_do_watermark($watermark, $offset_x, $offset_y, $opacity);

		return $this;
	}

	/**
	 * Set the background color of an image. This is only useful for images
	 * with alpha transparency.
	 *
	 * @param string  $color   Hexadecimal color value
	 * @param integer $opacity Background opacity: 0-100
	 *
	 * @return  $this
	 */
	public function background($color, $opacity = 100)
	{
		if (strpos($color, '#') === 0)
		{
			// Remove the pound
			$color = substr($color, 1);
		}

		if (strlen($color) === 3)
		{
			// Convert shorthand into longhand hex notation
			$color = preg_replace('/./', '$0$0', $color);
		}

		// Convert the hex into RGB values
		[$r, $g, $b] = array_map('hexdec', str_split($color, 2));

		// The opacity must be in the range of 0 to 100
		$opacity = min(max($opacity, 0), 100);

		// Call image driver background function
		$this->_do_background($r, $g, $b, $opacity);

		return $this;
	}

	/**
	 * Save the image. If the filename is omitted, the original image will
	 * be overwritten.
	 *
	 * @param string  $file    new image path
	 * @param integer $quality quality of image: 1-100
	 *
	 * @throws  Image_Exception
	 *
	 * @return  boolean
	 */
	public function save($file = NULL, $quality = 100)
	{
		if ($file === NULL)
		{
			// Overwrite the file
			$file = $this->file; //@codeCoverageIgnore
		}

		// Get File Extension
		$extension = pathinfo($file, PATHINFO_EXTENSION);

		if ( ! $extension)
		{
			// Use the current image type
			$extension = image_type_to_extension($this->type, FALSE);
		}

		// Check if it is supported
		// @codeCoverageIgnoreStart
		if ( ! $this->_is_supported_type(self::extension_to_image_type($extension)))
		{
			throw new Image_Exception('Image extension ":ext", is unknown by driver ":driver".', [
				':ext' => $extension,
				':driver' => get_class($this)
			]);
		}

		if (is_file($file))
		{
			if ( ! is_writable($file))
			{
				throw new Image_Exception('File must be writable: :file', [':file' => Debug::path($file)]);
			}
		}
		// @codeCoverageIgnoreEnd
		else
		{
			// Get the directory of the file
			$directory = realpath(pathinfo($file, PATHINFO_DIRNAME));

			// Check if is the directory is writable
			if ( ! is_dir($directory) || ! is_writable($directory))
			{
				throw new Image_Exception('Directory must be writable: :directory', [
					':directory' => Debug::path($directory)
				]);
			}
		}

		// The quality must be in the range of 1 to 100
		$quality = min(max($quality, 1), 100);

		// Call image driver save function
		return $this->_do_save($file, $quality);
	}

	/**
	 * Render the image and return the binary string.
	 *
	 * @param string  $extension    Image type to return: png, jpg, gif, etc
	 * @param integer $quality		Quality of image: 1-100
	 *
	 * @return  string
	 */
	public function render($extension = NULL, $quality = 100)
	{
		if ($extension === NULL)
		{
			// Use the current image type
			$extension = image_type_to_extension($this->type, FALSE);
		}
		elseif (strpos($extension, '.') === 0)
		{
			// remove "." from image extension
			$extension = substr($extension, 1);
		}

		// The quality must be in the range of 1 to 100
		$quality = min(max($quality, 1), 100);

		// Call image driver render function
		return $this->_do_render($extension, $quality);
	}

	/**
	 * Render the current image.
	 *
	 *     echo $image;
	 *
	 * [!!] The output of this function is binary and must be rendered with the
	 * appropriate Content-Type header or it will not be displayed correctly!
	 *
	 * @codeCoverageIgnore We can ignore this, since it just calls render()
	 *
	 * @return  string
	 */
	public function __toString()
	{
		// __toString() must not throw an exception so we catch and only log it
		try
		{
			// Render the current image
			return $this->render();
		}
		catch (Exception $e)
		{
			if (is_object(KO7::$log))
			{
				// Get the text of the exception
				$error = Image_Exception::text($e);

				// Add this exception to the log
				KO7::$log->add(Log::ERROR, $error);
			}

			// Showing any kind of error will be "inside" image data
			return '';
		}
	}

	/**
	 *
	 * Convert Image Extension to Image Type Constant
	 *
	 * @param string $extension	 Image Extension String
	 *
	 * @return integer
	 */
	public static function extension_to_image_type(string $extension) : int
	{
		// Convert to lowercase
		$extension = strtolower($extension);

		// Remove dot form extension if there
		if (strpos($extension, '.') === 0)
		{
			$extension = substr($extension, 1);
		}

		// jpe and jpg are both valid extensions for jpeg
		if (in_array($extension, [
			'jpe',
			'jpg',
		]))
		{
			$extension = 'jpeg';
		}

		// Convert to imagetype
		$type = 'IMAGETYPE_' . strtoupper($extension);

		// Check if constant exists return if so, else Unknown image type
		return defined($type) ? constant($type) : IMAGETYPE_UNKNOWN;
	}

	/**
	 * Check if ImageType is supported
	 *
	 * @param integer $type	ImageType Constant
	 *
	 * @return bool
	 */
	abstract protected function _is_supported_type(int $type) : bool;

	/**
	 * Resize Image
	 *
	 * @param integer $width  New width
	 * @param integer $height New height
	 *
	 * @return  bool  True on successful resize, false otherwise
	 */
	abstract protected function _do_resize($width, $height) : bool;

	/**
	 * Crop image.
	 *
	 * @param integer $width    New width
	 * @param integer $height   New height
	 * @param integer $offset_x Offset from the left
	 * @param integer $offset_y Offset from the top
	 *
	 * @return  bool  True on successful crop, false otherwise
	 */
	abstract protected function _do_crop($width, $height, $offset_x, $offset_y) : bool;

	/**
	 * Rotate image
	 *
	 * @param integer $degrees Degrees to rotate
	 *
	 * @return  bool  True on successful rotation, false otherwise
	 */
	abstract protected function _do_rotate($degrees) : bool;

	/**
	 * Flip the Image
	 *
	 * @param integer $direction Direction to flip
	 *
	 * @return  bool True on successful flip, false otherwise
	 */
	abstract protected function _do_flip($direction) : bool;

	/**
	 * Sharpen Image
	 *
	 * @param integer $amount Amount to sharpen (sigma)
	 *
	 * @return  bool True on successful sharpen, false otherwise
	 */
	abstract protected function _do_sharpen($amount) : bool;

	/**
	 * Create an image reflection effect
	 *
	 * @param integer $height  Reflection height
	 * @param integer $opacity Reflection opacity
	 * @param boolean $fade_in TRUE to fade out, FALSE to fade in
	 *
	 * @return  bool True if reflection applied successful, false otherwise
	 */
	abstract protected function _do_reflection($height, $opacity, $fade_in) : bool;

	/**
	 * Watermark image.
	 *
	 * @param Image   $image    The Watermark (has to be an Image)
	 * @param integer $offset_x Offset from the left
	 * @param integer $offset_y Offset from the top
	 * @param integer $opacity  Opacity of watermark
	 *
	 * @return  bool True if Watermark applied successful, false otherwise
	 */
	abstract protected function _do_watermark(Image $image, $offset_x, $offset_y, $opacity) : bool;

	/**
	 * Change / Add image background (RGB)
	 *
	 * @param integer $r       Red
	 * @param integer $g       Green
	 * @param integer $b       Blue
	 * @param integer $opacity Opacity of background
	 *
	 * @return  bool True if background applied successful, false otherwise
	 */
	abstract protected function _do_background($r, $g, $b, $opacity) : bool;

	/**
	 * Save the image
	 *
	 * @param string  $file    New image filename
	 * @param integer $quality Quality
	 *
	 * @return  bool True if saved successful, false otherwise
	 */
	abstract protected function _do_save($file, $quality) : bool;

	/**
	 * Render the Image
	 *
	 * @param string  $type    Image type: png, jpg, gif, etc
	 * @param integer $quality Image Quality
	 *
	 * @return  string
	 */
	abstract protected function _do_render($type, $quality) : string;

	/**
	 * Check if all requirements for the driver to work properly are met.
	 *
	 * @return bool
	 */
	abstract public static function check() : bool;
}

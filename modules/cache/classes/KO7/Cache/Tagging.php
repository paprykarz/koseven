<?php
/**
 * KO7 Cache Tagging Interface
 *
 * @package    KO7/Cache
 * @category   Base
 *
 * @copyright  (c) 2007-2016  Kohana Team
 * @copyright  (c) since 2016 Koseven Team
 * @license    https://koseven.dev/LICENSE
 */
interface KO7_Cache_Tagging {

	/**
	 * Set a value based on an id. Optionally add tags.
	 *
	 * Note : Some caching engines do not support
	 * tagging
	 *
	 * @param   string   $id        id
	 * @param   mixed    $data      data
	 * @param   integer  $lifetime  lifetime [Optional]
	 * @param   array    $tags      tags [Optional]
	 * @return  boolean
	 */
	public function set_with_tags($id, $data, $lifetime = NULL, ?array $tags = NULL);

	/**
	 * Delete cache entries based on a tag
	 *
	 * @param   string  $tag  tag
	 */
	public function delete_tag($tag);

	/**
	 * Find cache entries based on a tag
	 *
	 * @param   string  $tag  tag
	 * @return  array
	 */
	public function find($tag);
}

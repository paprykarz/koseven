<?php
/**
 * Cache Class for Redis
 *
 * Taken from Yoshiharu Shibata <shibata@zoga.me>
 * and Chris Go <chris@velocimedia.com>
 *
 * - requires PhpRedis
 *
 * @package    KO7\Cache
 * @category   Base
 *
 * @copyright  (c) Koseven Team
 * @license    https://koseven.dev/LICENSE
 */
class KO7_Cache_Redis extends Cache implements Cache_Tagging
{

    /**
     * Redis instance
     * @var Redis
     */
    protected $_redis;

    /**
     * Prefix for tag
     * @var string
     */
    protected $_tag_prefix = '_tag';

    /**
     * Ensures singleton pattern is observed, loads the default expiry
     *
     * @param  array $config            Configuration
     * @throws Cache_Exception          Redis ext. not loaded or server not configured
     */
    public function __construct(array $config)
    {
        if ( ! extension_loaded('redis'))
        {
            // @codeCoverageIgnoreStart
            throw new Cache_Exception(__METHOD__.' Redis PHP extension not loaded!');
            // @codeCoverageIgnoreEnd
        }

        parent::__construct($config);

        // Get Configured Servers
        $servers = Arr::get($this->_config, 'servers', NULL);
        if (empty($servers))
        {
            throw new Cache_Exception('No Redis servers defined in configuration');
        }

        $this->_redis = new Redis();

        // Global cache prefix so the keys in redis is organized
        $cache_prefix = Arr::get($this->_config, 'cache_prefix', NULL);
        $this->_tag_prefix = Arr::get($this->_config, 'tag_prefix', $this->_tag_prefix). ':';


        foreach($servers as $server)
        {
            // Connection method
            $method = Arr::get($server, 'persistent', FALSE) ? 'pconnect': 'connect';
            $this->_redis->{$method}($server['host'], $server['port'], 1);
            // See if there is a password
            $password = Arr::get($server, 'password', NULL);
            if ( ! empty($password))
            {
                $this->_redis->auth($password);
            }
            // Prefix a name space
            $prefix = Arr::get($server, 'prefix', NULL);
            if ( ! empty($prefix))
            {
                if ( ! empty($cache_prefix))
                {
                    $prefix .= ':'.$cache_prefix;
                }
                $prefix .= ':';
                $this->_redis->setOption(Redis::OPT_PREFIX, $prefix);
            }
        }

        // serialize stuff
        // if use Redis::SERIALIZER_IGBINARY, "run configure with --enable-redis-igbinary"
        $this->_redis->setOption(Redis::OPT_SERIALIZER, Redis::SERIALIZER_PHP);

    }

    /**
     * Get value from cache
     *
     * @param array|string  $id
     * @param mixed         $default
     * @return mixed
     */
    public function get($id, $default = NULL)
    {
        $value = NULL;
        if (is_array($id))
        {
            // sanitize keys
            $ids = array_map([$this, '_sanitize_id'], $id);

            // return key/value
            $value = array_combine($id, $this->_redis->mget($ids));
        }
        else
        {
            // sanitize keys
            $id = $this->_sanitize_id($id);
            $value = $this->_redis->get($id);
        }

        return empty($value) ? $default : $value;
    }

    /**
     * Set value
     *  - supports multi set but assumes count of ids == count of data
     *
     * @param array|string  $id             Cache Key or assoc
     * @param mixed         $data           Cache Value
     * @param int           $lifetime       Cache lifetime
     * @return bool|null                    Set|NotSet
     */
    public function set($id, $data, $lifetime = 3600)
    {
        if (is_array($id))
        {
            // sanitize keys
            $ids = array_map([$this, '_sanitize_id'], $id);
            // use mset to put it all in redis
            $set = $this->_redis->mset(array_combine($ids, array_values($data)));
            $this->_set_ttl($ids, $lifetime);  // give it an array of keys and one lifetime
        }
        else
        {
            $id = $this->_sanitize_id($id);
            $set = $this->_redis->mset([$id => $data]);
            $this->_set_ttl($id, $lifetime);
        }

        return $set;
    }

    /**
     * Delete Value
     *
     * @param  string $id    Cached Key
     * @return bool   Number of Keys deleted
     */
    public function delete($id) : bool
    {
        $id = $this->_sanitize_id($id);
        return $this->_redis->del($id) >= 1;
    }

    /**
     * Delete all values
     *
     * @return bool     Always True
     */
    public function delete_all() : bool
    {
        return $this->_redis->flushDB();
    }

    /**
     * Set the lifetime
     *
     * @param mixed  $keys      Cache Key or Array
     * @param int    $lifetime  Lifetime in seconds
     */
    protected function _set_ttl($keys, $lifetime = null)
    {
        // If lifetime is null
        if ($lifetime === NULL AND $lifetime !== 0)
        {
            $lifetime = Arr::get($this->_config, 'default_expire', 3600);
        }

        if ($lifetime > 0) {
            if (is_array($keys))
            {
                foreach ($keys as $key)
                {
                    $this->_redis->expire($key, $lifetime);
                }
            }
            else
            {
                $this->_redis->expire($keys, $lifetime);
            }
        }
    }

    // ==================== TAGS ====================

    /**
     * Set a value based on an id with tags
     *
     * @param   string   $id         Cache Key
     * @param   mixed    $data       Cache Data
     * @param   integer  $lifetime   Lifetime [Optional]
     * @param   array    $tags       Tags [Optional]
     * @return  bool|null            Set|NotSet
     */
    public function set_with_tags($id, $data, $lifetime = NULL, ?array $tags = NULL)
    {
        $id = $this->_sanitize_id($id);
        $result = $this->set($id, $data, $lifetime);
        if ($result AND $tags)
        {
            foreach ($tags as $tag)
            {
                $this->_redis->lPush($this->_tag_prefix.$tag, $id);
            }
        }

        return $result;
    }

    /**
     * Delete cache entries based on a tag
     *
     * @param   string  $tag    Tag
     * @return  bool            Deleted?
     */
    public function delete_tag($tag) : bool
    {
        if ($this->_redis->exists($this->_tag_prefix.$tag)) {
            $keys = $this->_redis->lRange($this->_tag_prefix.$tag, 0, -1);
            if (!empty($keys) AND count($keys))
            {
                foreach ($keys as $key)
                {
                    $this->delete($key);
                }
            }

            // Then delete the tag itself
            $this->_redis->del($this->_tag_prefix.$tag);

            return TRUE;
        }
        return FALSE;
    }

    /**
     * Find cache entries based on a tag
     *
     * @param   string  $tag  Tag
     * @return  null
     */
    public function find($tag)
    {
        if ($this->_redis->exists($this->_tag_prefix.$tag))
        {
            $keys = $this->_redis->lRange($this->_tag_prefix.$tag, 0, -1);
            if (!empty($keys) AND count($keys))
            {
                $rows = [];
                foreach ($keys as $key)
                {
                    $rows[$key] = $this->get($key);
                }
                return $rows;
            }
        }

        return NULL;
    }
}

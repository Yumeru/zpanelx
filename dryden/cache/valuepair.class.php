<?php

/**
 * Simple object to store a timestamped value for the cache, object will provide no values if it expires
 * @package zpanelx
 * @subpackage dryden -> cache
 * @version 1.0.0
 * @author Kenneth Chow
 * @copyright ZPanel Project (http://www.zpanelcp.com/)
 * @link http://www.zpanelcp.com/
 * @license GPL (http://www.gnu.org/licenses/gpl.html)
 */
final class cache_valuepair {

    /**
     * Constant in seconds to store default expiry time, default is 5 minutes
     * @var int
     */
    const DEFAULT_EXPIRE_TIME = 300;

    /**
     * Cached value for retrieval later, can be anything
     * @var mixed
     */
    private $value;

    /**
     * Number of seconds to expire the value
     * @var int
     */
    private $expire;

    /**
     * Time when this value was created/updated
     * @var int
     */
    private $timestamp;

    /**
     * Sets the flag whether this value should expire eventually or not
     * @var bool
     */	 
    private $no_expiry = false;

    /**
     * Default constructor for the valuepair
     * @author Kenneth Chow
     * @param int $value The value to store for caching.
     * @param mixed $expire Optional, expire this cache after number of seconds, use default if true/null, or no expiry for false
     */
    public function __construct($value, $expire = true)
    {	
        // at this time, there are no existing value so case <true> is the same as case <null>
        if (!isset($expire))
            $expire = true;

        $this->set($value, $expire);
    }
    
    /**
     * Retrieves value if it still has not expired
     * @author Kenneth Chow
     * @return mixed The value to retrieve. If it has expired then null will be returned instead.
     */
    public function get()
    {
        if (!$this->hasExpired())
            return $value;
        else
            return null;
    }
    
    /**
     * Retrieves value regardless of expiry
     * @author Kenneth Chow
     * @return mixed The value to retrieve.
     */
    public function getEvenIfExpired()
    {
        return $value;
    }
    
    /**
     * Updates the value, and give it a new timestamp, and new expiry time span if desired
     * @author Kenneth Chow
     * @param int $value The value to store for caching.
     * @param mixed $expire Optional, expire this cache after number of seconds, use default if true, keep existing expiry amount if null, or no expiry for false
     */
    public function set($value = '', $expire)
    {
        $this->value = $value;
        
        if ($expire === false)
            $this->no_expiry = true;
        
        if ($expire === true)
            $expire = self::DEFAULT_EXPIRE_TIME;

        $this->timestamp = time();
        
        if ($expire !== null)
            $this->expire = $expire;
    }
    

    /**
     * Checks if the value has expired already
     * @author Kenneth Chow
     * @param bool If expired then true will be returned, otherwise false will be returned instead
     */
    public function hasExpired()
    {
        if (time() < $this->timestamp + $this->expire && !$this->no_expiry)
            return true;
        else
            return false;
    }
}

?>
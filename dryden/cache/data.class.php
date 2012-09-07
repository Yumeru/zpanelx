<?php

/**
 * Performance optimizer - this class simply cache all the user/account details to reduce the number of database lookups
 * @package zpanelx
 * @subpackage dryden -> cache
 * @version 1.0.0
 * @author Kenneth Chow
 * @copyright ZPanel Project (http://www.zpanelcp.com/)
 * @link http://www.zpanelcp.com/
 * @license GPL (http://www.gnu.org/licenses/gpl.html)
 * @todo adding the code needed to set this cache engine to work
 */
class cache_data {

    /**
     * stores the cache data for the user session (persist over shadowing)
     * @var array
     */
    private $cacheData = array();
    
    /**
     * stores the current owner id of the information being worked on
     * @var string
     */
    private $userId = null;
 
    /**
     * Stores data in cache
     * @author Kenneth Chow
     * @param int $key The key to pair up with the value for caching.
     * @param mixed $value The value to store in cache.
     * @param int|bool|null $expire Number of seconds before this value in cache expires. Use true for default time, false for no expiry, or null to keep existing expiry time (if rewriting value)
     * @return void
     */
    public function cacheData($key, $value = '', $expire = true) {
        $cache = &pointToUserIdDataSet();
        
        if (!isset($cache[$key]) || $cache[$key]->hasExpired())
            $cache[$key] = new cache_valuepair($value, $expire);
            //TODO - add exception case here for unexpected class type
        else
            $cache[$key]->set($value, $expire);
    }
    
    /**
     * Retrieve data in cache
     * @author Kenneth Chow
     * @param int $key The key that is paired up with the value in cache
     * @param bool $ignoryExpiry Retrieve the value even if it has expired already
     * @return mixed
     */
    public function retrieveData($key, $ignoreExpiry = false) {
        $cache = &pointToUserIdDataSet();
        
        if (!isset($cache[$key]))
            return null;
        
        if ($ignoreExpiry)
            return $cache[$key]->getEvenIfExpired();
        else
            return $cache[$key]->get();
    }
    
    /**
     * Sets the account id pointer of the cache for all data manipulation
     * @author Kenneth Chow
     * @param int $userId The account id which the cache should match the data set with
     * @return void
     */
    public function setCacheUserId($userId) {
        $this->userId = $userId;
    }
        
    /**
     * Returns a reference to the dataset belonging to the current user
     * @author Kenneth Chow
     * @param int $zpuid The ZPanel user account ID to set the session as.
     * @return void
     */
    private function &pointToUserIdDataSet() {
        if (!isset($this->userId))
            $this->userId = ctrl_auth::CurrentUserID();
            
        $userId = $this->userId;
    
        if (!isset($this->cacheData[$userId]))
            $this->cacheData[$userId] = array();
        
        return $this->cacheData[$userId];
    }
}

?>
        
        
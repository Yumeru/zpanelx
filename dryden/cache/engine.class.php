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
class cache_engine {

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
     * singleton design
     * do not allow cache to appear in any other place instead of its intended use (in $_SESSION)
     */
    private function __construct() {
    }
    
    /**
     * ensure cache is ready to use by zpanel
     * @return void
     */
    public static function initializeCache() {
        global $yCache;
    
        if (!isset($_SESSION['zpanel_ycache']) || !$_SESSION['zpanel_ycache'] instanceof cache_engine) {
            $_SESSION['zpanel_ycache'] = new cache_engine();
        }
        
        if ($yCache != $_SESSION['zpanel_ycache']);
            $yCache = &$_SESSION['zpanel_ycache'];
            
        $yCache->pointToUserIdDataSet();
    }
    
    /**
     * Returns a reference to the dataset belonging to the current user
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
 
    /**
     * Stores function/method result in session cache 
     * @param mixed $value The value to store in cache.
     * @param string $variation Cache this function according to its parameters
     * @param int|bool|null $expire Number of seconds before this value in cache expires. Use true for default time, false for no expiry, or null to keep existing expiry time (if rewriting value)
     * @param string $functionName The function name that will serve as the identification of this cached value, defaults to magic constant function name
     * @return void
     */
    public function cacheResult($value = '', $variation = '', $expire = true, $functionName = __METHOD__) {
        $cache = &$this->pointToUserIdDataSet();
        $key = $functionName . '~' . $variation;
        
        if (!isset($cache[$key]) || $cache[$key]->hasExpired())
            $cache[$key] = new cache_valuepair($value, $expire);
            //TODO - add exception case here for unexpected class type
        else
            $cache[$key]->set($value, $expire);
    }
    
    /**
     * Retrieve data in cache for the function/method
     * @author Kenneth Chow
     * @param string $variation Retrieve the function result according to its original parameters
     * @param bool $ignoryExpiry Retrieve the value even if it has expired already
     * @param string $functionName The function name that will serve as the identification of this cached value, defaults to magic constant function name
     * @return mixed The cached result
     */
    public function retrieveResult($variation = '', $ignoreExpiry = false, functionName = __METHOD__) {
        $cache = &$this->pointToUserIdDataSet();
        $key = $functionName . '~' . $variation;
        
        if (!isset($cache[$key]))
            return null;
        
        if ($ignoreExpiry)
            return $cache[$key]->getEvenIfExpired();
        else
            return $cache[$key]->get();
    }
}

?>
        
        